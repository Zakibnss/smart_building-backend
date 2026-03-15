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
                    SELECT id, nom, email, telephone, specialite 
                    FROM technicians 
                    WHERE id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $technician = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($technician) {
                    echo json_encode([
                        'success' => true,
                        'technician' => $technician
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Technicien non trouvé'
                    ]);
                }
            } else {
                $stmt = $pdo->query("
                    SELECT id, nom, email, telephone, specialite 
                    FROM technicians
                    ORDER BY nom
                ");
                $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'technicians' => $technicians
                ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['nom']) || !isset($data['email']) || !isset($data['specialite'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nom, email et spécialité sont requis'
                ]);
                break;
            }

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM technicians WHERE email = ?");
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
                INSERT INTO technicians (nom, email, telephone, specialite) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['nom'],
                $data['email'],
                $data['telephone'] ?? null,
                $data['specialite']
            ]);

            $technicianId = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Technicien ajouté avec succès',
                'id' => $technicianId
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
            
            $stmt = $pdo->prepare("
                UPDATE technicians 
                SET nom = ?, email = ?, telephone = ?, specialite = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $data['nom'],
                $data['email'],
                $data['telephone'] ?? null,
                $data['specialite'],
                $_GET['id']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Technicien modifié avec succès'
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

            $stmt = $pdo->prepare("DELETE FROM technicians WHERE id = ?");
            $stmt->execute([$_GET['id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Technicien supprimé avec succès'
            ]);
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