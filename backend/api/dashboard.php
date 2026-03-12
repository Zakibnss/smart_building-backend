<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();

if(!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Non authentifié"
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "user" => [
        "id" => $_SESSION['user_id'],
        "name" => $_SESSION['user_name'],
        "role" => $_SESSION['user_role']
    ]
]);
?>