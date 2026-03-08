<?php
// auth/reset_password.php
session_start();

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/');
    exit();
}

// Inclure la configuration
require_once __DIR__ . '/../config/config.php';

// Récupérer les paramètres
$token = $_GET['token'] ?? '';
$userId = $_GET['uid'] ?? '';

// Variables
$error = '';
$success = '';
$valid_token = false;
$user_email = '';

// Validation initiale
if (empty($token) || empty($userId)) {
    $error = 'Lien de réinitialisation invalide.';
} else {
    try {
        // Vérifier le token
        $token_hash = hash('sha256', $token);
        $current_time = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("
            SELECT u.id, u.email, u.reset_token, u.reset_expires 
            FROM users u
            WHERE u.id = ? 
            AND u.reset_token = ?
            AND u.reset_expires > ?
        ");
        
        $stmt->bind_param("sss", $userId, $token_hash, $current_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Ce lien de réinitialisation est invalide ou a expiré.';
        } else {
            $user = $result->fetch_assoc();
            $user_email = $user['email'];
            $valid_token = true;
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error = 'Une erreur technique est survenue.';
    }
}

// Traitement du formulaire de réinitialisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Le mot de passe doit contenir au moins un chiffre.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            // Hacher le nouveau mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Mettre à jour le mot de passe et effacer le token
            $update_stmt = $conn->prepare("
                UPDATE users 
                SET password = ?, 
                    reset_token = NULL, 
                    reset_expires = NULL,
                    login_attempts = 0,
                    locked_until = NULL
                WHERE id = ?
            ");
            
            $update_stmt->bind_param("ss", $hashed_password, $userId);
            
            if ($update_stmt->execute()) {
                $success = 'Votre mot de passe a été réinitialisé avec succès !';
                $valid_token = false; // Invalider le token après utilisation
            } else {
                $error = 'Une erreur est survenue lors de la réinitialisation.';
            }
            $update_stmt->close();
            
        } catch (Exception $e) {
            $error = 'Une erreur technique est survenue.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation - UCAO Students Marketplace</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-green: #10b981;
            --dark-green: #059669;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --error-red: #dc2626;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #d1fae5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .reset-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        
        .reset-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.15);
            overflow: hidden;
            border: 1px solid #a7f3d0;
        }
        
        .reset-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .reset-header h1 {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        
        .reset-body {
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
            border-color: var(--primary-green);
            color: var(--text-dark);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-color: var(--error-red);
            color: var(--text-dark);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border: none;
            color: white;
            padding: 14px 32px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            width: 100%;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
            color: white;
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
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <!-- En-tête -->
            <div class="reset-header">
                <h1>Nouveau mot de passe</h1>
                <p>UCAO Students Marketplace</p>
            </div>
            
            <!-- Corps -->
            <div class="reset-body">
                <!-- Messages -->
                <?php if (!empty($success)): ?>
                    <div class="alert-message alert-success">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-check-circle-fill me-3"></i>
                            <div>
                                <strong>Réinitialisation réussie !</strong>
                                <div class="mt-2"><?php echo $success; ?></div>
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-success">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>
                                        Se connecter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($error)): ?>
                    <div class="alert-message alert-danger">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-triangle-fill me-3"></i>
                            <div>
                                <strong>Erreur</strong>
                                <div class="mt-2"><?php echo htmlspecialchars($error); ?></div>
                                <div class="mt-3">
                                    <a href="forgot_password.php" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-left me-2"></i>
                                        Nouvelle demande
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($valid_token): ?>
                    <!-- Formulaire de réinitialisation -->
                    <p class="mb-4">
                        <strong>Email :</strong> <?php echo htmlspecialchars($user_email); ?><br>
                        <small class="text-muted">Créez votre nouveau mot de passe ci-dessous.</small>
                    </p>
                    
                    <form method="POST" action="" id="resetForm">
                        <div class="mb-3">
                            <label for="password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required
                                   minlength="8"
                                   placeholder="8 caractères minimum">
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirmez le mot de passe</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required
                                   placeholder="Retapez votre mot de passe">
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-key-fill me-2"></i>
                                Réinitialiser le mot de passe
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <a href="login.php" class="text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i>
                            Retour à la connexion
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>