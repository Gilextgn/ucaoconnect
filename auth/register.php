<?php
// auth/register.php
session_start();

// Inclure les constantes
//require_once __DIR__ . '/../config/constants.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/');
    exit();
}

// Charger PHPMailer
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Connexion MySQL
require_once __DIR__ . '/../config/config.php';

// Variables du formulaire
$email = '';
$password = '';
$confirm_password = '';
$first_name = '';
$last_name = '';
$filiere = '';
$phone = '';
$error = '';
$success = '';
$validation_errors = [];

// Liste des filières possibles
$filieres = [
    'Informatique de Gestion (IG)',
    'Gestion des Entreprises Rurales et Agricoles (GERA)',
    'Production et Gestion des Ressources Animales (PGRA)',
    'Sciences et Techniques de Production Végétale (STPV)',
    'Gestion de l\'Environnement et Aménagement du Territoire (GEAT)',
    'Electronique (ELN)',
    'Système Industriel (SI)',
    'Informatique Industrielle et Maintenance (IIM)',
    'Génie Télécoms et TIC (GT-TICS)',
    'Droit (DRT)',
    'Economie (ECO)',
    'Action Commerciale et Force de Vente (ACFV)',
    'Communication et Action Publicitaire (CAP)',
    'Audit et Contrôle de Gestion (ACG)',
    'Assurance (ASS)',
    'Management des Ressources Humaines (MRH)',
    'Transport & Logistique (TL)',
    'Commerce International (CI )',
];

// ==================== FONCTION D'ENVOI D'EMAIL ====================
function sendConfirmationEmail($toEmail, $userId, $firstName) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP (MODIFIEZ CES VALEURS)
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = '9f1ba7001@smtp-brevo.com';
        $mail->Password = 'Qqx1psG476LBRWaK';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Expéditeur
        $mail->setFrom('gilchristtognisse@gmail.com', 'UCAO STUDENTS MARKETPLACE');
        $mail->addAddress($toEmail, $firstName);
        
        // Générer un token de confirmation
        $token = bin2hex(random_bytes(32));
        
        // Sauvegarder le token en session et dans la base de données
        $_SESSION['confirm_' . $userId] = [
            'token' => $token,
            'email' => $toEmail,
            'expires' => time() + 3600,
            'user_id' => $userId
        ];
        
        // Sauvegarder le token dans la base MySQL
        global $conn;
        $stmt = $conn->prepare("INSERT INTO email_confirmations (user_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))");
        $expires_at = time() + 3600;
        $stmt->bind_param("ssi", $userId, $token, $expires_at);
        $stmt->execute();
        $stmt->close();
        
        // Créer l'URL de confirmation
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = dirname($_SERVER['PHP_SELF']);
        $confirmUrl = $protocol . $host . $basePath . '/confirm.php?token=' . $token . '&uid=' . $userId;
        
        // Contenu HTML de l'email
        $mail->isHTML(true);
        $mail->Subject = 'Confirmez votre inscription -UCAO STUDENTS Marketplace';
        
        $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Confirmation d\'inscription</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f5f7fa; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background-color: white; border-radius: 10px; overflow: hidden; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                    .content { padding: 40px; }
                    .button { 
                        display: inline-block; 
                        padding: 14px 28px; 
                        background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
                        color: white; 
                        text-decoration: none; 
                        border-radius: 30px; 
                        font-weight: 600;
                        margin: 20px 0;
                        text-align: center;
                    }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #777; font-size: 0.9em; }
                    .alert { background-color: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>UCAO STUDENTS MARKETPLACE</h1>
                        <p>Confirmation d\'inscription</p>
                    </div>
                    
                    <div class="content">
                        <h2>Bonjour ' . htmlspecialchars($firstName) . ' !</h2>
                        
                        <p>Merci de vous être inscrit(e) sur notre plateforme étudiante. Pour finaliser votre inscription et activer votre compte, veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous :</p>
                        
                        <div style="text-align: center;">
                            <a href="' . $confirmUrl . '" class="button">Confirmer mon email</a>
                        </div>
                        
                        <p>Ou copiez ce lien dans votre navigateur :</p>
                        <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px;">
                            ' . $confirmUrl . '
                        </p>
                        
                        <div class="alert">
                            <strong> Important :</strong> Ce lien est valable pendant 1 heure. Si vous ne confirmez pas votre email dans ce délai, vous devrez en demander un nouveau.
                        </div>
                        
                        <p>Si vous n\'avez pas créé de compte sur notre plateforme, vous pouvez ignorer cet email en toute sécurité.</p>
                        
                        <div class="footer">
                            <p><strong>Marketplace Étudiante</strong><br>
                            La plateforme d\'échange entre étudiants<br>
                            Email : support@marketplace-etudiante.com</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        // Version texte
        $mail->AltBody = "Bonjour " . $firstName . ",\n\nMerci de vous être inscrit(e) sur Marketplace Étudiante.\n\nPour confirmer votre inscription, cliquez sur ce lien : " . $confirmUrl . "\n\nCe lien est valable pendant 1 heure.\n\nSi vous n'avez pas créé de compte, ignorez cet email.\n\nL'équipe UCAO STUDENTS MARKETPLACE";
        
        // Envoyer l'email
        if ($mail->send()) {
            return ['success' => true, 'token' => $token];
        } else {
            return ['success' => false, 'error' => 'Échec de l\'envoi'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
// ==================== FIN FONCTION EMAIL ====================

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $filiere = $_POST['filiere'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation des données
    $validation_errors = [];
    
    // Validation de l'email
    if (empty($email)) {
        $validation_errors[] = 'L\'adresse email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = 'L\'adresse email n\'est pas valide.';
    }
    
    // Validation du mot de passe
    if (empty($password)) {
        $validation_errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 8) {
        $validation_errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $validation_errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $validation_errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }
    
    // Validation de la confirmation du mot de passe
    if (empty($confirm_password)) {
        $validation_errors[] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $confirm_password) {
        $validation_errors[] = 'Les mots de passe ne correspondent pas.';
    }
    
    // Validation du prénom et nom
    if (empty($first_name)) {
        $validation_errors[] = 'Le prénom est obligatoire.';
    } elseif (strlen($first_name) < 2) {
        $validation_errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    }
    
    if (empty($last_name)) {
        $validation_errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($last_name) < 2) {
        $validation_errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }
    
    // Validation de la filière
    if (empty($filiere)) {
        $validation_errors[] = 'La filière est obligatoire.';
    } elseif (!in_array($filiere, $filieres)) {
        $validation_errors[] = 'Veuillez sélectionner une filière valide.';
    }
    
    // Validation du téléphone (optionnel mais si fourni)
    if (!empty($phone) && !preg_match('/^[0-9\s\-\+\(\)]{10,20}$/', $phone)) {
        $validation_errors[] = 'Le numéro de téléphone n\'est pas valide.';
    }

    // Si aucune erreur de validation, procéder à l'inscription
    if (empty($validation_errors)) {
        try {
            global $conn;
            
            // Vérifier si l'email existe déjà
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Cet email est déjà utilisé. Veuillez vous connecter ou utiliser un autre email.';
            } else {
                // Générer un ID unique pour l'utilisateur
                $userId = uniqid('user_', true);
                
                // Hacher le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Préparer l'insertion dans la base de données
                $stmt = $conn->prepare("INSERT INTO users (id, email, password, first_name, last_name, filiere, phone, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())");
                
                if (!$stmt) {
                    throw new Exception("Erreur de préparation de la requête: " . $conn->error);
                }
                
                $stmt->bind_param("sssssss", $userId, $email, $hashed_password, $first_name, $last_name, $filiere, $phone);
                
                if ($stmt->execute()) {
                    // Envoyer l'email de confirmation
                    $emailResult = sendConfirmationEmail($email, $userId, $first_name);
                    
                    if ($emailResult['success']) {
                        $success = 'Inscription réussie ! Un email de confirmation a été envoyé à <strong>' . $email . '</strong>. Vérifiez votre boîte de réception (et vos spams).';
                        
                        // Nettoyer le formulaire
                        $email = $password = $confirm_password = $first_name = $last_name = $filiere = $phone = '';
                    } else {
                        // Compte créé mais email non envoyé
                        $success = 'Inscription réussie ! Votre compte a été créé.';
                        $error = '⚠️ Note : L\'email de confirmation n\'a pas pu être envoyé. Vous pouvez vous connecter directement.';
                    }
                    
                    $stmt->close();
                } else {
                    $error = 'Erreur lors de l\'inscription : ' . $conn->error;
                }
            }
            
            $check_stmt->close();
            
        } catch (Exception $e) {
            $error = 'Une erreur technique est survenue : ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $validation_errors);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - UCAO Students Marketplace</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- CSS personnalisé -->
    <style>
        :root {
            --primary-blue: #2563eb;
            --dark-blue: #1e40af;
            --light-blue: #3b82f6;
            --very-light-blue: #eff6ff;
            --border-blue: #dbeafe;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --error-red: #dc2626;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
            margin: 0;
        }
        
        .register-container {
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
            padding: 0 15px;
        }
        
        .register-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.1);
            overflow: hidden;
            border: 1px solid var(--border-blue);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .register-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 50px rgba(37, 99, 235, 0.15);
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: var(--white);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .register-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .university-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .logo-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            backdrop-filter: blur(10px);
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .header-content h1 {
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .header-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .register-body {
            padding: 40px;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 15%;
            right: 15%;
            height: 2px;
            background: var(--gray-200);
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--white);
            border: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .step.active .step-circle {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: var(--white);
            transform: scale(1.1);
        }
        
        .step-label {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 500;
            text-align: center;
        }
        
        .step.active .step-label {
            color: var(--primary-blue);
            font-weight: 600;
        }
        
        .section-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-blue);
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-icon {
            color: var(--light-blue);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .required::after {
            content: " *";
            color: var(--error-red);
        }
        
        .form-control {
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: var(--white);
        }
        
        .form-control::placeholder {
            color: var(--text-light);
            opacity: 0.6;
        }
        
        .form-select {
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%231e40af' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") no-repeat right 16px center/16px 16px;
        }
        
        .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .input-group {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .input-group .form-control {
            border-right: none;
        }
        
        .input-group .btn-outline-secondary {
            border: 2px solid var(--gray-200);
            border-left: none;
            background: var(--white);
            color: var(--text-light);
            transition: all 0.3s ease;
        }
        
        .input-group .btn-outline-secondary:hover {
            background: var(--gray-100);
            color: var(--primary-blue);
        }
        
        .form-text {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 6px;
        }
        
        .password-strength-container {
            margin-top: 8px;
        }
        
        .password-strength {
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .strength-bar {
            height: 100%;
            width: 0;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .strength-0 .strength-bar { width: 0%; background: transparent; }
        .strength-1 .strength-bar { width: 25%; background: var(--error-red); }
        .strength-2 .strength-bar { width: 50%; background: var(--warning-orange); }
        .strength-3 .strength-bar { width: 75%; background: var(--light-blue); }
        .strength-4 .strength-bar { width: 100%; background: var(--success-green); }
        
        .password-requirements {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }
        
        .requirement {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .requirement i {
            font-size: 0.9rem;
        }
        
        .requirement.unmet {
            color: var(--text-light);
        }
        
        .requirement.met {
            color: var(--success-green);
        }
        
        .password-match {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
        }
        
        .checkbox-group {
            background: var(--very-light-blue);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border-blue);
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            border: 2px solid var(--gray-200);
            margin-top: 2px;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-check-label {
            color: var(--text-dark);
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .form-check-label a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-check-label a:hover {
            text-decoration: underline;
        }
        
        .alert-info {
            background: var(--very-light-blue);
            border: 1px solid var(--border-blue);
            border-left: 4px solid var(--primary-blue);
            color: var(--text-dark);
            border-radius: 8px;
            padding: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            border: none;
            color: white;
            padding: 14px 32px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
            color: white;
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--gray-200);
            color: var(--text-light);
            padding: 12px 24px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-secondary:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
            color: var(--text-dark);
        }
        
        .form-footer {
            padding-top: 25px;
            border-top: 1px solid var(--border-blue);
            text-align: center;
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .form-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .alert-message {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: var(--success-green);
            color: var(--text-dark);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-color: var(--error-red);
            color: var(--text-dark);
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-color: var(--warning-orange);
            color: var(--text-dark);
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .register-body {
                padding: 25px;
            }
            
            .header-content h1 {
                font-size: 1.8rem;
            }
            
            .progress-steps::before {
                left: 10%;
                right: 10%;
            }
            
            .step-label {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container register-container">
        <div class="register-card">
            <!-- En-tête -->
            <div class="register-header">
                <div class="header-content">
                    <div class="university-logo">
                        <div class="logo-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <div>
                            <h1>UCAO Students Marketplace</h1>
                            <p>Plateforme d'échange entre étudiants</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Indicateur d'étapes -->
            <div class="progress-steps">
                <div class="step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Informations<br>personnelles</div>
                </div>
                <div class="step">
                    <div class="step-circle">2</div>
                    <div class="step-label">Identifiants<br>de connexion</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Finalisation<br>inscription</div>
                </div>
            </div>
            
            <!-- Corps du formulaire -->
            <div class="register-body">
                <!-- Messages d'erreur/succès -->
                <?php if (!empty($error)): ?>
                    <div class="alert-message alert-danger">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.2rem;"></i>
                            <div>
                                <strong>Veuillez corriger les erreurs suivantes :</strong>
                                <div class="mt-2"><?php echo $error; ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert-message alert-success">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-check-circle-fill me-3" style="font-size: 1.2rem;"></i>
                            <div>
                                <strong>Inscription réussie</strong>
                                <div class="mt-2"><?php echo $success; ?></div>
                                <?php if (!empty($error)): ?>
                                    <div class="alert-message alert-warning mt-3">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <?php echo $error; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Formulaire d'inscription (masqué après succès) -->
                <?php if (empty($success)): ?>
                <form method="POST" action="" id="registerForm" novalidate>
                    <!-- Étape 1 : Informations personnelles -->
                    <div class="mb-5">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-person-circle section-icon"></i>
                                Informations personnelles
                            </h3>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label required">Prénom</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="first_name" 
                                       name="first_name" 
                                       value="<?php echo htmlspecialchars($first_name); ?>" 
                                       required
                                       minlength="2"
                                       placeholder="Entrez votre prénom">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="last_name" class="form-label required">Nom</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="last_name" 
                                       name="last_name" 
                                       value="<?php echo htmlspecialchars($last_name); ?>" 
                                       required
                                       minlength="2"
                                       placeholder="Entrez votre nom">
                            </div>
                            
                            <div class="col-12">
                                <label for="filiere" class="form-label required">Filière</label>
                                <select class="form-select" id="filiere" name="filiere" required>
                                    <option value="">Sélectionnez votre filière</option>
                                    <?php foreach ($filieres as $f): ?>
                                        <option value="<?php echo htmlspecialchars($f); ?>" 
                                            <?php echo $filiere === $f ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($f); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            
                            <div class="col-12">
                                <label for="phone" class="form-label">Numéro de téléphone</label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($phone); ?>" 
                                       placeholder="Ex: +229 96 34 56 78">
                                <div class="form-text">
                                    Optionnel - pour faciliter les contacts avec les autres étudiants
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Étape 2 : Identifiants de connexion -->
                    <div class="mb-5">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-shield-lock section-icon"></i>
                                Identifiants de connexion
                            </h3>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="email" class="form-label required">Adresse email</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($email); ?>" 
                                       required
                                       placeholder="votre.email@exemple.com">
                                <div class="form-text">
                                    Utilisez votre email académique si possible
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="password" class="form-label required">Mot de passe</label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           required
                                           minlength="8"
                                           placeholder="Créez un mot de passe sécurisé">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                
                                <!-- Indicateur de force du mot de passe -->
                                <div class="password-strength-container">
                                    <div class="password-strength" id="passwordStrength">
                                        <div class="strength-bar"></div>
                                    </div>
                                </div>
                                
                                <!-- Exigences du mot de passe -->
                                <div class="password-requirements">
                                    <div class="requirement unmet" id="reqLength">
                                        <i class="bi bi-circle"></i>
                                        <span>8 caractères minimum</span>
                                    </div>
                                    <div class="requirement unmet" id="reqUppercase">
                                        <i class="bi bi-circle"></i>
                                        <span>1 majuscule</span>
                                    </div>
                                    <div class="requirement unmet" id="reqNumber">
                                        <i class="bi bi-circle"></i>
                                        <span>1 chiffre</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label required">Confirmation</label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required
                                           placeholder="Confirmez votre mot de passe">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword2">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-match" id="passwordMatch"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Étape 3 : Conditions et inscription -->
                    <div class="mb-5">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-check-circle section-icon"></i>
                                Finalisation de l'inscription
                            </h3>
                        </div>
                        
                        <div class="checkbox-group">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        J'accepte les <a href="#">conditions d'utilisation</a> 
                                        et la <a href="#">politique de confidentialité</a>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="newsletter">
                                    <label class="form-check-label" for="newsletter">
                                        Je souhaite recevoir les actualités de la marketplace étudiante
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert-info mt-4">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-envelope-check me-3" style="font-size: 1.2rem;"></i>
                                <div>
                                    <strong>Confirmation par email requise</strong>
                                    <p class="mb-0 mt-1">Après votre inscription, vous recevrez un email de confirmation contenant un lien à cliquer pour valider votre compte.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="d-flex justify-content-between align-items-center mt-5">
                        <div>
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>
                                Retour à l'accueil
                            </a>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i>
                                Créer mon compte
                            </button>
                        </div>
                    </div>
                    
                    <!-- Lien de connexion -->
                    <div class="form-footer mt-4">
                        <p class="mb-0">
                            Vous avez déjà un compte ? 
                            <a href="login.php">Connectez-vous ici</a>
                        </p>
                    </div>
                </form>
                <?php else: ?>
                    <!-- Affichage après inscription réussie -->
                    <div class="text-center py-4">
                        <div class="alert-message alert-warning mb-4">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bi bi-envelope-exclamation me-3"></i>
                                <div>
                                    <strong>Pensez à vérifier vos spams</strong> si vous ne trouvez pas l'email de confirmation.
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Se connecter
                            </a>
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house me-2"></i>
                                Page d'accueil
                            </a>
                        </div>
                        
                        <div class="mt-4">
                            <p class="text-muted mb-2">
                                <small>Vous n'avez pas reçu l'email ?</small>
                            </p>
                            <a href="javascript:void(0)" id="resendEmail" class="text-decoration-none">
                                <small>Renvoyer l'email de confirmation</small>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts personnalisés -->
    <script>
        // Afficher/masquer les mots de passe
        function togglePassword(inputId, buttonId) {
            const passwordInput = document.getElementById(inputId);
            const icon = document.querySelector(`#${buttonId} i`);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        document.getElementById('togglePassword1').addEventListener('click', () => togglePassword('password', 'togglePassword1'));
        document.getElementById('togglePassword2').addEventListener('click', () => togglePassword('confirm_password', 'togglePassword2'));
        
        // Vérification de la force du mot de passe en temps réel
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthContainer = document.getElementById('passwordStrength');
            const reqLength = document.getElementById('reqLength');
            const reqUppercase = document.getElementById('reqUppercase');
            const reqNumber = document.getElementById('reqNumber');
            
            let strength = 0;
            
            // Vérification de la longueur
            if (password.length >= 8) {
                strength++;
                reqLength.classList.remove('unmet');
                reqLength.classList.add('met');
                reqLength.querySelector('i').className = 'bi bi-check-circle';
            } else {
                reqLength.classList.remove('met');
                reqLength.classList.add('unmet');
                reqLength.querySelector('i').className = 'bi bi-circle';
            }
            
            // Vérification des majuscules
            if (/[A-Z]/.test(password)) {
                strength++;
                reqUppercase.classList.remove('unmet');
                reqUppercase.classList.add('met');
                reqUppercase.querySelector('i').className = 'bi bi-check-circle';
            } else {
                reqUppercase.classList.remove('met');
                reqUppercase.classList.add('unmet');
                reqUppercase.querySelector('i').className = 'bi bi-circle';
            }
            
            // Vérification des chiffres
            if (/[0-9]/.test(password)) {
                strength++;
                reqNumber.classList.remove('unmet');
                reqNumber.classList.add('met');
                reqNumber.querySelector('i').className = 'bi bi-check-circle';
            } else {
                reqNumber.classList.remove('met');
                reqNumber.classList.add('unmet');
                reqNumber.querySelector('i').className = 'bi bi-circle';
            }
            
            // Vérification des caractères spéciaux (bonus)
            if (/[^A-Za-z0-9]/.test(password)) {
                strength++;
            }
            
            // Mise à jour de la barre de force
            strengthContainer.className = 'password-strength strength-' + strength;
            
            // Initialisation : si le champ est vide, retirer la classe strength
            if (password === '') {
                strengthContainer.className = 'password-strength';
            }
        });
        
        // Vérification de la correspondance des mots de passe
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm === '') {
                matchText.textContent = '';
                matchText.className = 'password-match';
            } else if (password === confirm) {
                matchText.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Les mots de passe correspondent';
                matchText.className = 'password-match text-success';
            } else {
                matchText.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i> Les mots de passe ne correspondent pas';
                matchText.className = 'password-match text-danger';
            }
        });
        
        // Validation du formulaire avant envoi
        document.getElementById('registerForm')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const filiere = document.getElementById('filiere').value;
            const terms = document.getElementById('terms').checked;
            
            let errors = [];
            
            // Validation des champs obligatoires
            if (!firstName || firstName.length < 2) {
                errors.push('Le prénom doit contenir au moins 2 caractères.');
            }
            
            if (!lastName || lastName.length < 2) {
                errors.push('Le nom doit contenir au moins 2 caractères.');
            }
            
            if (!filiere) {
                errors.push('Veuillez sélectionner votre filière.');
            }
            
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('Veuillez entrer une adresse email valide.');
            }
            
            if (password.length < 8) {
                errors.push('Le mot de passe doit contenir au moins 8 caractères.');
            }
            
            if (!/[A-Z]/.test(password)) {
                errors.push('Le mot de passe doit contenir au moins une majuscule.');
            }
            
            if (!/[0-9]/.test(password)) {
                errors.push('Le mot de passe doit contenir au moins un chiffre.');
            }
            
            if (password !== confirm) {
                errors.push('Les mots de passe ne correspondent pas.');
            }
            
            if (!terms) {
                errors.push('Vous devez accepter les conditions d\'utilisation.');
            }
            
            // Si des erreurs, empêcher l'envoi et les afficher
            if (errors.length > 0) {
                e.preventDefault();
                
                // Créer un message d'erreur
                let errorHtml = '<div class="alert-message alert-danger"><div class="d-flex align-items-start"><i class="bi bi-exclamation-triangle-fill me-3"></i><div><strong>Veuillez corriger les erreurs suivantes :</strong><ul class="mb-0 mt-2">';
                errors.forEach(error => {
                    errorHtml += '<li>' + error + '</li>';
                });
                errorHtml += '</ul></div></div></div>';
                
                // Afficher les erreurs en haut du formulaire
                const alertDiv = document.querySelector('.alert-danger');
                if (alertDiv) {
                    alertDiv.remove();
                }
                
                const form = document.getElementById('registerForm');
                form.insertAdjacentHTML('afterbegin', errorHtml);
                
                // Faire défiler vers le haut pour voir les erreurs
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
        
        // Mettre le focus sur le premier champ
        if (document.getElementById('first_name')) {
            document.getElementById('first_name').focus();
        }
        
        // Renvoyer l'email de confirmation
        document.getElementById('resendEmail')?.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Cette fonctionnalité sera disponible prochainement.');
        });
        
        // Animation des étapes au scroll
        const steps = document.querySelectorAll('.step');
        const sections = document.querySelectorAll('.mb-5');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const sectionId = entry.target.id || entry.target.querySelector('[id]')?.id;
                    if (sectionId) {
                        steps.forEach(step => step.classList.remove('active'));
                        const stepIndex = Array.from(sections).indexOf(entry.target);
                        if (stepIndex >= 0) {
                            steps[stepIndex].classList.add('active');
                        }
                    }
                }
            });
        }, { threshold: 0.5 });
        
        sections.forEach(section => observer.observe(section));
    </script>
</body>
</html>