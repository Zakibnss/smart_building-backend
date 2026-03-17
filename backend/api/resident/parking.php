<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

    if ($method != 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit();
    }

    // Récupérer les informations de parking du résident
    $stmt = $pdo->prepare("
        SELECT p.numero_place, p.type, p.statut,
               r.parking_id
        FROM resident r
        LEFT JOIN parking p ON r.parking_id = p.id
        WHERE r.utilisateur_id = ?
    ");
    $stmt->execute([$user_id]);
    $parking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($parking && $parking['parking_id']) {
        echo json_encode([
            'success' => true,
            'parking' => [
                'place' => $parking['numero_place'],
                'type' => $parking['type'],
                'statut' => $parking['statut']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'parking' => null,
            'message' => 'Aucune place de parking assignée'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>