<?php
// config/mysql_database.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'ucao_marketplace');  // Votre base de données
define('DB_USER', 'root');              // Votre utilisateur MySQL
define('DB_PASS', '');                  // Votre mot de passe MySQL (vide par défaut sur WAMP) 

/* define('DB_HOST', 'sql307.infinityfree.com');
define('DB_NAME', 'if0_41124555_marketplaceucao');  // Votre base de données
define('DB_USER', 'if0_41124555');              // Votre utilisateur MySQL
define('DB_PASS', 'marketplaceucao');                  // Votre mot de passe MySQL (vide par défaut sur WAMP) 
*/

/* $host = 'localhost';
$dbname = 'ucao_marketplace'; // Remplacez par le nom de votre base
$username = 'root'; // Utilisateur MySQL
$password = ''; // Mot de passe MySQL (vide par défaut sur WAMP) */

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Vérifier la connexion
    if ($conn->connect_error) {
        die("Échec de la connexion à MySQL : " . $conn->connect_error);
    }
    
    // Définir le jeu de caractères
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Fonction pour fermer la connexion (optionnel)
function closeConnection() {
    global $conn;
    if (isset($conn)) {
        $conn->close();
    }
}
?>
