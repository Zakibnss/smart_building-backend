<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';
require_once '../../utils/auth.php';

// Vérifier que l'utilisateur est admin
/*
$user_id = authenticate();
$stmt = $pdo->prepare("SELECT role FROM utilisateur WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}
*/

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] == 'stats') {
                // Statistiques globales
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_places,
                        SUM(CASE WHEN type = 'resident' THEN 1 ELSE 0 END) as places_resident,
                        SUM(CASE WHEN type = 'visiteur' THEN 1 ELSE 0 END) as places_visiteur,
                        SUM(CASE WHEN resident_id IS NOT NULL THEN 1 ELSE 0 END) as places_occupees
                    FROM parking
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'total_places' => (int)$stats['total_places'],
                        'places_resident' => (int)$stats['places_resident'],
                        'places_visiteur' => (int)$stats['places_visiteur'],
                        'places_occupees' => (int)$stats['places_occupees'],
                        'places_libres' => (int)$stats['total_places'] - (int)$stats['places_occupees']
                    ]
                ]);
            } elseif (isset($_GET['action']) && $_GET['action'] == 'history') {
                // Historique des attributions
                $stmt = $pdo->query("
                    SELECT pp.*, p.numero_place, p.type, 
                           u.nom as resident_nom, r.numero_appartement,
                           a.nom as assigne_par_nom
                    FROM place_parking pp
                    JOIN parking p ON pp.parking_id = p.id
                    LEFT JOIN resident r ON pp.resident_id = r.id
                    LEFT JOIN utilisateur u ON r.utilisateur_id = u.id
                    LEFT JOIN utilisateur a ON pp.assigne_par = a.id
                    ORDER BY pp.date_assignation DESC
                    LIMIT 50
                ");
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'history' => $history
                ]);
            } else {
                // Récupérer toutes les places avec leurs statuts
                $stmt = $pdo->query("
                    SELECT p.*, u.nom as resident_nom, r.numero_appartement
                    FROM parking p
                    LEFT JOIN resident r ON p.resident_id = r.id
                    LEFT JOIN utilisateur u ON r.utilisateur_id = u.id
                    ORDER BY p.numero_place
                ");
                $places = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Récupérer les résidents sans parking
                $stmt = $pdo->query("
                    SELECT u.id, u.nom, r.numero_appartement, r.batiment
                    FROM utilisateur u
                    JOIN resident r ON u.id = r.utilisateur_id
                    LEFT JOIN parking p ON r.id = p.resident_id
                    WHERE u.role = 'resident' AND p.resident_id IS NULL
                    ORDER BY r.numero_appartement
                ");
                $residents_sans_parking = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'places' => $places,
                    'residents_sans_parking' => $residents_sans_parking
                ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            if (isset($data['action']) && $data['action'] == 'configure') {
                // Configurer le parking
                if (!isset($data['places_resident']) || !isset($data['places_visiteur'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Nombre de places requis'
                    ]);
                    break;
                }

                $pdo->beginTransaction();

                try {
                    // Supprimer les places existantes non occupées
                    $stmt = $pdo->exec("DELETE FROM parking WHERE resident_id IS NULL");

                    $places_resident = (int)$data['places_resident'];
                    $places_visiteur = (int)$data['places_visiteur'];

                    // Créer places résidents (R001, R002, ...)
                    for ($i = 1; $i <= $places_resident; $i++) {
                        $numero = 'R' . str_pad($i, 3, '0', STR_PAD_LEFT);
                        $stmt = $pdo->prepare("
                            INSERT INTO parking (numero_place, type, statut, complex_id)
                            VALUES (?, 'resident', 'libre', 1)
                        ");
                        $stmt->execute([$numero]);
                    }

                    // Créer places visiteurs (V001, V002, ...)
                    for ($i = 1; $i <= $places_visiteur; $i++) {
                        $numero = 'V' . str_pad($i, 3, '0', STR_PAD_LEFT);
                        $stmt = $pdo->prepare("
                            INSERT INTO parking (numero_place, type, statut, complex_id)
                            VALUES (?, 'visiteur', 'libre', 1)
                        ");
                        $stmt->execute([$numero]);
                    }

                    $pdo->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Parking configuré avec succès'
                    ]);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } elseif (isset($data['action']) && $data['action'] == 'assign') {
                // Assigner une place à un résident
                if (!isset($data['resident_id']) || !isset($data['parking_id'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Résident et place requis'
                    ]);
                    break;
                }

                $pdo->beginTransaction();

                try {
                    // Vérifier que la place est libre
                    $stmt = $pdo->prepare("
                        SELECT id FROM parking 
                        WHERE id = ? AND resident_id IS NULL AND statut = 'libre'
                    ");
                    $stmt->execute([$data['parking_id']]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Place déjà occupée');
                    }

                    // Assigner la place
                    $stmt = $pdo->prepare("
                        UPDATE parking 
                        SET resident_id = ?, statut = 'occupee', date_occupation = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$data['resident_id'], $data['parking_id']]);

                    // Enregistrer dans l'historique
                    $stmt = $pdo->prepare("
                        INSERT INTO place_parking (parking_id, resident_id, complex_id, assigne_par)
                        VALUES (?, ?, 1, ?)
                    ");
                    $stmt->execute([$data['parking_id'], $data['resident_id'], $user_id]);

                    $pdo->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Place assignée avec succès'
                    ]);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);

            if (isset($data['action']) && $data['action'] == 'liberer') {
                if (!isset($data['parking_id'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID place requis'
                    ]);
                    break;
                }

                $pdo->beginTransaction();

                try {
                    // Libérer la place
                    $stmt = $pdo->prepare("
                        UPDATE parking 
                        SET resident_id = NULL, statut = 'libre', date_occupation = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$data['parking_id']]);

                    $pdo->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Place libérée avec succès'
                    ]);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>