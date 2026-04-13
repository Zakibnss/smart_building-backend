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

try {
    // 1. Compter les résidents ACTIFS seulement
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateur WHERE role = 'resident' AND statut = 'actif'");
    $totalResidents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. Compter les agents de sécurité ACTIFS
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateur WHERE role = 'agent_securite' AND statut = 'actif'");
    $totalSecurity = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 3. Compter les agents de service ACTIFS
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateur WHERE role = 'agent_service' AND statut = 'actif'");
    $totalService = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 4. Compter les réclamations (toutes, pas de filtre statut)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reclamation");
    $totalReclamations = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 5. Compter les missions en cours
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mission WHERE statut != 'terminee'");
    $totalMissions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 6. Calculer l'occupation du parking
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM parking");
    $totalParking = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 1;
    
    $stmt = $pdo->query("SELECT COUNT(*) as occupees FROM parking WHERE statut = 'occupee' OR resident_id IS NOT NULL");
    $occupiedParking = $stmt->fetch(PDO::FETCH_ASSOC)['occupees'] ?? 0;
    
    $parkingOccupation = $totalParking > 0 ? round(($occupiedParking / $totalParking) * 100, 1) : 0;

    // 7. Compter les techniciens (tous)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM technicians");
    $totalTechnicians = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 8. Compter les réclamations en attente (pour info supplémentaire)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reclamation WHERE statut = 'en_attente'");
    $reclamationsEnAttente = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 9. Construire la réponse
    $response = [
        'success' => true,
        'stats' => [
            'total_residents' => (int)$totalResidents,
            'total_security' => (int)$totalSecurity,
            'total_service' => (int)$totalService,
            'total_reclamations' => (int)$totalReclamations,
            'total_missions' => (int)$totalMissions,
            'parking_occupation' => (float)$parkingOccupation,
            'total_technicians' => (int)$totalTechnicians,
            'reclamations_en_attente' => (int)$reclamationsEnAttente  // Optionnel
        ]
    ];

    echo json_encode($response);

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