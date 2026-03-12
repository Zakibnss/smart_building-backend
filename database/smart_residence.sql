-- =============================================
-- BASE DE DONNÉES SMART BUILDING MANAGEMENT
-- =============================================

-- Créer la base de données
CREATE DATABASE IF NOT EXISTS smart_residence;
USE smart_residence;
CREATE TABLE complex (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    adresse TEXT NOT NULL,
    ville VARCHAR(100),
    code_postal VARCHAR(10),
    nombre_batiments INT DEFAULT 1,
    nombre_appartements INT DEFAULT 0,
    nombre_places_parking INT DEFAULT 0,
    telephone_accueil VARCHAR(20),
    email_contact VARCHAR(100),
    site_web VARCHAR(100),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- =============================================
-- 1. TABLE UTILISATEUR (avec tous les rôles)
-- =============================================
CREATE TABLE utilisateur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    role ENUM('admin', 'agent_service', 'agent_securite', 'resident') NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- 2. TABLE RESIDENT (informations du résident)
-- =============================================
CREATE TABLE resident (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT UNIQUE NOT NULL,
    numero_appartement VARCHAR(20) NOT NULL,
    batiment VARCHAR(10),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id) ON DELETE CASCADE
);

-- =============================================
-- 3. TABLE PARKING (places de parking)
-- =============================================
CREATE TABLE parking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_place VARCHAR(20) UNIQUE NOT NULL,
    type ENUM('resident', 'visiteur') NOT NULL,
    statut ENUM('libre', 'occupee', 'reservee') DEFAULT 'libre',
    resident_id INT NULL,
    date_occupation TIMESTAMP NULL,
    FOREIGN KEY (resident_id) REFERENCES resident(id) ON DELETE SET NULL
);

-- =============================================
-- 4. TABLE PLACE_PARKING (historique/assignation)
-- =============================================
CREATE TABLE place_parking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parking_id INT NOT NULL,
    resident_id INT NULL,
    date_assignation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_fin TIMESTAMP NULL,
    assigne_par INT,
    FOREIGN KEY (parking_id) REFERENCES parking(id),
    FOREIGN KEY (resident_id) REFERENCES resident(id),
    FOREIGN KEY (assigne_par) REFERENCES utilisateur(id)
);

-- =============================================
-- 5. TABLE COLIS (gestion des colis)
-- =============================================
CREATE TABLE colis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    agent_securite_id INT NOT NULL,
    description VARCHAR(255),
    type_colis VARCHAR(50),
    date_arrivee TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_remise TIMESTAMP NULL,
    statut ENUM('en_attente', 'remis', 'annule') DEFAULT 'en_attente',
    code_retrait VARCHAR(20),
    FOREIGN KEY (resident_id) REFERENCES resident(id),
    FOREIGN KEY (agent_securite_id) REFERENCES utilisateur(id)
);

-- =============================================
-- 6. TABLE RECLAMATION (gestion des réclamations)
-- =============================================
CREATE TABLE reclamation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    categorie VARCHAR(50),
    statut ENUM('en_attente', 'en_cours', 'resolue', 'rejetee') DEFAULT 'en_attente',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_resolution TIMESTAMP NULL,
    assigne_a INT NULL,
    FOREIGN KEY (resident_id) REFERENCES resident(id),
    FOREIGN KEY (assigne_a) REFERENCES utilisateur(id)
);

-- =============================================
-- 7. TABLE NOTIFICATION (toutes les notifications)
-- =============================================
CREATE TABLE notification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    titre VARCHAR(100),
    contenu TEXT NOT NULL,
    type ENUM('colis', 'reclamation', 'parking', 'service', 'systeme') NOT NULL,
    est_lu BOOLEAN DEFAULT FALSE,
    date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id) ON DELETE CASCADE
);

-- =============================================
-- 8. TABLE SMARTMAILBOX (boîte aux lettres intelligente)
-- =============================================
CREATE TABLE smartmailbox (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT UNIQUE NOT NULL,
    colis_id INT NULL,
    code_ouverture VARCHAR(10),
    date_depot TIMESTAMP NULL,
    date_retrait TIMESTAMP NULL,
    statut ENUM('vide', 'colis_present', 'en_attente') DEFAULT 'vide',
    notification_envoyee BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (resident_id) REFERENCES resident(id),
    FOREIGN KEY (colis_id) REFERENCES colis(id) ON DELETE SET NULL
);

-- =============================================
-- 9. TABLE SERVICE (demandes de service)
-- =============================================
CREATE TABLE service (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    agent_service_id INT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    type_service ENUM('maintenance', 'plomberie', 'electricite', 'nettoyage', 'autre') NOT NULL,
    statut ENUM('en_attente', 'assigne', 'en_cours', 'termine', 'annule') DEFAULT 'en_attente',
    date_demande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_intervention TIMESTAMP NULL,
    priorite ENUM('basse', 'normale', 'haute', 'urgente') DEFAULT 'normale',
    FOREIGN KEY (resident_id) REFERENCES resident(id),
    FOREIGN KEY (agent_service_id) REFERENCES utilisateur(id)
);

-- =============================================
-- 10. TABLE MISSION (missions des agents de service)
-- =============================================
CREATE TABLE mission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT UNIQUE NOT NULL,
    agent_service_id INT NOT NULL,
    date_assignation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('en_attente', 'acceptee', 'refusee', 'terminee') DEFAULT 'en_attente',
    date_acceptation TIMESTAMP NULL,
    date_fin TIMESTAMP NULL,
    commentaire TEXT,
    FOREIGN KEY (service_id) REFERENCES service(id),
    FOREIGN KEY (agent_service_id) REFERENCES utilisateur(id)
);

-- =============================================
-- INSÉRER DES DONNÉES DE TEST
-- =============================================

-- 1. Ajouter des utilisateurs (mdp: 123456 en MD5)
INSERT INTO utilisateur (nom, email, password, telephone, role) VALUES
('Admin Principal', 'admin@test.com', MD5('123456'), '0555000011', 'admin'),
('Agent Service 1', 'service1@test.com', MD5('123456'), '0555000022', 'agent_service'),
('Agent Service 2', 'service2@test.com', MD5('123456'), '0555000033', 'agent_service'),
('Agent Sécurité 1', 'securite1@test.com', MD5('123456'), '0555000044', 'agent_securite'),
('Agent Sécurité 2', 'securite2@test.com', MD5('123456'), '0555000055', 'agent_securite'),
('Ahmed Resident', 'ahmed@test.com', MD5('123456'), '0555000066', 'resident'),
('Fatima Resident', 'fatima@test.com', MD5('123456'), '0555000077', 'resident'),
('Mohamed Resident', 'mohamed@test.com', MD5('123456'), '0555000088', 'resident');

-- 2. Ajouter des résidents
INSERT INTO resident (utilisateur_id, numero_appartement, batiment) VALUES
(6, 'A101', 'A'),
(7, 'B205', 'B'),
(8, 'C310', 'C');

-- 3. Ajouter des places de parking
INSERT INTO parking (numero_place, type, statut) VALUES
('P001', 'resident', 'libre'),
('P002', 'resident', 'libre'),
('P003', 'resident', 'libre'),
('V001', 'visiteur', 'libre'),
('V002', 'visiteur', 'libre'),
('V003', 'visiteur', 'libre');

-- 4. Ajouter des réclamations
INSERT INTO reclamation (resident_id, titre, description, categorie, statut) VALUES
(1, 'Problème électricité', 'Les lumières du couloir ne fonctionnent pas', 'électricité', 'en_cours'),
(2, 'Fuite d eau', 'Fuite dans la salle de bain', 'plomberie', 'en_attente'),
(3, 'Bruit', 'Bruit excessif la nuit', 'voisinage', 'resolue');

-- 5. Ajouter des colis
INSERT INTO colis (resident_id, agent_securite_id, description, type_colis, statut, code_retrait) VALUES
(1, 4, 'Colis Amazon', 'carton', 'en_attente', 'COL123'),
(2, 5, 'Lettre recommandée', 'lettre', 'remis', 'COL456'),
(3, 4, 'Meuble', 'grand_carton', 'en_attente', 'COL789');

-- 6. Ajouter des notifications
INSERT INTO notification (utilisateur_id, titre, contenu, type) VALUES
(6, 'Colis arrivé', 'Votre colis COL123 est arrivé', 'colis'),
(7, 'Réclamation mise à jour', 'Votre réclamation est en cours de traitement', 'reclamation'),
(8, 'Place de parking', 'Votre place de parking est réservée', 'parking');

-- 7. Ajouter smartmailbox
INSERT INTO smartmailbox (resident_id, colis_id, statut) VALUES
(1, 1, 'colis_present'),
(2, NULL, 'vide'),
(3, 3, 'colis_present');

-- 8. Ajouter des demandes de service
INSERT INTO service (resident_id, titre, description, type_service, statut, priorite) VALUES
(1, 'Changer ampoule', 'Ampoule grillée dans le salon', 'maintenance', 'en_attente', 'normale'),
(2, 'Réparer robinet', 'Robinet qui fuit', 'plomberie', 'assigne', 'haute'),
(3, 'Problème électrique', 'Prise qui ne fonctionne pas', 'electricite', 'termine', 'normale');

-- 9. Ajouter des missions
INSERT INTO mission (service_id, agent_service_id, statut) VALUES
(1, 2, 'en_attente'),
(2, 3, 'acceptee'),
(3, 2, 'terminee');

-- =============================================
-- AFFICHER LES DONNÉES POUR VÉRIFICATION
-- =============================================

SELECT '=== UTILISATEURS ===' as '';
SELECT id, nom, email, role FROM utilisateur;

SELECT '=== RÉSIDENTS ===' as '';
SELECT r.id, u.nom, r.numero_appartement, r.batiment 
FROM resident r
JOIN utilisateur u ON r.utilisateur_id = u.id;

SELECT '=== PARKING ===' as '';
SELECT * FROM parking;

SELECT '=== RÉCLAMATIONS ===' as '';
SELECT re.id, u.nom, re.titre, re.statut 
FROM reclamation re
JOIN resident r ON re.resident_id = r.id
JOIN utilisateur u ON r.utilisateur_id = u.id;

SELECT '=== COLIS ===' as '';
SELECT c.id, u.nom, c.description, c.statut 
FROM colis c
JOIN resident r ON c.resident_id = r.id
JOIN utilisateur u ON r.utilisateur_id = u.id;

SELECT '=== NOTIFICATIONS ===' as '';
SELECT n.id, u.nom, n.titre, n.est_lu 
FROM notification n
JOIN utilisateur u ON n.utilisateur_id = u.id;

SELECT '=== SMARTMAILBOX ===' as '';
SELECT s.id, u.nom, s.statut 
FROM smartmailbox s
JOIN resident r ON s.resident_id = r.id
JOIN utilisateur u ON r.utilisateur_id = u.id;