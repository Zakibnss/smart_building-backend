<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $archive_id = $_GET['id'] ?? null;

    // GET - Récupérer toutes les archives
    if ($method === 'GET' && !$archive_id) {
        $query = "SELECT r.id, u.nom, u.email, u.telephone, 
                         r.numero_appartement as appartement, r.batiment,
                         u.date_creation,
                         a.id as archive_id, a.date_suppression, a.raison_suppression, 
                         a.supprime_par, a.reclamations_data, a.colis_data, 
                         a.services_data, a.acces_visiteurs_data
                  FROM resident_archive a
                  JOIN resident r ON a.resident_id = r.id
                  JOIN utilisateur u ON r.utilisateur_id = u.id
                  ORDER BY a.date_suppression DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $archives = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Décoder les données JSON
        foreach ($archives as &$archive) {
            $archive['reclamations'] = json_decode($archive['reclamations_data'] ?? '[]', true);
            $archive['colis'] = json_decode($archive['colis_data'] ?? '[]', true);
            $archive['services'] = json_decode($archive['services_data'] ?? '[]', true);
            $archive['acces_visiteurs'] = json_decode($archive['acces_visiteurs_data'] ?? '[]', true);
            unset($archive['reclamations_data'], $archive['colis_data'], $archive['services_data'], $archive['acces_visiteurs_data']);
        }
        
        echo json_encode([
            'success' => true,
            'archives' => $archives
        ]);
        exit;
    }
    
    // GET - Détail d'une archive spécifique
    if ($method === 'GET' && $archive_id) {
        $query = "SELECT r.id, u.nom, u.email, u.telephone, 
                         r.numero_appartement as appartement, r.batiment,
                         u.date_creation,
                         a.date_suppression, a.raison_suppression, 
                         a.supprime_par, a.reclamations_data, a.colis_data, 
                         a.services_data, a.acces_visiteurs_data
                  FROM resident_archive a
                  JOIN resident r ON a.resident_id = r.id
                  JOIN utilisateur u ON r.utilisateur_id = u.id
                  WHERE a.id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $archive_id]);
        $archive = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($archive) {
            $archive['reclamations'] = json_decode($archive['reclamations_data'] ?? '[]', true);
            $archive['colis'] = json_decode($archive['colis_data'] ?? '[]', true);
            $archive['services'] = json_decode($archive['services_data'] ?? '[]', true);
            $archive['acces_visiteurs'] = json_decode($archive['acces_visiteurs_data'] ?? '[]', true);
            unset($archive['reclamations_data'], $archive['colis_data'], $archive['services_data'], $archive['acces_visiteurs_data']);
        }
        
        echo json_encode([
            'success' => true,
            'archive' => $archive
        ]);
        exit;
    }
    
    // POST - Restaurer un résident
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'restaurer') {
            $resident_id = $input['resident_id'];
            
            $pdo->beginTransaction();
            
            try {
                // 1. Récupérer l'utilisateur_id avant de supprimer l'archive
                $getUserQuery = "SELECT utilisateur_id FROM resident WHERE id = :resident_id";
                $stmt = $pdo->prepare($getUserQuery);
                $stmt->execute([':resident_id' => $resident_id]);
                $resident = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$resident) {
                    throw new Exception('Résident non trouvé');
                }
                
                // 2. Supprimer l'archive
                $deleteQuery = "DELETE FROM resident_archive WHERE resident_id = :resident_id";
                $stmt = $pdo->prepare($deleteQuery);
                $stmt->execute([':resident_id' => $resident_id]);
                
                // 3. Marquer le résident comme actif
                $updateQuery = "UPDATE utilisateur SET statut = 'actif' WHERE id = :user_id";
                $stmt = $pdo->prepare($updateQuery);
                $stmt->execute([':user_id' => $resident['utilisateur_id']]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Résident restauré avec succès'
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            exit;
        }
    }
    
    // DELETE - Supprimer définitivement une archive (suppression complète)
    if ($method === 'DELETE' && $archive_id) {
        $pdo->beginTransaction();
        
        try {
            // 1. Récupérer les informations avant suppression
            $getArchiveQuery = "SELECT a.resident_id, r.utilisateur_id, u.email, u.nom
                               FROM resident_archive a
                               JOIN resident r ON a.resident_id = r.id
                               JOIN utilisateur u ON r.utilisateur_id = u.id
                               WHERE a.id = :id";
            $stmt = $pdo->prepare($getArchiveQuery);
            $stmt->execute([':id' => $archive_id]);
            $archive = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$archive) {
                throw new Exception('Archive non trouvée');
            }
            
            $resident_id = $archive['resident_id'];
            $utilisateur_id = $archive['utilisateur_id'];
            
            // 2. Supprimer les données associées (réclamations, colis, services, accès visiteurs)
            // Réclamations
            $stmt = $pdo->prepare("DELETE FROM reclamation WHERE resident_id = :resident_id");
            $stmt->execute([':resident_id' => $resident_id]);
            
            // Colis
            $stmt = $pdo->prepare("DELETE FROM colis WHERE resident_id = :resident_id");
            $stmt->execute([':resident_id' => $resident_id]);
            
            // Services
            $stmt = $pdo->prepare("DELETE FROM service WHERE resident_id = :resident_id");
            $stmt->execute([':resident_id' => $resident_id]);
            
            // Place parking (libérer la place)
            $stmt = $pdo->prepare("UPDATE parking SET resident_id = NULL, statut = 'libre' WHERE resident_id = :resident_id");
            $stmt->execute([':resident_id' => $resident_id]);
            
            // Notifications
            $stmt = $pdo->prepare("DELETE FROM notification WHERE utilisateur_id = :utilisateur_id");
            $stmt->execute([':utilisateur_id' => $utilisateur_id]);
            
            // Smartmailbox (si existe)
            $stmt = $pdo->prepare("DELETE FROM smartmailbox WHERE resident_id = :resident_id");
            $stmt->execute([':resident_id' => $resident_id]);
            
            // 3. Supprimer le résident
            $stmt = $pdo->prepare("DELETE FROM resident WHERE id = :resident_id");
            $stmt->execute([':resident_id' => $resident_id]);
            
            // 4. Supprimer l'utilisateur
            $stmt = $pdo->prepare("DELETE FROM utilisateur WHERE id = :utilisateur_id");
            $stmt->execute([':utilisateur_id' => $utilisateur_id]);
            
            // 5. Supprimer l'archive (déjà fait par la requête suivante, mais on le fait explicitement)
            $stmt = $pdo->prepare("DELETE FROM resident_archive WHERE id = :id");
            $stmt->execute([':id' => $archive_id]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Archive et toutes les données associées supprimées définitivement'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Méthode non supportée']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>