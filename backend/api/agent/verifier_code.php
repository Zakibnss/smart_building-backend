<?php
// Accepter n'importe quel port localhost
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (strpos($origin, 'http://localhost:') === 0) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: http://localhost:49504");
}
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$code = $_GET['code'] ?? '';

// Pour debug - retourner toutes les informations
$debug = [];

try {
    // Vérifier d'abord la connexion
    $debug['connection'] = 'OK';
    
    // Compter le nombre total d'enregistrements
    $countQuery = "SELECT COUNT(*) as total FROM acces_visiteurs";
    $countStmt = $pdo->query($countQuery);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC);
    $debug['total_records'] = $total['total'];
    
    // Vérifier si le code existe (sans condition de statut)
    $checkQuery = "SELECT * FROM acces_visiteurs WHERE code_acces = :code";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([':code' => $code]);
    $acces = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($acces) {
        $debug['code_found'] = true;
        $debug['record'] = $acces;
        
        // Vérifier le statut
        if ($acces['statut'] == 'actif') {
            echo json_encode([
                'success' => true,
                'valide' => true,
                'visiteur' => [
                    'id' => $acces['id'],
                    'nom_visiteur' => $acces['nom_visiteur'],
                    'appartement' => $acces['appartement'],
                    'code_acces' => $acces['code_acces']
                ],
                'debug' => $debug
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'valide' => false,
                'message' => 'Code expiré (statut: ' . $acces['statut'] . ')',
                'debug' => $debug
            ]);
        }
    } else {
        $debug['code_found'] = false;
        
        // Récupérer les 5 derniers codes pour debug
        $lastQuery = "SELECT id, nom_visiteur, code_acces, statut FROM acces_visiteurss ORDER BY id DESC LIMIT 5";
        $lastStmt = $pdo->query($lastQuery);
        $lastCodes = $lastStmt->fetchAll(PDO::FETCH_ASSOC);
        $debug['last_5_codes'] = $lastCodes;
        
        echo json_encode([
            'success' => true,
            'valide' => false,
            'message' => 'Code invalide',
            'debug' => $debug
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'valide' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'debug' => ['error' => $e->getMessage()]
    ]);
}
?>