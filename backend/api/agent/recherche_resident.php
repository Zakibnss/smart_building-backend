<?php
require_once __DIR__ . '/../config.php';

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Forcer le type de contenu JSON
header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? '';
    $nom = $_GET['nom'] ?? '';
    $appartement = $_GET['appartement'] ?? '';
    $batiment = $_GET['batiment'] ?? '';

    // Récupérer tous les résidents (SANS parking_id)
    if ($action === 'all') {
        $query = "SELECT r.id, u.nom, r.numero_appartement as appartement, r.batiment
                  FROM resident r
                  INNER JOIN utilisateur u ON r.utilisateur_id = u.id
                  WHERE u.role = 'resident' AND u.statut = 'actif'
                  ORDER BY u.nom";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'residents' => $residents,
            'count' => count($residents)
        ]);
        exit;
    }

    // Recherche par nom
    if (!empty($nom)) {
        $query = "SELECT r.id, u.nom, r.numero_appartement as appartement, r.batiment
                  FROM resident r
                  INNER JOIN utilisateur u ON r.utilisateur_id = u.id
                  WHERE u.role = 'resident' 
                  AND u.statut = 'actif'
                  AND u.nom LIKE :nom
                  ORDER BY u.nom";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':nom' => '%' . $nom . '%']);
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'residents' => $residents,
            'message' => count($residents) . ' résident(s) trouvé(s)'
        ]);
        exit;
    }

    // Recherche par appartement
    if (!empty($appartement)) {
        $query = "SELECT r.id, u.nom, r.numero_appartement as appartement, r.batiment
                  FROM resident r
                  INNER JOIN utilisateur u ON r.utilisateur_id = u.id
                  WHERE u.role = 'resident' 
                  AND u.statut = 'actif'
                  AND r.numero_appartement LIKE :appartement";
        
        $params = [':appartement' => '%' . $appartement . '%'];
        
        if (!empty($batiment)) {
            $query .= " AND r.batiment = :batiment";
            $params[':batiment'] = $batiment;
        }
        
        $query .= " ORDER BY u.nom";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'residents' => $residents,
            'message' => count($residents) . ' résident(s) trouvé(s)'
        ]);
        exit;
    }

    // Si aucun critère
    echo json_encode([
        'success' => false,
        'message' => 'Aucun critère de recherche',
        'residents' => []
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données: ' . $e->getMessage(),
        'residents' => []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'residents' => []
    ]);
}
?>