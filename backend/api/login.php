<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer les requêtes OPTIONS (préflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../models/User.php';
require_once '../models/Resident.php';

$user = new User();
$residentModel = new Resident();

// RÉCUPÉRER LES DONNÉES
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if ($data && isset($data['email']) && isset($data['password'])) {
    $email = $data['email'];
    $password = $data['password'];
    $source = 'JSON';
} else if (isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $source = 'POST';
} else if (isset($_GET['email']) && isset($_GET['password'])) {
    $email = $_GET['email'];
    $password = $_GET['password'];
    $source = 'GET';
}

if (!empty($email) && !empty($password)) {
    $result = $user->login($email, $password);
    
    if ($result) {
        session_start();
        $_SESSION['user_id'] = $result['id'];
        $_SESSION['user_role'] = $result['role'];
        $_SESSION['user_name'] = $result['nom'];
        $_SESSION['complex_id'] = $result['complex_id'];
        
        // 🔴 CORRECTION: Forcer les IDs en int
        $response = [
            "success" => true,
            "message" => "Connexion réussie (via $source)",
            "user" => [
                "id" => (int)$result['id'],                    // ← CAST en int
                "nom" => $result['nom'],
                "email" => $result['email'],
                "role" => $result['role'],
                "complex_id" => (int)$result['complex_id'],    // ← CAST en int
                "complex_nom" => $result['complex_nom'] ?? null
            ]
        ];
        
        if($result['role'] == 'resident') {
            $resident = $residentModel->getResidentByUserId($result['id']);
            if($resident) {
                $response['user']['resident_id'] = (int)$resident['id'];        // ← CAST en int
                $response['user']['numero_appartement'] = $resident['numero_appartement'];
                $response['user']['batiment'] = $resident['batiment'];
            }
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Email ou mot de passe incorrect"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Email et mot de passe requis"
    ]);
}
?>
