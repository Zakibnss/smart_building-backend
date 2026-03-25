<?php
require_once __DIR__ . '/../config.php';

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $agent_id = $_GET['agent_id'] ?? 0;

    // GET - Récupérer les notifications
    if ($method === 'GET') {
        // Vérifier si la table existe, sinon créer des données simulées
        try {
            $query = "SELECT 1 FROM notifications_agent LIMIT 1";
            $pdo->query($query);
        } catch (Exception $e) {
            // La table n'existe pas, retourner des données vides
            echo json_encode([
                'success' => true,
                'notifications' => [],
                'non_lues' => 0
            ]);
            exit;
        }

        // Récupérer les notifications
        $query = "SELECT * FROM notifications_agent 
                  WHERE agent_id = :agent_id 
                  ORDER BY date_creation DESC 
                  LIMIT 20";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':agent_id' => $agent_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Compter les non lues
        $countQuery = "SELECT COUNT(*) as non_lues FROM notifications_agent 
                       WHERE agent_id = :agent_id AND est_lue = 0";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute([':agent_id' => $agent_id]);
        $count = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'non_lues' => $count['non_lues'] ?? 0
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>