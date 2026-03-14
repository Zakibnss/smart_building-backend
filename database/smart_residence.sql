-- =============================================
-- BASE DE DONNÉES SMART BUILDING MANAGEMENT
-- =============================================

-- Créer la base de données
CREATE DATABASE IF NOT EXISTS smart_residence;
USE smart_residence;

-- =============================================
-- TABLE COMPLEX
-- =============================================
CREATE TABLE complex (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    adresse TEXT NOT NULL,
    ville VARCHAR(100),
    code_postal VARCHAR(10),
    telephone VARCHAR(20),
    email VARCHAR(100),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- TABLE UTILISATEUR (AVEC COMPLEX_ID)
-- =============================================
CREATE TABLE utilisateur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complex_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    role ENUM('admin', 'agent_service', 'agent_securite', 'resident') NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complex_id) REFERENCES complex(id)
);

-- =============================================
-- TABLE RESIDENT
-- =============================================
CREATE TABLE resident (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT UNIQUE NOT NULL,
    complex_id INT NOT NULL,
    numero_appartement VARCHAR(20) NOT NULL,
    batiment VARCHAR(10),
    date_entree DATE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id),
    FOREIGN KEY (complex_id) REFERENCES complex(id)
);

-- =============================================
-- TABLE PARKING
-- =============================================
CREATE TABLE parking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complex_id INT NOT NULL,
    numero_place VARCHAR(20) UNIQUE NOT NULL,
    type ENUM('resident', 'visiteur') NOT NULL,
    statut ENUM('libre', 'occupee', 'reservee') DEFAULT 'libre',
    resident_id INT NULL,
    date_occupation TIMESTAMP NULL,
    FOREIGN KEY (complex_id) REFERENCES complex(id),
    FOREIGN KEY (resident_id) REFERENCES resident(id)
);

-- =============================================
-- TABLE PLACE_PARKING
-- =============================================
CREATE TABLE place_parking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parking_id INT NOT NULL,
    resident_id INT NULL,
    complex_id INT NOT NULL,
    date_assignation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigne_par INT,
    FOREIGN KEY (parking_id) REFERENCES parking(id),
    FOREIGN KEY (resident_id) REFERENCES resident(id),
    FOREIGN KEY (complex_id) REFERENCES complex(id),
    FOREIGN KEY (assigne_par) REFERENCES utilisateur(id)
);

-- =============================================
-- TABLE COLIS
-- =============================================
CREATE TABLE colis (
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
    FOREIGN KEY (complex_id) REFERENCES complex(id),
    FOREIGN KEY (resident_id) REFERENCES resident(id),
    FOREIGN KEY (agent_securite_id) REFERENCES utilisateur(id)
);

-- =============================================
-- TABLE RECLAMATION
-- =============================================
CREATE TABLE reclamation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complex_id INT NOT NULL,
    resident_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    categorie VARCHAR(50),
    statut ENUM('en_attente', 'en_cours', 'resolue', 'rejetee') DEFAULT 'en_attente',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_resolution TIMESTAMP NULL,
    assigne_a INT NULL,
    FOREIGN KEY (complex_id) REFERENCES complex(id),
    FOREIGN KEY (resident_id) REFERENCES resident(id),
    FOREIGN KEY (assigne_a) REFERENCES utilisateur(id)
);

-- =============================================
-- TABLE NOTIFICATION
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
-- TABLE SMARTMAILBOX
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
-- TABLE SERVICE
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
-- TABLE MISSION
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

-- 1. Ajouter un complexe
INSERT INTO complex (nom, adresse, ville, telephone, email) VALUES
('Résidence Les Jardins', '123 Rue de la Paix', 'Alger', '023456789', 'contact@jardins.dz');

-- 2. Ajouter des utilisateurs (mdp: 123456 en MD5)
INSERT INTO utilisateur (complex_id, nom, email, password, telephone, role) VALUES
(1, 'Admin Principal', 'admin@test.com', MD5('123456'), '0555000011', 'admin'),
(1, 'Agent Service 1', 'service1@test.com', MD5('123456'), '0555000022', 'agent_service'),
(1, 'Agent Service 2', 'service2@test.com', MD5('123456'), '0555000033', 'agent_service'),
(1, 'Agent Sécurité 1', 'securite1@test.com', MD5('123456'), '0555000044', 'agent_securite'),
(1, 'Agent Sécurité 2', 'securite2@test.com', MD5('123456'), '0555000055', 'agent_securite'),
(1, 'Ahmed Resident', 'ahmed@test.com', MD5('123456'), '0555000066', 'resident'),
(1, 'Fatima Resident', 'fatima@test.com', MD5('123456'), '0555000077', 'resident'),
(1, 'Mohamed Resident', 'mohamed@test.com', MD5('123456'), '0555000088', 'resident');

-- 3. Ajouter des résidents
INSERT INTO resident (utilisateur_id, complex_id, numero_appartement, batiment, date_entree) VALUES
(6, 1, 'A101', 'A', '2024-01-15'),
(7, 1, 'B205', 'B', '2024-02-01'),
(8, 1, 'C310', 'C', '2024-01-20');

-- 4. Ajouter des places de parking
INSERT INTO parking (complex_id, numero_place, type, statut) VALUES
(1, 'P001', 'resident', 'libre'),
(1, 'P002', 'resident', 'libre'),
(1, 'P003', 'resident', 'libre'),
(1, 'V001', 'visiteur', 'libre'),
(1, 'V002', 'visiteur', 'libre'),
(1, 'V003', 'visiteur', 'libre');

-- 5. Ajouter des réclamations
INSERT INTO reclamation (complex_id, resident_id, titre, description, categorie, statut) VALUES
(1, 1, 'Problème électricité', 'Les lumières du couloir ne fonctionnent pas', 'électricité', 'en_cours'),
(1, 2, 'Fuite d eau', 'Fuite dans la salle de bain', 'plomberie', 'en_attente'),
(1, 3, 'Bruit', 'Bruit excessif la nuit', 'voisinage', 'resolue');

-- 6. Ajouter des colis
INSERT INTO colis (complex_id, resident_id, agent_securite_id, description, type_colis, statut, code_retrait) VALUES
(1, 1, 4, 'Colis Amazon', 'carton', 'en_attente', 'COL123'),
(1, 2, 5, 'Lettre recommandée', 'lettre', 'remis', 'COL456'),
(1, 3, 4, 'Meuble', 'grand_carton', 'en_attente', 'COL789');

-- 7. Ajouter des notifications
INSERT INTO notification (utilisateur_id, titre, contenu, type) VALUES
(6, 'Colis arrivé', 'Votre colis COL123 est arrivé', 'colis'),
(7, 'Réclamation mise à jour', 'Votre réclamation est en cours de traitement', 'reclamation'),
(8, 'Place de parking', 'Votre place de parking est réservée', 'parking');

-- 8. Ajouter smartmailbox
INSERT INTO smartmailbox (resident_id, colis_id, statut) VALUES
(1, 1, 'colis_present'),
(2, NULL, 'vide'),
(3, 3, 'colis_present');

-- 9. Ajouter des demandes de service
INSERT INTO service (resident_id, titre, description, type_service, statut, priorite) VALUES
(1, 'Changer ampoule', 'Ampoule grillée dans le salon', 'maintenance', 'en_attente', 'normale'),
(2, 'Réparer robinet', 'Robinet qui fuit', 'plomberie', 'assigne', 'haute'),
(3, 'Problème électrique', 'Prise qui ne fonctionne pas', 'electricite', 'termine', 'normale');

-- 10. Ajouter des missions
INSERT INTO mission (service_id, agent_service_id, statut) VALUES
(1, 2, 'en_attente'),
(2, 3, 'acceptee'),
(3, 2, 'terminee');
