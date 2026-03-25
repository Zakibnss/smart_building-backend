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
    $stmt = $pdo->prepare("SELECT id FROM resident WHERE utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    $resident_id = $resident['id'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Récupérer un colis spécifique
                $stmt = $pdo->prepare("
                    SELECT c.*, u.nom as agent_nom
                    FROM colis c
                    LEFT JOIN utilisateur u ON c.agent_securite_id = u.id
                    WHERE c.id = ? AND c.resident_id = ?
                ");
                $stmt->execute([$_GET['id'], $resident_id]);
                $colis = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($colis) {
                    echo json_encode([
                        'success' => true,
                        'colis' => $colis
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Colis non trouvé'
                    ]);
                }
            } else {
                // Récupérer tous les colis du résident
                $stmt = $pdo->prepare("
                    SELECT c.*, u.nom as agent_nom
                    FROM colis c
                    LEFT JOIN utilisateur u ON c.agent_securite_id = u.id
                    WHERE c.resident_id = ?
                    ORDER BY c.date_arrivee DESC
                ");
                $stmt->execute([$resident_id]);
                $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'colis' => $colis
                ]);
            }
            break;

        case 'PUT':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID requis']);
                break;
            }

            // Marquer un colis comme récupéré
            $stmt = $pdo->prepare("
                UPDATE colis SET statut = 'remis', date_remise = NOW()
                WHERE id = ? AND resident_id = ?
            ");
            $stmt->execute([$_GET['id'], $resident_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Colis marqué comme récupéré'
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