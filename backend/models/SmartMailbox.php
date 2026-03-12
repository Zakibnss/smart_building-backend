<?php
require_once '../config/database.php';

class SmartMailbox {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // Récupérer la smart mailbox d'un résident
    public function getByResident($resident_id) {
        $sql = "SELECT s.*, c.description as colis_description 
                FROM smartmailbox s
                LEFT JOIN colis c ON s.colis_id = c.id
                WHERE s.resident_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$resident_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Mettre à jour le statut
    public function updateStatut($resident_id, $statut, $colis_id = null) {
        $sql = "UPDATE smartmailbox SET statut = ?, colis_id = ?, date_depot = NOW() WHERE resident_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$statut, $colis_id, $resident_id]);
    }
}
?>