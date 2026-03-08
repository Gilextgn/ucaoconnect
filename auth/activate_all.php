<?php
// auth/activate_all.php
session_start();
require_once __DIR__ . '/../config/config.php';

// Vérifier l'accès (optionnel - ajoutez une vérification d'admin)
// if (!isset($_SESSION['is_admin'])) {
//     die("Accès non autorisé");
// }

try {
    // Activer tous les utilisateurs
    $conn->query("UPDATE users SET is_active = 1, email_verified = 1, verified_at = NOW()");
    
    // Marquer tous les tokens comme confirmés
    $conn->query("UPDATE email_confirmations SET confirmed = 1, confirmed_at = NOW()");
    
    echo "Tous les utilisateurs ont été activés avec succès !";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}

$conn->close();
?>