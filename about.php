<?php
// about.php
session_start();

// État de connexion pour la navbar
$isLoggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À Propos | UCAO Market</title>
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
            overflow-x: hidden;
        }

        /* Navbar & Mobile Menu */
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

        /* Bento Layout */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: minmax(180px, auto);
            gap: 20px;
            margin-bottom: 50px;
        }

        .bento-item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 30px;
            transition: 0.3s ease;
        }

        .bento-item:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.05); }

        /* Custom Spans */
        .col-span-2 { grid-column: span 2; }
        .row-span-2 { grid-row: span 2; }

        /* Stats */
        .stat-number { font-size: 3rem; font-weight: 800; color: var(--primary); display: block; }
        .stat-label { font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; opacity: 0.6; }

        /* Timeline Highlight */
        .mission-text { font-size: 1.25rem; font-weight: 600; line-height: 1.6; color: var(--text-title); }
        
        /* Icon box */
        .icon-box {
            width: 50px;
            height: 50px;
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 15px;
        }

        /* Switch */
        .switch { width: 44px; height: 22px; position: relative; display: inline-block; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 34px; transition: .4s; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .4s; }
        input:checked + .slider { background: var(--primary); }
        input:checked + .slider:before { transform: translateX(22px); }

        @media (max-width: 992px) {
            .bento-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .bento-grid { grid-template-columns: 1fr; }
            .col-span-2 { grid-column: span 1; }
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

    <header class="container mt-5 pt-4">
        <div class="row mb-5">
            <div class="col-lg-7">
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 mb-3 fw-bold">Notre Histoire</span>
                <h1 class="display-4 fw-800 text-title">Plus qu'une marketplace, une <span class="text-primary">communauté.</span></h1>
            </div>
        </div>

        <div class="bento-grid">
            <div class="bento-item col-span-2 row-span-2 d-flex flex-column justify-content-center">
                <div class="icon-box"><i class="bi bi-rocket-takeoff"></i></div>
                <h3 class="fw-800 mb-3">Notre Mission</h3>
                <p class="mission-text">
                    Faciliter la vie étudiante à l'UCAO en créant un espace de confiance pour l'échange de ressources, de matériel et de savoirs. 
                </p>
                <p class="mt-3 opacity-75">
                    Né d'un constat simple sur le campus, UCAO Market répond au besoin de circularité et d'entraide entre les étudiants de toutes les facultés.
                </p>
            </div>

            <div class="bento-item text-center d-flex flex-column justify-content-center">
                <span class="stat-number" data-target="1500">0</span>
                <span class="stat-label">Utilisateurs</span>
            </div>

            <div class="bento-item text-center d-flex flex-column justify-content-center">
                <span class="stat-number" data-target="450">0</span>
                <span class="stat-label">Annonces</span>
            </div>

            <div class="bento-item col-span-2 d-flex align-items-center bg-primary text-white">
                <div>
                    <h4 class="fw-800 mb-2">100% Sécurisé</h4>
                    <p class="mb-0 opacity-75">Tous les comptes sont vérifiés avec l'adresse email institutionnelle de l'UCAO.</p>
                </div>
                <i class="bi bi-shield-check ms-auto display-4"></i>
            </div>

            <div class="bento-item">
                <div class="icon-box"><i class="bi bi-heart"></i></div>
                <h5 class="fw-bold">Confiance</h5>
                <p class="small mb-0 opacity-75">Un système de notation entre étudiants pour garantir la qualité.</p>
            </div>

            <div class="bento-item">
                <div class="icon-box"><i class="bi bi-piggy-bank"></i></div>
                <h5 class="fw-bold">Économie</h5>
                <p class="small mb-0 opacity-75">Des prix adaptés au budget étudiant et des dons fréquents.</p>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="bento-item h-100">
                    <h4 class="fw-800 mb-4">Pourquoi nous ?</h4>
                    <div class="d-flex gap-3 mb-3">
                        <i class="bi bi-check2-circle text-primary h5"></i>
                        <p><strong>Zéro frais :</strong> Nous ne prenons aucune commission sur vos ventes.</p>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <i class="bi bi-check2-circle text-primary h5"></i>
                        <p><strong>Proximité :</strong> Les échanges se font directement sur le campus.</p>
                    </div>
                    <div class="d-flex gap-3">
                        <i class="bi bi-check2-circle text-primary h5"></i>
                        <p><strong>Rapidité :</strong> Publication d'annonce en moins de 2 minutes.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bento-item h-100 bg-dark text-white d-flex flex-column justify-content-center align-items-center text-center">
                    <h3 class="fw-800 mb-3">Rejoignez l'aventure</h3>
                    <p class="mb-4 opacity-75">Commencez à vider votre casier ou trouvez votre prochain PC.</p>
                    <a href="../auth/register.php" class="btn btn-primary rounded-pill px-5 fw-bold">S'inscrire maintenant</a>
                </div>
            </div>
        </div>
    </header>

    <footer class="container py-5 border-top text-center opacity-50">
        <p class="small mb-0">&copy; 2024 UCAO Market - Designed by Gilles-Christ TOGNISSE</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Stats Counter Animation
        const counters = document.querySelectorAll('.stat-number');
        const speed = 200;

        const animate = (counter) => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            const increment = target / speed;

            if (count < target) {
                counter.innerText = Math.ceil(count + increment);
                setTimeout(() => animate(counter), 1);
            } else {
                counter.innerText = target + '+';
            }
        };

        // Intersection Observer pour lancer l'anim des stats
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    animate(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 1 });

        counters.forEach(c => observer.observe(c));
    </script>
</body>
</html>