<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // POST - Enregistrer un colis
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $resident_id = $input['resident_id'];
        $type_colis = $input['type_colis'];
        $agent_id = $input['agent_securite_id'];
        $code_retrait = 'COL' . time() . rand(100, 999);
        
        $query = "INSERT INTO colis (complex_id, resident_id, agent_securite_id, description, type_colis, code_retrait, statut) 
                  VALUES (1, :resident_id, :agent_id, :description, :type_colis, :code, 'en_attente')";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':resident_id' => $resident_id,
            ':agent_id' => $agent_id,
            ':description' => $type_colis,
            ':type_colis' => $type_colis,
            ':code' => $code_retrait
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Colis enregistré',
            'code' => $code_retrait
        ]);
        exit;
    }

    // GET - Derniers colis
  // Dans colis.php, remplacez la partie GET recent par :
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'recent') {
    $query = "SELECT c.*, u.nom as resident_nom, r.numero_appartement as appartement 
              FROM colis c
              JOIN resident r ON c.resident_id = r.id
              JOIN utilisateur u ON r.utilisateur_id = u.id
              ORDER BY c.date_arrivee DESC
              LIMIT 10";
    $stmt = $pdo->query($query);
    $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'colis' => $colis
    ]);
    exit;
}

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>