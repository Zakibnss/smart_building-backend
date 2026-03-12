<?php
require_once '../config/database.php';

class Reclamation {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // Créer une réclamation
    public function create($data) {
        $sql = "INSERT INTO reclamation (complex_id, resident_id, titre, description, categorie) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['complex_id'],
            $data['resident_id'],
            $data['titre'],
            $data['description'],
            $data['categorie']
        ]);
    }
    
    // Récupérer les réclamations d'un résident
    public function getByResident($resident_id) {
        $sql = "SELECT * FROM reclamation WHERE resident_id = ? ORDER BY date_creation DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$resident_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Mettre à jour le statut
    public function updateStatut($id, $statut) {
        $sql = "UPDATE reclamation SET statut = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$statut, $id]);
    }
}
?>