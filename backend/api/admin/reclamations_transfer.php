<?php
// backend/api/admin/reclamations_transfer.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';

try {
    // Récupérer les réclamations non encore assignées
    $query = "SELECT r.*, 
                     u.nom as resident_nom, 
                     res.numero_appartement as appartement,
                     res.batiment
              FROM reclamation r
              LEFT JOIN resident res ON r.resident_id = res.id
              LEFT JOIN utilisateur u ON res.utilisateur_id = u.id
              WHERE (r.agent_service_id IS NULL OR r.agent_service_id = 0)
              AND r.statut NOT IN ('resolue', 'rejetee')
              ORDER BY 
                CASE WHEN r.priorite = 'urgente' THEN 1 
                     WHEN r.priorite = 'haute' THEN 2 
                     WHEN r.priorite = 'normale' THEN 3 
                     ELSE 4 END,
                r.date_creation ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($reclamations as &$reclamation) {
        $reclamation['id'] = (int)$reclamation['id'];
        $reclamation['resident_id'] = (int)$reclamation['resident_id'];
    }
    
    echo json_encode([
        'success' => true,
        'reclamations' => $reclamations
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'reclamations' => []
    ]);
}
?>