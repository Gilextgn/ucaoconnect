<?php
// dashboard/articles/add.php
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/upload.php'; // Contient la classe UploadManager

requireAuth();

$db = new Database();
$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

// Récupération infos étudiant
$userName = $_SESSION['first_name'] ?? 'Étudiant';

// --- LOGIQUE PHP (Multi-upload JSON & Correction Erreur) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = isset($_POST['prix']) ? (int)$_POST['prix'] : 0; 
    $categorie = $_POST['categorie'] ?? '';

    $uploaded_images = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $files = $_FILES['images'];
        $count = min(count($files['name']), 5);

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
                } else {
                    $errors[] = "Erreur image " . ($i + 1) . " : " . ($result['message'] ?? 'inconnue');
                }
            }
        }
    }

    if (empty($titre)) $errors[] = "Le titre est obligatoire.";

    if (empty($errors)) {
        // --- GÉNÉRATION DE L'UUID ---
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $data = [
            'id' => $uuid,
            'user_id' => $userId,
            'titre' => $titre,
            'description' => $description,
            'prix' => (int)$prix,
            'categorie' => $categorie,
            'image_url' => json_encode($uploaded_images),
            'statut' => 'disponible',
            'created_at' => date('Y-m-d H:i:s'),
            'is_deleted' => 0
        ];

        $conn = $db->getConnection();
        $sql = "INSERT INTO articles (id, user_id, titre, description, prix, categorie, image_url, statut, created_at, is_deleted) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssssissssi", 
                $data['id'], $data['user_id'], $data['titre'], $data['description'], 
                $data['prix'], $data['categorie'], $data['image_url'], 
                $data['statut'], $data['created_at'], $data['is_deleted']
            );
            
            if ($stmt->execute()) {
                // --- REDIRECTION SI RÉUSSITE ---
                header("Location: ../index.php?success=1");
                exit();
            } else {
                $errors[] = "Erreur lors de la publication : " . $stmt->error;
            }
        } else {
            $errors[] = "Erreur de préparation : " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCAO Market | Ajouter une annonce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-title: #0f172a;
            --border: #e2e8f0;
            --nav-bg: #ffffff;
        }

        [data-theme="dark"] {
            --bg-body: #020617;
            --bg-card: #1e293b;
            --text-title: #f8fafc;
            --border: #334155;
            --nav-bg: #1e293b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-title);
            transition: all 0.3s ease;
        }

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

        .bento-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px; }
        .bento-item { background: var(--bg-card); border: 1px solid var(--border); border-radius: 28px; padding: 30px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.02); }
        .col-span-2 { grid-column: span 2; }

        .form-label { font-weight: 700; font-size: 0.85rem; text-transform: uppercase; color: var(--primary); letter-spacing: 0.5px; }
        .premium-input { background: var(--bg-body); border: 1px solid var(--border); border-radius: 16px; padding: 14px; color: var(--text-title); width: 100%; transition: 0.3s; }
        .premium-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }

        .preview-img { width: 85px; height: 85px; object-fit: cover; border-radius: 14px; border: 2px solid var(--border); }

        @media (max-width: 991px) {
            .bento-grid { grid-template-columns: 1fr; }
            .col-span-2 { grid-column: span 1; }
        }
    </style>
</head>

<body data-theme="light">

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand fw-800" href="../../index.php">UCAO<span class="text-primary">.Market</span></a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <i class="bi bi-list fs-1"></i>
            </button>
            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav mx-auto gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="../../index.php">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link" href="../../public/browse.php">Annonces</a></li>
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
            <div class="col-12">
                <h1 class="fw-800 display-6">Vendre un <span class="text-primary">produit.</span></h1>
                <p class="opacity-50">Remplissez les détails ci-dessous pour publier votre annonce.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 p-3 mb-4" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="bento-grid">
                <div class="bento-item col-span-2">
                    <div class="mb-4">
                        <label class="form-label">Nom de l'article</label>
                        <input type="text" name="titre" class="premium-input" placeholder="Que vendez-vous ?" required>
                    </div>
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" class="premium-input" rows="6" placeholder="État, caractéristiques, raison de la vente..." required></textarea>
                    </div>
                </div>

                <div class="bento-item d-flex flex-column justify-content-between">
                    <div>
                        <label class="form-label">Prix de vente</label>
                        <div class="input-group">
                            <input type="number" name="prix" class="premium-input" placeholder="0">
                            <span class="input-group-text bg-transparent border-0 fw-bold">CFA</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="form-label">Catégorie</label>
                        <select name="categorie" class="premium-input" required>
                            <option value="Électronique">Électronique</option>
                            <option value="Livres">Livres / Cours</option>
                            <option value="Mode">Mode & Vêtements</option>
                            <option value="Services">Services</option>
                            <option value="Logement">Logement</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                </div>

                <div class="bento-item">
                    <label class="form-label">Images (Max 5)</label>
                    <div class="text-center p-4 border rounded-4 mt-2" style="border-style: dashed !important;">
                        <input type="file" name="images[]" id="imgInput" multiple hidden accept="image/*">
                        <i class="bi bi-images d-block mb-2 fs-2 opacity-25"></i>
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold" onclick="document.getElementById('imgInput').click()">Parcourir</button>
                    </div>
                    <div id="image-preview" class="d-flex flex-wrap gap-2 mt-3"></div>
                </div>

                <div class="bento-item col-span-2 d-flex align-items-center justify-content-between bg-dark text-white">
                    <div class="d-none d-md-block">
                        <h5 class="fw-bold mb-0">Prêt à valider ?</h5>
                        <small class="opacity-50">Votre annonce sera publiée instantanément.</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-800">
                        Publier l'annonce <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        const themeToggle = document.getElementById('themeToggle');
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', currentTheme);
        themeToggle.checked = (currentTheme === 'dark');

        themeToggle.addEventListener('change', function () {
            const theme = this.checked ? 'dark' : 'light';
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });

        document.getElementById('imgInput').addEventListener('change', function (e) {
            const container = document.getElementById('image-preview');
            container.innerHTML = '';
            Array.from(e.target.files).slice(0, 5).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    img.className = 'preview-img';
                    if (index === 0) img.style.borderColor = 'var(--primary)';
                    container.appendChild(img);
                }
                reader.readAsDataURL(file);
            });
        });
    </script>
</body>
</html>