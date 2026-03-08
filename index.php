<?php
/**
 * UCAO Students Marketplace - Accueil Principal
 * Design : Bento Moderniste / Premium Edge
 */

session_start();

// État de connexion
$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $_SESSION['first_name'] ?? '';

// --- LOGIQUE DE BASE DE DONNÉES (CONSERVÉE) ---
$databasePaths = [
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../../config/database.php',
    'C:/wamp64/www/marketplace/config/database.php',
];

$db = null;
$databaseLoaded = false;
foreach ($databasePaths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            if (class_exists('Database')) { $db = new Database(); $databaseLoaded = true; break; }
        } catch (Exception $e) { error_log("DB Error: " . $e->getMessage()); }
    }
}

// --- FONCTIONS UTILITAIRES ---
function generateWhatsAppUrl($itemTitle, $phoneNumber = '', $sellerName = '') {
    if (empty($phoneNumber)) return "#";
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    $message = "Salut " . ($sellerName ?: "vendeur") . " ! Je suis intéressé par ton annonce : \"" . $itemTitle . "\" sur UCAO Market.";
    return "https://wa.me/{$phoneNumber}?text=" . urlencode($message);
}

/**
 * NOUVELLE LOGIQUE D'AFFICHAGE DES IMAGES
 */
function getDisplayImageUrl($rawUrl) {
    if (empty($rawUrl)) return null;

    $decoded = json_decode($rawUrl, true);
    if (is_array($decoded) && !empty($decoded)) {
        $url = $decoded[0];
    } else {
        $url = $rawUrl;
    }

    if (strpos($url, 'http') === 0) return $url;

    $url = str_replace(['//', '\\'], '/', $url);
    if (strpos($url, '/') !== 0) {
        $url = '/' . $url;
    }

    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $url)) {
        return $url;
    }
    
    $fallbackPath = '/assets/uploads/articles/' . ltrim($url, '/');
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $fallbackPath)) {
        return $fallbackPath;
    }

    return null;
}

// --- RÉCUPÉRATION DES DONNÉES (MODIFIÉ : LIMITÉ À 6 ARTICLES) ---
$latestItems = [];
if ($databaseLoaded && method_exists($db, 'select')) {
    // Changement ici : passage à 6 articles
    $articles = $db->select('articles', ['*'], ['statut' => 'disponible'], null, 6, 'created_at DESC');
    if (!empty($articles)) {
        $usersCache = [];
        foreach ($articles as $art) {
            $uid = $art['user_id'];
            if (!isset($usersCache[$uid])) {
                $user = $db->select('users', ['*'], ['id' => $uid]);
                $usersCache[$uid] = $user[0] ?? null;
            }
            $seller = $usersCache[$uid];
            $art['seller_name'] = trim(($seller['first_name'] ?? '') . ' ' . ($seller['last_name'] ?? '')) ?: 'Étudiant';
            $art['seller_phone'] = $seller['phone'] ?? '';
            $art['formatted_price'] = $art['prix'] > 0 ? number_format($art['prix'], 0, '', ' ') . ' F' : 'Gratuit';
            $art['whatsapp_url'] = generateWhatsAppUrl($art['titre'], $art['seller_phone'], $art['seller_name']);
            $art['image_path'] = getDisplayImageUrl($art['image_url']);
            
            $latestItems[] = $art;
        }
    }
}

$categories = [
    'Livres'       => ['icon' => 'bi bi-book'],
    'Électronique' => ['icon' => 'bi bi-laptop'],
    'Matériel'     => ['icon' => 'bi bi-briefcase'],
    'Cours'        => ['icon' => 'bi bi-journal-text'],
    'Vêtements'    => ['icon' => 'bi bi-tag'],
    'Services'     => ['icon' => 'bi bi-tools']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCAO Market | Plateforme Officielle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb; --bg-body: #f8fafc; --bg-card: #ffffff;
            --text-title: #0f172a; --text-body: #334155; --border: #e2e8f0;
        }
        [data-theme="dark"] {
            --bg-body: #020617; --bg-card: #1e293b; --text-title: #f8fafc;
            --text-body: #cbd5e1; --border: #334155;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-body); transition: 0.3s ease; }

        /* --- NAVBAR --- */
        .navbar { padding: 20px 0; transition: 0.4s; z-index: 1100; background: transparent; }
        .navbar.scrolled { background: var(--bg-card) !important; padding: 12px 0; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-bottom: 1px solid var(--border); }
        .navbar.scrolled .nav-link, .navbar.scrolled .navbar-brand { color: var(--text-title) !important; }
        .navbar-brand { font-weight: 800; font-size: 1.5rem; color: #fff; }
        .nav-link { color: #fff !important; font-weight: 600; transition: 0.3s; }
        .nav-link:hover { color: var(--primary) !important; }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: var(--bg-card);
                margin-top: 15px;
                padding: 25px;
                border-radius: 24px;
                border: 1px solid var(--border);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            }
            .navbar:not(.scrolled) .nav-link, 
            .navbar:not(.scrolled) #loginText { color: var(--text-title) !important; }
            .navbar-nav .nav-item { border-bottom: 1px solid var(--border); }
            .navbar-nav .nav-item:last-child { border-bottom: none; }
        }

        /* --- THEME SWITCH --- */
        .theme-switch { position: relative; width: 48px; height: 24px; }
        .theme-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.3); transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background: var(--primary); }
        input:checked + .slider:before { transform: translateX(24px); }

        /* --- HERO --- */
        .hero-container { position: relative; height: 85vh; width: 100%; display: flex; align-items: center; justify-content: center; color: white; border-radius: 0 0 40px 40px; overflow: hidden; }
        .hero-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.8)), url('assets/img/ucaouuc.jpeg') center/cover; z-index: -1; }
        .hero-title { font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 800; letter-spacing: -1px; }

        /* --- BENTO CARDS --- */
        .bento-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 24px; padding: 30px; transition: 0.3s; height: 100%; }
        .bento-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        
        /* --- ARTICLES --- */
        .article-card { background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border); overflow: hidden; transition: 0.3s; height: 100%; display: flex; flex-direction: column; }
        .article-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .img-container { height: 200px; background: #f1f5f9; overflow: hidden; }
        .img-container img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .article-card:hover .img-container img { transform: scale(1.1); }
        .item-price { font-size: 1.2rem; font-weight: 800; color: var(--primary); }
        
        @media (max-width: 576px) {
            .img-container { height: 140px; }
            .article-card .p-4 { padding: 1rem !important; }
            .item-price { font-size: 1rem; }
            .btn-wa { font-size: 0.8rem; padding: 8px; }
        }

        .btn-view-more { 
            color: var(--primary); 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 0.9rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
            margin-bottom: 12px;
            transition: 0.2s;
        }
        .btn-view-more:hover { gap: 10px; color: #1d4ed8; }
        .btn-wa { background: #25D366; color: white !important; padding: 12px; border-radius: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; margin-top: auto; }

        /* --- SECTION STATS --- */
        .section-stats { background: var(--primary); color: white; padding: 40px; border-radius: 30px; margin-top: -60px; position: relative; z-index: 10; box-shadow: 0 15px 35px rgba(37, 99, 235, 0.2); }
    </style>
</head>
<body data-theme="light">

    <nav class="navbar navbar-expand-lg fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="#">UCAO<span style="color:var(--primary)"> Connect</span></a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <i class="bi bi-list fs-1 text-white" id="burgerIcon"></i>
            </button>

            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav mx-auto gap-lg-3 text-center py-4 py-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link" href="browse.php">Annonces</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">À Propos</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>

                <div class="d-flex flex-column flex-lg-row align-items-center justify-content-center gap-4">
                    <label class="theme-switch">
                        <input type="checkbox" id="themeToggle">
                        <span class="slider"></span>
                    </label>

                    <?php if ($isLoggedIn): ?>
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-none d-lg-block fw-bold text-white" id="userDisplay"><?= htmlspecialchars($userName) ?></span>
                            <a href="dashboard/index.php" class="btn btn-primary rounded-pill px-4 fw-bold">Dashboard</a>
                        </div>
                    <?php else: ?>
                        <a href="auth/login.php" class="nav-link" id="loginText">Connexion</a>
                        <a href="auth/register.php" class="btn btn-primary rounded-pill px-4 fw-bold">S'inscrire</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="hero-container">
        <div class="hero-bg"></div>
        <div class="hero-content text-center container">
            <span class="badge rounded-pill px-4 py-2 mb-3 bg-primary bg-opacity-25 border border-primary text-white">Marketplace Étudiante</span>
            <h1 class="hero-title">Simplifiez vos échanges au sein de l'UCAO.</h1>
            <p class="lead opacity-75 mb-5">Achetez, vendez et troquez vos articles entre étudiants.</p>
            <div class="bg-white p-2 rounded-pill shadow-lg mx-auto d-flex" style="max-width: 600px;">
                <input type="text" class="form-control border-0 px-4" placeholder="Rechercher un livre, un PC...">
                <button class="btn btn-primary rounded-pill px-4 fw-bold">Trouver</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="section-stats text-center">
            <div class="row g-4">
                <div class="col-md-4">
                    <h3 class="fw-800 mb-0">500+</h3>
                    <p class="small opacity-75 fw-bold mb-0 text-uppercase">Articles en ligne</p>
                </div>
                <div class="col-md-4 border-md-start border-md-end border-white border-opacity-25">
                    <h3 class="fw-800 mb-0">1.2k</h3>
                    <p class="small opacity-75 fw-bold mb-0 text-uppercase">Étudiants actifs</p>
                </div>
                <div class="col-md-4">
                    <h3 class="fw-800 mb-0">24h</h3>
                    <p class="small opacity-75 fw-bold mb-0 text-uppercase">Temps de vente moyen</p>
                </div>
            </div>
        </div>
    </div>

    <section class="container py-5 mt-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="fw-800 mb-0">Dernières pépites ✨</h2>
            <a href="browse.php" class="btn btn-link text-primary text-decoration-none fw-bold">Voir tout <i class="bi bi-arrow-right"></i></a>
        </div>

        <div class="row g-3 g-md-4">
            <?php if (empty($latestItems)): ?>
                <div class="col-12 text-center py-5 opacity-50">Aucun article disponible pour le moment.</div>
            <?php else: ?>
                <?php foreach ($latestItems as $item): ?>
                <div class="col-6 col-md-4">
                    <div class="article-card shadow-sm">
                        <div class="img-container">
                            <img src="<?= $item['image_path'] ?: 'assets/img/placeholder.jpg' ?>" alt="<?= htmlspecialchars($item['titre']) ?>">
                        </div>
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <span class="small opacity-50 fw-bold"><?= htmlspecialchars($item['categorie'] ?? 'Divers') ?></span>
                            <h6 class="fw-bold my-2 text-truncate"><?= htmlspecialchars($item['titre']) ?></h6>
                            <div class="item-price mb-3"><?= $item['formatted_price'] ?></div>
                            <div class="pt-3 border-top mb-3 d-none d-sm-block">
                                <span class="d-block small fw-bold text-muted text-truncate"><?= htmlspecialchars($item['seller_name']) ?></span>
                            </div>
                            
                            <a href="view-article.php?id=<?= $item['id'] ?>" class="btn-view-more">
                                Voir plus <i class="bi bi-arrow-right"></i>
                            </a>

                            <a href="<?= $item['whatsapp_url'] ?>" target="_blank" class="btn-wa">
                                <i class="bi bi-whatsapp"></i> <span class="d-none d-sm-inline">Contacter</span>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4">
            <div class="col-md-8">
                <div class="bento-card bg-primary text-white">
                    <h2 class="fw-800 mb-4">Sécurité & Proximité</h2>
                    <p class="opacity-75 lead">Tous nos membres sont des étudiants vérifiés de l'UCAO. Pas de mauvaises surprises, les échanges se font directement sur le campus.</p>
                    <div class="mt-4">
                        <i class="bi bi-shield-check" style="font-size: 4rem;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bento-card">
                    <h5 class="fw-bold mb-3">0% Commission</h5>
                    <p class="small opacity-75 mb-4">La plateforme est totalement gratuite. L'argent de votre vente vous revient entièrement. Nous sommes là pour aider la communauté.</p>
                    <i class="bi bi-cash-stack text-primary" style="font-size: 3rem;"></i>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-5 mt-5" style="background: var(--bg-card); border-top: 1px solid var(--border);">
        <div class="container text-center">
            <h4 class="fw-800">UCAO<span class="text-primary">Connect</span></h4>
            <p class="small opacity-50 mb-4">La communauté d'échange n°1 de l'UCAO.</p>
            <div class="d-flex justify-content-center gap-3 mb-4">
                <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-facebook"></i></a>
                <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-instagram"></i></a>
            </div>
            <p class="x-small opacity-25">© 2026 UCAO Market. Designed for Students.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // SCROLL NAVBAR
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('mainNav');
            const burgerIcon = document.getElementById('burgerIcon');
            const userDisplay = document.getElementById('userDisplay');
            const loginText = document.getElementById('loginText');
            
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
                burgerIcon.classList.replace('text-white', 'text-dark');
                if(userDisplay) userDisplay.classList.replace('text-white', 'text-dark');
                if(loginText) loginText.classList.add('text-dark');
            } else {
                nav.classList.remove('scrolled');
                burgerIcon.classList.replace('text-dark', 'text-white');
                if(userDisplay) userDisplay.classList.replace('text-dark', 'text-white');
                if(loginText) loginText.classList.remove('text-dark');
            }
        });

        // THEME MANAGEMENT
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('change', function() {
            const mode = this.checked ? 'dark' : 'light';
            document.body.setAttribute('data-theme', mode);
            localStorage.setItem('theme', mode);
        });

        const savedTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
        themeToggle.checked = (savedTheme === 'dark');
    </script>
</body>
</html>