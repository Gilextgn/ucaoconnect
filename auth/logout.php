<?php
// auth/logout.php
session_start();

// Vérifier si l'utilisateur est connecté
if (isset($_SESSION['user_id']) && isset($_SESSION['access_token'])) {
    try {
        // Déconnexion de Supabase
        require_once __DIR__ . '/../config/database.php';
        
        $url = SUPABASE_URL . '/auth/v1/logout';
        $headers = [
            'Authorization: Bearer ' . $_SESSION['access_token'],
            'apikey: ' . SUPABASE_KEY
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        curl_exec($ch);
        curl_close($ch);
        
    } catch (Exception $e) {
        // Continuer même en cas d'erreur de déconnexion
        error_log("Logout error: " . $e->getMessage());
    }
}

// Détruire toutes les données de session
$_SESSION = [];

// Détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Redirection vers la page d'accueil
header('Location: ../index.php');
exit();
?>