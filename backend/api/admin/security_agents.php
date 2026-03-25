<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("
                    SELECT id, nom, email, telephone, statut
                    FROM utilisateur 
                    WHERE role = 'agent_securite' AND id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $agent = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($agent) {
                    echo json_encode([
                        'success' => true,
                        'agent' => $agent
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Agent non trouvé'
                    ]);
                }
            } else {
                $stmt = $pdo->query("
                    SELECT id, nom, email, telephone, statut
                    FROM utilisateur 
                    WHERE role = 'agent_securite'
                    ORDER BY nom
                ");
                $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'agents' => $agents
                ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['nom']) || !isset($data['email']) || !isset($data['password'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nom, email et mot de passe sont requis'
                ]);
                break;
            }

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM utilisateur WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Cet email est déjà utilisé'
                ]);
                break;
            }

            $stmt = $pdo->prepare("
                INSERT INTO utilisateur (nom, email, password, telephone, statut, role, complex_id) 
                VALUES (?, ?, MD5(?), ?, ?, 'agent_securite', ?)
            ");
            $stmt->execute([
                $data['nom'],
                $data['email'],
                $data['password'],
                $data['telephone'] ?? null,
                $data['statut'] ?? 'actif',
                $data['complex_id'] ?? 1
            ]);

            $agentId = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Agent de sécurité ajouté avec succès',
                'id' => $agentId
            ]);
            break;

        case 'PUT':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID requis'
                ]);
                break;
            }

            $data = json_decode(file_get_contents("php://input"), true);
            
            $sql = "UPDATE utilisateur SET nom = ?, email = ?, telephone = ?, statut = ?";
            $params = [$data['nom'], $data['email'], $data['telephone'] ?? null, $data['statut'] ?? 'actif'];

            if (isset($data['password']) && !empty($data['password'])) {
                $sql .= ", password = MD5(?)";
                $params[] = $data['password'];
            }

            $sql .= " WHERE id = ? AND role = 'agent_securite'";
            $params[] = $_GET['id'];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode([
                'success' => true,
                'message' => 'Agent modifié avec succès'
            ]);
            break;

        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID requis'
                ]);
                break;
            }

            $userId = $_GET['id'];
            
            $pdo->beginTransaction();

            try {
                // Supprimer les missions liées aux services de cet agent
                $stmt = $pdo->prepare("
                    DELETE m FROM mission m
                    INNER JOIN service s ON m.service_id = s.id
                    WHERE s.agent_service_id = ?
                ");
                $stmt->execute([$userId]);
                
                // Supprimer les services où cet agent est assigné
                $stmt = $pdo->prepare("UPDATE service SET agent_service_id = NULL WHERE agent_service_id = ?");
                $stmt->execute([$userId]);
                
                // Supprimer les notifications
                $stmt = $pdo->prepare("DELETE FROM notification WHERE utilisateur_id = ?");
                $stmt->execute([$userId]);
                
                // Supprimer l'utilisateur
                $stmt = $pdo->prepare("DELETE FROM utilisateur WHERE id = ? AND role = 'agent_securite'");
                $stmt->execute([$userId]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Agent supprimé avec succès'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Méthode non autorisée'
            ]);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>