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
// require_once '../../utils/auth.php'; // Décommentez pour la production

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // Récupérer l'ID utilisateur
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if ($user_id == 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
        exit();
    }

    // Récupérer l'ID du résident
    $stmt = $pdo->prepare("SELECT id FROM resident WHERE utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resident) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Résident non trouvé']);
        exit();
    }
    
    $resident_id = $resident['id'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] == 'myplace') {
                // Récupérer la place du résident
                $stmt = $pdo->prepare("
                    SELECT p.* 
                    FROM parking p
                    WHERE p.resident_id = ?
                ");
                $stmt->execute([$resident_id]);
                $place = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($place) {
                    echo json_encode([
                        'success' => true,
                        'has_place' => true,
                        'place' => [
                            'id' => (int)$place['id'],
                            'numero' => $place['numero_place'],
                            'type' => $place['type'],
                            'statut' => $place['statut']
                        ]
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'has_place' => false
                    ]);
                }
            } elseif (isset($_GET['action']) && $_GET['action'] == 'available') {
                // Récupérer les places disponibles
                $stmt = $pdo->query("
                    SELECT id, numero_place, type
                    FROM parking
                    WHERE resident_id IS NULL AND statut = 'libre' AND type = 'resident'
                    ORDER BY numero_place
                ");
                $places = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($places as &$place) {
                    $place['id'] = (int)$place['id'];
                }

                echo json_encode([
                    'success' => true,
                    'places' => $places
                ]);
            } else {
                // Statistiques générales
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN type = 'resident' THEN 1 ELSE 0 END) as total_resident,
                        SUM(CASE WHEN type = 'visiteur' THEN 1 ELSE 0 END) as total_visiteur,
                        SUM(CASE WHEN resident_id IS NOT NULL THEN 1 ELSE 0 END) as occupees
                    FROM parking
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'total' => (int)$stats['total'],
                        'total_resident' => (int)$stats['total_resident'],
                        'total_visiteur' => (int)$stats['total_visiteur'],
                        'occupees' => (int)$stats['occupees'],
                        'disponibles' => (int)$stats['total'] - (int)$stats['occupees']
                    ]
                ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['parking_id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de place requis'
                ]);
                break;
            }

            // Vérifier si le résident a déjà une place
            $stmt = $pdo->prepare("SELECT id FROM parking WHERE resident_id = ?");
            $stmt->execute([$resident_id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Vous avez déjà une place de parking'
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
                $place = $stmt->fetch();

                if (!$place) {
                    throw new Exception('Place déjà occupée');
                }

                // Réserver la place
                $stmt = $pdo->prepare("
                    UPDATE parking 
                    SET resident_id = ?, statut = 'occupee', date_occupation = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$resident_id, $data['parking_id']]);

                // Ajouter à l'historique
                $stmt = $pdo->prepare("
                    INSERT INTO place_parking (parking_id, resident_id, complex_id, assigne_par, date_assignation)
                    VALUES (?, ?, 1, ?, NOW())
                ");
                $stmt->execute([$data['parking_id'], $resident_id, $user_id]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Place réservée avec succès'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'DELETE':
            $pdo->beginTransaction();

            try {
                // Vérifier que le résident a une place
                $stmt = $pdo->prepare("
                    SELECT id FROM parking WHERE resident_id = ?
                ");
                $stmt->execute([$resident_id]);
                $place = $stmt->fetch();

                if (!$place) {
                    throw new Exception('Vous n\'avez pas de place de parking');
                }

                // Libérer la place
                $stmt = $pdo->prepare("
                    UPDATE parking 
                    SET resident_id = NULL, statut = 'libre', date_occupation = NULL
                    WHERE resident_id = ?
                ");
                $stmt->execute([$resident_id]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Place libérée avec succès'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
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