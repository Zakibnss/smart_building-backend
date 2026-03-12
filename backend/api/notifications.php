<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();
require_once '../models/Notification.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Non authentifié"]);
    exit;
}

$notification = new Notification();
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';

switch($action) {
    case 'get':
        $notifications = $notification->getByUser($user_id);
        echo json_encode(["success" => true, "notifications" => $notifications]);
        break;
        
    case 'read':
        $id = $_GET['id'] ?? 0;
        $result = $notification->markAsRead($id);
        echo json_encode(["success" => $result]);
        break;
        
    default:
        echo json_encode(["success" => false, "message" => "Action non valide"]);
}
?>