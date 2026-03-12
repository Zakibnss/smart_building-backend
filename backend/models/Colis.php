<?php
require_once '../config/database.php';

class Colis {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // Créer un nouveau colis
    public function create($data) {
        $sql = "INSERT INTO colis (complex_id, resident_id, agent_securite_id, description, type_colis, code_retrait) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['complex_id'],
            $data['resident_id'],
            $data['agent_securite_id'],
            $data['description'],
            $data['type_colis'],
            $data['code_retrait']
        ]);
    }
    
    // Récupérer les colis d'un résident
    public function getByResident($resident_id) {
        $sql = "SELECT c.*, u.nom as agent_nom 
                FROM colis c
                LEFT JOIN utilisateur u ON c.agent_securite_id = u.id
                WHERE c.resident_id = ?
                ORDER BY c.date_arrivee DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$resident_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>