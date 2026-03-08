<?php
/**
 * UCAO Market - Vue Détail Article
 * Style : Bento Premium Coherent
 */

session_start();

// État de connexion
$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $_SESSION['first_name'] ?? '';

// 📥 RÉCUPÉRATION ID ARTICLE
$articleId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($articleId)) {
    header('Location: browse.php');
    exit();
}

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

// 🔍 RÉCUPÉRATION DES DONNÉES
$article = null;
$seller = null;
$images = [];

if ($databaseLoaded) {
    $res = $db->select('articles', ['*'], ['id' => $articleId]);
    if (!empty($res)) {
        $article = $res[0];
        $resUser = $db->select('users', ['*'], ['id' => $article['user_id']]);
        $seller = $resUser[0] ?? null;

        $rawUrl = $article['image_url'];
        $decoded = json_decode($rawUrl, true);
        if (is_array($decoded) && !empty($decoded)) {
            $images = $decoded;
        } elseif (!empty($rawUrl)) {
            $images = [$rawUrl];
        }
    }
}

if (!$article) { die("Article non trouvé."); }

// Formatage WhatsApp
$phoneNumber = preg_replace('/[^0-9]/', '', $seller['phone'] ?? '');
$sellerName = trim(($seller['first_name'] ?? '') . ' ' . ($seller['last_name'] ?? '')) ?: 'Étudiant';
$message = "Salut " . $sellerName . " ! Je suis intéressé par ton annonce : \"" . $article['titre'] . "\" sur UCAO Market.";
$whatsappUrl = "https://wa.me/{$phoneNumber}?text=" . urlencode($message);

function getDisplayPath($url) {
    if (strpos($url, 'http') === 0) return $url;
    $url = str_replace(['//', '\\'], '/', $url);
    if (strpos($url, '/') !== 0) $url = '/' . $url;
    return $url;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($article['titre']) ?> | UCAO Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb; --bg-body: #f8fafc; --bg-card: #ffffff;
            --text-title: #0f172a; --text-body: #334155; --border: #e2e8f0; --nav-bg: #ffffff;
        }
        [data-theme="dark"] {
            --bg-body: #020617; --bg-card: #1e293b; --text-title: #f8fafc;
            --text-body: #cbd5e1; --border: #334155; --nav-bg: #1e293b;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-body); transition: 0.3s; }

        /* --- NAVBAR RESPONSIVE --- */
        .navbar { background: var(--nav-bg) !important; border-bottom: 1px solid var(--border); padding: 15px 0; }
        .navbar-brand { font-weight: 800; color: var(--text-title) !important; }
        
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: var(--nav-bg);
                padding: 20px;
                border-radius: 20px;
                margin-top: 15px;
                border: 1px solid var(--border);
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            .nav-link-custom { padding: 10px 0; display: block; }
        }

        /* --- STYLE BENTO --- */
        .bento-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 30px;
            transition: 0.3s ease;
        }

        /* Galerie */
        .main-img-container { 
            height: 500px; 
            border-radius: 24px; 
            overflow: hidden; 
            background: var(--bg-card); 
            border: 1px solid var(--border); 
        }
        .main-img-container img { width: 100%; height: 100%; object-fit: contain; }
        
        .thumb { 
            width: 80px; height: 80px; border-radius: 16px; 
            cursor: pointer; object-fit: cover; border: 2px solid transparent; 
            transition: 0.2s; background: var(--bg-card);
        }
        .thumb.active { border-color: var(--primary); transform: scale(0.95); }

        /* Infos */
        .price-tag { font-size: 2rem; font-weight: 800; color: var(--primary); }
        .info-label { font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; opacity: 0.6; }

        /* Boutons & Thème */
        .btn-wa { 
            background: #25D366; color: white !important; 
            padding: 16px; border-radius: 18px; font-weight: 700; 
            display: flex; align-items: center; justify-content: center; gap: 10px; 
            text-decoration: none; transition: 0.3s;
        }
        .btn-wa:hover { background: #1eb954; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(37, 211, 102, 0.2); }

        .switch { width: 44px; height: 22px; position: relative; display: inline-block; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 34px; transition: .4s; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .4s; }
        input:checked + .slider { background: var(--primary); }
        input:checked + .slider:before { transform: translateX(22px); }

        .stat-box-mini {
            background: rgba(37, 99, 235, 0.05);
            border-radius: 18px;
            padding: 15px;
            text-align: center;
            border: 1px solid var(--border);
        }
    </style>
</head>
<body data-theme="light">

    <nav class="navbar sticky-top navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">UCAO<span class="text-primary"> Connect</span></a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <i class="bi bi-list fs-1" style="color: var(--text-title);"></i>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <div class="ms-auto d-flex flex-column flex-lg-row align-items-lg-center gap-4 py-3 py-lg-0">
                    <div class="d-flex align-items-center gap-3">
                        <label class="switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
                        <span class="small fw-bold d-lg-none" style="color: var(--text-body);">Mode Sombre</span>
                    </div>
                    <a href="index.php" class="nav-link-custom small fw-bold text-decoration-none" style="color: var(--text-body);">Accueil</a>
                    <a href="browse.php" class="nav-link-custom small fw-bold text-decoration-none" style="color: var(--text-body);">Annonces</a>
                    <a href="about.php" class="nav-link-custom small fw-bold text-decoration-none" style="color: var(--text-body);">A propos</a>
                    <a href="contact.php" class="nav-link-custom small fw-bold text-decoration-none" style="color: var(--text-body);">Contact</a>
                    <?php if ($isLoggedIn): ?>
                        <a href="../dashboard/index.php" class="btn btn-primary rounded-pill btn-sm px-4 fw-bold">Dashboard</a>
                    <?php else: ?>
                        <a href="../auth/login.php" class="btn btn-outline-primary rounded-pill btn-sm px-4 fw-bold">Connexion</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-5 mt-lg-4">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="bento-card p-2">
                    <div class="main-img-container">
                        <img id="mainDisplay" src="<?= getDisplayPath($images[0] ?? 'assets/img/placeholder.jpg') ?>" alt="Produit">
                    </div>
                </div>
                
                <?php if(count($images) > 1): ?>
                <div class="d-flex gap-3 mt-3 overflow-x-auto pb-2">
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?= getDisplayPath($img) ?>" 
                             class="thumb <?= $idx === 0 ? 'active' : '' ?>" 
                             onclick="updatePreview('<?= getDisplayPath($img) ?>', this)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="row g-3 mt-3">
                    <div class="col-4">
                        <div class="stat-box-mini">
                            <i class="bi bi-calendar3 text-primary d-block mb-1"></i>
                            <span class="info-label">Publié le</span>
                            <div class="fw-bold small"><?= date('d/m/y', strtotime($article['created_at'])) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box-mini">
                            <i class="bi bi-geo-alt text-primary d-block mb-1"></i>
                            <span class="info-label">Lieu</span>
                            <div class="fw-bold small">UCAO </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box-mini">
                            <i class="bi bi-shield-check text-primary d-block mb-1"></i>
                            <span class="info-label">État</span>
                            <div class="fw-bold small">Vérifié</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="bento-card h-100 d-flex flex-column">
                    <div class="mb-2">
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-bold">
                            <?= htmlspecialchars($article['categorie']) ?>
                        </span>
                    </div>
                    
                    <h1 class="display-6 fw-800 text-title mb-3"><?= htmlspecialchars($article['titre']) ?></h1>
                    
                    <div class="price-tag mb-4">
                        <?= $article['prix'] > 0 ? number_format($article['prix'], 0, '', ' ') . ' <small style="font-size:1rem">FCFA</small>' : 'Gratuit' ?>
                    </div>

                    <div class="mb-5">
                        <span class="info-label d-block mb-2">Description</span>
                        <p class="text-body lh-lg" style="white-space: pre-line;"><?= htmlspecialchars($article['description']) ?></p>
                    </div>

                    <div class="mt-auto">
                        <div class="d-flex align-items-center gap-3 p-3 rounded-4 border mb-4 bg-light bg-opacity-50">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                <?= strtoupper(substr($sellerName, 0, 1)) ?>
                            </div>
                            <div>
                                <div class="info-label" style="font-size: 0.65rem;">Proposé par</div>
                                <div class="fw-bold text-title"><?= htmlspecialchars($sellerName) ?></div>
                            </div>
                            <div class="ms-auto">
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill">Étudiant UCAO</span>
                            </div>
                        </div>

                        <a href="<?= $whatsappUrl ?>" target="_blank" class="btn-wa">
                            <i class="bi bi-whatsapp fs-5"></i>
                            Contacter le vendeur
                        </a>
                        
                        <p class="text-center mt-3 small opacity-50">
                            <i class="bi bi-info-circle me-1"></i> 
                            Paiement en main propre uniquement.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Galerie Switcher
        function updatePreview(url, el) {
            document.getElementById('mainDisplay').src = url;
            document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
        }

        // Theme Switcher Logic
        const toggle = document.getElementById('themeToggle');
        if(localStorage.getItem('theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            toggle.checked = true;
        }
        toggle.addEventListener('change', function() {
            const theme = this.checked ? 'dark' : 'light';
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });
    </script>
</body>
</html>