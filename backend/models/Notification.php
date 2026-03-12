<?php
require_once '../config/database.php';

class Notification {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // Créer une notification
    public function create($utilisateur_id, $titre, $contenu, $type) {
        $sql = "INSERT INTO notification (utilisateur_id, titre, contenu, type) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$utilisateur_id, $titre, $contenu, $type]);
    }
    
    // Récupérer les notifications d'un utilisateur
    public function getByUser($utilisateur_id) {
        $sql = "SELECT * FROM notification WHERE utilisateur_id = ? ORDER BY date_envoi DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$utilisateur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Marquer comme lu
    public function markAsRead($id) {
        $sql = "UPDATE notification SET est_lu = TRUE WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
}
?>