<?php
// dashboard/articles/edit.php
session_start();
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/upload.php';

// Vérification de la session utilisateur
requireAuth();

$db = new Database();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['first_name'] ?? 'Étudiant';
$errors = [];
$articleId = $_GET['id'] ?? '';

// --- FONCTIONS DE GESTION DES IMAGES ---

/**
 * Nettoie et formate l'URL de l'image pour le front-end
 */
function fixImageUrl($url) {
    if (empty($url)) return null;
    if (strpos($url, 'data:') === 0 || strpos($url, 'http') === 0) return $url;
    $url = str_replace('//', '/', $url);
    if (strpos($url, '/') !== 0) $url = '/' . $url;
    return $url;
}

/**
 * Vérifie si le fichier image existe physiquement sur le serveur
 */
function getValidImageUrl($url) {
    if (empty($url)) return null;
    $correctedUrl = fixImageUrl($url);
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $correctedUrl;
    return (file_exists($fullPath)) ? $correctedUrl : null;
}

// 1. VÉRIFICATION DE L'EXISTENCE DE L'ARTICLE
if (empty($articleId)) {
    header('Location: ../index.php?error=ID manquant');
    exit();
}

// Récupération de l'article (sécurité : l'article doit appartenir à l'utilisateur connecté)
$res = $db->select('articles', ['*'], ['id' => $articleId, 'user_id' => $userId]);
if (empty($res)) {
    header('Location: ../index.php?error=Article non trouvé');
    exit();
}
$article = $res[0];

// --- LOGIQUE DE MISE À JOUR (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = isset($_POST['prix']) ? (int)$_POST['prix'] : 0;
    $categorie = $_POST['categorie'] ?? '';
    $statut = $_POST['statut'] ?? 'disponible';

    // Par défaut, on garde les anciennes images
    $final_images_json = $article['image_url']; 

    // Gestion de l'upload de nouvelles images
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $uploaded_images = [];
        $files = $_FILES['images'];
        $count = min(count($files['name']), 5); // Limite à 5 photos

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_to_upload = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                $result = UploadManager::uploadImage($file_to_upload, $userId, 'articles');
                if ($result && $result['success']) {
                    $uploaded_images[] = $result['url'];
                }
            }
        }
        if (!empty($uploaded_images)) {
            $final_images_json = json_encode($uploaded_images);
        }
    }

    if (empty($titre)) $errors[] = "Le titre est obligatoire.";

    // Exécution de la mise à jour si aucune erreur
    if (empty($errors)) {
        $updateData = [
            'titre' => $titre,
            'description' => $description,
            'prix' => $prix,
            'categorie' => $categorie,
            'statut' => $statut,
            'image_url' => $final_images_json
        ];

        if ($db->update('articles', $updateData, ['id' => $articleId])) {
            header("Location: ../index.php?success=L'annonce a été mise à jour.");
            exit();
        } else {
            $errors[] = "Erreur lors de la mise à jour.";
        }
    }
}

// Préparation de l'affichage des images (décodage JSON)
$images_raw = json_decode($article['image_url'], true);
$currentImages = is_array($images_raw) ? $images_raw : [$article['image_url']];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier | <?= htmlspecialchars($article['titre']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* VARIABLES DE THÈME */
        :root { 
            --primary: #2563eb; 
            --bg-body: #f8fafc; 
            --bg-card: #ffffff; 
            --text-title: #0f172a; 
            --border: #e2e8f0; 
            --nav-bg: #ffffff; 
            --nav-link: #475569;
        }

        [data-theme="dark"] { 
            --bg-body: #020617; 
            --bg-card: #1e293b; 
            --text-title: #f8fafc; 
            --border: #334155; 
            --nav-bg: #1e293b;
            --nav-link: #cbd5e1; /* Couleur claire pour le menu en Dark Mode */
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-title); transition: 0.3s; }
        
        /* NAVBAR STYLE */
        .navbar { background: var(--nav-bg) !important; border-bottom: 1px solid var(--border); padding: 15px 0; }
        .nav-link { color: var(--nav-link) !important; font-weight: 600; transition: 0.2s; }
        .nav-link:hover { color: var(--primary) !important; }

        /* THEME SWITCH (Copie conforme Index) */
        .theme-switch { position: relative; width: 48px; height: 24px; }
        .theme-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked+.slider { background-color: var(--primary); }
        input:checked+.slider:before { transform: translateX(24px); }

        /* BENTO LAYOUT */
        .bento-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px; }
        .bento-item { background: var(--bg-card); border: 1px solid var(--border); border-radius: 28px; padding: 30px; }
        .col-span-2 { grid-column: span 2; }
        
        /* FORM ELEMENTS */
        .form-label { font-weight: 700; font-size: 0.85rem; text-transform: uppercase; color: var(--primary); letter-spacing: 0.5px; }
        .premium-input { background: var(--bg-body); border: 1px solid var(--border); border-radius: 16px; padding: 14px; color: var(--text-title); width: 100%; transition: 0.3s; }
        .premium-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
        .preview-img { width: 65px; height: 65px; object-fit: cover; border-radius: 12px; border: 1px solid var(--border); background: #eee; }

        @media (max-width: 991px) { 
            .bento-grid { grid-template-columns: 1fr; } 
            .col-span-2 { grid-column: span 1; } 
        }
    </style>
</head>
<body data-theme="light">

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand fw-800 text-decoration-none text-reset" href="../../index.php">UCAO<span class="text-primary"> Connect</span></a>
            
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

                    <a href="../index.php" class="btn btn-dark rounded-pill px-4 fw-bold d-none d-lg-block">Dashboard</a>
                    
                    <div class="px-3 py-2 rounded-pill bg-primary bg-opacity-10 text-primary fw-bold">
                        <?= htmlspecialchars($userName) ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h1 class="fw-800 display-6">Modifier votre <span class="text-primary">annonce.</span></h1>
                <p class="opacity-50">Mise à jour de : <?= htmlspecialchars($article['titre']) ?></p>
            </div>
        </div>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="bento-grid">
                <div class="bento-item col-span-2">
                    <div class="mb-4">
                        <label class="form-label">Titre de l'annonce</label>
                        <input type="text" name="titre" class="premium-input" value="<?= htmlspecialchars($article['titre']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Description détaillée</label>
                        <textarea name="description" class="premium-input" rows="8" required><?= htmlspecialchars($article['description']) ?></textarea>
                    </div>
                </div>

                <div class="bento-item d-flex flex-column gap-4">
                    <div>
                        <label class="form-label">Prix (CFA)</label>
                        <input type="number" name="prix" class="premium-input" value="<?= $article['prix'] ?>">
                    </div>
                    <div>
                        <label class="form-label">Catégorie</label>
                        <select name="categorie" class="premium-input">
                            <?php 
                            $cats = ['Électronique', 'Livres', 'Mode', 'Services', 'Meubles', 'Autre'];
                            foreach($cats as $c): ?>
                                <option value="<?= $c ?>" <?= $article['categorie'] == $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Statut de l'annonce</label>
                        <select name="statut" class="premium-input fw-bold">
                            <option value="disponible" class="text-success" <?= $article['statut'] == 'disponible' ? 'selected' : '' ?>> Disponible</option>
                            <option value="vendu" class="text-danger" <?= $article['statut'] == 'vendu' ? 'selected' : '' ?>> Vendu</option>
                            <option value="reserve" class="text-warning" <?= ($article['statut'] == 'reserve' || $article['statut'] == 'réservé') ? 'selected' : '' ?>> Réservé</option>
                        </select>
                    </div>
                </div>

                <div class="bento-item">
                    <label class="form-label">Photos de l'article</label>
                    <div class="text-center p-4 border rounded-4 mt-2" style="border-style: dashed !important; cursor: pointer;" onclick="document.getElementById('imgInput').click()">
                        <input type="file" name="images[]" id="imgInput" multiple hidden accept="image/*">
                        <i class="bi bi-camera-fill d-block mb-2 fs-2 opacity-25"></i>
                        <span class="small fw-bold text-primary">Remplacer les photos</span>
                    </div>
                    
                    <div class="mt-4">
                        <p class="small opacity-50 mb-2">Images enregistrées :</p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach($currentImages as $img): 
                                $validUrl = getValidImageUrl($img);
                                if($validUrl): ?>
                                    <img src="<?= $validUrl ?>" class="preview-img" title="Image actuelle">
                                <?php endif; 
                            endforeach; ?>
                        </div>
                    </div>
                    <div id="image-preview" class="d-flex flex-wrap gap-2 mt-3"></div>
                </div>

                <div class="bento-item col-span-2 d-flex align-items-center justify-content-between bg-dark text-white">
                    <div>
                        <h5 class="fw-bold mb-0 text-white">Valider les changements ?</h5>
                        <small class="opacity-50">L'annonce sera mise à jour sur le marché.</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-800">
                        Enregistrer <i class="bi bi-check-lg ms-2"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /**
         * GESTION DU THÈME SOMBRE/CLAIR
         * Récupère la préférence dans le localStorage pour rester synchrone avec l'index
         */
        const themeToggle = document.getElementById('themeToggle');
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', currentTheme);
        themeToggle.checked = (currentTheme === 'dark');

        themeToggle.addEventListener('change', function () {
            const theme = this.checked ? 'dark' : 'light';
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });

        /**
         * APERÇU DES NOUVELLES IMAGES AVANT ENVOI
         */
        document.getElementById('imgInput').addEventListener('change', function (e) {
            const container = document.getElementById('image-preview');
            container.innerHTML = '<div class="w-100 small text-primary fw-bold mb-1">Aperçu des nouvelles photos :</div>';
            Array.from(e.target.files).slice(0, 5).forEach(file => {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    img.className = 'preview-img border-primary shadow-sm';
                    container.appendChild(img);
                }
                reader.readAsDataURL(file);
            });
        });
    </script>
</body>
</html>