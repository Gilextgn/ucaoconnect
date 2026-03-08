<?php
// auth/check_tokens.php
session_start();
require_once __DIR__ . '/../config/config.php';

// Vérifier la connexion
if (!$conn || $conn->connect_error) {
    die("<div class='alert alert-danger'>Erreur de connexion à la base de données</div>");
}

// Traitement de l'activation manuelle
if (isset($_POST['activate_user'])) {
    $user_id = $_POST['user_id'] ?? '';
    
    if (!empty($user_id)) {
        try {
            $stmt = $conn->prepare("
                UPDATE users 
                SET is_active = 1,
                    email_verified = 1,
                    verified_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param("s", $user_id);
            if ($stmt->execute()) {
                $success_message = "Utilisateur activé avec succès !";
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $error_message = "Erreur: " . $e->getMessage();
        }
    }
}

// Traitement de la régénération de token_hash
if (isset($_POST['regenerate_hash'])) {
    try {
        $update_sql = "UPDATE email_confirmations SET token_hash = SHA2(token, 256) WHERE token_hash IS NULL";
        if ($conn->query($update_sql)) {
            $success_message = "Token_hash régénéré pour tous les tokens !";
        }
    } catch (Exception $e) {
        $error_message = "Erreur: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Vérification des Tokens</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-blue: #2563eb;
            --dark-blue: #1e40af;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #dc2626;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .admin-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 25px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .admin-body {
            padding: 30px;
        }
        
        .section-title {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: var(--gray-100);
            color: var(--dark-blue);
            font-weight: 600;
            border-bottom: 2px solid var(--gray-200);
            padding: 12px 15px;
        }
        
        .table td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: var(--gray-100);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(37, 99, 235, 0.05);
        }
        
        .badge {
            font-size: 0.75em;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .badge-success {
            background: var(--success-green);
        }
        
        .badge-danger {
            background: var(--error-red);
        }
        
        .badge-warning {
            background: var(--warning-orange);
            color: white;
        }
        
        .badge-info {
            background: var(--primary-blue);
        }
        
        .btn-action {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .btn-action-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }
        
        .token-hash {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #495057;
        }
        
        .expired {
            color: var(--error-red);
            font-weight: 500;
        }
        
        .valid {
            color: var(--success-green);
            font-weight: 500;
        }
        
        .alert-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stats-icon.blue {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-blue);
        }
        
        .stats-icon.green {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-green);
        }
        
        .stats-icon.orange {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-orange);
        }
        
        .stats-icon.red {
            background: rgba(220, 38, 38, 0.1);
            color: var(--error-red);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin: 10px 0;
        }
        
        .nav-tabs .nav-link {
            color: #6b7280;
            font-weight: 500;
            border: none;
            padding: 10px 20px;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-blue);
            border-bottom: 3px solid var(--primary-blue);
            background: transparent;
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--gray-200);
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .admin-body {
                padding: 20px 15px;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Messages d'alerte -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-fixed alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-fixed alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="admin-card mb-4">
            <div class="admin-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="bi bi-shield-check me-2"></i>
                            Panel Admin - Vérification des Tokens
                        </h1>
                        <p class="mb-0 opacity-75">Gestion des confirmations d'email et activation des comptes</p>
                    </div>
                    <div>
                        <a href="../index.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>
                            Retour au site
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="admin-body">
                <!-- Statistiques -->
                <div class="row mb-4">
                    <?php
                    // Récupérer les statistiques
                    $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
                    $active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];
                    $total_tokens = $conn->query("SELECT COUNT(*) as count FROM email_confirmations")->fetch_assoc()['count'];
                    $confirmed_tokens = $conn->query("SELECT COUNT(*) as count FROM email_confirmations WHERE confirmed = 1")->fetch_assoc()['count'];
                    ?>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon blue">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stats-number"><?php echo $total_users; ?></div>
                            <div class="stats-label">Utilisateurs totaux</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon green">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stats-number"><?php echo $active_users; ?></div>
                            <div class="stats-label">Comptes actifs</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon orange">
                                <i class="bi bi-key"></i>
                            </div>
                            <div class="stats-number"><?php echo $total_tokens; ?></div>
                            <div class="stats-label">Tokens générés</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon red">
                                <i class="bi bi-envelope-check"></i>
                            </div>
                            <div class="stats-number"><?php echo $confirmed_tokens; ?></div>
                            <div class="stats-label">Emails confirmés</div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions rapides -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="admin-card">
                            <div class="admin-body">
                                <h5 class="section-title">Actions rapides</h5>
                                <div class="action-buttons">
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="regenerate_hash" class="btn btn-warning btn-action">
                                            <i class="bi bi-arrow-clockwise me-1"></i>
                                            Régénérer token_hash
                                        </button>
                                    </form>
                                    <a href="check_tokens.php" class="btn btn-outline-primary btn-action">
                                        <i class="bi bi-arrow-repeat me-1"></i>
                                        Actualiser la page
                                    </a>
                                    <button class="btn btn-outline-success btn-action" onclick="activateAllUsers()">
                                        <i class="bi bi-check-all me-1"></i>
                                        Activer tous les utilisateurs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation par onglets -->
                <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                            <i class="bi bi-people me-1"></i>
                            Utilisateurs non activés
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tokens-tab" data-bs-toggle="tab" data-bs-target="#tokens" type="button" role="tab">
                            <i class="bi bi-key me-1"></i>
                            Tous les tokens
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="structure-tab" data-bs-toggle="tab" data-bs-target="#structure" type="button" role="tab">
                            <i class="bi bi-database me-1"></i>
                            Structure de la base
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="adminTabsContent">
                    <!-- Tab 1: Utilisateurs non activés -->
                    <div class="tab-pane fade show active" id="users" role="tabpanel">
                        <div class="admin-card">
                            <div class="admin-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="section-title mb-0">Utilisateurs non activés</h5>
                                    <span class="badge bg-danger"><?php 
                                        $inactive_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 0 OR email_verified = 0")->fetch_assoc()['count'];
                                        echo $inactive_count;
                                    ?> compte(s)</span>
                                </div>
                                
                                <?php
                                $inactive_users = $conn->query("
                                    SELECT u.* 
                                    FROM users u
                                    WHERE u.is_active = 0 
                                    OR u.email_verified = 0
                                    OR u.email_verified IS NULL
                                    ORDER BY u.created_at DESC
                                ");
                                ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th width="30%">ID</th>
                                                <th>Email</th>
                                                <th>Statut</th>
                                                <th>Créé le</th>
                                                <th width="150px">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($user = $inactive_users->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($user['id'], 0, 25)); ?>...</small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                                                    <?php if ($user['first_name']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                        </span>
                                                        <span class="badge <?php echo ($user['email_verified'] ?? 0) ? 'bg-success' : 'bg-warning'; ?>">
                                                            Email <?php echo ($user['email_verified'] ?? 0) ? 'Vérifié' : 'Non vérifié'; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                            <button type="submit" name="activate_user" class="btn btn-success btn-action btn-action-sm">
                                                                <i class="bi bi-check-lg"></i> Activer
                                                            </button>
                                                        </form>
                                                        <a href="login.php?email=<?php echo urlencode($user['email']); ?>" 
                                                           class="btn btn-outline-primary btn-action btn-action-sm"
                                                           target="_blank">
                                                            <i class="bi bi-box-arrow-in-right"></i> Tester
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 2: Tous les tokens -->
                    <div class="tab-pane fade" id="tokens" role="tabpanel">
                        <div class="admin-card">
                            <div class="admin-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="section-title mb-0">Tous les tokens de confirmation</h5>
                                    <span class="badge bg-info"><?php echo $total_tokens; ?> token(s)</span>
                                </div>
                                
                                <?php
                                $tokens = $conn->query("
                                    SELECT ec.*, u.email 
                                    FROM email_confirmations ec
                                    LEFT JOIN users u ON ec.user_id = u.id
                                    ORDER BY ec.created_at DESC
                                ");
                                ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User ID</th>
                                                <th>Email</th>
                                                <th>Token Hash</th>
                                                <th>Expiration</th>
                                                <th>Statut</th>
                                                <th>Créé le</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($token = $tokens->fetch_assoc()): 
                                                $is_expired = strtotime($token['expires_at']) < time();
                                            ?>
                                            <tr>
                                                <td><?php echo $token['id']; ?></td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($token['user_id'], 0, 20)); ?>...</small>
                                                </td>
                                                <td><?php echo htmlspecialchars($token['email'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if (isset($token['token_hash']) && !empty($token['token_hash'])): ?>
                                                        <span class="token-hash" title="<?php echo htmlspecialchars($token['token_hash']); ?>">
                                                            <?php echo substr($token['token_hash'], 0, 20); ?>...
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">NULL</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $is_expired ? 'expired' : 'valid'; ?>">
                                                        <?php echo date('d/m/Y H:i', strtotime($token['expires_at'])); ?>
                                                        <?php if ($is_expired): ?>
                                                            <i class="bi bi-clock-history ms-1"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($token['confirmed']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i> Confirmé
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="bi bi-clock me-1"></i> En attente
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($token['created_at'])); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 3: Structure de la base -->
                    <div class="tab-pane fade" id="structure" role="tabpanel">
                        <div class="admin-card">
                            <div class="admin-body">
                                <h5 class="section-title">Structure des tables</h5>
                                
                                <!-- Table email_confirmations -->
                                <h6 class="mt-4 mb-3 text-primary">
                                    <i class="bi bi-table me-2"></i>
                                    Table email_confirmations
                                </h6>
                                <?php
                                $structure = $conn->query("DESCRIBE email_confirmations");
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Champ</th>
                                                <th>Type</th>
                                                <th>Null</th>
                                                <th>Clé</th>
                                                <th>Défaut</th>
                                                <th>Extra</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($col = $structure->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo $col['Field']; ?></strong></td>
                                                <td><code><?php echo $col['Type']; ?></code></td>
                                                <td>
                                                    <span class="badge <?php echo $col['Null'] === 'YES' ? 'bg-warning' : 'bg-success'; ?>">
                                                        <?php echo $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($col['Key'])): ?>
                                                        <span class="badge bg-info"><?php echo $col['Key']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $col['Default'] ?? '<em>NULL</em>'; ?></td>
                                                <td><?php echo $col['Extra']; ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Commandes SQL utiles -->
                                <h6 class="mt-4 mb-3 text-primary">
                                    <i class="bi bi-code-slash me-2"></i>
                                    Commandes SQL utiles
                                </h6>
                                <div class="code-block">
// Remplir token_hash manquants<br>
UPDATE email_confirmations <br>
SET token_hash = SHA2(token, 256)<br>
WHERE token_hash IS NULL;<br><br>

// Activer tous les utilisateurs<br>
UPDATE users <br>
SET is_active = 1, email_verified = 1, verified_at = NOW();<br><br>

// Nettoyer les tokens expirés<br>
DELETE FROM email_confirmations <br>
WHERE expires_at < NOW() AND confirmed = 0;
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center text-muted mt-4">
            <small>
                <i class="bi bi-info-circle me-1"></i>
                Panel Admin - UCAO Students Marketplace | 
                <a href="mailto:support@marketplace-ucao.com" class="text-decoration-none">Support Technique</a>
            </small>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts personnalisés -->
    <script>
        // Fermer automatiquement les alertes après 5 secondes
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-fixed');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Fonction pour activer tous les utilisateurs
        function activateAllUsers() {
            if (confirm('Êtes-vous sûr de vouloir activer TOUS les utilisateurs ?')) {
                // Envoyer une requête AJAX pour activer tous les utilisateurs
                fetch('activate_all.php')
                    .then(response => response.text())
                    .then(data => {
                        alert('Tous les utilisateurs ont été activés !');
                        location.reload();
                    })
                    .catch(error => {
                        alert('Erreur: ' + error);
                    });
            }
        }
        
        // Fonction pour copier le token hash
        function copyTokenHash(hash) {
            navigator.clipboard.writeText(hash).then(() => {
                alert('Token hash copié !');
            });
        }
        
        // Initialiser les tooltips Bootstrap
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    </script>
</body>
</html>