<?php
// includes/session.php

class SessionManager {
    private $conn;
    
    public function __construct() {
        // Vérifier si session est démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Inclure la configuration MySQL
        require_once __DIR__ . '/../config/config.php';
        
        // Initialiser la connexion
        if (!isset($GLOBALS['conn'])) {
            // Tenter d'établir la connexion si elle n'existe pas
            if (isset($servername) && isset($username) && isset($password) && isset($dbname)) {
                $conn = new mysqli($servername, $username, $password, $dbname);
                if (!$conn->connect_error) {
                    $GLOBALS['conn'] = $conn;
                }
            }
        }
        
        if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli) || $GLOBALS['conn']->connect_error) {
            // Ne pas mourir immédiatement, mais logger l'erreur
            error_log("Erreur de connexion à la base de données dans SessionManager.");
            $this->conn = null;
        } else {
            $this->conn = $GLOBALS['conn'];
        }
    }
    
    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
            return false;
        }
        
        // Vérifier l'expiration de la session (2 heures)
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 7200) {
            $this->logout();
            return false;
        }
        
        // Optionnel : vérifier le token de session en base
        if (isset($_SESSION['session_token'])) {
            return $this->validateSessionToken($_SESSION['session_token']);
        }
        
        return true;
    }
    
    /**
     * Valide le token de session en base de données
     */
    private function validateSessionToken($token) {
        if (!$this->conn) {
            return true; // Si pas de connexion BD, on accepte la session
        }
        
        // Vérifier si la table existe
        $table_check = $this->conn->query("SHOW TABLES LIKE 'user_sessions'");
        if (!$table_check || $table_check->num_rows == 0) {
            return true; // Table non existante
        }
        
        $stmt = $this->conn->prepare("
            SELECT id FROM user_sessions 
            WHERE session_token = ? 
            AND expires_at > NOW() 
            AND is_active = 1
        ");
        
        if (!$stmt) {
            return true; // Si la requête échoue
        }
        
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $valid = ($result->num_rows > 0);
        $stmt->close();
        
        return $valid;
    }
    
    /**
     * Connecte l'utilisateur avec MySQL
     */
    public function login($email, $password) {
        if (!$this->conn) {
            return ['success' => false, 'error' => 'Erreur de connexion à la base de données'];
        }
        
        try {
            // Vérifier si l'utilisateur existe
            $stmt = $this->conn->prepare("
                SELECT id, email, password, first_name, last_name, filiere, phone, 
                       is_active, email_verified, login_attempts, locked_until
                FROM users 
                WHERE email = ?
            ");
            
            if (!$stmt) {
                error_log("Erreur préparation requête login: " . $this->conn->error);
                return ['success' => false, 'error' => 'Erreur système'];
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
            }
            
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // Vérifier si le compte est verrouillé
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $lock_time = date('H:i', strtotime($user['locked_until']));
                return ['success' => false, 'error' => "Compte verrouillé jusqu'à $lock_time"];
            }
            
            // Vérifier si le compte est actif
            if (isset($user['is_active']) && $user['is_active'] == 0) {
                return ['success' => false, 'error' => 'Compte non activé'];
            }
            
            // Vérifier si l'email est vérifié
            if (isset($user['email_verified']) && $user['email_verified'] == 0) {
                return ['success' => false, 'error' => 'Email non vérifié'];
            }
            
            // Vérifier le mot de passe
            if (!password_verify($password, $user['password'])) {
                // Incrémenter les tentatives
                $this->incrementLoginAttempts($user['id']);
                return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
            }
            
            // Réinitialiser les tentatives de connexion
            $this->resetLoginAttempts($user['id']);
            
            // Créer la session
            $this->createSession($user);
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Erreur login: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur système'];
        }
    }
    
    /**
     * Incrémente les tentatives de connexion
     */
    private function incrementLoginAttempts($userId) {
        if (!$this->conn) return;
        
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET login_attempts = IFNULL(login_attempts, 0) + 1,
                locked_until = CASE 
                    WHEN IFNULL(login_attempts, 0) >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE locked_until 
                END
            WHERE id = ?
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Réinitialise les tentatives de connexion
     */
    private function resetLoginAttempts($userId) {
        if (!$this->conn) return;
        
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET login_attempts = 0, 
                locked_until = NULL,
                last_login = NOW()
            WHERE id = ?
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Crée une session utilisateur
     */
    private function createSession($user) {
        // Démarrer session si pas déjà démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        $_SESSION['filiere'] = $user['filiere'] ?? '';
        $_SESSION['phone'] = $user['phone'] ?? '';
        $_SESSION['user_type'] = 'student';
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Créer un token de session sécurisé
        $session_token = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = $session_token;
        
        // Stocker le token en base (optionnel)
        $this->storeSessionToken($user['id'], $session_token);
    }
    
    /**
     * Stocke le token de session en base
     */
    private function storeSessionToken($userId, $token) {
        if (!$this->conn) return;
        
        // Vérifier si la table existe
        $table_check = $this->conn->query("SHOW TABLES LIKE 'user_sessions'");
        if (!$table_check || $table_check->num_rows == 0) {
            return; // Table non existante
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_sessions (user_id, session_token, created_at, expires_at) 
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR))
            ON DUPLICATE KEY UPDATE
                session_token = VALUES(session_token),
                expires_at = VALUES(expires_at),
                is_active = 1
        ");
        
        if ($stmt) {
            $stmt->bind_param("ss", $userId, $token);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        // Désactiver le token de session en base
        if (isset($_SESSION['session_token']) && $this->conn) {
            $this->invalidateSessionToken($_SESSION['session_token']);
        }
        
        // Détruire la session
        if (session_status() !== PHP_SESSION_NONE) {
            // Vider toutes les variables de session
            $_SESSION = array();
            
            // Si vous voulez détruire le cookie de session
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Détruire la session
            session_destroy();
        }
    }
    
    /**
     * Invalide le token de session
     */
    private function invalidateSessionToken($token) {
        if (!$this->conn) return;
        
        $stmt = $this->conn->prepare("
            UPDATE user_sessions 
            SET is_active = 0 
            WHERE session_token = ?
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Récupère l'ID utilisateur
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Récupère les infos utilisateur
     */
    public function getUserInfo() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'first_name' => $_SESSION['first_name'] ?? null,
            'last_name' => $_SESSION['last_name'] ?? null,
            'filiere' => $_SESSION['filiere'] ?? null,
            'phone' => $_SESSION['phone'] ?? null,
            'user_type' => $_SESSION['user_type'] ?? 'student'
        ];
    }
    
    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole($role) {
        $userInfo = $this->getUserInfo();
        return ($userInfo && $userInfo['user_type'] === $role);
    }
    
    /**
     * Redirige si non connecté
     */
    public function requireLogin($redirectTo = '../auth/login.php') {
        if (!$this->isLoggedIn()) {
            header("Location: $redirectTo");
            exit();
        }
    }
    
    /**
     * Redirige si déjà connecté
     */
    public function requireNoLogin($redirectTo = '../dashboard/') {
        if ($this->isLoggedIn()) {
            header("Location: $redirectTo");
            exit();
        }
    }
    
    /**
     * Rafraîchit les informations utilisateur depuis la base
     */
    public function refreshUserData() {
        if (!$this->isLoggedIn() || !$this->conn) {
            return false;
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT first_name, last_name, filiere, phone, email_verified, is_active
                FROM users 
                WHERE id = ?
            ");
            
            if (!$stmt) {
                return false;
            }
            
            $userId = $this->getUserId();
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $_SESSION['first_name'] = $user['first_name'] ?? '';
                $_SESSION['last_name'] = $user['last_name'] ?? '';
                $_SESSION['filiere'] = $user['filiere'] ?? '';
                $_SESSION['phone'] = $user['phone'] ?? '';
                $stmt->close();
                return true;
            }
            
            $stmt->close();
            return false;
            
        } catch (Exception $e) {
            error_log("Erreur refreshUserData: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifie la connexion à la base de données
     */
    public function isDatabaseConnected() {
        return ($this->conn && $this->conn->ping());
    }
    
    /**
     * Récupère la connexion à la base de données
     */
    public function getConnection() {
        return $this->conn;
    }
}

// Fonction pour obtenir l'instance unique du SessionManager
function getSessionManager() {
    static $sessionManager = null;
    if ($sessionManager === null) {
        $sessionManager = new SessionManager();
    }
    return $sessionManager;
}

// Fonction helper pour vérifier l'authentification rapidement
/*function requireAuth($redirectTo = '../auth/login.php') {
    $session = getSessionManager();
    $session->requireLogin($redirectTo);
}*/

// Fonction helper pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    $session = getSessionManager();
    return $session->isLoggedIn();
}

// Initialiser la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}