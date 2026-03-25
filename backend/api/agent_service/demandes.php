<?php
// backend/api/agent_service/demandes.php
require_once __DIR__ . '/../config.php';

try {
    $query = "SELECT s.*, r.numero_appartement as appartement, 
                     u.nom as resident_nom, u.telephone
              FROM service s
              JOIN resident r ON s.resident_id = r.id
              JOIN utilisateur u ON r.utilisateur_id = u.id
              WHERE s.statut = 'en_attente'
              ORDER BY 
                CASE WHEN s.priorite = 'urgente' THEN 1 
                     WHEN s.priorite = 'haute' THEN 2 
                     WHEN s.priorite = 'normale' THEN 3 
                     ELSE 4 END,
                s.date_demande ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($demandes as &$demande) {
        $demande['id'] = (int)$demande['id'];
        $demande['resident_id'] = (int)$demande['resident_id'];
    }
    
    echo json_encode([
        'success' => true,
        'demandes' => $demandes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'demandes' => []
    ]);
}
?>