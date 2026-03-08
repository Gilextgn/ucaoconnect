        </div> <!-- Fermeture du container -->
    </main>
    
    <!-- Footer -->
    <footer class="footer mt-auto">
        <div class="container py-5">
            <div class="row">
                <!-- Logo et description -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="mb-3">
                        <i class="bi bi-shop-window me-2" style="font-size: 2rem; color: white;"></i>
                        <span class="h5 mb-0" style="color: white;">UCAO Marketplace</span>
                    </div>
                    <p class="mb-4" style="color: rgba(255, 255, 255, 0.8);">
                        Plateforme d'échange entre étudiants de l'Université Catholique de l'Afrique de l'Ouest.
                        Vendez, achetez et échangez vos livres et matériel académique.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white">
                            <i class="bi bi-facebook" style="font-size: 1.2rem;"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="bi bi-twitter" style="font-size: 1.2rem;"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="bi bi-instagram" style="font-size: 1.2rem;"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="bi bi-linkedin" style="font-size: 1.2rem;"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Liens rapides -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-3" style="color: white;">Liens rapides</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="/">Accueil</a>
                        </li>
                        <li class="mb-2">
                            <a href="/public/browse.php">Rechercher</a>
                        </li>
                        <li class="mb-2">
                            <a href="/public/about.php">À propos</a>
                        </li>
                        <li class="mb-2">
                            <a href="/public/contact.php">Contact</a>
                        </li>
                        <li class="mb-2">
                            <a href="/public/faq.php">FAQ</a>
                        </li>
                    </ul>
                </div>
                
                <!-- Catégories -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-3" style="color: white;">Catégories</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="/public/browse.php?categorie=livres">Livres</a>
                        </li>
                        <li class="mb-2">
                            <a href="/public/browse.php?categorie=materiel">Matériel</a>
                        </li>
                        <li class="mb-2">
                            <a href="/public/browse.php?categorie=electronique">Électronique</a>
                        </li>
                        <li class="mb-2">
                            <a href="/public/browse.php?categorie=autres">Autres</a>
                        </li>
                    </ul>
                </div>
                
                <!-- Contact et support -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="mb-3" style="color: white;">Contact & Support</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3 d-flex align-items-start">
                            <i class="bi bi-geo-alt me-2 mt-1" style="color: rgba(255, 255, 255, 0.8);"></i>
                            <div>
                                <div style="color: rgba(255, 255, 255, 0.9); font-weight: 500;">Adresse</div>
                                <div style="color: rgba(255, 255, 255, 0.8);">
                                    Université Catholique de l'Afrique de l'Ouest<br>
                                    Abidjan, Côte d'Ivoire
                                </div>
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="bi bi-envelope me-2 mt-1" style="color: rgba(255, 255, 255, 0.8);"></i>
                            <div>
                                <div style="color: rgba(255, 255, 255, 0.9); font-weight: 500;">Email</div>
                                <div style="color: rgba(255, 255, 255, 0.8);">
                                    support@ucao-marketplace.ci
                                </div>
                            </div>
                        </li>
                        <li class="d-flex align-items-start">
                            <i class="bi bi-telephone me-2 mt-1" style="color: rgba(255, 255, 255, 0.8);"></i>
                            <div>
                                <div style="color: rgba(255, 255, 255, 0.9); font-weight: 500;">Téléphone</div>
                                <div style="color: rgba(255, 255, 255, 0.8);">
                                    +225 27 22 44 00 00
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr style="border-color: rgba(255, 255, 255, 0.2);">
            
            <!-- Copyright et mentions légales -->
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem;">
                        &copy; <?php echo date('Y'); ?> UCAO Students Marketplace. Tous droits réservés.
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex flex-wrap justify-content-md-end gap-3">
                        <a href="/public/terms.php" style="font-size: 0.9rem;">Conditions d'utilisation</a>
                        <a href="/public/privacy.php" style="font-size: 0.9rem;">Politique de confidentialité</a>
                        <a href="/public/cookies.php" style="font-size: 0.9rem;">Cookies</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bouton retour en haut -->
    <button type="button" 
            class="btn btn-primary btn-floating rounded-circle shadow" 
            id="btn-back-to-top"
            style="position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; display: none;">
        <i class="bi bi-arrow-up"></i>
    </button>
    
    <!-- Modals globaux -->
    <div class="modal fade" id="globalModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="globalModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="globalModalBody"></div>
                <div class="modal-footer" id="globalModalFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts globaux -->
    <script>
        // Back to top button
        const backToTopButton = document.getElementById('btn-back-to-top');
        
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTopButton.style.display = 'block';
            } else {
                backToTopButton.style.display = 'none';
            }
        });
        
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Initialiser les tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                new bootstrap.Tooltip(tooltip);
            });
            
            // Initialiser les popovers
            const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
            popovers.forEach(popover => {
                new bootstrap.Popover(popover);
            });
            
            // Auto-dismiss des alertes après 5 secondes
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
            // Gérer la validation des formulaires
            const forms = document.querySelectorAll('.needs-validation');
            forms.forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Confirmation avant certaines actions
            document.querySelectorAll('[data-confirm]').forEach(element => {
                element.addEventListener('click', event => {
                    const message = element.getAttribute('data-confirm');
                    if (!confirm(message)) {
                        event.preventDefault();
                    }
                });
            });
        });
        
        // Fonction pour afficher un modal global
        function showGlobalModal(title, body, footer = null) {
            document.getElementById('globalModalTitle').textContent = title;
            document.getElementById('globalModalBody').innerHTML = body;
            
            if (footer) {
                document.getElementById('globalModalFooter').innerHTML = footer;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('globalModal'));
            modal.show();
        }
        
        // Fonction pour afficher une notification toast
        function showToast(message, type = 'info') {
            const toastHtml = `
                <div class="toast align-items-center text-bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            const toastContainer = document.getElementById('toastContainer') || createToastContainer();
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = toastContainer.lastElementChild;
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // Nettoyer après fermeture
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.position = 'fixed';
            container.style.top = '20px';
            container.style.right = '20px';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }
        
        // Fonction pour afficher un loader
        function showLoader(containerId = null) {
            const loaderHtml = `
                <div class="d-flex justify-content-center align-items-center py-5">
                    <div class="loader"></div>
                </div>
            `;
            
            if (containerId) {
                document.getElementById(containerId).innerHTML = loaderHtml;
            }
            return loaderHtml;
        }
        
        // Gestion des formulaires AJAX
        document.addEventListener('submit', async function(e) {
            const form = e.target;
            if (form.hasAttribute('data-ajax')) {
                e.preventDefault();
                
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn?.innerHTML;
                
                if (submitBtn) {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
                    submitBtn.disabled = true;
                }
                
                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: form.method,
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else if (data.message) {
                            showToast(data.message, 'success');
                            if (data.reload) {
                                setTimeout(() => location.reload(), 1500);
                            }
                        }
                    } else {
                        showToast(data.message || 'Une erreur est survenue', 'danger');
                    }
                } catch (error) {
                    showToast('Erreur réseau', 'danger');
                } finally {
                    if (submitBtn) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                }
            }
        });
        
        // Fonction utilitaire pour les requêtes AJAX
        async function ajaxRequest(url, options = {}) {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            const mergedOptions = { ...defaultOptions, ...options };
            
            try {
                const response = await fetch(url, mergedOptions);
                return await response.json();
            } catch (error) {
                return { success: false, message: 'Erreur réseau' };
            }
        }
        
        // Gestion de la déconnexion automatique après inactivité (30 minutes)
        let inactivityTimer;
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                if (window.location.pathname.includes('/dashboard')) {
                    showToast('Session expirée. Redirection...', 'warning');
                    setTimeout(() => {
                        window.location.href = '/auth/logout.php?timeout=1';
                    }, 2000);
                }
            }, 30 * 60 * 1000); // 30 minutes
        }
        
        // Réinitialiser le timer sur les événements utilisateur
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetInactivityTimer);
        });
        
        resetInactivityTimer();
    </script>
    
    <!-- Scripts spécifiques à la page -->
    <?php if (isset($page_scripts)): ?>
        <script><?php echo $page_scripts; ?></script>
    <?php endif; ?>
    
    <!-- Scripts externes optionnels -->
    <?php if (isset($external_scripts)): ?>
        <?php foreach ($external_scripts as $script): ?>
            <script src="<?php echo htmlspecialchars($script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>