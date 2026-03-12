<?php
require_once '../config/database.php';

class Parking {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // Récupérer toutes les places de parking
    public function getAllParking() {
        $sql = "SELECT p.*, c.nom as complex_nom, u.nom as resident_nom
                FROM parking p
                LEFT JOIN complex c ON p.complex_id = c.id
                LEFT JOIN resident r ON p.resident_id = r.id
                LEFT JOIN utilisateur u ON r.utilisateur_id = u.id";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer les places de parking par type
    public function getParkingByType($type) {
        $sql = "SELECT p.*, u.nom as resident_nom
                FROM parking p
                LEFT JOIN resident r ON p.resident_id = r.id
                LEFT JOIN utilisateur u ON r.utilisateur_id = u.id
                WHERE p.type = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Mettre à jour le statut d'une place
    public function updateStatut($id, $statut, $resident_id = null) {
        $sql = "UPDATE parking SET statut = ?, resident_id = ?, date_occupation = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$statut, $resident_id, $id]);
    }
}
?>