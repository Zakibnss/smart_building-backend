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
                // Récupérer un service spécifique
                $stmt = $pdo->prepare("
                    SELECT s.*, u.nom as agent_nom
                    FROM service s
                    LEFT JOIN utilisateur u ON s.agent_service_id = u.id
                    WHERE s.id = ? AND s.resident_id = ?
                ");
                $stmt->execute([$_GET['id'], $resident_id]);
                $service = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($service) {
                    // Formater les données
                    $service['id'] = (int)$service['id'];
                    $service['resident_id'] = (int)$service['resident_id'];
                    $service['agent_service_id'] = $service['agent_service_id'] ? (int)$service['agent_service_id'] : null;
                    
                    echo json_encode([
                        'success' => true,
                        'service' => $service
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Service non trouvé'
                    ]);
                }
            } elseif (isset($_GET['history'])) {
                // Récupérer l'historique des services
                $stmt = $pdo->prepare("
                    SELECT s.*, u.nom as agent_nom
                    FROM service s
                    LEFT JOIN utilisateur u ON s.agent_service_id = u.id
                    WHERE s.resident_id = ?
                    ORDER BY s.date_demande DESC
                    LIMIT 20
                ");
                $stmt->execute([$resident_id]);
                $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($services as &$service) {
                    $service['id'] = (int)$service['id'];
                    $service['resident_id'] = (int)$service['resident_id'];
                }

                echo json_encode([
                    'success' => true,
                    'services' => $services
                ]);
            } else {
                // Récupérer tous les services du résident
                $stmt = $pdo->prepare("
                    SELECT s.*, u.nom as agent_nom
                    FROM service s
                    LEFT JOIN utilisateur u ON s.agent_service_id = u.id
                    WHERE s.resident_id = ?
                    ORDER BY s.date_demande DESC
                ");
                $stmt->execute([$resident_id]);
                $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($services as &$service) {
                    $service['id'] = (int)$service['id'];
                    $service['resident_id'] = (int)$service['resident_id'];
                }

                echo json_encode([
                    'success' => true,
                    'services' => $services
                ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['type']) || !isset($data['description'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Type et description sont requis'
                ]);
                break;
            }

            // Valider le type de service
            $validTypes = ['Réparation', 'Plomberie', 'Nettoyage', 'Gardien', 'Sécurité', 'Maintenance'];
            if (!in_array($data['type'], $validTypes)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Type de service invalide'
                ]);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Insérer la demande de service
                $stmt = $pdo->prepare("
                    INSERT INTO service (
                        resident_id, 
                        type_service, 
                        description, 
                        date_souhaitee,
                        heure_souhaitee,
                        statut, 
                        date_demande
                    ) VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())
                ");
                
                $stmt->execute([
                    $resident_id,
                    $data['type'],
                    $data['description'],
                    $data['date_souhaitee'] ?? null,
                    $data['heure_souhaitee'] ?? null
                ]);

                $service_id = $pdo->lastInsertId();

                // Créer une notification pour les agents de service
                $stmt = $pdo->prepare("
                    INSERT INTO notification (utilisateur_id, titre, contenu, type)
                    SELECT id, ?, ?, 'service'
                    FROM utilisateur WHERE role = 'agent_service'
                ");
                $stmt->execute([
                    'Nouvelle demande de service',
                    'Demande de ' . $data['type'] . ' de la part du résident'
                ]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Demande de service créée avec succès',
                    'id' => $service_id
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID requis']);
                break;
            }

            // Supprimer un service (seulement si en attente)
            $stmt = $pdo->prepare("
                DELETE FROM service 
                WHERE id = ? AND resident_id = ? AND statut = 'en_attente'
            ");
            $stmt->execute([$_GET['id'], $resident_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Demande de service annulée'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Impossible d\'annuler cette demande'
                ]);
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
}
?>