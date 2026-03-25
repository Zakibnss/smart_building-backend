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
    $id = $_GET['id'] ?? null;

    // GET - Récupérer tous les résidents
    if ($method === 'GET' && !$id) {
        $query = "SELECT r.id, u.nom, u.email, u.telephone, 
                         r.numero_appartement, r.batiment, r.date_entree,
                         u.statut, u.date_creation
                  FROM resident r
                  JOIN utilisateur u ON r.utilisateur_id = u.id
                  WHERE u.role = 'resident'
                  ORDER BY r.numero_appartement";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'residents' => $residents
        ]);
        exit;
    }
    
    // GET - Détail d'un résident
    if ($method === 'GET' && $id) {
        $query = "SELECT r.id, u.nom, u.email, u.telephone, 
                         r.numero_appartement, r.batiment, r.date_entree,
                         u.statut, u.date_creation
                  FROM resident r
                  JOIN utilisateur u ON r.utilisateur_id = u.id
                  WHERE u.role = 'resident' AND r.id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $id]);
        $resident = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'resident' => $resident
        ]);
        exit;
    }
    
    // POST - Ajouter un résident
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Vérifier si l'utilisateur existe déjà
        $checkQuery = "SELECT id FROM utilisateur WHERE email = :email";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([':email' => $input['email']]);
        
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Cet email est déjà utilisé'
            ]);
            exit;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Créer l'utilisateur
            $userQuery = "INSERT INTO utilisateur (complex_id, nom, email, password, telephone, role, statut) 
                          VALUES (1, :nom, :email, MD5(:password), :telephone, 'resident', 'actif')";
            $stmt = $pdo->prepare($userQuery);
            $stmt->execute([
                ':nom' => $input['nom'],
                ':email' => $input['email'],
                ':password' => $input['password'] ?? '123456',
                ':telephone' => $input['telephone']
            ]);
            $userId = $pdo->lastInsertId();
            
            // Créer le résident
            $residentQuery = "INSERT INTO resident (utilisateur_id, complex_id, numero_appartement, batiment, date_entree) 
                              VALUES (:user_id, 1, :appartement, :batiment, NOW())";
            $stmt = $pdo->prepare($residentQuery);
            $stmt->execute([
                ':user_id' => $userId,
                ':appartement' => $input['appartement'],
                ':batiment' => $input['batiment']
            ]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Résident ajouté avec succès',
                'id' => $pdo->lastInsertId()
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }
    
    // PUT - Modifier un résident
    if ($method === 'PUT' && $id) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $pdo->beginTransaction();
        
        try {
            // Mettre à jour l'utilisateur
            $userQuery = "UPDATE utilisateur SET nom = :nom, email = :email, telephone = :telephone 
                          WHERE id = (SELECT utilisateur_id FROM resident WHERE id = :resident_id)";
            $stmt = $pdo->prepare($userQuery);
            $stmt->execute([
                ':nom' => $input['nom'],
                ':email' => $input['email'],
                ':telephone' => $input['telephone'],
                ':resident_id' => $id
            ]);
            
            // Mettre à jour le résident
            $residentQuery = "UPDATE resident SET numero_appartement = :appartement, batiment = :batiment 
                              WHERE id = :id";
            $stmt = $pdo->prepare($residentQuery);
            $stmt->execute([
                ':appartement' => $input['appartement'],
                ':batiment' => $input['batiment'],
                ':id' => $id
            ]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Résident modifié avec succès'
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }
    
    // DELETE - Supprimer un résident (avec archivage)
    if ($method === 'DELETE' && $id) {
        $pdo->beginTransaction();
        
        try {
            // 1. Récupérer les informations du résident avant suppression
            $residentQuery = "SELECT r.*, u.nom, u.email, u.telephone, u.date_creation 
                              FROM resident r
                              JOIN utilisateur u ON r.utilisateur_id = u.id
                              WHERE r.id = :id";
            $stmt = $pdo->prepare($residentQuery);
            $stmt->execute([':id' => $id]);
            $resident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$resident) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Résident non trouvé'
                ]);
                exit;
            }
            
            // 2. Récupérer les réclamations du résident
            $reclamationsQuery = "SELECT id, titre, description, categorie, statut, date_creation 
                                   FROM reclamation WHERE resident_id = :resident_id";
            $stmt = $pdo->prepare($reclamationsQuery);
            $stmt->execute([':resident_id' => $id]);
            $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3. Récupérer les colis du résident
            $colisQuery = "SELECT id, type_colis, description, code_retrait, statut, date_arrivee 
                           FROM colis WHERE resident_id = :resident_id";
            $stmt = $pdo->prepare($colisQuery);
            $stmt->execute([':resident_id' => $id]);
            $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 4. Récupérer les services demandés
            $servicesQuery = "SELECT id, titre, description, type_service, statut, date_demande 
                              FROM service WHERE resident_id = :resident_id";
            $stmt = $pdo->prepare($servicesQuery);
            $stmt->execute([':resident_id' => $id]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 5. Récupérer les accès visiteurs
            $accesQuery = "SELECT id, nom_visiteur, cin, appartement, code_acces, duree, date_arrivee, statut 
                           FROM acces_visiteurs WHERE appartement = :appartement";
            $stmt = $pdo->prepare($accesQuery);
            $stmt->execute([':appartement' => $resident['numero_appartement']]);
            $accesVisiteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 6. Archiver les données
            $archiveQuery = "INSERT INTO resident_archive (
                resident_id, date_suppression, raison_suppression, supprime_par,
                reclamations_data, colis_data, services_data, acces_visiteurs_data
            ) VALUES (
                :resident_id, NOW(), :raison, :supprime_par,
                :reclamations, :colis, :services, :acces
            )";
            
            $stmt = $pdo->prepare($archiveQuery);
            $stmt->execute([
                ':resident_id' => $id,
                ':raison' => 'Supprimé par administrateur',
                ':supprime_par' => 'Admin',
                ':reclamations' => json_encode($reclamations, JSON_UNESCAPED_UNICODE),
                ':colis' => json_encode($colis, JSON_UNESCAPED_UNICODE),
                ':services' => json_encode($services, JSON_UNESCAPED_UNICODE),
                ':acces' => json_encode($accesVisiteurs, JSON_UNESCAPED_UNICODE)
            ]);
            
            // 7. Mettre à jour le statut de l'utilisateur (soft delete)
            $updateQuery = "UPDATE utilisateur SET statut = 'inactif' 
                            WHERE id = :user_id";
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([':user_id' => $resident['utilisateur_id']]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Résident supprimé et archivé avec succès'
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