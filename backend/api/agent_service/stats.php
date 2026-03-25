<?php
// backend/api/agent_service/stats.php
require_once __DIR__ . '/../config.php';

try {
    $agent_id = $_GET['agent_id'] ?? 0;
    
    if ($agent_id == 0) {
        echo json_encode(['success' => false, 'message' => 'ID agent requis']);
        exit;
    }
    
    // Statistiques des missions
    $query = "SELECT 
                COUNT(CASE WHEN m.statut = 'en_cours' THEN 1 END) as en_cours,
                COUNT(CASE WHEN m.statut = 'terminee' THEN 1 END) as terminees,
                COUNT(CASE WHEN m.statut = 'en_attente' THEN 1 END) as en_attente,
                COUNT(*) as total,
                AVG(TIMESTAMPDIFF(HOUR, m.date_assignation, m.date_fin)) as temps_moyen
              FROM mission m
              WHERE m.agent_service_id = :agent_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':agent_id' => $agent_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Missions ce mois
    $query = "SELECT COUNT(*) as missions_mois 
              FROM mission 
              WHERE agent_service_id = :agent_id 
              AND MONTH(date_assignation) = MONTH(NOW())
              AND YEAR(date_assignation) = YEAR(NOW())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':agent_id' => $agent_id]);
    $missionsMois = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'en_cours' => (int)($stats['en_cours'] ?? 0),
            'terminees' => (int)($stats['terminees'] ?? 0),
            'en_attente' => (int)($stats['en_attente'] ?? 0),
            'total' => (int)($stats['total'] ?? 0),
            'missions_mois' => (int)($missionsMois['missions_mois'] ?? 0),
            'temps_moyen' => round($stats['temps_moyen'] ?? 0, 1)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'stats' => []
    ]);
}
?>