<?php
function authenticate() {
    // Vérifier d'abord la session
    session_start();
    
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    
    // Sinon, vérifier si un user_id est passé en paramètre GET
    if (isset($_GET['user_id'])) {
        return intval($_GET['user_id']);
    }
    
    // Sinon, vérifier si un user_id est passé en POST
    if (isset($_POST['user_id'])) {
        return intval($_POST['user_id']);
    }
    
    // Si rien n'est trouvé
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non authentifié'
    ]);
    exit();
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}
?>