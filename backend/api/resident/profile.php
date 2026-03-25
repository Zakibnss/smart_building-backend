<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';
require_once '../../utils/auth.php';

// Vérifier l'authentification
$user_id = authenticate();

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Récupérer les informations du résident
            $stmt = $pdo->prepare("
                SELECT u.id, u.nom, u.email, u.telephone, 
                       r.numero_appartement, r.batiment, 
                       r.parking_id, r.smartmailbox_id,
                       p.numero_place as place_parking,
                       s.numero_boite as smartmailbox_numero
                FROM utilisateur u
                LEFT JOIN resident r ON u.id = r.utilisateur_id
                LEFT JOIN parking p ON r.parking_id = p.id
                LEFT JOIN smartmailbox s ON r.smartmailbox_id = s.id
                WHERE u.id = ? AND u.role = 'resident'
            ");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profile) {
                echo json_encode([
                    'success' => true,
                    'profile' => [
                        'id' => (int)$profile['id'],
                        'nom' => $profile['nom'],
                        'email' => $profile['email'],
                        'telephone' => $profile['telephone'] ?? '+33 6 00 00 00 00',
                        'batiment' => $profile['batiment'] ?? 'A',
                        'appartement' => $profile['numero_appartement'] ?? 'A101',
                        'place_parking' => $profile['place_parking'] ?? 'P-102',
                        'smartmailbox' => $profile['smartmailbox_numero'] ?? '23-G'
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Profil non trouvé'
                ]);
            }
            break;

        case 'PUT':
            // Mettre à jour le profil
            $data = json_decode(file_get_contents("php://input"), true);

            $sql = "UPDATE utilisateur SET telephone = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['telephone'] ?? null, $user_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Profil mis à jour avec succès'
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