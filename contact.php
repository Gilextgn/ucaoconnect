<?php
// contact.php
session_start();

// --- LOGIQUE PHP CONSERVÉE ---
$isLoggedIn = isset($_SESSION['user_id']);
$userEmail = $_SESSION['email'] ?? '';
$userName = isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? '') : '';

// À REMPLACER : Votre adresse email de réception réelle
$my_email = "votre-email@ucaouuc.com"; 
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | UCAO Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-title: #0f172a;
            --text-body: #334155;
            --border: #e2e8f0;
            --nav-bg: #ffffff;
        }

        [data-theme="dark"] {
            --bg-body: #020617;
            --bg-card: #1e293b;
            --text-title: #f8fafc;
            --text-body: #cbd5e1;
            --border: #334155;
            --nav-bg: #1e293b;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-body);
            transition: all 0.3s ease;
        }

        /* Navbar & Responsive Menu */
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

        /* Bento Cards */
        .bento-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 35px;
            height: 100%;
            transition: 0.3s ease;
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .form-control-custom {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 18px;
            color: var(--text-body);
            transition: 0.3s;
        }
        .form-control-custom:focus {
            background: var(--bg-card);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        /* Switch Theme */
        .switch { width: 44px; height: 22px; position: relative; display: inline-block; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 34px; transition: .4s; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .4s; }
        input:checked + .slider { background: var(--primary); }
        input:checked + .slider:before { transform: translateX(22px); }
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

    <div class="container my-5 pt-4">
        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="bento-card">
                    <h1 class="fw-800 h2 mb-4" style="color: var(--text-title);">Contact.</h1>
                    <p class="opacity-75 mb-5">Besoin d'aide ou d'un renseignement ? L'équipe UCAO Market vous répond dans les plus brefs délais.</p>
                    
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="contact-icon"><i class="bi bi-envelope"></i></div>
                        <div>
                            <div class="small fw-bold opacity-50 text-uppercase">Email Support</div>
                            <div class="fw-bold" style="color: var(--text-title);">support@ucao-market.edu</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="contact-icon"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <div class="small fw-bold opacity-50 text-uppercase">Campus</div>
                            <div class="fw-bold" style="color: var(--text-title);">UCAO-UUC, Cotonou</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="bento-card">
                    <h3 class="fw-800 mb-4" style="color: var(--text-title);">Envoyer un message</h3>
                    
                    <form id="contactForm" action="https://formsubmit.co/<?= $my_email ?>" method="POST">
                        <input type="hidden" name="_subject" value="Nouveau contact UCAO Market">
                        <input type="hidden" name="_template" value="table">
                        <input type="hidden" name="_next" value="<?= "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]?success=1" ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small fw-bold mb-2">Votre Nom</label>
                                <input type="text" name="name" class="form-control form-control-custom" placeholder="Nom complet" required value="<?= htmlspecialchars($userName) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold mb-2">Votre Email</label>
                                <input type="email" name="email" class="form-control form-control-custom" placeholder="votre@email.com" required value="<?= htmlspecialchars($userEmail) ?>">
                            </div>
                            <div class="col-12">
                                <label class="small fw-bold mb-2">Sujet</label>
                                <input type="text" name="subject" class="form-control form-control-custom" placeholder="De quoi s'agit-il ?" required>
                            </div>
                            <div class="col-12">
                                <label class="small fw-bold mb-2">Message</label>
                                <textarea name="message" rows="6" class="form-control form-control-custom" placeholder="Décrivez votre demande en quelques mots..." required minlength="15"></textarea>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary rounded-pill px-5 py-3 fw-bold w-100 w-md-auto shadow-sm">
                                    Envoyer <i class="bi bi-send-fill ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if(isset($_GET['success'])): ?>
                        <div class="alert alert-success mt-4 rounded-4 border-0 p-3 shadow-sm d-flex align-items-center">
                            <i class="bi bi-check-circle-fill me-3 h4 mb-0"></i>
                            <div><strong>Message envoyé !</strong> Nous reviendrons vers vous très bientôt par email.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion du Thème (Dark/Light)
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

        // Contrôle visuel de l'envoi
        document.getElementById('contactForm').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Envoi en cours...';
            btn.classList.add('disabled');
        });
    </script>
</body>
</html>