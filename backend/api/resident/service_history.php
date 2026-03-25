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

    // Récupérer l'historique complet avec statistiques
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            u.nom as agent_nom,
            DATE_FORMAT(s.date_demande, '%d/%m/%Y') as date_format,
            CASE 
                WHEN s.statut = 'en_attente' THEN 'En attente'
                WHEN s.statut = 'assigne' THEN 'Assigné'
                WHEN s.statut = 'en_cours' THEN 'En cours'
                WHEN s.statut = 'termine' THEN 'Terminé'
                ELSE s.statut
            END as statut_label
        FROM service s
        LEFT JOIN utilisateur u ON s.agent_service_id = u.id
        WHERE s.resident_id = ?
        ORDER BY s.date_demande DESC
    ");
    $stmt->execute([$resident_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques
    $stats = [
        'total' => count($services),
        'en_attente' => 0,
        'en_cours' => 0,
        'termine' => 0
    ];

    foreach ($services as $service) {
        if ($service['statut'] == 'en_attente') $stats['en_attente']++;
        if ($service['statut'] == 'en_cours' || $service['statut'] == 'assigne') $stats['en_cours']++;
        if ($service['statut'] == 'termine') $stats['termine']++;
    }

    echo json_encode([
        'success' => true,
        'services' => $services,
        'statistiques' => $stats
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>