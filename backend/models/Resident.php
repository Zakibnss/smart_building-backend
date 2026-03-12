<?php
require_once '../config/database.php';

class Resident {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // Récupérer un résident par ID utilisateur
    public function getResidentByUserId($user_id) {
        $sql = "SELECT r.*, u.nom, u.email, u.telephone 
                FROM resident r
                JOIN utilisateur u ON r.utilisateur_id = u.id
                WHERE r.utilisateur_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Récupérer tous les résidents
    public function getAllResidents() {
        $sql = "SELECT r.*, u.nom, u.email, u.telephone, c.nom as complex_nom
                FROM resident r
                JOIN utilisateur u ON r.utilisateur_id = u.id
                JOIN complex c ON r.complex_id = c.id";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>