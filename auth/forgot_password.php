<?php
// auth/forgot_password.php
session_start();

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/');
    exit();
}

// Inclure la configuration
require_once __DIR__ . '/../config/config.php';

// Charger PHPMailer
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Variables
$email = '';
$error = '';
$success = '';
$message = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        $error = 'Veuillez entrer votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } else {
        try {
            // Vérifier si l'utilisateur existe
            $stmt = $conn->prepare("SELECT id, email, first_name, last_name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Pour des raisons de sécurité, on ne dit pas que l'email n'existe pas
                $success = "Si votre email existe dans notre système, vous recevrez un lien de réinitialisation.";
                $message = "Veuillez vérifier votre boîte de réception (et vos spams).";
            } else {
                $user = $result->fetch_assoc();
                $stmt->close();
                
                // Générer un token de réinitialisation
                $reset_token = bin2hex(random_bytes(32));
                $reset_token_hash = hash('sha256', $reset_token);
                $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 heure
                
                // Sauvegarder le token en base
                $update_stmt = $conn->prepare("
                    UPDATE users 
                    SET reset_token = ?, 
                        reset_expires = ?
                    WHERE id = ?
                ");
                $update_stmt->bind_param("sss", $reset_token_hash, $expires_at, $user['id']);
                
                if ($update_stmt->execute()) {
                    // Envoyer l'email de réinitialisation
                    $mail_result = sendResetEmail($user['email'], $user['first_name'], $reset_token, $user['id']);
                    
                    if ($mail_result['success']) {
                        $success = "Un lien de réinitialisation a été envoyé à votre adresse email.";
                        $message = "Veuillez vérifier votre boîte de réception (et vos spams). Le lien est valable 1 heure.";
                        $email = ''; // Nettoyer le champ
                    } else {
                        $error = "L'email n'a pas pu être envoyé. Erreur: " . $mail_result['error'];
                    }
                } else {
                    $error = "Une erreur est survenue lors de la génération du lien de réinitialisation.";
                }
                $update_stmt->close();
            }
            
        } catch (Exception $e) {
            error_log("Erreur réinitialisation: " . $e->getMessage());
            $error = 'Une erreur technique est survenue. Veuillez réessayer.';
        }
    }
}

// Fonction d'envoi d'email
function sendResetEmail($toEmail, $firstName, $token, $userId) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = '9f1ba7001@smtp-brevo.com';
        $mail->Password = 'Qqx1psG476LBRWaK';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Expéditeur
        $mail->setFrom('gilchristtognisse@gmail.com', 'UCAO Students Marketplace');
        $mail->addAddress($toEmail, $firstName);
        
        // Créer l'URL de réinitialisation
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = dirname($_SERVER['PHP_SELF']);
        $resetUrl = $protocol . $host . $basePath . '/reset_password.php?token=' . $token . '&uid=' . $userId;
        
        // Contenu HTML
        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation de votre mot de passe - Marketplace Étudiante';
        
        $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Réinitialisation de mot de passe</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f5f7fa; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background-color: white; border-radius: 10px; overflow: hidden; }
                    .header { background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; padding: 30px; text-align: center; }
                    .content { padding: 40px; }
                    .button { 
                        display: inline-block; 
                        padding: 14px 28px; 
                        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); 
                        color: white; 
                        text-decoration: none; 
                        border-radius: 30px; 
                        font-weight: 600;
                        margin: 20px 0;
                        text-align: center;
                    }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #777; font-size: 0.9em; }
                    .alert { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
                    .warning { background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>UCAO STUDENTS MARKETPLACE</h1>
                        <p>Réinitialisation de mot de passe</p>
                    </div>
                    
                    <div class="content">
                        <h2>Bonjour ' . htmlspecialchars($firstName) . ' !</h2>
                        
                        <p>Vous avez demandé la réinitialisation de votre mot de passe. Pour créer un nouveau mot de passe, cliquez sur le bouton ci-dessous :</p>
                        
                        <div style="text-align: center;">
                            <a href="' . $resetUrl . '" class="button">Réinitialiser mon mot de passe</a>
                        </div>
                        
                        <p>Ou copiez ce lien dans votre navigateur :</p>
                        <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px;">
                            ' . $resetUrl . '
                        </p>
                        
                        <div class="alert">
                            <strong>Important :</strong> Ce lien est valable pendant 1 heure. Si vous ne l\'utilisez pas dans ce délai, vous devrez en demander un nouveau.
                        </div>
                        
                        <div class="warning">
                            <strong>Sécurité :</strong> Si vous n\'avez pas demandé cette réinitialisation, veuillez ignorer cet email. Votre mot de passe restera inchangé.
                        </div>
                        
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
        $mail->AltBody = "Bonjour " . $firstName . ",\n\nVous avez demandé la réinitialisation de votre mot de passe.\n\nPour créer un nouveau mot de passe, cliquez sur ce lien : " . $resetUrl . "\n\nCe lien est valable pendant 1 heure.\n\nSi vous n'avez pas demandé cette réinitialisation, ignorez cet email.\n\nL'équipe UCAO STUDENTS MARKETPLACE";
        
        // Envoyer l'email
        if ($mail->send()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - UCAO Students Marketplace</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-blue: #2563eb;
            --dark-blue: #1e40af;
            --warning-orange: #f59e0b;
            --warning-dark: #d97706;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --error-red: #dc2626;
            --success-green: #10b981;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .password-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        
        .password-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(245, 158, 11, 0.15);
            overflow: hidden;
            border: 1px solid #fcd34d;
        }
        
        .password-header {
            background: linear-gradient(135deg, var(--warning-orange) 0%, var(--warning-dark) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .password-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .logo-section {
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
            font-size: 1.8rem;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .header-content p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .password-body {
            padding: 40px;
        }
        
        .alert-message {
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: var(--success-green);
            color: var(--text-dark);
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-color: var(--warning-orange);
            color: var(--text-dark);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-color: var(--error-red);
            color: var(--text-dark);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 0.95rem;
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
            border-color: var(--warning-orange);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
            background: var(--white);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning-orange) 0%, var(--warning-dark) 100%);
            border: none;
            color: white;
            padding: 14px 32px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
            width: 100%;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(245, 158, 11, 0.3);
            color: white;
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--gray-200);
            color: var(--text-light);
            padding: 12px 24px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-outline-secondary:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
            color: var(--text-dark);
        }
        
        .password-footer {
            padding-top: 25px;
            margin-top: 25px;
            border-top: 1px solid var(--border-blue);
            text-align: center;
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .password-footer a {
            color: var(--warning-orange);
            text-decoration: none;
            font-weight: 600;
        }
        
        .password-footer a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-box i {
            color: var(--warning-orange);
            font-size: 1.2rem;
            margin-right: 10px;
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
            .password-body {
                padding: 30px 25px;
            }
            
            .password-header {
                padding: 30px 20px;
            }
            
            .header-content h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="password-container">
        <div class="password-card">
            <!-- En-tête -->
            <div class="password-header">
                <div class="header-content">
                    <div class="logo-section">
                        <div class="logo-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <div>
                            <h1>Mot de passe oublié</h1>
                            <p>UCAO Students Marketplace</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Corps -->
            <div class="password-body">
                <!-- Messages -->
                <?php if (!empty($success)): ?>
                    <div class="alert-message alert-success">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-check-circle-fill me-3" style="font-size: 1.2rem;"></i>
                            <div>
                                <strong>Email envoyé !</strong>
                                <div class="mt-2"><?php echo $success; ?></div>
                                <?php if (!empty($message)): ?>
                                    <div class="mt-2"><?php echo $message; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert-message alert-danger">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.2rem;"></i>
                            <div>
                                <strong>Erreur</strong>
                                <div class="mt-2"><?php echo htmlspecialchars($error); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Info Box -->
                <div class="info-box">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-info-circle"></i>
                        <div>
                            <strong>Comment ça marche ?</strong>
                            <p class="mb-0 mt-1">
                                Entrez votre adresse email. Si elle existe dans notre système, 
                                vous recevrez un lien pour réinitialiser votre mot de passe.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Formulaire -->
                <?php if (empty($success)): ?>
                <form method="POST" action="" id="forgotForm">
                    <div class="mb-4">
                        <label for="email" class="form-label">Adresse email</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required
                               placeholder="votre.email@exemple.com"
                               autocomplete="email">
                        <div class="form-text mt-2">
                            Utilisez l'adresse email avec laquelle vous vous êtes inscrit.
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-envelope-arrow-up me-2"></i>
                            Envoyer le lien de réinitialisation
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- Pied de page -->
                <div class="password-footer">
                    <p class="mb-2">
                        <a href="login.php">
                            <i class="bi bi-arrow-left me-1"></i>
                            Retour à la connexion
                        </a>
                    </p>
                    <p class="mb-0">
                        <a href="../index.php">
                            <i class="bi bi-house me-1"></i>
                            Page d'accueil
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts personnalisés -->
    <script>
        // Validation du formulaire
        document.getElementById('forgotForm')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            
            if (!email) {
                e.preventDefault();
                showError('Veuillez entrer votre adresse email.');
                return;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                showError('Veuillez entrer une adresse email valide.');
                return;
            }
            
            // Afficher un indicateur de chargement
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Envoi en cours...';
            submitBtn.disabled = true;
        });
        
        function showError(message) {
            const alertDiv = document.querySelector('.alert-danger');
            if (alertDiv) {
                alertDiv.remove();
            }
            
            const errorHtml = `
                <div class="alert-message alert-danger">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.2rem;"></i>
                        <div>
                            <strong>Erreur</strong>
                            <div class="mt-2">${message}</div>
                        </div>
                    </div>
                </div>
            `;
            
            const form = document.getElementById('forgotForm');
            form.insertAdjacentHTML('afterbegin', errorHtml);
            
            // Faire défiler vers le haut
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Mettre le focus sur le champ email
        document.getElementById('email')?.focus();
    </script>
</body>
</html>