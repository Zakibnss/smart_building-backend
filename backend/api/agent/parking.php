<?php
require_once __DIR__ . '/../config.php';

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $type = $_GET['type'] ?? '';

    // ==================== GET ====================
    if ($method === 'GET') {
        // Récupérer les places résidents
        if ($type === 'resident') {
            $query = "SELECT p.id, p.numero_place, 
                             CASE WHEN p.statut = 'occupee' THEN 'Occupé' ELSE 'Libre' END as statut,
                             CASE WHEN p.resident_id IS NOT NULL THEN u.nom ELSE NULL END as resident_nom
                      FROM parking p
                      LEFT JOIN resident r ON p.resident_id = r.id
                      LEFT JOIN utilisateur u ON r.utilisateur_id = u.id
                      WHERE p.type = 'resident'
                      ORDER BY p.numero_place";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'places' => $places
            ]);
            exit;
        }
        
        // Récupérer les places visiteurs
        if ($type === 'visiteur') {
            $query = "SELECT id, numero_place, 
                             CASE WHEN statut = 'occupee' THEN 'Occupé' ELSE 'Libre' END as statut,
                             visiteur_nom, 
                             visiteur_immatriculation as immatriculation
                      FROM parking 
                      WHERE type = 'visiteur'
                      ORDER BY numero_place";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'places' => $places
            ]);
            exit;
        }
    }

    // ==================== POST (Réservation) ====================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'message' => 'Données JSON invalides'
            ]);
            exit;
        }

        $action = $input['action'] ?? '';

        if ($action === 'reserver') {
            $parking_id = $input['parking_id'];
            $nom_visiteur = $input['nom_visiteur'];
            $immatriculation = $input['immatriculation'];
            
            // Vérifier que la place existe et est libre
            $checkQuery = "SELECT id, statut FROM parking WHERE id = :id AND type = 'visiteur'";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->execute([':id' => $parking_id]);
            $place = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$place) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Place non trouvée'
                ]);
                exit;
            }
            
            if ($place['statut'] !== 'libre') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cette place est déjà occupée'
                ]);
                exit;
            }
            
            // Mettre à jour la place avec les bons noms de colonnes
            $query = "UPDATE parking 
                      SET statut = 'occupee', 
                          visiteur_nom = :nom, 
                          visiteur_immatriculation = :immat,
                          date_occupation = NOW()
                      WHERE id = :id";
            
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([
                ':nom' => $nom_visiteur,
                ':immat' => $immatriculation,
                ':id' => $parking_id
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Place réservée avec succès'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la réservation'
                ]);
            }
            exit;
        }
    }

    // ==================== PUT (Libération) ====================
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'message' => 'Données JSON invalides'
            ]);
            exit;
        }

        $action = $input['action'] ?? '';

        if ($action === 'liberer') {
            $parking_id = $input['parking_id'];
            
            $query = "UPDATE parking 
                      SET statut = 'libre', 
                          visiteur_nom = NULL, 
                          visiteur_immatriculation = NULL,
                          date_occupation = NULL
                      WHERE id = :id AND type = 'visiteur'";
            
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([':id' => $parking_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Place libérée avec succès'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la libération'
                ]);
            }
            exit;
        }
    }

    echo json_encode([
        'success' => false,
        'message' => 'Action non supportée'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>