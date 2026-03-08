<?php
/**
 * En-tête commune pour toutes les pages
 */

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration du fuseau horaire
date_default_timezone_set('Europe/Paris');

// URL de base
$base_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . 
            '://' . $_SERVER['HTTP_HOST'] . '/';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UCAO Students Marketplace - Plateforme d'échange entre étudiants">
    <meta name="keywords" content="UCAO, marketplace, étudiants, livres, matériel, échange">
    <meta name="author" content="UCAO Students Marketplace">
    
    <title><?php echo $page_title ?? 'UCAO Students Marketplace'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    
    <!-- Styles personnalisés -->
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
            --gray-300: #d1d5db;
            --error-red: #dc2626;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            color: var(--text-dark);
            background-color: var(--gray-100);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .main-content {
            flex: 1;
            padding-bottom: 3rem;
        }
        
        /* Navigation principale */
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--dark-blue) !important;
        }
        
        .navbar-brand i {
            color: var(--primary-blue);
        }
        
        .nav-link {
            font-weight: 500;
            color: var(--text-dark) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: var(--very-light-blue);
            color: var(--primary-blue) !important;
        }
        
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
        }
        
        .navbar-toggler:focus {
            box-shadow: 0 0 0 2px var(--primary-blue);
        }
        
        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
        }
        
        /* Cards */
        .card {
            border: 1px solid var(--border-blue);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            background-color: var(--very-light-blue);
            border-bottom: 1px solid var(--border-blue);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        
        /* Buttons */
        .btn {
            font-weight: 500;
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--very-light-blue);
            border-color: var(--dark-blue);
            color: var(--dark-blue);
        }
        
        /* Forms */
        .form-control, .form-select {
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        /* Alerts */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.25rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: var(--text-dark);
            border-left: 4px solid var(--success-green);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: var(--text-dark);
            border-left: 4px solid var(--error-red);
        }
        
        .alert-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: var(--text-dark);
            border-left: 4px solid var(--primary-blue);
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%);
            color: var(--text-dark);
            border-left: 4px solid var(--warning-orange);
        }
        
        /* Tables */
        .table {
            --bs-table-striped-bg: rgba(37, 99, 235, 0.05);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(37, 99, 235, 0.1);
        }
        
        /* Breadcrumb */
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        
        .breadcrumb-item a {
            color: var(--text-light);
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            color: var(--primary-blue);
        }
        
        .breadcrumb-item.active {
            color: var(--text-dark);
            font-weight: 500;
        }
        
        /* Custom utilities */
        .text-primary-gradient {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .bg-very-light-blue {
            background-color: var(--very-light-blue);
        }
        
        .border-blue {
            border-color: var(--border-blue) !important;
        }
        
        /* Avatar */
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-blue);
        }
        
        .avatar-lg {
            width: 120px;
            height: 120px;
        }
        
        /* Dropdown */
        .dropdown-menu {
            border: 1px solid var(--border-blue);
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            padding: 0.5rem;
        }
        
        .dropdown-item {
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            background-color: var(--very-light-blue);
            color: var(--primary-blue);
        }
        
        /* Modals */
        .modal-content {
            border-radius: 12px;
            border: 1px solid var(--border-blue);
        }
        
        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #1e3a8a 100%);
            color: white;
            margin-top: auto;
        }
        
        .footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .footer a:hover {
            color: white;
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar-nav .nav-item {
                margin-bottom: 0.5rem;
            }
            
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }
        
        /* Toast notifications */
        .toast {
            border-radius: 10px;
            border: 1px solid var(--border-blue);
            box-shadow: var(--shadow-lg);
        }
        
        /* Loader */
        .loader {
            width: 40px;
            height: 40px;
            border: 3px solid var(--gray-200);
            border-top: 3px solid var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Progress bars */
        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: var(--gray-200);
        }
        
        .progress-bar {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            border-radius: 4px;
        }
        
        /* List group */
        .list-group-item {
            border: 1px solid var(--border-blue);
            padding: 1rem 1.25rem;
        }
        
        .list-group-item:first-child {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        
        .list-group-item:last-child {
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
        }
        
        /* Pagination */
        .page-link {
            color: var(--primary-blue);
            border: 1px solid var(--border-blue);
            padding: 0.5rem 1rem;
        }
        
        .page-link:hover {
            background-color: var(--very-light-blue);
            color: var(--dark-blue);
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            border-color: var(--primary-blue);
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --text-dark: #f1f5f9;
                --text-light: #94a3b8;
                --white: #0f172a;
                --gray-100: #1e293b;
                --gray-200: #334155;
                --gray-300: #475569;
                --border-blue: #1e3a8a;
                --very-light-blue: #1e3a8a20;
            }
            
            .card {
                background-color: #1e293b;
                border-color: var(--border-blue);
            }
            
            .form-control, .form-select {
                background-color: #334155;
                border-color: #475569;
                color: #f1f5f9;
            }
            
            .form-control:focus, .form-select:focus {
                background-color: #334155;
                color: #f1f5f9;
            }
            
            .form-label {
                color: #cbd5e1;
            }
            
            .table {
                --bs-table-bg: transparent;
                --bs-table-striped-bg: rgba(37, 99, 235, 0.1);
                --bs-table-hover-bg: rgba(37, 99, 235, 0.2);
                color: #cbd5e1;
            }
        }
    </style>
    
    <!-- Styles spécifiques à la page -->
    <?php if (isset($page_styles)): ?>
        <style><?php echo $page_styles; ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- Barre de navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand d-flex align-items-center" href="/">
                <i class="bi bi-shop-window me-2" style="font-size: 1.8rem;"></i>
                <span>UCAO Marketplace</span>
            </a>
            
            <!-- Bouton menu mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Menu principal -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="/">
                            <i class="bi bi-house me-1"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/browse.php">
                            <i class="bi bi-search me-1"></i> Rechercher
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['logged_in']): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/dashboard/">
                                <i class="bi bi-speedometer2 me-1"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/dashboard/articles/add.php">
                                <i class="bi bi-plus-circle me-1"></i> Vendre
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/about.php">
                            <i class="bi bi-info-circle me-1"></i> À propos
                        </a>
                    </li>
                </ul>
                
                <!-- Actions utilisateur -->
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['logged_in']): ?>
                        <!-- Utilisateur connecté -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary d-flex align-items-center dropdown-toggle" 
                                    type="button" 
                                    data-bs-toggle="dropdown">
                                <div class="me-2">
                                    <?php if (!empty($_SESSION['profile_photo'])): ?>
                                        <img src="<?php echo htmlspecialchars($_SESSION['profile_photo']); ?>" 
                                             class="avatar" 
                                             alt="Photo de profil">
                                    <?php else: ?>
                                        <div class="avatar bg-very-light-blue d-flex align-items-center justify-content-center">
                                            <i class="bi bi-person text-primary"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="d-none d-md-inline">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Utilisateur'); ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="/dashboard/">
                                        <i class="bi bi-speedometer2 me-2"></i> Tableau de bord
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/dashboard/profile/edit.php">
                                        <i class="bi bi-person me-2"></i> Mon profil
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/dashboard/mes-demandes.php">
                                        <i class="bi bi-chat me-2"></i> Mes demandes
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="/auth/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                                    </a>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Notification badge (optionnel) -->
                        <?php if (isset($_SESSION['unread_messages']) && $_SESSION['unread_messages'] > 0): ?>
                            <a href="/dashboard/messages.php" class="btn btn-light position-relative ms-2">
                                <i class="bi bi-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $_SESSION['unread_messages']; ?>
                                    <span class="visually-hidden">messages non lus</span>
                                </span>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Utilisateur non connecté -->
                        <a href="/auth/login.php" class="btn btn-outline-primary me-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Connexion
                        </a>
                        <a href="/auth/register.php" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i> Inscription
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <div class="container py-4">
            <!-- Fil d'Ariane -->
            <?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/">Accueil</a></li>
                        <?php if (isset($breadcrumb_items)): ?>
                            <?php foreach ($breadcrumb_items as $item): ?>
                                <?php if (isset($item['active']) && $item['active']): ?>
                                    <li class="breadcrumb-item active" aria-current="page">
                                        <?php echo htmlspecialchars($item['text']); ?>
                                    </li>
                                <?php else: ?>
                                    <li class="breadcrumb-item">
                                        <a href="<?php echo htmlspecialchars($item['url']); ?>">
                                            <?php echo htmlspecialchars($item['text']); ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </nav>
            <?php endif; ?>
            
            <!-- Titre de page -->
            <?php if (isset($page_title) && !isset($hide_page_title)): ?>
                <div class="mb-4">
                    <h1 class="h2 mb-2"><?php echo htmlspecialchars($page_title); ?></h1>
                    <?php if (isset($page_subtitle)): ?>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($page_subtitle); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>