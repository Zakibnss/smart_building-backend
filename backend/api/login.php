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

// RÉCUPÉRER LES DONNÉES - VERSION AMÉLIORÉE
$email = '';
$password = '';

// 1. Essayer de récupérer depuis php://input (JSON)
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if ($data && isset($data['email']) && isset($data['password'])) {
    // Format JSON
    $email = $data['email'];
    $password = $data['password'];
    $source = 'JSON';
} 
// 2. Essayer depuis POST (form-data)
else if (isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $source = 'POST';
}
// 3. Essayer depuis GET (pour test)
else if (isset($_GET['email']) && isset($_GET['password'])) {
    $email = $_GET['email'];
    $password = $_GET['password'];
    $source = 'GET';
}

// DÉBOGAGE - À SUPPRIMER PLUS TARD
error_log("Source: " . ($source ?? 'aucune'));
error_log("Email: " . $email);
error_log("Password: " . $password);
error_log("Raw input: " . file_get_contents("php://input"));

if (!empty($email) && !empty($password)) {
    $result = $user->login($email, $password);
    
    if ($result) {
        session_start();
        $_SESSION['user_id'] = $result['id'];
        $_SESSION['user_role'] = $result['role'];
        $_SESSION['user_name'] = $result['nom'];
        $_SESSION['complex_id'] = $result['complex_id'];
        
        $response = [
            "success" => true,
            "message" => "Connexion réussie (via $source)",
            "user" => [
                "id" => $result['id'],
                "nom" => $result['nom'],
                "email" => $result['email'],
                "role" => $result['role'],
                "complex_id" => $result['complex_id'],
                "complex_nom" => $result['complex_nom'] ?? null
            ]
        ];
        
        if($result['role'] == 'resident') {
            $resident = $residentModel->getResidentByUserId($result['id']);
            if($resident) {
                $response['user']['resident_id'] = $resident['id'];
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
        "message" => "Email et mot de passe requis. (Reçu: email=" . ($email ?: 'vide') . ", password=" . ($password ?: 'vide') . ", source=" . ($source ?? 'aucune') . ")"
    ]);
}
?>