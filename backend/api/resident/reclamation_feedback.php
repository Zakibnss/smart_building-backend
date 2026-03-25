<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';
require_once '../../utils/auth.php';

$user_id = authenticate();

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['reclamation_id']) || !isset($data['note'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID réclamation et note requis'
        ]);
        exit();
    }

    // Vérifier que la réclamation appartient au résident et est résolue
    $stmt = $pdo->prepare("
        SELECT r.id FROM reclamation r
        JOIN resident res ON r.resident_id = res.id
        WHERE r.id = ? AND res.utilisateur_id = ? AND r.statut = 'resolue'
    ");
    $stmt->execute([$data['reclamation_id'], $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Réclamation non trouvée ou non résolue'
        ]);
        exit();
    }

    // Insérer ou mettre à jour le feedback
    $stmt = $pdo->prepare("
        INSERT INTO reclamation_feedback (reclamation_id, note, commentaire, date_feedback)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            note = VALUES(note),
            commentaire = VALUES(commentaire),
            date_feedback = NOW()
    ");
    $stmt->execute([
        $data['reclamation_id'],
        $data['note'],
        $data['commentaire'] ?? null
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Merci pour votre retour !'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>