<?php
session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireAuth();

if (empty($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit();
}
$userId = $_SESSION['user_id'];

try {
    $db = new Database();
} catch (Exception $e) {
    die("Erreur de connexion à la base de données.");
}

// --- LOGIQUE DE SUPPRESSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_article'])) {
    $articleId = $_POST['article_id'] ?? '';
    if (!empty($articleId)) {
        $stmt = $db->connection->prepare("SELECT id, user_id, image_url FROM articles WHERE id = ?");
        $stmt->bind_param("s", $articleId);
        $stmt->execute();
        $article = $stmt->get_result()->fetch_assoc();

        if ($article && $article['user_id'] == $userId) {
            $images = json_decode($article['image_url'], true);
            if (is_array($images)) {
                foreach ($images as $imgUrl) {
                    $imagePath = $_SERVER['DOCUMENT_ROOT'] . parse_url($imgUrl, PHP_URL_PATH);
                    if (file_exists($imagePath)) unlink($imagePath);
                }
            }
            $deleteStmt = $db->connection->prepare("DELETE FROM articles WHERE id = ?");
            $deleteStmt->bind_param("s", $articleId);
            $deleteStmt->execute();
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- FONCTIONS URL & RÉCUPÉRATION DONNÉES ---
function fixImageUrl($url) {
    if (empty($url)) return null;
    if (strpos($url, 'data:') === 0 || strpos($url, 'http') === 0) return $url;
    $url = str_replace(['//', '\\'], '/', $url);
    if (strpos($url, '/') !== 0) $url = '/' . $url;
    return $url;
}

function getValidImageUrl($url) {
    if (empty($url)) return null;
    $correctedUrl = fixImageUrl($url);
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $correctedUrl;
    return (file_exists($fullPath)) ? $correctedUrl : null;
}

$userData = $db->select('users', ['*'], ['id' => $userId])[0] ?? [];
$userName = $userData['first_name'] ?? 'Étudiant';
$userData['photo_url'] = getValidImageUrl($userData['photo_url'] ?? null);

$articles = $db->select('articles', ['*'], ['user_id' => $userId], null, null, 'created_at DESC');
foreach ($articles as &$art) {
    $images = json_decode($art['image_url'], true);
    $displayUrl = (is_array($images) && !empty($images)) ? $images[0] : $art['image_url'];
    $art['image_url'] = getValidImageUrl($displayUrl);
}

$total_articles = count($articles);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCAO Market | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb; --bg-body: #f8fafc; --bg-card: #ffffff;
            --text-title: #0f172a; --border: #e2e8f0; --nav-bg: #ffffff;
        }
        [data-theme="dark"] {
            --bg-body: #020617; --bg-card: #1e293b; --text-title: #f8fafc;
            --border: #334155; --nav-bg: #1e293b;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-title); transition: all 0.3s ease; }
        
        .navbar { background: var(--nav-bg) !important; border-bottom: 1px solid var(--border); padding: 15px 0; }
        .navbar-brand { font-weight: 800; }
        .nav-link { font-weight: 600; color: var(--text-title) !important; opacity: 0.7; }
        .nav-link:hover { opacity: 1; color: var(--primary) !important; }

        .theme-switch { position: relative; width: 48px; height: 24px; }
        .theme-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked+.slider { background-color: var(--primary); }
        input:checked+.slider:before { transform: translateX(24px); }

        .bento-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 24px; padding: 25px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.02); height: 100%; }
        .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 15px; }

        .article-row { 
            display: flex; align-items: center; padding: 12px; border-radius: 16px; 
            transition: 0.2s; border: 1px solid transparent; width: 100%; 
        }
        .article-row:hover { background: var(--bg-body); border-color: var(--border); }
        
        .img-preview { width: 50px; height: 50px; border-radius: 10px; object-fit: cover; margin-right: 15px; flex-shrink: 0; }
        
        .article-info { 
            flex: 1; 
            min-width: 0; 
            margin-right: 15px; 
        }
        .article-title { 
            font-weight: 700; margin-bottom: 0; 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
        }
        .article-price { 
            font-weight: 800; color: var(--primary); 
            display: inline-block; white-space: nowrap; font-size: 0.95rem;
        }

        .btn-logout-icon { color: #ef4444; font-size: 1.2rem; transition: 0.2s; }
        .btn-logout-icon:hover { opacity: 0.7; transform: scale(1.1); }

        @media (max-width: 768px) {
            .article-row { flex-wrap: wrap; }
            .article-info { flex-basis: calc(100% - 70px); margin-right: 0; }
            .me-md-3 { width: 100%; margin: 8px 0 4px 65px; order: 3; }
            .ms-auto { margin-left: 65px !important; margin-top: 5px; order: 4; width: 100%; display: flex; }
            .article-price { display: block; margin-top: 2px; }
        }

        .footer-simple { margin-top: 80px; padding: 40px 0; border-top: 1px solid var(--border); opacity: 0.6; font-size: 0.85rem; }
        .quick-link-card { background: var(--primary); color: white; border-radius: 20px; padding: 20px; display: flex; align-items: center; justify-content: space-between; text-decoration: none; transition: 0.3s; border: none; }
        .quick-link-card:hover { transform: translateY(-5px); color: white; box-shadow: 0 12px 24px rgba(37, 99, 235, 0.25); }

        .badge-status { padding: 4px 10px; border-radius: 100px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-disponible { background: #dcfce7; color: #166534; }
        .status-vendu { background: #fee2e2; color: #991b1b; }
        .status-reserve { background: #fef9c3; color: #854d0e; }
        .status-default { background: #f1f5f9; color: #475569; }
        
        .btn-action { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: none; background: #f1f5f9; color: #64748b; transition: 0.2s; text-decoration: none; }
        .btn-action:hover { background: var(--primary); color: white; }
        .btn-action.edit:hover { background: #f59e0b; color: white; }
    </style>
</head>

<body data-theme="light">

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand fw-800" href="../../index.php">UCAO<span class="text-primary"> Connect</span></a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <i class="bi bi-list fs-1"></i>
            </button>
            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav mx-auto gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="../../index.php">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link" href="../../browse.php">Annonces</a></li>
                    <li class="nav-item"><a class="nav-link" href="../../about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="../../contact.php">Contact</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2 me-2">
                        <i class="bi bi-sun-fill text-warning"></i>
                        <label class="theme-switch">
                            <input type="checkbox" id="themeToggle">
                            <span class="slider"></span>
                        </label>
                        <i class="bi bi-moon-stars-fill text-primary"></i>
                    </div>
                    <div class="px-3 py-2 rounded-pill bg-primary bg-opacity-10 text-primary fw-bold small d-none d-sm-block">
                        <?= htmlspecialchars($userName) ?>
                    </div>
                    <a href="/auth/logout.php" class="btn-logout-icon" title="Déconnexion">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row mb-5 align-items-center">
            <div class="col-md-7">
                <h1 class="fw-800">Mon Espace <span class="text-primary">UCAO Connect</span></h1>
                <p class="opacity-50">Bienvenue, gérez vos ventes et suivez vos performances.</p>
            </div>
            <div class="col-md-5 text-md-end">
                <a href="articles/add.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                    <i class="bi bi-plus-lg me-2"></i> Publier une annonce
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-3 col-6"> 
                <div class="bento-card text-center text-md-start">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto mx-md-0"><i class="bi bi-box"></i></div>
                    <h3 class="fw-800 mb-0"><?= $total_articles ?></h3>
                    <p class="small opacity-50 mb-0">Annonces</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bento-card text-center text-md-start">
                    <div class="stat-icon bg-success bg-opacity-10 text-success mx-auto mx-md-0"><i class="bi bi-eye"></i></div>
                    <h3 class="fw-800 mb-0">1,248</h3>
                    <p class="small opacity-50 mb-0">Vues</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bento-card text-center text-md-start">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning mx-auto mx-md-0"><i class="bi bi-chat-dots"></i></div>
                    <h3 class="fw-800 mb-0">12</h3>
                    <p class="small opacity-50 mb-0">Messages</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bento-card text-center text-md-start">
                    <div class="stat-icon bg-info bg-opacity-10 text-info mx-auto mx-md-0"><i class="bi bi-wallet2"></i></div>
                    <h3 class="fw-800 mb-0">45k</h3>
                    <p class="small opacity-50 mb-0">CFA</p>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="bento-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-800 mb-0">Mes Publications</h5>
                        <a href="../../browse.php" class="btn btn-light btn-sm rounded-pill px-3 fw-bold">Tout voir</a>
                    </div>

                    <?php if (empty($articles)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-cloud-upload fs-1 opacity-25"></i>
                            <p class="mt-3 opacity-50">Aucune annonce pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($articles as $article): 
                            $status_val = strtolower($article['statut'] ?? 'disponible');
                            $status_class = 'status-default';
                            
                            if($status_val == 'disponible') $status_class = 'status-disponible';
                            elseif($status_val == 'vendu') $status_class = 'status-vendu';
                            elseif($status_val == 'reserve' || $status_val == 'réservé') $status_class = 'status-reserve';
                        ?>
                            <div class="article-row">
                                <a href="/view-article.php?id=<?= $article['id'] ?>">
                                    <img src="<?= $article['image_url'] ?? '/assets/img/placeholder.jpg' ?>" class="img-preview">
                                </a>

                                <div class="article-info">
                                    <a href="/view-article.php?id=<?= $article['id'] ?>" class="text-decoration-none text-reset">
                                        <h6 class="article-title" title="<?= htmlspecialchars($article['titre']) ?>">
                                            <?= htmlspecialchars($article['titre']) ?>
                                        </h6>
                                    </a>
                                    <span class="article-price"><?= number_format($article['prix'], 0, '', ' ') ?> CFA</span>
                                </div>

                                <div class="me-md-3">
                                    <span class="badge-status <?= $status_class ?>"><?= htmlspecialchars($article['statut'] ?? 'disponible') ?></span>
                                </div>

                                <div class="d-flex gap-2 ms-auto">
                                    <a href="/view-article.php?id=<?= $article['id'] ?>" class="btn-action" title="Voir l'annonce">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="articles/edit.php?id=<?= $article['id'] ?>" class="btn-action edit" title="Modifier">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button class="btn-action text-danger" onclick="confirmDelete('<?= $article['id'] ?>', '<?= addslashes($article['titre']) ?>')" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="bento-card bg-dark text-white">
                            <h6 class="fw-800 mb-3"><i class="bi bi-lightning-fill text-warning me-2"></i>Conseil Vendeur</h6>
                            <p class="small opacity-75">Mettez à jour vos statuts dès qu'un article est réservé pour instaurer la confiance.</p>
                            <button class="btn btn-outline-light btn-sm rounded-pill w-100 mt-2">En savoir plus</button>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="bento-card">
                            <h6 class="fw-800 mb-4">Activité Récente</h6>
                            <div class="d-flex gap-3 mb-0">
                                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle flex-shrink-0" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-heart-fill small"></i>
                                </div>
                                <div>
                                    <p class="small fw-bold mb-0">Visibilité en hausse</p>
                                    <p class="x-small opacity-50 mb-0">Vos annonces ont reçu 15% de clics en plus cette semaine.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <a href="../../browse.php" class="quick-link-card">
                            <div>
                                <h6 class="fw-700 mb-0">Explorer le Market</h6>
                                <p class="small mb-0 opacity-75">Découvrez les pépites</p>
                            </div>
                            <i class="bi bi-arrow-right-short fs-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <footer class="footer-simple text-center">
            <p class="mb-0">UCAO Connect &copy; 2026 - Plateforme de commerce étudiant</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const themeToggle = document.getElementById('themeToggle');
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', currentTheme);
        themeToggle.checked = (currentTheme === 'dark');

        themeToggle.addEventListener('change', function () {
            const theme = this.checked ? 'dark' : 'light';
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });

        function confirmDelete(id, titre) {
            if(confirm(`Voulez-vous vraiment supprimer "${titre}" ?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="article_id" value="${id}"><input type="hidden" name="delete_article" value="1">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>