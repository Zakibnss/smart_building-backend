<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    // POST - Générer un accès visiteur
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Vérifier que toutes les clés existent
        $nom_visiteur = $input['nom_visiteur'] ?? '';
        $cin = $input['cin'] ?? '';
        $appartement_cible = $input['appartement_cible'] ?? ''; // Note: c'est 'appartement_cible' pas 'appartement'
        $duree = $input['duree'] ?? '2h';
        $date_arrivee = $input['date_arrivee'] ?? date('Y-m-d');
        
        if (empty($nom_visiteur) || empty($cin) || empty($appartement_cible)) {
            echo json_encode([
                'success' => false,
                'message' => 'Données manquantes'
            ]);
            exit;
        }
        
        // Générer un code unique
        $code_acces = rand(100000, 999999);
        
        $query = "INSERT INTO acces_visiteurs 
                  (nom_visiteur, cin, appartement, code_acces, duree, date_arrivee, statut) 
                  VALUES 
                  (:nom, :cin, :appartement, :code, :duree, :date, 'actif')";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nom' => $nom_visiteur,
            ':cin' => $cin,
            ':appartement' => $appartement_cible,
            ':code' => $code_acces,
            ':duree' => $duree,
            ':date' => $date_arrivee
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Accès généré avec succès',
            'code_acces' => $code_acces,
            'id' => $conn->lastInsertId()
        ]);
        exit;
    }

    // GET - Récupérer les accès en cours
    if ($method === 'GET') {
        $query = "SELECT * FROM acces_visiteurs WHERE statut = 'actif' ORDER BY date_arrivee DESC";
        $stmt = $conn->query($query);
        $acces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'acces' => $acces
        ]);
        exit;
    }

    // PUT - Terminer un accès
    if ($method === 'PUT') {
        $acces_id = $_GET['id'] ?? 0;
        $input = json_decode(file_get_contents('php://input'), true);
        $statut = $input['statut'] ?? 'termine';
        
        $query = "UPDATE acces_visiteurs SET statut = :statut WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':statut' => $statut,
            ':id' => $acces_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Accès terminé'
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>