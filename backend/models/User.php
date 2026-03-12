<?php
require_once '../config/database.php';

class User {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // Authentifier un utilisateur
    public function login($email, $password) {
        $sql = "SELECT u.*, c.nom as complex_nom 
                FROM utilisateur u
                LEFT JOIN complex c ON u.complex_id = c.id
                WHERE u.email = ? AND u.password = MD5(?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email, $password]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Récupérer un utilisateur par ID
    public function getUserById($id) {
        $sql = "SELECT u.*, c.nom as complex_nom 
                FROM utilisateur u
                LEFT JOIN complex c ON u.complex_id = c.id
                WHERE u.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Récupérer tous les utilisateurs par rôle
    public function getUsersByRole($role) {
        $sql = "SELECT u.*, c.nom as complex_nom 
                FROM utilisateur u
                LEFT JOIN complex c ON u.complex_id = c.id
                WHERE u.role = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>