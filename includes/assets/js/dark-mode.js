class DarkMode {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.init();
    }
    
    init() {
        this.applyTheme();
        this.createToggle();
    }
    
    applyTheme() {
        if (this.theme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        } else {
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }
    }
    
    createToggle() {
        const toggle = document.createElement('button');
        toggle.className = 'btn btn-outline-secondary btn-sm';
        toggle.innerHTML = this.theme === 'dark' ? '☀️' : '🌙';
        toggle.title = 'Changer le thème';
        
        toggle.addEventListener('click', () => {
            this.toggle();
        });
        
        // Ajouter dans la navbar
        const navbar = document.querySelector('.navbar .navbar-nav');
        if (navbar) {
            const li = document.createElement('li');
            li.className = 'nav-item';
            li.appendChild(toggle);
            navbar.appendChild(li);
        }
    }
    
    toggle() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', this.theme);
        this.applyTheme();
        location.reload(); // Pour appliquer le thème Bootstrap
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new DarkMode();
});