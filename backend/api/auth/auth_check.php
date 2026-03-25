<?php
// auth/auth_check.php

function checkAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Non authentifié. Veuillez vous reconnecter.'
        ]);
        exit;
    }
    
    return $_SESSION['user_id'];
}

function checkRole($allowed_roles) {
    if (!isset($_SESSION['user_role'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Non authentifié'
        ]);
        exit;
    }
    
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Accès non autorisé. Rôle requis: ' . implode(', ', $allowed_roles)
        ]);
        exit;
    }
    
    return $_SESSION['user_id'];
}