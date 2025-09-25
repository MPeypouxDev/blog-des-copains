<?php
/**
 * Système d'authentification sécurisé pour le Blog des Copains
 * Gestion complète : inscription, connexion, déconnexion, sessions
 */

// Fonction utilitaire pour les messages flash
function setFlashMessage($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessages() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

// Fonction de connexion à la base de données (à adapter selon votre configuration)
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $host = 'localhost';
            $dbname = 'blog_copains';
            $username = 'root';
            $password = '';
            
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

class AuthSystem {
    private $db;
    private $session_timeout = 600; // 10 minutes en secondes
    
    public function __construct($database) {
        $this->db = $database;
        $this->createTablesIfNotExists(); // Ajout de cette ligne
        $this->initSession();
    }
    
    /**
     * Création des tables nécessaires si elles n'existent pas
     */
    private function createTablesIfNotExists() {
        try {
            // Table users
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    verification_token VARCHAR(64),
                    is_active TINYINT(1) DEFAULT 1,
                    failed_attempts INT DEFAULT 0,
                    last_attempt TIMESTAMP NULL,
                    login_count INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_login TIMESTAMP NULL,
                    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Table remember_tokens
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS remember_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    selector VARCHAR(32) UNIQUE NOT NULL,
                    token VARCHAR(64) NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_selector (selector),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
        } catch (Exception $e) {
            error_log("Erreur création tables: " . $e->getMessage());
        }
    }
    
    /**
     * Initialisation sécurisée des sessions
     */
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuration sécurisée des sessions
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', 0); // Mettre à 1 en HTTPS
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
        }
        
        // Régénération de l'ID de session pour éviter la fixation
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
        
        // Vérification du timeout
        $this->checkSessionTimeout();
    }
    
    /**
     * Vérification et gestion du timeout de session
     */
    private function checkSessionTimeout() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $this->session_timeout) {
                $this->logout();
                setFlashMessage('warning', 'Votre session a expiré. Veuillez vous reconnecter.');
                return false;
            }
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register($username, $email, $password, $confirm_password) {
        try {
            // Validation des données
            $errors = $this->validateRegistrationData($username, $email, $password, $confirm_password);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Vérification de l'unicité
            if ($this->userExists($username, $email)) {
                return ['success' => false, 'errors' => ['Nom d\'utilisateur ou email déjà utilisé']];
            }
            
            // Hashage du mot de passe avec fallback si ARGON2ID non disponible
            $password_options = [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ];
            
            if (defined('PASSWORD_ARGON2ID')) {
                $hashed_password = password_hash($password, PASSWORD_ARGON2ID, $password_options);
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            }
            
            // Génération token de vérification
            $verification_token = bin2hex(random_bytes(32));
            
            // Insertion en base
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, verification_token, created_at, last_activity) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            
            if ($stmt->execute([$username, $email, $hashed_password, $verification_token])) {
                $user_id = $this->db->lastInsertId();
                
                // Auto-connexion après inscription
                $this->createSession($user_id, $username, $email);
                
                return ['success' => true, 'message' => 'Inscription réussie ! Bienvenue sur le blog !'];
            }
            
        } catch (Exception $e) {
            error_log("Erreur inscription: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Une erreur est survenue lors de l\'inscription']];
        }
        
        return ['success' => false, 'errors' => ['Erreur lors de l\'inscription']];
    }
    
    /**
     * Connexion utilisateur
     */
    public function login($identifier, $password, $remember_me = false) {
        try {
            // Limitation des tentatives (protection brute force)
            if ($this->isBruteForceAttempt($identifier)) {
                return ['success' => false, 'errors' => ['Trop de tentatives. Réessayez dans 15 minutes.']];
            }
            
            // Recherche utilisateur par username ou email
            $stmt = $this->db->prepare("
                SELECT id, username, email, password, is_active, failed_attempts, last_attempt 
                FROM users 
                WHERE (username = ? OR email = ?) AND is_active = 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                $this->recordFailedAttempt($identifier);
                return ['success' => false, 'errors' => ['Identifiants incorrects']];
            }
            
            // Réinitialisation des tentatives échouées
            $this->resetFailedAttempts($user['id']);
            
            // Mise à jour du hash si nécessaire (migration vers algo plus récent)
            $current_algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            if (password_needs_rehash($user['password'], $current_algo)) {
                $new_hash = password_hash($password, $current_algo);
                $this->updatePassword($user['id'], $new_hash);
            }
            
            // Création de la session
            $this->createSession($user['id'], $user['username'], $user['email']);
            
            // Gestion "Se souvenir de moi"
            if ($remember_me) {
                $this->setRememberMeCookie($user['id']);
            }
            
            return ['success' => true, 'message' => 'Connexion réussie !'];
            
        } catch (Exception $e) {
            error_log("Erreur connexion: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Erreur lors de la connexion']];
        }
    }
    
    /**
     * Déconnexion utilisateur
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            // Mise à jour de la dernière activité en base
            $stmt = $this->db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        // Suppression des cookies "Se souvenir de moi"
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
            setcookie('remember_selector', '', time() - 3600, '/', '', false, true);
            
            // Suppression du token en base
            if (isset($_SESSION['user_id'])) {
                $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            }
        }
        
        // Destruction de la session
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        session_start(); // Redémarrage pour les messages flash
        
        setFlashMessage('success', 'Déconnexion réussie !');
    }
    
    /**
     * Création de session utilisateur
     */
    private function createSession($user_id, $username, $email) {
        session_regenerate_id(true); // Nouvelle session ID pour sécurité
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        
        // Mise à jour de la dernière connexion en base
        $stmt = $this->db->prepare("
            UPDATE users 
            SET last_login = NOW(), last_activity = NOW(), login_count = login_count + 1 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
    }
    
    /**
     * Validation des données d'inscription
     */
    private function validateRegistrationData($username, $email, $password, $confirm_password) {
        $errors = [];
        
        // Validation nom d'utilisateur
        if (empty($username)) {
            $errors[] = "Le nom d'utilisateur est requis";
        } elseif (strlen($username) < 3) {
            $errors[] = "Le nom d'utilisateur doit contenir au moins 3 caractères";
        } elseif (strlen($username) > 50) {
            $errors[] = "Le nom d'utilisateur ne peut pas dépasser 50 caractères";
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = "Le nom d'utilisateur ne peut contenir que lettres, chiffres, _ et -";
        }
        
        // Validation email
        if (empty($email)) {
            $errors[] = "L'email est requis";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format d'email invalide";
        } elseif (strlen($email) > 100) {
            $errors[] = "L'email ne peut pas dépasser 100 caractères";
        }
        
        // Validation mot de passe
        if (empty($password)) {
            $errors[] = "Le mot de passe est requis";
        } elseif (strlen($password) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre";
        }
        
        // Confirmation mot de passe
        if ($password !== $confirm_password) {
            $errors[] = "Les mots de passe ne correspondent pas";
        }
        
        return $errors;
    }
    
    /**
     * Vérification existence utilisateur
     */
    private function userExists($username, $email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Protection contre les attaques brute force
     */
    private function isBruteForceAttempt($identifier) {
        $stmt = $this->db->prepare("
            SELECT failed_attempts, last_attempt 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        if ($user && $user['failed_attempts'] >= 5) {
            $last_attempt = strtotime($user['last_attempt']);
            if (time() - $last_attempt < 900) { // 15 minutes
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enregistrement tentative échouée
     */
    private function recordFailedAttempt($identifier) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_attempts = failed_attempts + 1, last_attempt = NOW() 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$identifier, $identifier]);
    }
    
    /**
     * Réinitialisation tentatives échouées
     */
    private function resetFailedAttempts($user_id) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_attempts = 0, last_attempt = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
    }
    
    /**
     * Mise à jour mot de passe
     */
    private function updatePassword($user_id, $new_hash) {
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
    }
    
    /**
     * Gestion cookie "Se souvenir de moi"
     */
    private function setRememberMeCookie($user_id) {
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $hashed_token = hash('sha256', $token);
        
        // Suppression des anciens tokens pour cet utilisateur
        $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Stockage en base
        $stmt = $this->db->prepare("
            INSERT INTO remember_tokens (user_id, selector, token, expires_at) 
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ");
        $stmt->execute([$user_id, $selector, $hashed_token]);
        
        // Cookies sécurisés
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('remember_selector', $selector, time() + (30 * 24 * 3600), '/', '', $secure, true);
        setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', $secure, true);
    }
    
    /**
     * Vérification cookie "Se souvenir de moi"
     */
    public function checkRememberMe() {
        if (isset($_COOKIE['remember_selector'], $_COOKIE['remember_token'])) {
            $selector = $_COOKIE['remember_selector'];
            $token = $_COOKIE['remember_token'];
            
            $stmt = $this->db->prepare("
                SELECT rt.user_id, rt.token, u.username, u.email, u.is_active
                FROM remember_tokens rt
                JOIN users u ON rt.user_id = u.id
                WHERE rt.selector = ? AND rt.expires_at > NOW() AND u.is_active = 1
            ");
            $stmt->execute([$selector]);
            $result = $stmt->fetch();
            
            if ($result && hash_equals($result['token'], hash('sha256', $token))) {
                // Connexion automatique
                $this->createSession($result['user_id'], $result['username'], $result['email']);
                
                // Renouvellement du token
                $this->setRememberMeCookie($result['user_id']);
                
                return true;
            } else {
                // Suppression cookies invalides
                $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                setcookie('remember_selector', '', time() - 3600, '/', '', $secure, true);
                setcookie('remember_token', '', time() - 3600, '/', '', $secure, true);
            }
        }
        
        return false;
    }
    
    /**
     * Vérification si utilisateur connecté
     */
    public function isLoggedIn() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            return $this->checkSessionTimeout();
        }
        
        // Vérification cookie "Se souvenir de moi"
        return $this->checkRememberMe();
    }
    
    /**
     * Obtenir données utilisateur courant
     */
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email']
            ];
        }
        return null;
    }
    
    /**
     * Nettoyage sessions expirées et tokens
     */
    public function cleanup() {
        try {
            // Suppression tokens expirés
            $this->db->exec("DELETE FROM remember_tokens WHERE expires_at < NOW()");
            
            // Réinitialisation tentatives anciennes (plus de 24h)
            $this->db->exec("
                UPDATE users 
                SET failed_attempts = 0, last_attempt = NULL 
                WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
        } catch (Exception $e) {
            error_log("Erreur cleanup: " . $e->getMessage());
        }
    }
}

/**
 * Instance globale et initialisation
 */
try {
    $auth = new AuthSystem(getDB());
    
    // Vérification automatique du "Se souvenir de moi" sur chaque page
    if (!$auth->isLoggedIn()) {
        $auth->checkRememberMe();
    }
    
    // Nettoyage périodique (1% de chance à chaque chargement)
    if (rand(1, 100) === 1) {
        $auth->cleanup();
    }
} catch (Exception $e) {
    error_log("Erreur initialisation auth: " . $e->getMessage());
    die('Erreur système. Veuillez réessayer plus tard.');
}

/**
 * Fonctions helper
 */
function requireLogin() {
    global $auth;
    if (!$auth->isLoggedIn()) {
        setFlashMessage('error', 'Vous devez être connecté pour accéder à cette page.');
        header('Location: /auth/login.php');
        exit;
    }
}

function getCurrentUser() {
    global $auth;
    return $auth->getCurrentUser();
}

function isLoggedIn() {
    global $auth;
    return $auth->isLoggedIn();
}
?>