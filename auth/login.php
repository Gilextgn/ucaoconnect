<?php
// auth/login.php
session_start();

error_log("=== DÉBUT LOGIN.PHP ===");
error_log("Session ID: " . session_id());
error_log("User ID en session: " . ($_SESSION['user_id'] ?? 'NON DÉFINI'));

// Configuration de sécurité
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Correction automatique du user_id si mismatch
if (isset($_SESSION['email'])) {
    error_log("Correction user_id: email trouvé en session");
    require_once __DIR__ . '/../config/config.php';
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        error_log("Erreur connexion DB pour correction: " . $mysqli->connect_error);
    } else {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $_SESSION['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && ($_SESSION['user_id'] ?? '') != $user['id']) {
            // Correction silencieuse
            $_SESSION['user_id'] = $user['id'];
            error_log("Auto-correction user_id: " . ($_SESSION['user_id'] ?? 'old') . " -> " . $user['id']);
        } else {
            error_log("Pas de correction nécessaire");
        }
        
        $mysqli->close();
    }
}

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    error_log("Utilisateur déjà connecté, redirection vers dashboard");
    header('Location: ../dashboard/');
    exit();
}

error_log("Utilisateur non connecté, affichage du formulaire");

// Inclure la configuration MySQL
require_once __DIR__ . '/../config/config.php';

// Vérifier la connexion
if (!isset($conn) || $conn->connect_error) {
    error_log("ERREUR CRITIQUE: Connexion DB échouée - " . ($conn->connect_error ?? 'Variable $conn non définie'));
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

error_log("Connexion DB OK");

// Variables
$error = '';
$email = '';
$success = '';
$email_error = '';
$password_error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== TRAITEMENT FORMULAIRE POST ===");
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    error_log("Données reçues - Email: $email, Remember: " . ($remember ? 'OUI' : 'NON'));
    
    // Validation côté serveur
    $is_valid = true;
    
    // Validation email
    if (empty($email)) {
        $email_error = 'L\'adresse email est requise.';
        $is_valid = false;
        error_log("Erreur validation: email vide");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_error = 'Format d\'email invalide.';
        $is_valid = false;
        error_log("Erreur validation: email invalide");
    } elseif (strlen($email) > 100) {
        $email_error = 'L\'email ne doit pas dépasser 100 caractères.';
        $is_valid = false;
        error_log("Erreur validation: email trop long");
    }
    
    // Validation mot de passe
    if (empty($password)) {
        $password_error = 'Le mot de passe est requis.';
        $is_valid = false;
        error_log("Erreur validation: mot de passe vide");
    } elseif (strlen($password) < 8) {
        $password_error = 'Le mot de passe doit contenir au moins 8 caractères.';
        $is_valid = false;
        error_log("Erreur validation: mot de passe trop court");
    } elseif (strlen($password) > 72) {
        $password_error = 'Le mot de passe est trop long.';
        $is_valid = false;
        error_log("Erreur validation: mot de passe trop long");
    }
    
    // Vérifier les tentatives de login par IP
    $client_ip = $_SERVER['REMOTE_ADDR'];
    error_log("IP client: $client_ip");
    $rate_limit_key = 'login_attempts_' . md5($client_ip);
    
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = [
            'count' => 0,
            'first_attempt' => time(),
            'last_attempt' => time()
        ];
        error_log("Initialisation compteur tentative pour IP: $client_ip");
    }
    
    $rate_limit = &$_SESSION[$rate_limit_key];
    
    // Vérifier si l'IP est bloquée
    if ($rate_limit['count'] >= 10 && (time() - $rate_limit['first_attempt']) < 3600) {
        $error = 'Trop de tentatives de connexion. Veuillez réessayer dans 1 heure.';
        $is_valid = false;
        error_log("IP bloquée - Tentatives: " . $rate_limit['count']);
    }
    
    if ($is_valid) {
        try {
            error_log("Validation réussie, recherche utilisateur...");
            
            // Vérifier si la table existe
            $table_check = $conn->query("SHOW TABLES LIKE 'users'");
            if ($table_check->num_rows == 0) {
                $error = 'Erreur système : base de données non configurée.';
                error_log("ERREUR: Table users n'existe pas");
                $table_check->free();
            } else {
                $table_check->free();
                
                // Requête avec les colonnes essentielles
                $sql = "SELECT id, email, password, first_name, last_name, filiere, phone, is_active, 
                               email_verified, login_attempts, locked_until 
                        FROM users WHERE email = ?";
                
                error_log("Exécution requête SQL: " . $sql);
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    error_log("Erreur préparation requête: " . $conn->error);
                    // Fallback
                    $sql = "SELECT id, email, password FROM users WHERE email = ?";
                    error_log("Tentative requête fallback: " . $sql);
                    $stmt = $conn->prepare($sql);
                }
                
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    error_log("Résultats trouvés: " . $result->num_rows);
                    
                    if ($result->num_rows === 0) {
                        // Email non trouvé
                        $rate_limit['count']++;
                        $rate_limit['last_attempt'] = time();
                        
                        $error = 'Identifiants incorrects.';
                        $email_error = 'Veuillez vérifier vos identifiants.';
                        error_log("Email non trouvé dans la base de données");
                    } else {
                        $user = $result->fetch_assoc();
                        $stmt->close();
                        
                        // Initialiser les valeurs
                        $user['email_verified'] = $user['email_verified'] ?? 1;
                        $user['login_attempts'] = $user['login_attempts'] ?? 0;
                        $user['locked_until'] = $user['locked_until'] ?? null;
                        $user['is_active'] = $user['is_active'] ?? 1;
                        
                        error_log("Utilisateur trouvé - ID: " . $user['id'] . 
                                 ", Email vérifié: " . $user['email_verified'] . 
                                 ", Compte actif: " . $user['is_active']);
                        
                        processLogin($conn, $user, $password, $email, $remember);
                    }
                } else {
                    error_log("ERREUR: Impossible de préparer la requête - " . $conn->error);
                    $error = 'Erreur système. Veuillez réessayer.';
                }
            }
        } catch (Exception $e) {
            error_log("EXCEPTION lors connexion: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            $error = 'Une erreur technique est survenue. Veuillez réessayer.';
        }
    } else {
        // Incrémenter le compteur de tentatives pour cette IP
        $rate_limit['count']++;
        $rate_limit['last_attempt'] = time();
        
        // Réinitialiser le compteur après 1 heure
        if ((time() - $rate_limit['first_attempt']) > 3600) {
            $rate_limit = [
                'count' => 1,
                'first_attempt' => time(),
                'last_attempt' => time()
            ];
            error_log("Réinitialisation compteur tentative pour IP: $client_ip");
        }
        
        error_log("Validation échouée - Compteur tentative: " . $rate_limit['count']);
    }
}

// Fonction pour traiter la connexion
function processLogin($conn, $user, $password, $email, $remember) {
    global $error, $email_error, $password_error;
    
    error_log("=== PROCESS LOGIN ===");
    
    // Vérifier si le compte est verrouillé
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $lock_time = date('H:i', strtotime($user['locked_until']));
        $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
        $error = "Compte temporairement verrouillé. Réessayez dans $remaining minute(s) (après $lock_time).";
        error_log("Compte verrouillé jusqu'à: " . $user['locked_until']);
        return;
    }
    
    // Vérifier si le compte est actif
    /*if ($user['is_active'] == 0) {
        $error = 'Votre compte n\'est pas encore activé. Veuillez vérifier vos emails pour le lien d\'activation.';
        error_log("Compte non activé");
        return;
    }*/
    
    // Vérifier si l'email est vérifié
    if ($user['email_verified'] == 0) {
        $resend_link = "resend_verification.php?email=" . urlencode($email);
        $error = 'Votre email n\'est pas encore vérifié. 
                 <a href="' . $resend_link . '" style="color: #3498db; text-decoration: underline;">
                 Renvoyer le lien de vérification</a>';
        error_log("Email non vérifié");
        return;
    }
    
    // Vérifier le mot de passe
    if (!password_verify($password, $user['password'])) {
        error_log("Mot de passe incorrect");
        $attempts = ($user['login_attempts'] ?? 0) + 1;
        
        // Vérifier si la colonne login_attempts existe
        $check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'login_attempts'");
        $has_login_col = ($check_col->num_rows > 0);
        $check_col->free();
        
        if ($has_login_col) {
            if ($attempts >= 5) {
                // Verrouiller le compte pour 15 minutes
                $lock_until = date('Y-m-d H:i:s', time() + 900);
                
                $lock_stmt = $conn->prepare("
                    UPDATE users 
                    SET login_attempts = ?, 
                        locked_until = ?
                    WHERE id = ?
                ");
                
                if ($lock_stmt) {
                    $lock_stmt->bind_param("iss", $attempts, $lock_until, $user['id']);
                    $lock_stmt->execute();
                    $lock_stmt->close();
                }
                
                $error = 'Trop de tentatives échouées. Votre compte est verrouillé pour 15 minutes.';
                $password_error = 'Trop de tentatives. Réessayez dans 15 minutes.';
                error_log("Compte verrouillé après 5 tentatives échouées");
            } else {
                // Incrémenter les tentatives
                $update_stmt = $conn->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                
                if ($update_stmt) {
                    $update_stmt->bind_param("is", $attempts, $user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                $remaining = 5 - $attempts;
                $error = "Mot de passe incorrect. Il vous reste $remaining tentative(s).";
                $password_error = "Mot de passe incorrect. ($remaining tentative(s) restante(s))";
                error_log("Tentative échouée #$attempts pour utilisateur: " . $user['id']);
            }
        } else {
            $error = "Identifiants incorrects.";
            $password_error = "Veuillez vérifier votre mot de passe.";
        }
        
        return;
    }
    
    // CONNEXION RÉUSSIE
    error_log("=== CONNEXION RÉUSSIE ===");
    $real_user_id = $user['id'];
    
    // Réinitialiser les tentatives de connexion
    $check_reset_col = $conn->query("SHOW COLUMNS FROM users LIKE 'login_attempts'");
    $has_reset_col = ($check_reset_col->num_rows > 0);
    $check_reset_col->free();
    
    if ($has_reset_col) {
        $reset_stmt = $conn->prepare("
            UPDATE users 
            SET login_attempts = 0, 
                locked_until = NULL,
                last_login = NOW()
            WHERE id = ?
        ");
        
        if ($reset_stmt) {
            $reset_stmt->bind_param("s", $real_user_id);
            $reset_stmt->execute();
            $reset_stmt->close();
            error_log("Compteur tentatives réinitialisé pour utilisateur: " . $real_user_id);
        }
    }
    
    // Réinitialiser le compteur IP
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = 'login_attempts_' . md5($client_ip);
    unset($_SESSION[$rate_limit_key]);
    
    // Créer la session
    $_SESSION['user_id'] = $real_user_id;
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'] ?? '';
    $_SESSION['last_name'] = $user['last_name'] ?? '';
    $_SESSION['filiere'] = $user['filiere'] ?? '';
    $_SESSION['phone'] = $user['phone'] ?? '';
    $_SESSION['user_type'] = 'student';
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    
    // Token de session
    $session_token = bin2hex(random_bytes(32));
    $_SESSION['session_token'] = $session_token;
    
    error_log("Session créée pour user_id: " . $real_user_id . 
              ", email: " . $user['email'] . 
              ", nom: " . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    
    // Gestion du "Se souvenir de moi"
    if ($remember) {
        $remember_token = bin2hex(random_bytes(32));
        $expiry = time() + (30 * 24 * 60 * 60);
        
        // Stocker le token dans la base de données
        $token_hash = hash('sha256', $remember_token);
        $stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sss", $token_hash, date('Y-m-d H:i:s', $expiry), $real_user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Stocker dans un cookie
        $cookie_value = $real_user_id . ':' . $remember_token;
        setcookie('remember_me', $cookie_value, $expiry, '/', '', true, true);
        error_log("Cookie 'remember me' créé");
    }
    
    error_log("Redirection vers dashboard...");
    
    // Redirection
    header('Location: ../dashboard/');
    exit();
}

// Vérifier si une déconnexion a été demandée
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('remember_me', '', time() - 3600, '/');
    $success = 'Vous avez été déconnecté avec succès.';
    error_log("Utilisateur déconnecté");
}

// Vérifier si un message de confirmation est présent
if (isset($_GET['confirmed'])) {
    $success = 'Votre compte a été activé avec succès ! Vous pouvez maintenant vous connecter.';
}

// Vérifier si un reset de mot de passe a été demandé
if (isset($_GET['reset'])) {
    $success = 'Un email de réinitialisation de mot de passe vous a été envoyé.';
}

error_log("=== FIN TRAITEMENT PHP, AFFICHAGE HTML ===");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - UCAO Marketplace</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #3498db;
            --primary-blue-dark: #2980b9;
            --primary-blue-light: #e8f4fc;
            --secondary-blue: #2c3e50;
            --accent-teal: #1abc9c;
            --accent-orange: #f39c12;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --bg-light: #f8f9fa;
            --bg-white: #ffffff;
            --border-color: #e9ecef;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f1c40f;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
        }
        
        .login-card {
            background: var(--bg-white);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(52, 152, 219, 0.1);
            overflow: hidden;
            border: 1px solid rgba(52, 152, 219, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(52, 152, 219, 0.15);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .logo-wrapper {
            position: relative;
            z-index: 2;
        }
        
        .logo-circle {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .logo-circle i {
            font-size: 36px;
            color: white;
        }
        
        .brand-name {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .brand-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 300;
            margin: 0;
        }
        
        .login-body {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .form-label i {
            color: var(--primary-blue);
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: var(--bg-light);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: var(--bg-white);
        }
        
        .form-control::placeholder {
            color: var(--text-light);
            opacity: 0.7;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--primary-blue);
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        
        .forgot-password a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .forgot-password a:hover {
            color: var(--primary-blue-dark);
            text-decoration: underline;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .custom-checkbox {
            display: inline-block;
            position: relative;
            cursor: pointer;
            user-select: none;
        }
        
        .custom-checkbox input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkmark {
            position: relative;
            height: 20px;
            width: 20px;
            background-color: var(--bg-light);
            border: 2px solid var(--border-color);
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .custom-checkbox input:checked ~ .checkmark {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .custom-checkbox input:checked ~ .checkmark:after {
            display: block;
        }
        
        .remember-text {
            font-size: 0.95rem;
            color: var(--text-dark);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin-top: 10px;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .login-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            text-align: center;
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .login-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .login-footer a:hover {
            color: var(--primary-blue-dark);
            text-decoration: underline;
        }
        
        .alert-message {
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            animation: slideDown 0.4s ease;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #e8f8f1 0%, #d1f2e5 100%);
            border-left: 4px solid var(--success-color);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fdeaea 0%, #fad4d4 100%);
            border-left: 4px solid var(--error-color);
            color: #721c24;
        }
        
        .alert-icon {
            font-size: 1.4rem;
            margin-right: 15px;
        }
        
        .alert-success .alert-icon {
            color: var(--success-color);
        }
        
        .alert-danger .alert-icon {
            color: var(--error-color);
        }
        
        /* Styles pour les erreurs de champ */
        .form-control.error {
            border-color: var(--error-color) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23e74c3c' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23e74c3c' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 18px 18px;
            padding-right: 50px;
        }
        
        .form-control.success {
            border-color: var(--success-color) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%232ecc71' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 18px 18px;
            padding-right: 50px;
        }
        
        .field-error {
            color: var(--error-color);
            font-size: 0.85rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.3s ease;
        }
        
        .field-error i {
            font-size: 0.9rem;
        }
        
        .attempts-warning {
            background: linear-gradient(135deg, #fff9e6 0%, #fff4d6 100%);
            border-left: 4px solid var(--warning-color);
            padding: 14px 18px;
            border-radius: 10px;
            margin-top: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            animation: slideDown 0.4s ease;
            color: #856404;
        }
        
        .attempts-warning i {
            color: var(--warning-color);
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        .security-info {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .security-info i {
            color: var(--primary-blue);
        }
        
        /* Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .login-body {
                padding: 30px 20px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .brand-name {
                font-size: 1.8rem;
            }
            
            .form-control {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- En-tête avec logo -->
            <div class="login-header">
                <div class="logo-wrapper">
                    <div class="logo-circle">
                        <i class="bi bi-shop-window"></i>
                    </div>
                    <h1 class="brand-name">UCAO Marketplace</h1>
                    <p class="brand-subtitle">La plateforme d'échange entre étudiants</p>
                </div>
            </div>
            
            <!-- Corps du formulaire -->
            <div class="login-body">
                <!-- Messages d'alerte -->
                <?php if (!empty($success)): ?>
                    <div class="alert-message alert-success">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill alert-icon"></i>
                            <div>
                                <strong>Succès !</strong>
                                <div class="mt-2"><?php echo $success; ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert-message alert-danger">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
                            <div>
                                <strong>Attention</strong>
                                <div class="mt-2"><?php echo htmlspecialchars($error); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Formulaire de connexion -->
                <form method="POST" action="" id="loginForm" novalidate>
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope"></i>
                            Adresse email
                        </label>
                        <input type="email" 
                               class="form-control <?php echo !empty($email_error) ? 'error' : ''; ?>" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required
                               placeholder="exemple@ucao.com"
                               autocomplete="email"
                               aria-describedby="emailHelp">
                        <?php if (!empty($email_error)): ?>
                            <div class="field-error" id="emailError">
                                <i class="bi bi-exclamation-circle"></i>
                                <?php echo htmlspecialchars($email_error); ?>
                            </div>
                        <?php else: ?>
                            <div class="field-error" id="emailError" style="display: none;"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i>
                            Mot de passe
                        </label>
                        <div class="password-wrapper">
                            <input type="password" 
                                   class="form-control <?php echo !empty($password_error) ? 'error' : ''; ?>" 
                                   id="password" 
                                   name="password" 
                                   required
                                   placeholder="Votre mot de passe"
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($password_error)): ?>
                            <div class="field-error" id="passwordError">
                                <i class="bi bi-exclamation-circle"></i>
                                <?php echo htmlspecialchars($password_error); ?>
                            </div>
                        <?php else: ?>
                            <div class="field-error" id="passwordError" style="display: none;"></div>
                        <?php endif; ?>
                        
                        <div class="security-info">
                            <i class="bi bi-info-circle"></i>
                            Votre mot de passe est sécurisé et chiffré
                        </div>
                        
                        <div class="forgot-password">
                            <a href="forgot_password.php">
                                <i class="bi bi-question-circle me-1"></i>
                                Mot de passe oublié ?
                            </a>
                        </div>
                    </div>
                    
                    <div class="remember-me">
                        <label class="custom-checkbox">
                            <input type="checkbox" id="remember" name="remember">
                            <span class="checkmark"></span>
                        </label>
                        <span class="remember-text">Se souvenir de moi pendant 30 jours</span>
                    </div>
                    
                    <?php
                    // Afficher un avertissement sur les tentatives si applicable
                    if (isset($_SESSION['login_attempts_' . md5($_SERVER['REMOTE_ADDR'])])) {
                        $rate_limit = $_SESSION['login_attempts_' . md5($_SERVER['REMOTE_ADDR'])];
                        if ($rate_limit['count'] >= 3) {
                            $remaining = 10 - $rate_limit['count'];
                            echo '<div class="attempts-warning">
                                    <i class="bi bi-shield-exclamation"></i>
                                    Attention : Il vous reste ' . $remaining . ' tentative(s) avant blocage.
                                  </div>';
                        }
                    }
                    ?>
                    
                    <button type="submit" class="btn-login" id="submitBtn">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Se connecter</span>
                    </button>
                </form>
                
                <!-- Pied de page -->
                <div class="login-footer">
                    <p class="mb-2">
                        Nouveau sur UCAO Marketplace ? 
                        <a href="register.php">Créer un compte</a>
                    </p>
                    <p class="mb-0">
                        <a href="../index.php">
                            <i class="bi bi-arrow-left me-1"></i>
                            Retour à l'accueil
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts personnalisés améliorés -->
    <script>
        console.log("=== DÉBUT SCRIPT JS LOGIN ===");
        
        // Afficher/masquer le mot de passe
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Changer l'icône
                const icon = this.querySelector('i');
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
                
                // Mettre à jour l'accessibilité
                this.setAttribute('aria-label', 
                    type === 'password' ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
            });
            console.log("Toggle password fonctionnel");
        } else {
            console.error("Éléments toggle password non trouvés");
        }
        
        // Validation du formulaire
        const loginForm = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const emailError = document.getElementById('emailError');
        const passwordError = document.getElementById('passwordError');
        const submitBtn = document.getElementById('submitBtn');
        
        if (!loginForm) {
            console.error("Formulaire loginForm non trouvé");
        }
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function showError(element, message) {
            if (element) {
                element.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + message;
                element.style.display = 'flex';
            }
        }
        
        function hideError(element) {
            if (element) {
                element.innerHTML = '';
                element.style.display = 'none';
            }
        }
        
        function markFieldValid(field) {
            if (field) {
                field.classList.remove('error');
                field.classList.add('success');
            }
        }
        
        function markFieldInvalid(field) {
            if (field) {
                field.classList.remove('success');
                field.classList.add('error');
            }
        }
        
        function markFieldNormal(field) {
            if (field) {
                field.classList.remove('error', 'success');
            }
        }
        
        // Validation en temps réel
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                
                if (email && validateEmail(email)) {
                    markFieldValid(this);
                    hideError(emailError);
                } else if (email && !validateEmail(email)) {
                    markFieldInvalid(this);
                } else {
                    markFieldNormal(this);
                    hideError(emailError);
                }
            });
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                if (password.length >= 8) {
                    markFieldValid(this);
                    hideError(passwordError);
                } else if (password.length > 0) {
                    markFieldInvalid(this);
                } else {
                    markFieldNormal(this);
                    hideError(passwordError);
                }
            });
        }
        
        // Validation au blur
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                const email = this.value.trim();
                
                if (!email) {
                    showError(emailError, 'L\'adresse email est requise.');
                    markFieldInvalid(this);
                } else if (!validateEmail(email)) {
                    showError(emailError, 'Format d\'email invalide.');
                    markFieldInvalid(this);
                } else {
                    hideError(emailError);
                    markFieldValid(this);
                }
            });
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('blur', function() {
                const password = this.value;
                
                if (!password) {
                    showError(passwordError, 'Le mot de passe est requis.');
                    markFieldInvalid(this);
                } else if (password.length < 8) {
                    showError(passwordError, 'Le mot de passe doit contenir au moins 8 caractères.');
                    markFieldInvalid(this);
                } else if (password.length > 72) {
                    showError(passwordError, 'Le mot de passe est trop long.');
                    markFieldInvalid(this);
                } else {
                    hideError(passwordError);
                    markFieldValid(this);
                }
            });
        }
        
        // Clear errors on focus
        if (emailInput) {
            emailInput.addEventListener('focus', function() {
                hideError(emailError);
                markFieldNormal(this);
            });
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('focus', function() {
                hideError(passwordError);
                markFieldNormal(this);
            });
        }
        
        // Soumission du formulaire
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log("Tentative de soumission du formulaire");
                
                const email = emailInput ? emailInput.value.trim() : '';
                const password = passwordInput ? passwordInput.value : '';
                let isValid = true;
                
                // Valider l'email
                if (!email) {
                    showError(emailError, 'L\'adresse email est requise.');
                    markFieldInvalid(emailInput);
                    if (emailInput) emailInput.focus();
                    isValid = false;
                } else if (!validateEmail(email)) {
                    showError(emailError, 'Format d\'email invalide.');
                    markFieldInvalid(emailInput);
                    if (emailInput) emailInput.focus();
                    isValid = false;
                }
                
                // Valider le mot de passe seulement si l'email est valide
                if (isValid && !password) {
                    showError(passwordError, 'Le mot de passe est requis.');
                    markFieldInvalid(passwordInput);
                    if (passwordInput) passwordInput.focus();
                    isValid = false;
                } else if (isValid && password.length < 8) {
                    showError(passwordError, 'Le mot de passe doit contenir au moins 8 caractères.');
                    markFieldInvalid(passwordInput);
                    if (passwordInput) passwordInput.focus();
                    isValid = false;
                }
                
                if (isValid) {
                    console.log("Formulaire valide, soumission...");
                    // Désactiver le bouton et afficher le spinner
                    if (submitBtn) {
                        const originalHTML = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<div class="spinner"></div> <span>Connexion en cours...</span>';
                    }
                    
                    // Ajouter un délai minimal
                    setTimeout(() => {
                        this.submit();
                    }, 500);
                } else {
                    console.log("Formulaire invalide");
                }
            });
        }
        
        // Gestion du "Se souvenir de moi" avec localStorage
        const rememberCheckbox = document.getElementById('remember');
        
        // Charger l'email sauvegardé
        const savedEmail = localStorage.getItem('remembered_email');
        if (savedEmail && validateEmail(savedEmail)) {
            if (emailInput) {
                emailInput.value = savedEmail;
            }
            if (rememberCheckbox) {
                rememberCheckbox.checked = true;
            }
            if (emailInput) {
                markFieldValid(emailInput);
            }
            
            // Focus sur le mot de passe si l'email est pré-rempli
            if (savedEmail && passwordInput) {
                passwordInput.focus();
            }
        }
        
        // Sauvegarder l'email si "se souvenir" est coché
        if (rememberCheckbox) {
            rememberCheckbox.addEventListener('change', function() {
                const email = emailInput ? emailInput.value.trim() : '';
                if (this.checked && email && validateEmail(email)) {
                    localStorage.setItem('remembered_email', email);
                } else {
                    localStorage.removeItem('remembered_email');
                }
            });
        }
        
        // Sauvegarder l'email quand il change
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                if (rememberCheckbox && rememberCheckbox.checked && email && validateEmail(email)) {
                    localStorage.setItem('remembered_email', email);
                }
            });
        }
        
        // Focus automatique sur le premier champ vide
        window.addEventListener('load', function() {
            console.log("Page chargée");
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            } else if (passwordInput && !passwordInput.value) {
                passwordInput.focus();
            }
        });
        
        console.log("=== FIN SCRIPT JS LOGIN ===");
    </script>
</body>
</html>