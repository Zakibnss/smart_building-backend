<?php
// Configuration de la base de données avec le bon port
$host = 'localhost';
$port = '3310';  // IMPORTANT: Votre MySQL utilise le port 3310
$dbname = 'smart_residence';
$username = 'root';
$password = '';  // Pas de mot de passe d'après votre config

try {
    // Connexion avec le port spécifié
    $pdo = new PDO("mysql:host=$host;port=$port", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la base de données si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");
    
    // Test réussi
    // echo "Connexion réussie à MySQL sur le port $port!";
    
} catch(PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage() . "<br>
         Vérifiez que MySQL tourne sur le port 3310");
}

?>
