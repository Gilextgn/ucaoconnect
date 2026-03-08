<?php
// marketplace/public/profile.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGS
error_log("=== PUBLIC PROFILE ===");

// 📥 RÉCUPÉRATION ID VENDEUR
$sellerId = isset($_GET['id']) ? trim($_GET['id']) : '';

// LOGS
error_log("Seller ID: '$sellerId'");

// ⚠️ REDIRECTION SI PAS D'ID
if (empty($sellerId)) {
    error_log("REDIRECTION: Pas d'ID vendeur");
    header('Location: index.php');
    exit();
}

// 📂 CHARGEMENT BASE DE DONNÉES
$databasePaths = [
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../../config/database.php',
    __DIR__ . '/../database.php',
    __DIR__ . '/config/database.php',
];

$databaseLoaded = false;
$db = null;

foreach ($databasePaths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            $databaseLoaded = true;
            
            if (class_exists('Database')) {
                $db = new Database();
                if ($db->testConnection()) {
                    break;
                }
            }
        } catch (Exception $e) {
            error_log("Erreur chargement DB $path: " . $e->getMessage());
        }
    }
}

if (!$databaseLoaded || !$db) {
    die("Erreur de connexion à la base de données");
}

// ============ FONCTIONS UTILITAIRES ============

/**
 * Fonction pour formater la date
 */
function formatDate($dateString) {
    if (empty($dateString)) return "Récemment";
    
    try {
        $date = new DateTime($dateString);
        $now = new DateTime();
        $diff = $now->diff($date);
        
        if ($diff->days == 0) return "Aujourd'hui";
        if ($diff->days == 1) return "Hier";
        if ($diff->days < 7) return "Il y a " . $diff->days . " jours";
        if ($diff->days < 30) {
            $weeks = floor($diff->days / 7);
            return "Il y a " . $weeks . " semaine" . ($weeks > 1 ? "s" : "");
        }
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return "Récemment";
    }
}

/**
 * Fonction pour trouver le chemin d'une image
 */
function findUserImagePath($filename, $userId, $db = null) {
    if (empty($filename)) return null;
    
    $basePath = $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/articles/';
    $usPath = $basePath . 'us/';
    
    if (is_dir($usPath)) {
        $userFolders = scandir($usPath);
        foreach ($userFolders as $folder) {
            if ($folder != '.' && $folder != '..' && is_dir($usPath . $folder)) {
                $imagePath = $usPath . $folder . '/' . $filename;
                if (file_exists($imagePath)) {
                    return '/assets/uploads/articles/us/' . $folder . '/' . $filename;
                }
            }
        }
    }
    
    if (is_dir($basePath)) {
        $allFolders = scandir($basePath);
        foreach ($allFolders as $folder) {
            if ($folder != '.' && $folder != '..' && $folder != 'us' && is_dir($basePath . $folder)) {
                $imagePath = $basePath . $folder . '/' . $filename;
                if (file_exists($imagePath)) {
                    return '/assets/uploads/articles/' . $folder . '/' . $filename;
                }
            }
        }
    }
    
    if ($userId) {
        $userIdPath = $basePath . $userId . '/';
        if (is_dir($userIdPath)) {
            $imagePath = $userIdPath . $filename;
            if (file_exists($imagePath)) {
                return '/assets/uploads/articles/' . $userId . '/' . $filename;
            }
        }
        
        $userIdUsPath = $usPath . $userId . '/';
        if (is_dir($userIdUsPath)) {
            $imagePath = $userIdUsPath . $filename;
            if (file_exists($imagePath)) {
                return '/assets/uploads/articles/us/' . $userId . '/' . $filename;
            }
        }
    }
    
    return null;
}

/**
 * Fonction pour générer les initiales
 */
function getInitials($name) {
    if (empty($name)) return '??';
    
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
    }
    return substr($initials, 0, 2);
}

// ============ FIN DES FONCTIONS ============

// 🔍 RÉCUPÉRATION DU VENDEUR
$seller = null;
$sellerInfo = [];

try {
    error_log("Recherche vendeur ID: $sellerId");
    
    $sellerInfo = $db->select(
        'users',
        ['*'],
        ['id' => $sellerId]
    );
    
    if (empty($sellerInfo)) {
        $sellerInfo = $db->select(
            'users',
            ['*'],
            ['email' => $sellerId]
        );
    }
    
    if (empty($sellerInfo)) {
        error_log("Vendeur non trouvé: $sellerId");
        $_SESSION['error_message'] = "Le vendeur demandé n'existe pas ou a été supprimé.";
        header('Location: index.php?error=user_not_found');
        exit();
    }
    
    $seller = $sellerInfo[0];
    error_log("✓ Vendeur trouvé: " . $seller['first_name'] . ' ' . $seller['last_name']);
    
    // Formater les données
    $seller['full_name'] = trim(($seller['first_name'] ?? '') . ' ' . ($seller['last_name'] ?? ''));
    $seller['formatted_date'] = formatDate($seller['created_at'] ?? '');
    
    if (!empty($seller['created_at'])) {
        $created = new DateTime($seller['created_at']);
        $seller['member_since'] = $created->format('d/m/Y');
        $now = new DateTime();
        $diff = $now->diff($created);
        $seller['member_years'] = $diff->y;
    } else {
        $seller['member_since'] = 'Date inconnue';
        $seller['member_years'] = 0;
    }
    
} catch (Exception $e) {
    error_log("ERREUR recherche vendeur: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la récupération des informations du vendeur.";
    header('Location: index.php?error=db');
    exit();
}

// 📊 STATISTIQUES DU VENDEUR
$sellerStats = [
    'total_articles' => 0,
    'available_articles' => 0,
    'sold_articles' => 0
];

try {
    $sellerStats['total_articles'] = $db->count('articles', ['user_id' => $seller['id']]);
    $sellerStats['available_articles'] = $db->count('articles', ['user_id' => $seller['id'], 'statut' => 'disponible']);
    $sellerStats['sold_articles'] = $db->count('articles', ['user_id' => $seller['id'], 'statut' => 'vendu']);
    
} catch (Exception $e) {
    error_log("ERREUR statistiques: " . $e->getMessage());
}

// 📦 ARTICLES DU VENDEUR
$sellerArticles = [];

try {
    $articlesData = $db->select(
        'articles',
        ['*'],
        ['user_id' => $seller['id'], 'statut' => 'disponible'],
        null,
        6,
        'created_at DESC'
    );
    
    foreach ($articlesData as $article) {
        $item = $article;
        
        $item['formatted_price'] = !empty($article['prix']) ? 
            number_format($article['prix'], 0, '', ' ') . ' FCFA' : 'Gratuit';
        
        $item['short_title'] = strlen($article['titre'] ?? '') > 30 ? 
            substr($article['titre'], 0, 27) . '...' : ($article['titre'] ?? 'Sans titre');
        
        if (!empty($article['image_url'])) {
            $filename = basename($article['image_url']);
            $item['image_path'] = findUserImagePath($filename, $seller['id'], $db);
        } else {
            $item['image_path'] = null;
        }
        
        $item['formatted_date'] = formatDate($article['created_at'] ?? '');
        
        $sellerArticles[] = $item;
    }
    
} catch (Exception $e) {
    error_log("ERREUR récupération articles: " . $e->getMessage());
}

// Générer l'URL WhatsApp
$whatsappUrl = '#';
if (!empty($seller['phone'])) {
    $phoneNumber = preg_replace('/[^0-9]/', '', $seller['phone']);
    $message = "Bonjour " . $seller['first_name'] . " ! ";
    $message .= "Je vous contacte depuis votre profil UCAO Marketplace.";
    $whatsappUrl = "https://wa.me/{$phoneNumber}?text=" . urlencode($message);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seller['full_name']); ?> - Profil Vendeur | UCAO Marketplace</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #111827;
            --primary-dark: #0d1321;
            --secondary: #3b82f6;
            --accent: #10b981;
            --light: #f9fafb;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 6px 15px -1px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: var(--light);
            color: var(--gray-800);
            line-height: 1.6;
            padding-top: 0;
        }

        /* Navigation */
        .navbar {
            background: white;
            box-shadow: var(--shadow-sm);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.98);
        }

        .navbar-brand {
            color: var(--primary);
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            color: var(--secondary);
        }

        .nav-link {
            color: var(--gray-700);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            color: var(--secondary);
            background: var(--gray-50);
        }

        /* Hero Section */
        .profile-hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 100px 0 60px;
            position: relative;
            overflow: hidden;
            margin-bottom: 60px;
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                       radial-gradient(circle at 70% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--secondary) 0%, #1d4ed8 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .profile-name {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.1;
            letter-spacing: -0.5px;
        }

        .profile-title {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
            max-width: 600px;
        }

        .profile-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            opacity: 0.85;
        }

        .meta-item i {
            font-size: 1.1rem;
            opacity: 0.7;
        }

        /* Stats */
        .stats-section {
            margin-bottom: 60px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 10px;
            line-height: 1;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.95rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 40px;
            margin-bottom: 80px;
        }

        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .info-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--secondary);
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--gray-100);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            color: var(--gray-600);
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }

        .info-value {
            color: var(--gray-800);
            font-size: 1.05rem;
            font-weight: 500;
        }

        /* Contact Button */
        .contact-btn {
            background: linear-gradient(135deg, var(--secondary) 0%, #1d4ed8 100%);
            text-decoration: none;
            border: none;
            color: white;
            padding: 18px 30px;
            font-weight: 600;
            border-radius: var(--radius);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin-top: 10px;
            font-size: 1.1rem;
            position: relative;
            overflow: hidden;
        }

        .contact-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
            color: white;
        }

        .contact-btn:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        /* Articles Section */
        .articles-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .articles-count {
            background: var(--gray-100);
            color: var(--secondary);
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .article-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }

        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--secondary);
            text-decoration: none;
            color: inherit;
        }

        .article-image {
            height: 180px;
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            position: relative;
            overflow: hidden;
        }

        .article-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .article-card:hover .article-image img {
            transform: scale(1.05);
        }

        .article-content {
            padding: 25px;
        }

        .article-category {
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: block;
        }

        .article-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 15px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .article-price {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--secondary);
            margin-bottom: 15px;
        }

        .article-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--gray-600);
            font-size: 0.85rem;
            border-top: 1px solid var(--gray-200);
            padding-top: 15px;
        }

        .view-details {
            color: var(--secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* No Articles */
        .no-articles {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: var(--radius-lg);
            border: 2px dashed var(--gray-300);
            margin: 20px 0;
        }

        .no-articles i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }

        .no-articles h4 {
            color: var(--gray-700);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .no-articles p {
            color: var(--gray-600);
            margin-bottom: 25px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Footer */
        .footer {
            background: var(--primary);
            color: white;
            padding: 60px 0 30px;
            margin-top: 80px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 60px;
            margin-bottom: 40px;
        }

        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }

        .footer-brand {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .footer-description {
            color: var(--gray-300);
            line-height: 1.7;
            max-width: 400px;
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .footer-link {
            color: var(--gray-300);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.2s ease;
        }

        .footer-link:hover {
            color: white;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid var(--gray-800);
            color: var(--gray-400);
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-hero {
                padding: 80px 0 40px;
            }
            
            .profile-name {
                font-size: 2.2rem;
            }
            
            .profile-meta {
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .articles-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-number {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-meta {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadein {
            animation: fadeIn 0.6s ease forwards;
            opacity: 0;
        }

        /* Loading State */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shop-window"></i>
                UCAO Marketplace
            </a>
            
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house me-1"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="browse.php">
                            <i class="bi bi-grid me-1"></i> Annonces
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">
                            <i class="bi bi-info-circle me-1"></i> À propos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">
                            <i class="bi bi-envelope me-1"></i> Contact
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="profile-hero">
        <div class="container">
            <div class="hero-content">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="profile-avatar">
                            <?php echo getInitials($seller['full_name']); ?>
                        </div>
                        <h1 class="profile-name"><?php echo htmlspecialchars($seller['full_name']); ?></h1>
                        <p class="profile-title">
                            Étudiant vérifié de l'UCAO • Membre actif de la communauté
                        </p>
                        <div class="profile-meta">
                            <div class="meta-item">
                                <i class="bi bi-calendar"></i>
                                <span>Membre depuis <?php echo htmlspecialchars($seller['member_since']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-envelope"></i>
                                <span><?php echo htmlspecialchars($seller['email']); ?></span>
                            </div>
                            <?php if (!empty($seller['phone'])): ?>
                            <div class="meta-item">
                                <i class="bi bi-telephone"></i>
                                <span><?php echo htmlspecialchars($seller['phone']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $sellerStats['total_articles']; ?></div>
                    <div class="stat-label">Articles au total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $sellerStats['available_articles']; ?></div>
                    <div class="stat-label">Disponibles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $sellerStats['sold_articles']; ?></div>
                    <div class="stat-label">Vendus</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo max(1, $seller['member_years']); ?>+</div>
                    <div class="stat-label">Années sur la plateforme</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <div class="content-grid">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="info-card">
                    <h3 class="card-title">
                        <i class="bi bi-person-circle"></i>
                        Informations
                    </h3>
                    <div class="info-list">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="bi bi-person"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Nom complet</div>
                                <div class="info-value"><?php echo htmlspecialchars($seller['full_name']); ?></div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="bi bi-envelope"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($seller['email']); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($seller['phone'])): ?>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="bi bi-telephone"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Téléphone</div>
                                <div class="info-value"><?php echo htmlspecialchars($seller['phone']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="bi bi-calendar"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Membre depuis</div>
                                <div class="info-value"><?php echo htmlspecialchars($seller['member_since']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3 class="card-title">
                        <i class="bi bi-chat-dots"></i>
                        Contact
                    </h3>
                    <?php if ($whatsappUrl !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($whatsappUrl); ?>" 
                           target="_blank" 
                           class="contact-btn">
                            <i class="bi bi-whatsapp"></i>
                            Contacter sur WhatsApp
                        </a>
                    <?php else: ?>
                        <button class="contact-btn" disabled>
                            <i class="bi bi-whatsapp me-2"></i>
                            Contact non disponible
                        </button>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <div class="articles-section">
                    <div class="articles-header">
                        <h2 class="section-title">
                            <i class="bi bi-box"></i>
                            Articles de <?php echo htmlspecialchars($seller['first_name']); ?>
                        </h2>
                        <span class="articles-count">
                            <?php echo count($sellerArticles); ?> article<?php echo count($sellerArticles) > 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($sellerArticles)): ?>
                        <div class="articles-grid">
                            <?php foreach ($sellerArticles as $article): ?>
                                <a href="view-article.php?id=<?php echo $article['id']; ?>" class="article-card">
                                    <div class="article-image">
                                        <?php if (!empty($article['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($article['image_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($article['titre']); ?>"
                                                 onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2YwZjBmMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTYiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5QYXMgZCdJbWFnZTwvdGV4dD48L3N2Zz4=';">
                                        <?php else: ?>
                                            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2YwZjBmMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTYiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5QYXMgZCdJbWFnZTwvdGV4dD48L3N2Zz4=" 
                                                 alt="Pas d'image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="article-content">
                                        <span class="article-category"><?php echo htmlspecialchars($article['categorie'] ?? 'Non catégorisé'); ?></span>
                                        <h3 class="article-title"><?php echo htmlspecialchars($article['short_title']); ?></h3>
                                        <div class="article-price"><?php echo htmlspecialchars($article['formatted_price']); ?></div>
                                        <div class="article-meta">
                                            <span><?php echo htmlspecialchars($article['formatted_date']); ?></span>
                                            <span class="view-details">
                                                Voir <i class="bi bi-arrow-right"></i>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($sellerStats['available_articles'] > 6): ?>
                            <div class="text-center mt-5">
                                <a href="browse.php?seller=<?php echo urlencode($seller['id']); ?>" class="contact-btn">
                                    <i class="bi bi-eye me-2"></i>
                                    Voir tous les articles (<?php echo $sellerStats['available_articles']; ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="no-articles">
                            <i class="bi bi-box"></i>
                            <h4>Aucun article disponible</h4>
                            <p>Ce vendeur n'a actuellement aucun article disponible à la vente.</p>
                            <a href="browse.php" class="contact-btn">
                                <i class="bi bi-arrow-left me-2"></i>
                                Parcourir les annonces
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div>
                    <div class="footer-brand">
                        <i class="bi bi-shop-window"></i>
                        UCAO Marketplace
                    </div>
                    <p class="footer-description">
                        La plateforme officielle d'échanges entre étudiants de l'Université Catholique de l'Afrique de l'Ouest.
                        Conçu pour les étudiants, par des étudiants.
                    </p>
                </div>
                <div>
                    <h5 class="text-white mb-3">Navigation</h5>
                    <div class="footer-links">
                        <a href="index.php" class="footer-link">
                            <i class="bi bi-house"></i>
                            Accueil
                        </a>
                        <a href="browse.php" class="footer-link">
                            <i class="bi bi-grid"></i>
                            Annonces
                        </a>
                        <a href="#" class="footer-link">
                            <i class="bi bi-info-circle"></i>
                            À propos
                        </a>
                        <a href="#" class="footer-link">
                            <i class="bi bi-envelope"></i>
                            Contact
                        </a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> UCAO Marketplace. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Animation au scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        // Observer les cartes
        document.querySelectorAll('.stat-card, .info-card, .article-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            observer.observe(el);
        });

        // Animation des statistiques
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach((number, index) => {
                setTimeout(() => {
                    number.style.opacity = '1';
                    number.style.transform = 'scale(1)';
                }, index * 150);
            });
            
            // Gestion des erreurs d'images
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    if (!this.src.includes('data:image/svg+xml')) {
                        this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMyBvcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSI0MDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNiIgZmlsbD0iIzk5OTk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPlBhcyBkJ0ltYWdlPC90ZXh0Pjwvc3ZnPg==';
                    }
                });
            });
            
            // Effet de hover sur les cartes d'articles
            const articleCards = document.querySelectorAll('.article-card');
            articleCards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.zIndex = '10';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.zIndex = '';
                });
            });
        });
    </script>
</body>
</html>