<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';
require_once '../../utils/auth.php';

$user_id = authenticate();

try {
    // Récupérer l'ID du résident
    $stmt = $pdo->prepare("SELECT id FROM resident WHERE utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    $resident_id = $resident['id'];

    // Statistiques globales
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
            SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'resolue' THEN 1 ELSE 0 END) as resolues,
            SUM(CASE WHEN statut = 'rejetee' THEN 1 ELSE 0 END) as rejetees
        FROM reclamation
        WHERE resident_id = ?
    ");
    $stmt->execute([$resident_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Statistiques par catégorie
    $stmt = $pdo->prepare("
        SELECT 
            categorie,
            COUNT(*) as total
        FROM reclamation
        WHERE resident_id = ?
        GROUP BY categorie
        ORDER BY total DESC
    ");
    $stmt->execute([$resident_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Temps moyen de résolution (pour les réclamations résolues)
    $stmt = $pdo->prepare("
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, date_creation, date_resolution)) as temps_moyen_heures
        FROM reclamation
        WHERE resident_id = ? AND statut = 'resolue' AND date_resolution IS NOT NULL
    ");
    $stmt->execute([$resident_id]);
    $temps_moyen = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'statistiques' => [
            'total' => (int)$stats['total'],
            'en_attente' => (int)$stats['en_attente'],
            'en_cours' => (int)$stats['en_cours'],
            'resolues' => (int)$stats['resolues'],
            'rejetees' => (int)$stats['rejetees'],
            'par_categorie' => $categories,
            'temps_moyen_resolution' => round($temps_moyen['temps_moyen_heures'] ?? 0)
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>