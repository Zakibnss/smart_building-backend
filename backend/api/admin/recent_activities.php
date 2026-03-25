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

try {
    $activities = [];

    // 1. Dernier nouveau résident
    $stmt = $pdo->query("
        SELECT u.nom, r.numero_appartement, u.date_creation 
        FROM utilisateur u
        JOIN resident r ON u.id = r.utilisateur_id
        WHERE u.role = 'resident'
        ORDER BY u.date_creation DESC
        LIMIT 1
    ");
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nom = explode(' ', $row['nom'])[0];
        $activities[] = [
            'icon' => 'person_add',
            'color' => 'lightBlue',
            'title' => 'Nouveau résident',
            'description' => $nom . ' - ' . $row['numero_appartement'],
            'time' => time_elapsed_string($row['date_creation'])
        ];
    }

    // 2. Dernière réclamation résolue
    $stmt = $pdo->query("
        SELECT r.titre, r.date_resolution 
        FROM reclamation r
        WHERE r.statut = 'resolue' AND r.date_resolution IS NOT NULL
        ORDER BY r.date_resolution DESC
        LIMIT 1
    ");
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $activities[] = [
            'icon' => 'check_circle',
            'color' => 'lightGreen',
            'title' => 'Réclamation résolue',
            'description' => $row['titre'],
            'time' => time_elapsed_string($row['date_resolution'])
        ];
    }

    // 3. Dernier colis arrivé
    $stmt = $pdo->query("
        SELECT c.*, u.nom as resident_nom 
        FROM colis c
        JOIN resident r ON c.resident_id = r.id
        JOIN utilisateur u ON r.utilisateur_id = u.id
        ORDER BY c.date_arrivee DESC
        LIMIT 1
    ");
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $prenom = explode(' ', $row['resident_nom'])[0];
        $activities[] = [
            'icon' => 'inventory',
            'color' => 'orange',
            'title' => 'Colis arrivé',
            'description' => 'Pour ' . $prenom,
            'time' => time_elapsed_string($row['date_arrivee'])
        ];
    }

    // 4. Dernière mission assignée
    $stmt = $pdo->query("
        SELECT s.type_service, m.date_assignation 
        FROM mission m
        JOIN service s ON m.service_id = s.id
        ORDER BY m.date_assignation DESC
        LIMIT 1
    ");
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $typeLabels = [
            'plomberie' => 'Plombier',
            'electricite' => 'Électricien',
            'maintenance' => 'Maintenance',
            'nettoyage' => 'Nettoyage',
            'autre' => 'Service'
        ];
        $type = $typeLabels[$row['type_service']] ?? 'Mission';
        
        $activities[] = [
            'icon' => 'build',
            'color' => 'mediumBlue',
            'title' => 'Mission assignée',
            'description' => $type,
            'time' => time_elapsed_string($row['date_assignation'])
        ];
    }

    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur SQL: ' . $e->getMessage()
    ]);
}

function time_elapsed_string($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return 'Il y a ' . $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return 'Il y a ' . $diff->m . ' mois';
    if ($diff->d > 0) return 'Il y a ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return 'Il y a ' . $diff->h . ' h';
    if ($diff->i > 0) return 'Il y a ' . $diff->i . ' min';
    return 'À l\'instant';
}
?>