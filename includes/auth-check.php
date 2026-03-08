<?php
require_once __DIR__ . '/../config/session.php';

$session = new SessionManager();

/**
 * Protection de route - Requiert authentification
 */
function requireAuth() {
    global $session;
    
    if (!$session->isLoggedIn()) {
        header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

/**
 * Protection de route - Redirige si déjà connecté
 */
function redirectIfLoggedIn() {
    global $session;
    
    if ($session->isLoggedIn()) {
        header('Location: /dashboard/');
        exit();
    }
}

/**
 * Vérifie si l'utilisateur est propriétaire d'une ressource
 */
function isOwner($resourceUserId) {
    global $session;
    return $session->getUserId() === $resourceUserId;
}
?>