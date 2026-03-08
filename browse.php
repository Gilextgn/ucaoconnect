<?php
/**
 * UCAO Market - Explorer (Browse) Premium Edition
 */
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $_SESSION['first_name'] ?? '';

// 📂 CHARGEMENT BASE DE DONNÉES
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
if (!$databaseLoaded) { die("Erreur de connexion."); }

// 🔢 PAGINATION
$articlesParPage = 8;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $articlesParPage;

// 📥 FILTRES
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$catSelected = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$whereClauses = ["a.statut = 'disponible'"];
$queryParams = [];
$queryTypes = "";

if (!empty($search)) {
    $whereClauses[] = "(a.titre LIKE ? OR a.description LIKE ?)";
    $st = "%$search%";
    $queryParams[] = $st; $queryParams[] = $st;
    $queryTypes .= "ss";
}
if (!empty($catSelected)) {
    $whereClauses[] = "a.categorie = ?";
    $queryParams[] = $catSelected;
    $queryTypes .= "s";
}

$whereSql = " WHERE " . implode(" AND ", $whereClauses);

// TOTAL POUR PAGINATION
$countSql = "SELECT COUNT(*) as total FROM articles a " . $whereSql;
$stmtCount = $db->connection->prepare($countSql);
if (!empty($queryTypes)) { $stmtCount->bind_param($queryTypes, ...$queryParams); }
$stmtCount->execute();
$totalArticles = $stmtCount->get_result()->fetch_assoc()['total'];
$totalNombrePages = ceil($totalArticles / $articlesParPage);

// RÉCUPÉRATION
$sql = "SELECT a.*, u.first_name FROM articles a JOIN users u ON a.user_id = u.id" . $whereSql;
if ($sort === 'price_asc') $sql .= " ORDER BY a.prix ASC";
elseif ($sort === 'price_desc') $sql .= " ORDER BY a.prix DESC";
else $sql .= " ORDER BY a.created_at DESC";

$sql .= " LIMIT ? OFFSET ?";
$queryParams[] = $articlesParPage; $queryParams[] = $offset;
$queryTypes .= "ii";

$stmt = $db->connection->prepare($sql);
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = [
    'Électronique' => 'bi-laptop', 
    'Mode' => 'bi-smartwatch', 
    'Fournitures' => 'bi-pencil-fill', 
    'Services' => 'bi-tools', 
    'Livres'     => 'bi-book-half',
    'Logement' => 'bi-house-heart', 
    'Autre' => 'bi-grid'
];

function getFirstImage($rawUrl) {
    $decoded = json_decode($rawUrl, true);
    $url = (is_array($decoded) && !empty($decoded)) ? $decoded[0] : $rawUrl;
    if (empty($url)) return 'assets/img/placeholder.jpg';
    if (strpos($url, 'http') === 0) return $url;
    return '/' . ltrim(str_replace(['//', '\\'], '/', $url), '/');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorer | UCAO Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb; --primary-hover: #1d4ed8;
            --bg-body: #f8fafc; --bg-card: #ffffff;
            --text-title: #0f172a; --text-body: #475569; --border: #e2e8f0;
            --card-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        [data-theme="dark"] {
            --bg-body: #020617; --bg-card: #0f172a; 
            --text-title: #f8fafc; --text-body: #94a3b8; --border: #1e293b;
            --card-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-body); padding-top: 100px; transition: 0.3s; }

        /* NAVBAR */
        .navbar { background: #0f172a !important; border-bottom: 1px solid rgba(255,255,255,0.1); padding: 15px 0; }
        .navbar-brand { font-weight: 800; font-size: 1.5rem; color: #fff !important; }
        
        @media (max-width: 991.98px) {
            .navbar-collapse { background: #0f172a; padding: 20px; border-radius: 20px; margin-top: 15px; border: 1px solid rgba(255,255,255,0.1); }
        }

        /* --- FIX DARK MODE FILTERS --- */
        .filter-header {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 10px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
        }
        .filter-header .form-control, 
        .filter-header .form-select {
            color: var(--text-title) !important;
            font-weight: 600;
        }
        .filter-header select option {
            background: var(--bg-card);
            color: var(--text-title);
        }
        
        .search-control { border: none; background: transparent; padding-left: 15px; }
        .search-control:focus { box-shadow: none; background: transparent; }
        
        .divider-v { width: 1px; height: 30px; background: var(--border); margin: 0 15px; }

        /* --- CARDS PREMIUM STYLING --- */
        .item-card { 
            background: var(--bg-card); border-radius: 22px; border: 1px solid var(--border);
            overflow: hidden; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            height: 100%; display: flex; flex-direction: column;
            box-shadow: var(--card-shadow);
        }
        .item-card:hover { 
            transform: translateY(-12px); 
            border-color: var(--primary);
            box-shadow: 0 25px 50px -12px rgba(37, 99, 235, 0.2);
        }
        .img-wrapper { position: relative; height: 240px; overflow: hidden; }
        .img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: 0.6s; }
        .item-card:hover .img-wrapper img { transform: scale(1.1); }
        
        .img-wrapper::after {
            content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 40%;
            background: linear-gradient(to top, rgba(0,0,0,0.4), transparent);
            opacity: 0; transition: 0.3s;
        }
        .item-card:hover .img-wrapper::after { opacity: 1; }

        .price-tag { 
            position: absolute; bottom: 15px; left: 15px; background: var(--primary); 
            color: #fff; font-weight: 800; padding: 6px 16px; border-radius: 12px;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
            z-index: 2;
        }

        .view-more-link {
            font-weight: 700; color: var(--primary); text-decoration: none; font-size: 0.85rem;
            display: flex; align-items: center; gap: 5px; transition: 0.2s;
        }
        .view-more-link:hover { gap: 10px; color: var(--primary-hover); }

        /* --- STATS SECTION (RESPONSIVE FIX) --- */
        .stats-section {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 35px; padding: 60px 40px; margin: 80px 0; color: white;
            position: relative; overflow: hidden;
        }
        .stat-number { font-size: 2.5rem; font-weight: 800; display: block; color: var(--primary); }
        .stat-label { font-size: 0.75rem; font-weight: 600; opacity: 0.6; text-transform: uppercase; letter-spacing: 1.5px; }

        @media (max-width: 768px) {
            .stats-section { padding: 40px 20px; border-radius: 25px; }
            .stat-number { font-size: 1.8rem; }
            .border-md-end-custom { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 20px; }
        }
        @media (min-width: 768px) {
            .border-md-end-custom { border-right: 1px solid rgba(255,255,255,0.1); }
        }

        /* --- PAGINATION --- */
        .page-link { background: var(--bg-card); color: var(--text-body); border: 1px solid var(--border) !important; padding: 10px 18px; }
        .page-item.active .page-link { background: var(--primary); border-color: var(--primary) !important; color: white; }

        /* --- THEME SWITCH --- */
        .theme-switch { position: relative; width: 48px; height: 24px; cursor: pointer; }
        .theme-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #475569; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background: var(--primary); }
        input:checked + .slider:before { transform: translateX(24px); }
    </style>
</head>
<body data-theme="light">

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">UCAO<span style="color:var(--primary)"> Connect</span></a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <i class="bi bi-list text-white fs-1"></i>
            </button>

            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav mx-auto gap-lg-3">
                    <li class="nav-item"><a class="nav-link text-white opacity-75" href="index.php">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="browse.php">Annonces</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="about.php">A propos</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="contact.php">Contact</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-4">
                    <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
                    <?php if ($isLoggedIn): ?>
                        <a href="dashboard/index.php" class="btn btn-primary rounded-pill px-4 fw-bold">Dashboard</a>
                    <?php else: ?>
                        <a href="auth/login.php" class="btn btn-outline-light rounded-pill px-4 fw-bold border-0">Connexion</a>
                        <a href="auth/register.php" class="btn btn-primary rounded-pill px-4 fw-bold">S'inscrire</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container">
        
        <div class="filter-header">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5 d-flex align-items-center">
                    <i class="bi bi-search ms-3 text-primary"></i>
                    <input type="text" name="q" class="form-control search-control" placeholder="Que recherchez-vous ?" value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="divider-v d-none d-md-block"></div>

                <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-grid ms-2 text-muted"></i>
                        <select name="category" class="form-select border-0 bg-transparent shadow-none" onchange="this.form.submit()">
                            <option value="">Toutes catégories</option>
                            <?php foreach($categories as $name => $icon): ?>
                                <option value="<?= $name ?>" <?= $catSelected == $name ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="divider-v d-none d-md-block"></div>

                <div class="col-md-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-sort-down ms-2 text-muted"></i>
                        <select name="sort" class="form-select border-0 bg-transparent shadow-none" onchange="this.form.submit()">
                            <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Plus récent</option>
                            <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                            <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-1 text-end pe-3">
                    <button type="submit" class="btn btn-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:42px; height:42px;">
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="row g-4">
            <?php if (empty($articles)): ?>
                <div class="col-12 text-center py-5">
                    <div class="display-1 opacity-10"><i class="bi bi-emoji-frown"></i></div>
                    <p class="h5 fw-bold mt-3">Aucun article ne correspond à votre recherche.</p>
                    <a href="browse.php" class="btn btn-primary rounded-pill mt-3">Voir toutes les annonces</a>
                </div>
            <?php else: ?>
                <?php foreach($articles as $art): ?>
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <div class="item-card">
                        <div class="img-wrapper">
                            <img src="<?= getFirstImage($art['image_url']) ?>" alt="Article">
                            <div class="price-tag"><?= number_format($art['prix'], 0, '', ' ') ?> F</div>
                        </div>
                        <div class="p-4 flex-grow-1 d-flex flex-column">
                            <div class="mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3" style="font-size: 0.7rem;"><?= strtoupper($art['categorie']) ?></span>
                            </div>
                            <h6 class="fw-800 text-title mb-3" style="height: 44px; overflow: hidden; line-height: 1.4;"><?= htmlspecialchars($art['titre']) ?></h6>
                            
                            <div class="mt-auto d-flex justify-content-between align-items-center pt-3 border-top">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                        <i class="bi bi-person text-primary" style="font-size: 0.8rem;"></i>
                                    </div>
                                    <span class="small fw-600"><?= $art['first_name'] ?></span>
                                </div>
                                <a href="view-article.php?id=<?= $art['id'] ?>" class="view-more-link">
                                    Détails <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalNombrePages > 1): ?>
        <nav class="mt-5 pb-5">
            <ul class="pagination justify-content-center gap-2">
                <?php for ($i = 1; $i <= $totalNombrePages; $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                        <a class="page-link rounded-3 fw-bold shadow-sm" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&category=<?= urlencode($catSelected) ?>&sort=<?= $sort ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <section class="stats-section">
            <div class="row align-items-center text-center text-md-start">
                <div class="col-md-4 border-md-end-custom mb-4 mb-md-0">
                    <h2 class="fw-800 mb-3" style="font-size: 1.5rem;">Le marché n°1 de l'UCAO</h2>
                    <p class="opacity-75 small">Trouvez tout ce dont vous avez besoin pour vos études en quelques clics.</p>
                </div>
                <div class="col-md-8">
                    <div class="row g-3">
                        <div class="col-4">
                            <span class="stat-number">2.5k</span>
                            <span class="stat-label">Annonces</span>
                        </div>
                        <div class="col-4">
                            <span class="stat-number">98%</span>
                            <span class="stat-label">Confiance</span>
                        </div>
                        <div class="col-4">
                            <span class="stat-number">UCAO</span>
                            <span class="stat-label">Connect</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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