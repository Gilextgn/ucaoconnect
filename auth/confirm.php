<?php
// auth/confirm.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token_raw = $_GET['token'] ?? '';
$userId = $_GET['uid'] ?? '';

// Debug
error_log("=== CONFIRMATION ===");
error_log("Token reçu: " . substr($token_raw, 0, 20) . "...");
error_log("User ID: " . $userId);

if (empty($token_raw) || empty($userId)) {
    die("<h2>Lien invalide</h2><p>Le lien de confirmation est incomplet.</p>");
}

// Connexion MySQL
require_once __DIR__ . '/../config/config.php';

if (!isset($conn) || $conn->connect_error) {
    die("<h2>Erreur de connexion</h2><p>Impossible de se connecter à la base de données ok  .</p>");
}

// Fonction améliorée pour valider le token
function validateToken($conn, $userId, $token) {
    // Option 1: Chercher avec le token original (colonne 'token')
    $sql1 = "
        SELECT ec.*, u.email, u.first_name, u.last_name, u.filiere, u.phone 
        FROM email_confirmations ec
        JOIN users u ON ec.user_id = u.id
        WHERE ec.user_id = ? 
        AND ec.token = ? 
        AND ec.expires_at > NOW()
        AND ec.confirmed = 0
    ";
    
    $stmt = $conn->prepare($sql1);
    if ($stmt) {
        $stmt->bind_param("ss", $userId, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            error_log("Token trouvé avec colonne 'token'");
            $tokenData = $result->fetch_assoc();
            $stmt->close();
            return $tokenData;
        }
        $stmt->close();
    }
    
    // Option 2: Chercher avec le hash (colonne 'token_hash')
    $token_hash = hash('sha256', $token);
    error_log("Essai avec hash: " . substr($token_hash, 0, 20) . "...");
    
    $sql2 = "
        SELECT ec.*, u.email, u.first_name, u.last_name, u.filiere, u.phone 
        FROM email_confirmations ec
        JOIN users u ON ec.user_id = u.id
        WHERE ec.user_id = ? 
        AND ec.token_hash = ? 
        AND ec.expires_at > NOW()
        AND ec.confirmed = 0
    ";
    
    $stmt = $conn->prepare($sql2);
    if ($stmt) {
        $stmt->bind_param("ss", $userId, $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            error_log("Token trouvé avec colonne 'token_hash'");
            $tokenData = $result->fetch_assoc();
            $stmt->close();
            return $tokenData;
        }
        $stmt->close();
    }
    
    error_log("Token non trouvé avec aucune méthode");
    return false;
}

// Vérifier le token
$tokenData = validateToken($conn, $userId, $token_raw);

if (!$tokenData) {
    // Debug avancé
    error_log("Debug: vérification des données en base...");
    
    // Vérifier si l'utilisateur existe
    $check_user = $conn->prepare("SELECT id, email FROM users WHERE id = ?");
    $check_user->bind_param("s", $userId);
    $check_user->execute();
    $user_result = $check_user->get_result();
    
    if ($user_result->num_rows === 0) {
        die("<h2>Utilisateur introuvable</h2><p>ID: " . htmlspecialchars($userId) . "</p>");
    }
    
    $user = $user_result->fetch_assoc();
    $check_user->close();
    
    // Vérifier les tokens pour cet utilisateur
    $check_tokens = $conn->prepare("
        SELECT token, token_hash, expires_at, confirmed 
        FROM email_confirmations 
        WHERE user_id = ?
    ");
    $check_tokens->bind_param("s", $userId);
    $check_tokens->execute();
    $tokens_result = $check_tokens->get_result();
    
    echo "<h2>Debug Information</h2>";
    echo "<p>Utilisateur: " . htmlspecialchars($user['email']) . "</p>";
    echo "<p>Token reçu: " . htmlspecialchars(substr($token_raw, 0, 30)) . "...</p>";
    
    if ($tokens_result->num_rows > 0) {
        echo "<h3>Tokens enregistrés pour cet utilisateur:</h3>";
        echo "<table border='1'><tr><th>Token (début)</th><th>Token Hash (début)</th><th>Expire</th><th>Confirmé</th></tr>";
        
        while ($row = $tokens_result->fetch_assoc()) {
            $token_match = ($row['token'] === $token_raw) ? "✅" : "❌";
            $hash_match = (!empty($row['token_hash']) && hash('sha256', $token_raw) === $row['token_hash']) ? "✅" : "❌";
            
            echo "<tr>";
            echo "<td>" . substr($row['token'], 0, 20) . "... $token_match</td>";
            echo "<td>" . (isset($row['token_hash']) ? substr($row['token_hash'], 0, 20) . "... $hash_match" : "NULL") . "</td>";
            echo "<td>" . $row['expires_at'] . (strtotime($row['expires_at']) < time() ? " ⏰" : "") . "</td>";
            echo "<td>" . ($row['confirmed'] ? 'Oui' : 'Non') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Aucun token trouvé pour cet utilisateur</p>";
    }
    
    $check_tokens->close();
    
    die("<h2>Lien invalide</h2>
        <p>Le lien de confirmation a expiré, a déjà été utilisé ou n'est pas valide.</p>
        <p><a href='activate_user.php?id=" . urlencode($userId) . "'>Activer manuellement ce compte</a></p>");
}

try {
    // Débuter une transaction
    $conn->begin_transaction();
    
    // 1. Marquer le token comme confirmé (utiliser l'ID du token pour être sûr)
    $updateTokenStmt = $conn->prepare("
        UPDATE email_confirmations 
        SET confirmed = 1, 
            confirmed_at = NOW() 
        WHERE id = ?
    ");
    
    $updateTokenStmt->bind_param("i", $tokenData['id']);
    $updateTokenStmt->execute();
    $updateTokenStmt->close();
    
    // 2. Activer l'utilisateur
    $updateUserStmt = $conn->prepare("
        UPDATE users 
        SET is_active = 1,
            email_verified = 1,
            verified_at = NOW()
        WHERE id = ?
    ");
    
    $updateUserStmt->bind_param("s", $userId);
    $updateUserStmt->execute();
    $updateUserStmt->close();
    
    // 3. Valider la transaction
    $conn->commit();
    
    // 4. Nettoyer les tokens expirés
    $cleanupStmt = $conn->prepare("DELETE FROM email_confirmations WHERE expires_at < NOW() AND confirmed = 0");
    $cleanupStmt->execute();
    $cleanupStmt->close();
    
    // Données pour l'affichage
    $userEmail = $tokenData['email'];
    $userName = trim($tokenData['first_name'] . ' ' . $tokenData['last_name']);
    $filiere = $tokenData['filiere'];
    
    // SUCCÈS
    $success = true;
    $message = "Votre compte a été activé avec succès !";
    
    error_log("✅ Compte activé: " . $userEmail);
    
} catch (Exception $e) {
    $conn->rollback();
    $success = false;
    $message = "Erreur: " . $e->getMessage();
    error_log("❌ Erreur activation: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email confirmé - UCAO Students Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Gardez votre CSS existant */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .confirmation-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.1);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            border: 1px solid #dbeafe;
        }
        .confirmation-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .confirmation-body {
            padding: 40px 30px;
            text-align: center;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: white;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            background: #dc2626;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }
        .btn-outline-secondary {
            border: 2px solid #e5e7eb;
            color: #6b7280;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .btn-outline-secondary:hover {
            background: #f3f4f6;
            color: #1f2937;
            text-decoration: none;
        }
        .alert-success {
            background: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
        }
        .alert-danger {
            background: #fee2e2;
            border: 1px solid #dc2626;
            color: #7f1d1d;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
        }
        .alert-info {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-left: 4px solid #2563eb;
            color: #1f2937;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }
        .user-info {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            text-align: left;
        }
        .user-info-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #4b5563;
        }
        .info-value {
            color: #1f2937;
        }
        .debug-info {
            background: #f3f4f6;
            border: 1px dashed #9ca3af;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
            font-size: 0.85rem;
            color: #6b7280;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="confirmation-card">
        <div class="confirmation-header">
            <h1 class="h3 mb-2">
                <?php echo $success ? 'Confirmation réussie' : 'Erreur de confirmation'; ?>
            </h1>
            <p class="mb-0">UCAO Students Marketplace</p>
        </div>
        
        <div class="confirmation-body">
            <?php if ($success): ?>
                <div class="success-icon">
                    <i class="bi bi-check-lg"></i>
                </div>
                
                <h2 class="h4 mb-3">Bienvenue <?php echo htmlspecialchars($userName); ?> !</h2>
                
                <div class="alert-success">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div>
                            <strong>Email confirmé avec succès</strong>
                            <p class="mb-0">Votre compte a été activé.</p>
                        </div>
                    </div>
                </div>
                
                <div class="user-info">
                    <div class="user-info-item">
                        <span class="info-label">Email :</span>
                        <span class="info-value"><?php echo htmlspecialchars($userEmail); ?></span>
                    </div>
                    <div class="user-info-item">
                        <span class="info-label">Nom complet :</span>
                        <span class="info-value"><?php echo htmlspecialchars($userName); ?></span>
                    </div>
                    <div class="user-info-item">
                        <span class="info-label">Filière :</span>
                        <span class="info-value"><?php echo htmlspecialchars($filiere); ?></span>
                    </div>
                    <div class="user-info-item">
                        <span class="info-label">Statut :</span>
                        <span class="info-value text-success">Compte actif ✓</span>
                    </div>
                </div>
                
                <div class="alert-info">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-lightbulb me-3"></i>
                        <div>
                            <p class="mb-0">
                                Vous pouvez maintenant vous connecter et accéder à toutes les fonctionnalités de la marketplace.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-3">
                    <a href="login.php?confirmed=1" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Se connecter maintenant
                    </a>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house me-2"></i>
                        Page d'accueil
                    </a>
                </div>
                
            <?php else: ?>
                <div class="error-icon">
                    <i class="bi bi-x-lg"></i>
                </div>
                
                <h2 class="h4 mb-3">Échec de confirmation</h2>
                
                <div class="alert-danger">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div>
                            <strong>Erreur lors de l'activation</strong>
                            <p class="mb-0"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="alert-info">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle me-3"></i>
                        <div>
                            <p class="mb-0">
                                Veuillez réessayer ou contacter le support si le problème persiste.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-3">
                    <a href="activate_user.php?id=<?php echo urlencode($userId); ?>" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>
                        Activer manuellement ce compte
                    </a>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house me-2"></i>
                        Retour à l'accueil
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="mt-4 pt-3 border-top">
                <p class="text-muted mb-0">
                    <small>Besoin d'aide ? <a href="mailto:support@marketplace-ucao.com">Contactez le support</a></small>
                </p>
            </div>
        </div>
    </div>
</body>
</html>