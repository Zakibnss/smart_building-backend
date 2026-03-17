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

$user_id = authenticate();

try {
    $method = $_SERVER['REQUEST_METHOD'];

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
            if (isset($_GET['id'])) {
                // Récupérer une réclamation spécifique
                $stmt = $pdo->prepare("
                    SELECT * FROM reclamation 
                    WHERE id = ? AND resident_id = ?
                ");
                $stmt->execute([$_GET['id'], $resident_id]);
                $reclamation = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($reclamation) {
                    // Récupérer l'historique des statuts
                    $stmt = $pdo->prepare("
                        SELECT * FROM reclamation_status_history 
                        WHERE reclamation_id = ?
                        ORDER BY date_changement ASC
                    ");
                    $stmt->execute([$_GET['id']]);
                    $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $reclamation['id'] = (int)$reclamation['id'];
                    $reclamation['resident_id'] = (int)$reclamation['resident_id'];
                    $reclamation['historique'] = $historique;

                    echo json_encode([
                        'success' => true,
                        'reclamation' => $reclamation
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Réclamation non trouvée'
                    ]);
                }
            } else {
                // Récupérer toutes les réclamations du résident
                $stmt = $pdo->prepare("
                    SELECT * FROM reclamation 
                    WHERE resident_id = ? 
                    ORDER BY date_creation DESC
                ");
                $stmt->execute([$resident_id]);
                $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($reclamations as &$reclamation) {
                    $reclamation['id'] = (int)$reclamation['id'];
                    $reclamation['resident_id'] = (int)$reclamation['resident_id'];
                }

                echo json_encode([
                    'success' => true,
                    'reclamations' => $reclamations
                ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['titre']) || !isset($data['description']) || !isset($data['categorie']) || !isset($data['lieu'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Titre, description, catégorie et lieu sont requis'
                ]);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Insérer la réclamation
                $stmt = $pdo->prepare("
                    INSERT INTO reclamation (resident_id, titre, description, categorie, lieu, statut, date_creation)
                    VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())
                ");
                $stmt->execute([
                    $resident_id,
                    $data['titre'],
                    $data['description'],
                    $data['categorie'],
                    $data['lieu']
                ]);

                $reclamation_id = $pdo->lastInsertId();

                // Ajouter à l'historique
                $stmt = $pdo->prepare("
                    INSERT INTO reclamation_status_history (reclamation_id, statut, date_changement)
                    VALUES (?, 'en_attente', NOW())
                ");
                $stmt->execute([$reclamation_id]);

                // Créer une notification pour les admins
                $stmt = $pdo->prepare("
                    INSERT INTO notification (utilisateur_id, titre, contenu, type)
                    SELECT id, ?, ?, 'reclamation'
                    FROM utilisateur WHERE role = 'admin'
                ");
                $stmt->execute([
                    'Nouvelle réclamation',
                    'Un résident a créé une réclamation: ' . $data['titre']
                ]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Réclamation créée avec succès',
                    'id' => $reclamation_id
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'PUT':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID requis']);
                break;
            }

            $data = json_decode(file_get_contents("php://input"), true);

            // Vérifier que la réclamation appartient au résident
            $stmt = $pdo->prepare("
                SELECT id, statut FROM reclamation 
                WHERE id = ? AND resident_id = ?
            ");
            $stmt->execute([$_GET['id'], $resident_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Réclamation non trouvée']);
                break;
            }

            // Ne permettre la modification que si la réclamation est en attente
            if ($existing['statut'] != 'en_attente') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Impossible de modifier une réclamation déjà traitée']);
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE reclamation 
                SET titre = ?, description = ?, categorie = ?, lieu = ?
                WHERE id = ? AND resident_id = ?
            ");
            $stmt->execute([
                $data['titre'],
                $data['description'],
                $data['categorie'],
                $data['lieu'],
                $_GET['id'],
                $resident_id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Réclamation mise à jour'
            ]);
            break;

        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID requis']);
                break;
            }

            // Vérifier que la réclamation appartient au résident et est en attente
            $stmt = $pdo->prepare("
                SELECT id, statut FROM reclamation 
                WHERE id = ? AND resident_id = ?
            ");
            $stmt->execute([$_GET['id'], $resident_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Réclamation non trouvée']);
                break;
            }

            if ($existing['statut'] != 'en_attente') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Impossible d\'annuler une réclamation déjà traitée']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM reclamation WHERE id = ? AND resident_id = ?");
            $stmt->execute([$_GET['id'], $resident_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Réclamation annulée'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>