<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.nom, u.email, u.telephone, 
                           r.numero_appartement, r.batiment
                    FROM utilisateur u
                    LEFT JOIN resident r ON u.id = r.utilisateur_id
                    WHERE u.role = 'resident' AND u.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $resident = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($resident) {
                    echo json_encode([
                        'success' => true,
                        'resident' => $resident
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Résident non trouvé'
                    ]);
                }
            } else {
                $stmt = $pdo->query("
                    SELECT u.id, u.nom, u.email, u.telephone, 
                           r.numero_appartement, r.batiment
                    FROM utilisateur u
                    LEFT JOIN resident r ON u.id = r.utilisateur_id
                    WHERE u.role = 'resident'
                    ORDER BY r.numero_appartement
                ");
                $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'residents' => $residents
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

            // Récupérer un complex_id valide (par défaut 1)
            $complex_id = 1;
            
            // Vérifier si le complex_id existe
            $stmt = $pdo->prepare("SELECT id FROM complex WHERE id = ?");
            $stmt->execute([$complex_id]);
            if (!$stmt->fetch()) {
                // Si le complexe n'existe pas, le créer
                $stmt = $pdo->prepare("INSERT INTO complex (nom, adresse) VALUES ('Complexe Principal', 'Adresse par défaut')");
                $stmt->execute();
                $complex_id = $pdo->lastInsertId();
            }

            $pdo->beginTransaction();

            try {
                // Insérer dans utilisateur
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateur (nom, email, password, telephone, role, complex_id) 
                    VALUES (?, ?, MD5(?), ?, 'resident', ?)
                ");
                $stmt->execute([
                    $data['nom'],
                    $data['email'],
                    $data['password'],
                    $data['telephone'] ?? null,
                    $complex_id
                ]);

                $userId = $pdo->lastInsertId();

                // Insérer dans resident
                $stmt = $pdo->prepare("
                    INSERT INTO resident (utilisateur_id, complex_id, numero_appartement, batiment) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $complex_id,
                    $data['numero_appartement'] ?? '',
                    $data['batiment'] ?? ''
                ]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Résident ajouté avec succès',
                    'id' => $userId
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
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
            
            $pdo->beginTransaction();

            try {
                // Mettre à jour utilisateur
                $sql = "UPDATE utilisateur SET nom = ?, email = ?, telephone = ?";
                $params = [$data['nom'], $data['email'], $data['telephone'] ?? null];

                if (isset($data['password']) && !empty($data['password'])) {
                    $sql .= ", password = MD5(?)";
                    $params[] = $data['password'];
                }

                $sql .= " WHERE id = ?";
                $params[] = $_GET['id'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Mettre à jour resident
                $stmt = $pdo->prepare("
                    UPDATE resident 
                    SET numero_appartement = ?, batiment = ? 
                    WHERE utilisateur_id = ?
                ");
                $stmt->execute([
                    $data['numero_appartement'] ?? '',
                    $data['batiment'] ?? '',
                    $_GET['id']
                ]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Résident modifié avec succès'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
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
            
                $userId = intval($_GET['id']);
                
                // Vérifier si l'utilisateur existe
                $stmt = $pdo->prepare("SELECT id FROM utilisateur WHERE id = ? AND role = 'resident'");
                $stmt->execute([$userId]);
                if (!$stmt->fetch()) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Résident non trouvé'
                    ]);
                    break;
                }
            
                // Commencer une transaction
                $pdo->beginTransaction();
            
                try {
                    // 1. Récupérer l'ID du résident
                    $stmt = $pdo->prepare("SELECT id FROM resident WHERE utilisateur_id = ?");
                    $stmt->execute([$userId]);
                    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($resident) {
                        $residentId = $resident['id'];
                        
                        // 2. Récupérer les IDs des services de ce résident
                        $stmt = $pdo->prepare("SELECT id FROM service WHERE resident_id = ?");
                        $stmt->execute([$residentId]);
                        $services = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        // 3. Supprimer les missions liées à ces services
                        if (!empty($services)) {
                            $placeholders = implode(',', array_fill(0, count($services), '?'));
                            $stmt = $pdo->prepare("DELETE FROM mission WHERE service_id IN ($placeholders)");
                            $stmt->execute($services);
                        }
                        
                        // 4. Supprimer les smartmailbox
                        $stmt = $pdo->prepare("DELETE FROM smartmailbox WHERE resident_id = ?");
                        $stmt->execute([$residentId]);
                        
                        // 5. Supprimer les colis
                        $stmt = $pdo->prepare("DELETE FROM colis WHERE resident_id = ?");
                        $stmt->execute([$residentId]);
                        
                        // 6. Supprimer les réclamations
                        $stmt = $pdo->prepare("DELETE FROM reclamation WHERE resident_id = ?");
                        $stmt->execute([$residentId]);
                        
                        // 7. Supprimer les services
                        $stmt = $pdo->prepare("DELETE FROM service WHERE resident_id = ?");
                        $stmt->execute([$residentId]);
                        
                        // 8. Libérer les places de parking
                        $stmt = $pdo->prepare("UPDATE parking SET resident_id = NULL WHERE resident_id = ?");
                        $stmt->execute([$residentId]);
                        
                        // 9. Supprimer le résident
                        $stmt = $pdo->prepare("DELETE FROM resident WHERE id = ?");
                        $stmt->execute([$residentId]);
                    }
            
                    // 10. Supprimer les notifications
                    $stmt = $pdo->prepare("DELETE FROM notification WHERE utilisateur_id = ?");
                    $stmt->execute([$userId]);
            
                    // 11. Supprimer l'utilisateur
                    $stmt = $pdo->prepare("DELETE FROM utilisateur WHERE id = ?");
                    $stmt->execute([$userId]);
            
                    $pdo->commit();
            
                    echo json_encode([
                        'success' => true,
                        'message' => 'Résident et toutes ses données associées supprimés avec succès'
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