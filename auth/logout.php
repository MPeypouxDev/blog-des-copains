<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérification de la méthode et du token CSRF pour éviter les déconnexions malveillantes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification token CSRF si présent
    if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            setFlashMessage('error', 'Token de sécurité invalide.');
            header('Location: ../index.php');
            exit;
        }
    }
    
    // Déconnexion effective
    $auth->logout();
    header('Location: ../index.php');
    exit;
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['confirm'])) {
    // Déconnexion directe via GET avec confirmation
    $auth->logout();
    header('Location: ../index.php');
    exit;
    
} else {
    // Affichage de la page de confirmation
    if (!isLoggedIn()) {
        setFlashMessage('info', 'Vous êtes déjà déconnecté.');
        header('Location: ../index.php');
        exit;
    }
}

// Génération token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déconnexion - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-background">
            <div class="auth-overlay"></div>
        </div>
        
        <div class="auth-content">
            <!-- Logo et retour -->
            <div class="auth-header">
                <a href="../index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Retour au blog
                </a>
                <h1 class="auth-logo">🎌 Blog des Copains</h1>
            </div>
            
            <!-- Carte de déconnexion -->
            <div class="auth-card">
                <div class="auth-card-header">
                    <div class="logout-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <h2>Déconnexion</h2>
                    <p>Êtes-vous sûr de vouloir vous déconnecter ?</p>
                </div>
                
                <!-- Informations utilisateur -->
                <?php if ($user): ?>
                <div class="user-info-card">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                        <small>
                            <i class="fas fa-clock"></i>
                            Connecté depuis <?php echo date('H:i', $_SESSION['login_time'] ?? time()); ?>
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Actions de déconnexion -->
                <div class="logout-actions">
                    <form method="POST" class="logout-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <button type="submit" class="btn btn-primary btn-full logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            Oui, me déconnecter
                        </button>
                    </form>
                    
                    <a href="../index.php" class="btn btn-secondary btn-full">
                        <i class="fas fa-times"></i>
                        Annuler
                    </a>
                </div>
                
                <!-- Options de déconnexion -->
                <div class="logout-options">
                    <div class="option-item">
                        <input type="checkbox" id="logout_all_devices" name="logout_all_devices">
                        <label for="logout_all_devices">
                            <i class="fas fa-devices"></i>
                            Déconnecter de tous les appareils
                        </label>
                    </div>
                    
                    <div class="option-item">
                        <input type="checkbox" id="clear_remember" name="clear_remember" checked>
                        <label for="clear_remember">
                            <i class="fas fa-eraser"></i>
                            Supprimer "Se souvenir de moi"
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Liens rapides -->
            <div class="quick-actions">
                <h4>Avant de partir...</h4>
                <div class="action-links">
                    <a href="../pages/profile.php" class="action-link">
                        <i class="fas fa-user-cog"></i>
                        <span>Gérer mon profil</span>
                    </a>
                    <a href="../pages/settings.php" class="action-link">
                        <i class="fas fa-cog"></i>
                        <span>Paramètres</span>
                    </a>
                    <a href="../pages/help.php" class="action-link">
                        <i class="fas fa-question-circle"></i>
                        <span>Aide</span>
                    </a>
                </div>
            </div>
            
            <!-- Informations de session -->
            <div class="session-info">
                <div class="session-details">
                    <div class="session-item">
                        <i class="fas fa-globe"></i>
                        <span>IP: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Inconnue'; ?></span>
                    </div>
                    <div class="session-item">
                        <i class="fas fa-desktop"></i>
                        <span>Navigateur: <?php echo substr($_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu', 0, 50); ?>...</span>
                    </div>
                    <div class="session-item">
                        <i class="fas fa-clock"></i>
                        <span>Dernière activité: <?php echo date('d/m/Y à H:i'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.logout-form');
            const logoutBtn = document.querySelector('.logout-btn');
            const logoutAllDevices = document.getElementById('logout_all_devices');
            const clearRemember = document.getElementById('clear_remember');
            
            // Gestion des options de déconnexion
            logoutAllDevices.addEventListener('change', function() {
                if (this.checked) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'logout_all_devices';
                    input.value = '1';
                    form.appendChild(input);
                } else {
                    const existing = form.querySelector('input[name="logout_all_devices"]');
                    if (existing) existing.remove();
                }
            });
            
            clearRemember.addEventListener('change', function() {
                if (this.checked) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'clear_remember';
                    input.value = '1';
                    form.appendChild(input);
                } else {
                    const existing = form.querySelector('input[name="clear_remember"]');
                    if (existing) existing.remove();
                }
            });
            
            // Animation du bouton de déconnexion
            let countdown = 0;
            logoutBtn.addEventListener('click', function(e) {
                if (countdown === 0) {
                    e.preventDefault();
                    countdown = 3;
                    
                    const originalText = this.innerHTML;
                    const interval = setInterval(() => {
                        this.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Déconnexion dans ${countdown}s...`;
                        countdown--;
                        
                        if (countdown < 0) {
                            clearInterval(interval);
                            this.innerHTML = '<i class="fas fa-sign-out-alt"></i> Déconnexion...';
                            this.disabled = true;
                            form.submit();
                        }
                    }, 1000);
                    
                    // Possibilité d'annuler
                    this.addEventListener('dblclick', function() {
                        clearInterval(interval);
                        countdown = 0;
                        this.innerHTML = originalText;
                        this.disabled = false;
                    });
                }
            });
            
            // Raccourci clavier
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.ctrlKey) {
                    form.submit();
                }
                if (e.key === 'Escape') {
                    window.location.href = '../index.php';
                }
            });
            
            // Auto-déconnexion après inactivité (optionnel)
            let inactivityTimer;
            function resetInactivityTimer() {
                clearTimeout(inactivityTimer);
                inactivityTimer = setTimeout(() => {
                    if (confirm('Aucune activité détectée. Déconnexion automatique ?')) {
                        form.submit();
                    } else {
                        resetInactivityTimer();
                    }
                }, 300000); // 5 minutes
            }
            
            // Événements pour détecter l'activité
            ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
                document.addEventListener(event, resetInactivityTimer, true);
            });
            
            resetInactivityTimer();
        });
    </script>
    
    <style>
        .logout-icon {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }
        
        .user-info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info-card .user-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .user-details h3 {
            margin: 0 0 0.3rem 0;
            color: var(--secondary-color);
        }
        
        .user-details p {
            margin: 0 0 0.5rem 0;
            color: var(--text-light);
        }
        
        .user-details small {
            color: var(--text-light);
            font-size: 0.8rem;
        }
        
        .logout-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .logout-options {
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }
        
        .option-item label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
            color: var(--text-color);
        }
        
        .quick-actions {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
        }
        
        .quick-actions h4 {
            color: white;
            margin-bottom: 1rem;
        }
        
        .action-links {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .action-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
            color: white;
            text-decoration: none;
            padding: 0.8rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .action-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .action-link i {
            font-size: 1.2rem;
        }
        
        .action-link span {
            font-size: 0.8rem;
        }
        
        .session-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .session-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .session-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
        }
        
        .session-item i {
            width: 16px;
            text-align: center;
        }
        
        @media (max-width: 576px) {
            .user-info-card {
                flex-direction: column;
                text-align: center;
            }
            
            .action-links {
                flex-direction: column;
                align-items: center;
            }
            
            .action-link {
                flex-direction: row;
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</body>
</html>