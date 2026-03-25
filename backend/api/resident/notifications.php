<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PUT, OPTIONS");
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

    switch ($method) {
        case 'GET':
            // Récupérer les notifications du résident
            $stmt = $pdo->prepare("
                SELECT * FROM notification
                WHERE utilisateur_id = ?
                ORDER BY date_envoi DESC
                LIMIT 20
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compter les non lues
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as non_lues FROM notification
                WHERE utilisateur_id = ? AND est_lu = 0
            ");
            $stmt->execute([$user_id]);
            $non_lues = $stmt->fetch(PDO::FETCH_ASSOC)['non_lues'];

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'non_lues' => (int)$non_lues
            ]);
            break;

        case 'PUT':
            if (isset($_GET['id'])) {
                // Marquer une notification comme lue
                $stmt = $pdo->prepare("
                    UPDATE notification SET est_lu = 1
                    WHERE id = ? AND utilisateur_id = ?
                ");
                $stmt->execute([$_GET['id'], $user_id]);
            } elseif (isset($_GET['all'])) {
                // Marquer toutes les notifications comme lues
                $stmt = $pdo->prepare("
                    UPDATE notification SET est_lu = 1
                    WHERE utilisateur_id = ? AND est_lu = 0
                ");
                $stmt->execute([$user_id]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Notifications mises à jour'
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