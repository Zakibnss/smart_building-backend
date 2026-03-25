<?php
// backend/api/agent_service/missions.php
require_once __DIR__ . '/../config.php';

// Désactiver l'affichage des erreurs pour éviter le HTML
error_reporting(0);
ini_set('display_errors', 0);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $agent_id = $_GET['agent_id'] ?? null;
    $mission_id = $_GET['id'] ?? null;

    // GET - Récupérer les missions de l'agent
    if ($method === 'GET' && $agent_id) {
        $query = "SELECT s.*, r.numero_appartement as appartement, 
                         u.nom as resident_nom, u.telephone,
                         m.id as mission_id, m.statut as mission_statut,
                         m.date_assignation, m.date_fin
                  FROM mission m
                  JOIN service s ON m.service_id = s.id
                  JOIN resident r ON s.resident_id = r.id
                  JOIN utilisateur u ON r.utilisateur_id = u.id
                  WHERE m.agent_service_id = :agent_id
                  ORDER BY m.date_assignation DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':agent_id' => $agent_id]);
        $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($missions as &$mission) {
            $mission['id'] = (int)$mission['id'];
            $mission['service_id'] = (int)$mission['service_id'];
            $mission['mission_id'] = (int)$mission['mission_id'];
        }
        
        echo json_encode([
            'success' => true,
            'missions' => $missions
        ]);
        exit;
    }

    // POST - Accepter ou refuser une mission
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
            exit;
        }
        
        $action = $input['action'] ?? '';
        $demande_id = $input['demande_id'] ?? 0;
        $agent_id = $input['agent_id'] ?? 0;
        
        if ($demande_id == 0 || $agent_id == 0) {
            echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
            exit;
        }
        
        // Vérifier si une mission existe déjà pour ce service
        $checkQuery = "SELECT id FROM mission WHERE service_id = :service_id";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([':service_id' => $demande_id]);
        $existingMission = $checkStmt->fetch();
        
        if ($existingMission && $action === 'accepter') {
            echo json_encode(['success' => false, 'message' => 'Ce service a déjà été assigné à un agent']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        try {
            if ($action === 'accepter') {
                // Vérifier si le service existe et est en attente
                $checkQuery = "SELECT id FROM service WHERE id = :id AND statut = 'en_attente'";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([':id' => $demande_id]);
                $service = $checkStmt->fetch();
                
                if (!$service) {
                    echo json_encode(['success' => false, 'message' => 'Service non disponible ou déjà assigné']);
                    $pdo->rollBack();
                    exit;
                }
                
                // Créer une mission
                $query = "INSERT INTO mission (service_id, agent_service_id, date_assignation, statut) 
                          VALUES (:service_id, :agent_id, NOW(), 'en_cours')";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':service_id' => $demande_id,
                    ':agent_id' => $agent_id
                ]);
                
                // Mettre à jour le statut du service
                $query = "UPDATE service SET statut = 'assigne', agent_service_id = :agent_id WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':agent_id' => $agent_id,
                    ':id' => $demande_id
                ]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Mission acceptée avec succès'
                ]);
            } 
            elseif ($action === 'refuser') {
                // Mettre à jour le statut du service
                $query = "UPDATE service SET statut = 'en_attente' WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $demande_id]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Mission refusée'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
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