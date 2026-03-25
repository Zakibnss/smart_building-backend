<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // GET - Récupérer toutes les réclamations
    if ($method === 'GET') {
        $query = "SELECT r.*, 
                         u.nom as resident_nom, 
                         res.numero_appartement as appartement,
                         a.nom as assigne_a_nom
                  FROM reclamation r
                  LEFT JOIN resident res ON r.resident_id = res.id
                  LEFT JOIN utilisateur u ON res.utilisateur_id = u.id
                  LEFT JOIN utilisateur a ON r.assigne_a = a.id
                  ORDER BY r.date_creation DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'reclamations' => $reclamations
        ]);
        exit;
    }
    
    // PUT - Mettre à jour le statut
    if ($method === 'PUT' && isset($_GET['id'])) {
        $reclamationId = $_GET['id'];
        $input = json_decode(file_get_contents('php://input'), true);
        $statut = $input['statut'] ?? '';
        
        if (empty($statut)) {
            echo json_encode(['success' => false, 'message' => 'Statut requis']);
            exit;
        }
        
        $query = "UPDATE reclamation SET statut = :statut WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':statut' => $statut,
            ':id' => $reclamationId
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur mise à jour']);
        }
        exit;
    }
    
    // POST - Assigner une réclamation
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'assign') {
            $reclamationId = $input['reclamation_id'];
            $agentId = $input['agent_id'];
            
            $query = "UPDATE reclamation SET assigne_a = :agent_id WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([
                ':agent_id' => $agentId,
                ':id' => $reclamationId
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Réclamation assignée']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur assignation']);
            }
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Méthode non supportée']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>