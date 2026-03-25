<?php
// backend/api/admin/transfer_reclamation.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
        exit;
    }
    
    $reclamation_id = $input['reclamation_id'] ?? 0;
    $agent_id = $input['agent_id'] ?? 0;
    $priorite = $input['priorite'] ?? 'normale';
    
    if ($reclamation_id == 0 || $agent_id == 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        exit;
    }
    
    // Vérifier que l'agent existe et est un agent de service
    $checkAgent = "SELECT id FROM utilisateur WHERE id = :id AND role = 'agent_service' AND statut = 'actif'";
    $stmt = $pdo->prepare($checkAgent);
    $stmt->execute([':id' => $agent_id]);
    $agent = $stmt->fetch();
    
    if (!$agent) {
        echo json_encode(['success' => false, 'message' => 'Agent de service non trouvé ou inactif']);
        exit;
    }
    
    // Vérifier que la réclamation existe
    $checkReclamation = "SELECT id, titre, description, priorite FROM reclamation WHERE id = :id";
    $stmt = $pdo->prepare($checkReclamation);
    $stmt->execute([':id' => $reclamation_id]);
    $reclamation = $stmt->fetch();
    
    if (!$reclamation) {
        echo json_encode(['success' => false, 'message' => 'Réclamation non trouvée']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    try {
        // 1. Créer un service basé sur la réclamation
        $serviceQuery = "INSERT INTO service (
            resident_id, 
            type_service, 
            description, 
            priorite, 
            statut, 
            date_demande
        ) VALUES (
            (SELECT resident_id FROM reclamation WHERE id = :rec_id),
            :type,
            :description,
            :priorite,
            'en_attente',
            NOW()
        )";
        
        $stmt = $pdo->prepare($serviceQuery);
        $stmt->execute([
            ':rec_id' => $reclamation_id,
            ':type' => $reclamation['categorie'] ?? 'Maintenance',
            ':description' => $reclamation['description'],
            ':priorite' => $priorite
        ]);
        
        $service_id = $pdo->lastInsertId();
        
        // 2. Créer une mission pour l'agent
        $missionQuery = "INSERT INTO mission (
            service_id, 
            agent_service_id, 
            date_assignation, 
            statut
        ) VALUES (
            :service_id,
            :agent_id,
            NOW(),
            'en_attente'
        )";
        
        $stmt = $pdo->prepare($missionQuery);
        $stmt->execute([
            ':service_id' => $service_id,
            ':agent_id' => $agent_id
        ]);
        
        $mission_id = $pdo->lastInsertId();
        
        // 3. Mettre à jour la réclamation
        $updateReclamation = "UPDATE reclamation 
                              SET agent_service_id = :agent_id, 
                                  mission_id = :mission_id,
                                  statut = 'en_cours'
                              WHERE id = :id";
        $stmt = $pdo->prepare($updateReclamation);
        $stmt->execute([
            ':agent_id' => $agent_id,
            ':mission_id' => $mission_id,
            ':id' => $reclamation_id
        ]);
        
        // 4. Créer une notification pour l'agent
        $notificationQuery = "INSERT INTO notification (utilisateur_id, titre, contenu, type) 
                              VALUES (:agent_id, :titre, :contenu, 'mission')";
        $stmt = $pdo->prepare($notificationQuery);
        $stmt->execute([
            ':agent_id' => $agent_id,
            ':titre' => 'Nouvelle mission assignée',
            ':contenu' => 'Réclamation: ' . $reclamation['titre']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Réclamation transférée en mission avec succès',
            'mission_id' => $mission_id,
            'service_id' => $service_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>