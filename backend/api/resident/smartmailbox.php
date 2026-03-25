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

$user_id = authenticate();

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // Récupérer l'ID du résident
    $stmt = $pdo->prepare("SELECT id, smartmailbox_id FROM resident WHERE utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    $resident_id = $resident['id'];
    $smartmailbox_id = $resident['smartmailbox_id'];

    switch ($method) {
        case 'GET':
            // Récupérer l'état de la smart mailbox
            if ($smartmailbox_id) {
                $stmt = $pdo->prepare("
                    SELECT s.*, c.description, c.code_retrait
                    FROM smartmailbox s
                    LEFT JOIN colis c ON s.colis_id = c.id
                    WHERE s.id = ? AND s.resident_id = ?
                ");
                $stmt->execute([$smartmailbox_id, $resident_id]);
                $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'mailbox' => [
                        'numero' => $mailbox['numero_boite'] ?? '23-G',
                        'statut' => $mailbox['statut'] ?? 'vide',
                        'colis_present' => ($mailbox['statut'] == 'colis_present'),
                        'description' => $mailbox['description'] ?? null,
                        'code' => $mailbox['code_retrait'] ?? null
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'mailbox' => [
                        'numero' => 'Non assignée',
                        'statut' => 'non_disponible',
                        'colis_present' => false
                    ]
                ]);
            }
            break;

        case 'PUT':
            if (!isset($_GET['action'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Action requise']);
                break;
            }

            if ($_GET['action'] == 'ouvrir') {
                // Ouvrir la mailbox (générer un code temporaire)
                $code = rand(1000, 9999) . '-' . chr(65 + rand(0, 25));
                
                echo json_encode([
                    'success' => true,
                    'code' => $code,
                    'message' => 'Code d\'ouverture généré'
                ]);
            } elseif ($_GET['action'] == 'recuperer' && isset($_GET['colis_id'])) {
                // Marquer le colis comme récupéré
                $stmt = $pdo->prepare("
                    UPDATE smartmailbox 
                    SET colis_id = NULL, statut = 'vide'
                    WHERE id = ? AND resident_id = ?
                ");
                $stmt->execute([$smartmailbox_id, $resident_id]);

                $stmt = $pdo->prepare("
                    UPDATE colis SET statut = 'remis', date_remise = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_GET['colis_id']]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Colis récupéré avec succès'
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