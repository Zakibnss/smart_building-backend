-- =============================================
-- BASE DE DONNÉES SMART BUILDING MANAGEMENT
-- =============================================

-- Créer la base de données
CREATE DATABASE IF NOT EXISTS smart_residence;
USE smart_residence;

-- =============================================
-- TABLE COMPLEX
-- =============================================
CREATE TABLE IF NOT EXISTS complex (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    adresse TEXT,
    ville VARCHAR(100),
    code_postal VARCHAR(20),
    telephone VARCHAR(20),
    email VARCHAR(100),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- TABLE UTILISATEUR
-- =============================================
CREATE TABLE IF NOT EXISTS utilisateur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complex_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    role ENUM('admin', 'agent_service', 'agent_securite', 'resident') NOT NULL,
    statut ENUM('actif', 'inactif') DEFAULT 'actif',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complex_id) REFERENCES complex(id) ON DELETE CASCADE
);

-- =============================================
-- TABLE RESIDENT
-- =============================================
CREATE TABLE IF NOT EXISTS resident (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT UNIQUE NOT NULL,
    complex_id INT NOT NULL,
    numero_appartement VARCHAR(20) NOT NULL,
    batiment VARCHAR(10),
    parking_id INT NULL,
    smartmailbox_id INT NULL,
    date_entree DATE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (complex_id) REFERENCES complex(id) ON DELETE CASCADE
);

-- =============================================
-- TABLE PARKING
-- =============================================
CREATE TABLE IF NOT EXISTS parking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complex_id INT NOT NULL,
    numero_place VARCHAR(20) UNIQUE NOT NULL,
    type ENUM('resident', 'visiteur') NOT NULL,
    statut ENUM('libre', 'occupee', 'reservee') DEFAULT 'libre',
    resident_id INT NULL,
    visiteur_nom VARCHAR(100) NULL,
    visiteur_immatriculation VARCHAR(50) NULL,
    date_occupation TIMESTAMP NULL,
    FOREIGN KEY (complex_id) REFERENCES complex(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES resident(id) ON DELETE SET NULL
);

-- =============================================
-- TABLE PLACE_PARKING (Historique)
-- =============================================
CREATE TABLE IF NOT EXISTS place_parking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parking_id INT NOT NULL,
    resident_id INT NULL,
    complex_id INT NOT NULL,
    date_assignation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigne_par INT,
    FOREIGN KEY (parking_id) REFERENCES parking(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES resident(id) ON DELETE SET NULL,
    FOREIGN KEY (complex_id) REFERENCES complex(id) ON DELETE CASCADE,
    FOREIGN KEY (assigne_par) REFERENCES utilisateur(id) ON DELETE SET NULL
);

-- =============================================
-- TABLE COLIS
-- =============================================
CREATE TABLE IF NOT EXISTS colis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complex_id INT NOT NULL,
    resident_id INT NOT NULL,
    agent_securite_id INT NOT NULL,
    description VARCHAR(255),
    type_colis VARCHAR(50),
    date_arrivee TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_remise TIMESTAMP NULL,
    statut ENUM('en_attente', 'remis', 'annule') DEFAULT 'en_attente',
    code_retrait VARCHAR(20),
    FOREIGN KEY (complex_id) REFERENCES complex(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES resident(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_securite_id) REFERENCES utilisateur(id) ON DELETE CASCADE
);

-- =============================================
-- TABLE SMARTMAILBOX
-- =============================================
CREATE TABLE IF NOT EXISTS smartmailbox (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT UNIQUE NOT NULL,
    colis_id INT NULL,
    numero_boite VARCHAR(20),
    code_ouverture VARCHAR(10),
    date_depot TIMESTAMP NULL,
    date_retrait TIMESTAMP NULL,
    statut ENUM('vide', 'colis_present', 'en_attente', 'non_disponible') DEFAULT 'vide',
    notification_envoyee BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (resident_id) REFERENCES resident(id) ON DELETE CASCADE,
    FOREIGN KEY (colis_id) REFERENCES colis(id) ON DELETE SET NULL
);

-- =============================================
-- TABLE RECLAMATION
-- =============================================
CREATE TABLE IF NOT EXISTS reclamation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complex_id INT NOT NULL,
    resident_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    categorie VARCHAR(50),
    lieu VARCHAR(100),
    priorite ENUM('basse', 'normale', 'haute', 'urgente') DEFAULT 'normale',
    statut ENUM('en_attente', 'en_cours', 'resolue', 'rejetee') DEFAULT 'en_attente',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_resolution TIMESTAMP NULL,
    assigne_a INT NULL,
    agent_service_id INT NULL,
    mission_id INT NULL,
    FOREIGN KEY (complex_id) REFERENCES complex(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES resident(id) ON DELETE CASCADE,
    FOREIGN KEY (assigne_a) REFERENCES utilisateur(id) ON DELETE SET NULL,
    FOREIGN KEY (agent_service_id) REFERENCES utilisateur(id) ON DELETE SET NULL
);

-- =============================================
-- TABLE RECLAMATION_STATUS_HISTORY
-- =============================================
CREATE TABLE IF NOT EXISTS reclamation_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reclamation_id INT NOT NULL,
    statut VARCHAR(50) NOT NULL,
    date_changement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    commentaire TEXT,
    FOREIGN KEY (reclamation_id) REFERENCES reclamation(id) ON DELETE CASCADE
);

-- =============================================
-- TABLE RECLAMATION_FEEDBACK
-- =============================================
CREATE TABLE IF NOT EXISTS reclamation_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reclamation_id INT UNIQUE NOT NULL,
    note INT CHECK (note >= 1 AND note <= 5),
    commentaire TEXT,
    date_feedback TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reclamation_id) REFERENCES reclamation(id) ON DELETE CASCADE
);

-- =============================================
-- TABLE SERVICE
-- =============================================
CREATE TABLE IF NOT EXISTS service (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    agent_service_id INT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    type_service ENUM('maintenance', 'plomberie', 'electricite', 'nettoyage', 'autre', 'Réparation', 'Gardien', 'Sécurité') NOT NULL,
    statut ENUM('en_attente', 'assigne', 'en_cours', 'termine', 'annule') DEFAULT 'en_attente',
    date_demande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_intervention TIMESTAMP NULL,
    date_souhaitee DATE NULL,
    heure_souhaitee TIME NULL,
    priorite ENUM('basse', 'normale', 'haute', 'urgente') DEFAULT 'normale',
    FOREIGN KEY (resident_id) REFERENCES resident(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_service_id) REFERENCES utilisateur(id) ON DELETE SET NULL
);

-- =============================================
-- TABLE MISSION
-- =============================================
CREATE TABLE IF NOT EXISTS mission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT UNIQUE NOT NULL,
    agent_service_id INT NOT NULL,
    date_assignation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('en_attente', 'acceptee', 'refusee', 'terminee', 'en_cours') DEFAULT 'en_attente',
    date_acceptation TIMESTAMP NULL,
    date_fin TIMESTAMP NULL,
    commentaire TEXT,
    FOREIGN KEY (service_id) REFERENCES service(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_service_id) REFERENCES utilisateur(id) ON DELETE CASCADE
);

-- =============================================
-- TABLE NOTIFICATION
-- =============================================
CREATE TABLE IF NOT EXISTS notification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    titre VARCHAR(100),
    contenu TEXT NOT NULL,
    type ENUM('colis', 'reclamation', 'parking', 'service', 'systeme', 'mission') NOT NULL,
    est_lu BOOLEAN DEFAULT FALSE,
    date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id) ON DELETE CASCADE
);

-- =============================================
-- TABLE TECHNICIANS
-- =============================================
CREATE TABLE IF NOT EXISTS technicians (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telephone VARCHAR(20),
    specialite VARCHAR(100),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- TABLE RESIDENT_ARCHIVE
-- =============================================
CREATE TABLE IF NOT EXISTS resident_archive (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    date_suppression TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    raison_suppression TEXT,
    supprime_par VARCHAR(100),
    reclamations_data JSON,
    colis_data JSON,
    services_data JSON,
    acces_visiteurs_data JSON,
    FOREIGN KEY (resident_id) REFERENCES resident(id) ON DELETE CASCADE
);

-- =============================================
-- TABLE ACCES_VISITEURS
-- =============================================
CREATE TABLE IF NOT EXISTS acces_visiteurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom_visiteur VARCHAR(100) NOT NULL,
    cin VARCHAR(50) NOT NULL,
    appartement VARCHAR(20) NOT NULL,
    code_acces VARCHAR(10) NOT NULL,
    duree VARCHAR(20) DEFAULT '2h',
    date_arrivee DATE,
    statut ENUM('actif', 'termine', 'expire') DEFAULT 'actif',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- TABLE NOTIFICATIONS_AGENT
-- =============================================
CREATE TABLE IF NOT EXISTS notifications_agent (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agent_id INT NOT NULL,
    titre VARCHAR(100),
    message TEXT,
    type VARCHAR(50),
    est_lue BOOLEAN DEFAULT FALSE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES utilisateur(id) ON DELETE CASCADE
);

-- =============================================
-- INSÉRER DES DONNÉES DE TEST
-- =============================================

-- 1. Ajouter un complexe
INSERT INTO complex (id, nom, adresse, ville, code_postal, telephone, email) VALUES 
(1, 'Résidence Les Jardins', '123 Rue de la Paix', 'Alger', '16000', '+213 23 45 67 89', 'contact@jardins.dz')
ON DUPLICATE KEY UPDATE id=id;

-- 2. Ajouter des utilisateurs (mdp: 123456 en MD5)
INSERT INTO utilisateur (complex_id, nom, email, password, telephone, role, statut) VALUES
(1, 'Admin Principal', 'admin@test.com', MD5('123456'), '+213 555 00 00 11', 'admin', 'actif'),
(1, 'Agent Service 1', 'service1@test.com', MD5('123456'), '+213 555 00 00 22', 'agent_service', 'actif'),
(1, 'Agent Service 2', 'service2@test.com', MD5('123456'), '+213 555 00 00 33', 'agent_service', 'actif'),
(1, 'Agent Sécurité 1', 'securite1@test.com', MD5('123456'), '+213 555 00 00 44', 'agent_securite', 'actif'),
(1, 'Agent Sécurité 2', 'securite2@test.com', MD5('123456'), '+213 555 00 00 55', 'agent_securite', 'actif'),
(1, 'Ahmed Benali', 'ahmed@test.com', MD5('123456'), '+213 555 00 00 66', 'resident', 'actif'),
(1, 'Fatima Kaci', 'fatima@test.com', MD5('123456'), '+213 555 00 00 77', 'resident', 'actif'),
(1, 'Mohamed Ouali', 'mohamed@test.com', MD5('123456'), '+213 555 00 00 88', 'resident', 'actif')
ON DUPLICATE KEY UPDATE email=email;

-- 3. Ajouter des résidents
INSERT INTO resident (utilisateur_id, complex_id, numero_appartement, batiment, date_entree) VALUES
(6, 1, 'A101', 'A', '2024-01-15'),
(7, 1, 'B205', 'B', '2024-02-01'),
(8, 1, 'C310', 'C', '2024-01-20')
ON DUPLICATE KEY UPDATE utilisateur_id=utilisateur_id;

-- 4. Mettre à jour les parking_id et smartmailbox_id
UPDATE resident SET parking_id = 1, smartmailbox_id = 1 WHERE id = 1;
UPDATE resident SET parking_id = 2, smartmailbox_id = 2 WHERE id = 2;
UPDATE resident SET parking_id = 3, smartmailbox_id = 3 WHERE id = 3;

-- 5. Ajouter des places de parking
INSERT INTO parking (complex_id, numero_place, type, statut, resident_id) VALUES
(1, 'P001', 'resident', 'occupee', 1),
(1, 'P002', 'resident', 'occupee', 2),
(1, 'P003', 'resident', 'occupee', 3),
(1, 'P004', 'resident', 'libre', NULL),
(1, 'P005', 'resident', 'libre', NULL),
(1, 'V001', 'visiteur', 'libre', NULL),
(1, 'V002', 'visiteur', 'libre', NULL),
(1, 'V003', 'visiteur', 'libre', NULL)
ON DUPLICATE KEY UPDATE numero_place=numero_place;

-- 6. Ajouter des smartmailbox
INSERT INTO smartmailbox (id, resident_id, colis_id, numero_boite, statut) VALUES
(1, 1, NULL, 'MB101', 'vide'),
(2, 2, NULL, 'MB205', 'vide'),
(3, 3, NULL, 'MB310', 'vide')
ON DUPLICATE KEY UPDATE id=id;

-- 7. Ajouter des colis
INSERT INTO colis (complex_id, resident_id, agent_securite_id, description, type_colis, statut, code_retrait) VALUES
(1, 1, 4, 'Colis Amazon - Livre', 'carton', 'en_attente', 'COL' || FLOOR(RAND() * 10000)),
(1, 2, 5, 'Lettre recommandée', 'lettre', 'remis', 'COL' || FLOOR(RAND() * 10000)),
(1, 3, 4, 'Colis Meuble', 'grand_carton', 'en_attente', 'COL' || FLOOR(RAND() * 10000))
ON DUPLICATE KEY UPDATE id=id;

-- 8. Ajouter des réclamations
INSERT INTO reclamation (complex_id, resident_id, titre, description, categorie, lieu, priorite, statut) VALUES
(1, 1, 'Problème électricité', 'Les lumières du couloir ne fonctionnent pas depuis 3 jours', 'Électricité', 'Couloir étage 1', 'haute', 'en_cours'),
(1, 2, 'Fuite d eau', 'Fuite importante dans la salle de bain', 'Plomberie', 'Appartement B205', 'urgente', 'en_attente'),
(1, 3, 'Bruit excessif', 'Bruit la nuit venant de l appartement voisin', 'Voisinage', 'Appartement C310', 'normale', 'resolue')
ON DUPLICATE KEY UPDATE id=id;

-- 9. Ajouter des demandes de service
INSERT INTO service (resident_id, titre, description, type_service, statut, priorite) VALUES
(1, 'Changer ampoule', 'Ampoule grillée dans le salon', 'maintenance', 'en_attente', 'normale'),
(2, 'Réparer robinet', 'Robinet de cuisine qui fuit', 'plomberie', 'assigne', 'haute'),
(3, 'Problème électrique', 'Prise électrique ne fonctionne pas', 'electricite', 'termine', 'normale')
ON DUPLICATE KEY UPDATE id=id;

-- 10. Ajouter des missions
INSERT INTO mission (service_id, agent_service_id, statut) VALUES
(1, 2, 'en_attente'),
(2, 3, 'acceptee'),
(3, 2, 'terminee')
ON DUPLICATE KEY UPDATE service_id=service_id;

-- 11. Ajouter des notifications
INSERT INTO notification (utilisateur_id, titre, contenu, type, est_lu) VALUES
(6, 'Bienvenue', 'Bienvenue dans votre espace résident', 'systeme', FALSE),
(7, 'Bienvenue', 'Bienvenue dans votre espace résident', 'systeme', FALSE),
(8, 'Bienvenue', 'Bienvenue dans votre espace résident', 'systeme', FALSE),
(6, 'Colis arrivé', 'Votre colis est arrivé à la réception', 'colis', FALSE),
(7, 'Réclamation mise à jour', 'Votre réclamation est en cours de traitement', 'reclamation', FALSE),
(8, 'Place de parking', 'Votre place de parking P003 vous est assignée', 'parking', FALSE)
ON DUPLICATE KEY UPDATE id=id;

-- 12. Ajouter des techniciens
INSERT INTO technicians (nom, email, telephone, specialite) VALUES
('Karim Hadj', 'karim@tech.com', '+213 555 11 22 33', 'Électricien'),
('Sofia Amrani', 'sofia@tech.com', '+213 555 44 55 66', 'Plombier'),
('Reda Mansouri', 'reda@tech.com', '+213 555 77 88 99', 'Maintenance')
ON DUPLICATE KEY UPDATE email=email;

-- 13. Ajouter des accès visiteurs
INSERT INTO acces_visiteurs (nom_visiteur, cin, appartement, code_acces, duree, date_arrivee, statut) VALUES
('Jean Dupont', '123456789', 'A101', FLOOR(RAND() * 900000 + 100000), '2h', CURDATE(), 'actif'),
('Marie Martin', '987654321', 'B205', FLOOR(RAND() * 900000 + 100000), '1j', CURDATE(), 'actif'),
('Pierre Durand', '456789123', 'C310', FLOOR(RAND() * 900000 + 100000), '2h', CURDATE(), 'termine')
ON DUPLICATE KEY UPDATE id=id;

-- 14. Mettre à jour les smartmailbox avec colis
UPDATE smartmailbox SET colis_id = 1, statut = 'colis_present' WHERE id = 1;
UPDATE smartmailbox SET colis_id = 3, statut = 'colis_present' WHERE id = 3;