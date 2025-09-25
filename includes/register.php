<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirection si déjà connecté
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$errors = [];
$form_data = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'username' => sanitize_input($_POST['username'] ?? ''),
        'email' => sanitize_input($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    $result = $auth->register(
        $form_data['username'],
        $form_data['email'],
        $form_data['password'],
        $form_data['confirm_password']
    );
    
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
        header('Location: ../index.php');
        exit;
    } else {
        $errors = $result['errors'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?php echo SITE_NAME; ?></title>
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
            
            <!-- Formulaire d'inscription -->
            <div class="auth-card">
                <div class="auth-card-header">
                    <h2>Inscription</h2>
                    <p>Rejoignez la communauté des fans d'animés</p>
                </div>
                
                <!-- Messages d'erreur -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form" autocomplete="on" novalidate>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Adresse email <span class="required">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                            autocomplete="email"
                            placeholder="votre.email@exemple.com"
                            required
                        >
                        <div class="field-hint">
                            Utilisée pour la récupération de mot de passe
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Mot de passe <span class="required">*</span>
                        </label>
                        <div class="password-input">
                            <input 
                                type="password" 
                                id="password" 
                                name="password"
                                autocomplete="new-password"
                                placeholder="Créez un mot de passe sécurisé"
                                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$"
                                title="Minimum 8 caractères avec majuscule, minuscule et chiffre"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                        <div class="field-hint">
                            Minimum 8 caractères avec majuscule, minuscule et chiffre
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i>
                            Confirmer le mot de passe <span class="required">*</span>
                        </label>
                        <div class="password-input">
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password"
                                autocomplete="new-password"
                                placeholder="Confirmez votre mot de passe"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" class="password-match"></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">
                                J'accepte les <a href="../legal/terms.php" target="_blank">conditions d'utilisation</a> 
                                et la <a href="../legal/privacy.php" target="_blank">politique de confidentialité</a>
                                <span class="required">*</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="newsletter" name="newsletter">
                            <label for="newsletter">
                                Je souhaite recevoir la newsletter avec les dernières actualités animés
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full" id="registerBtn">
                        <i class="fas fa-user-plus"></i>
                        Créer mon compte
                    </button>
                </form>
                
                <div class="auth-divider">
                    <span>ou</span>
                </div>
                
                <!-- Liens vers la connexion -->
                <div class="auth-links">
                    <p>Déjà un compte ?</p>
                    <a href="login.php" class="btn btn-secondary btn-full">
                        <i class="fas fa-sign-in-alt"></i>
                        Se connecter
                    </a>
                </div>
            </div>
            
            <!-- Informations de sécurité -->
            <div class="security-info">
                <div class="security-features">
                    <div class="security-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Données cryptées</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-user-lock"></i>
                        <span>Compte sécurisé</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-envelope-open-text"></i>
                        <span>Pas de spam</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/auth.js"></script>
    <script>
        // Validation en temps réel
        document.addEventListener('DOMContentLoaded', function() {
            const username = document.getElementById('username');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const form = document.querySelector('.auth-form');
            const registerBtn = document.getElementById('registerBtn');
            
            // Validation nom d'utilisateur
            username.addEventListener('input', function() {
                validateUsername(this.value);
            });
            
            // Validation email
            email.addEventListener('input', function() {
                validateEmail(this.value);
            });
            
            // Force du mot de passe
            password.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                if (confirmPassword.value) {
                    checkPasswordMatch();
                }
            });
            
            // Vérification correspondance mots de passe
            confirmPassword.addEventListener('input', checkPasswordMatch);
            
            // Validation formulaire
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });
        });
        
        function validateUsername(username) {
            const regex = /^[a-zA-Z0-9_-]{3,50}$/;
            const field = document.getElementById('username');
            
            if (username.length === 0) {
                setFieldState(field, 'neutral');
                return false;
            }
            
            if (username.length < 3) {
                setFieldState(field, 'error', 'Trop court (minimum 3 caractères)');
                return false;
            }
            
            if (username.length > 50) {
                setFieldState(field, 'error', 'Trop long (maximum 50 caractères)');
                return false;
            }
            
            if (!regex.test(username)) {
                setFieldState(field, 'error', 'Caractères invalides');
                return false;
            }
            
            setFieldState(field, 'success', 'Nom d\'utilisateur valide');
            return true;
        }
        
        function validateEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const field = document.getElementById('email');
            
            if (email.length === 0) {
                setFieldState(field, 'neutral');
                return false;
            }
            
            if (!regex.test(email)) {
                setFieldState(field, 'error', 'Format d\'email invalide');
                return false;
            }
            
            setFieldState(field, 'success', 'Email valide');
            return true;
        }
        
        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('passwordStrength');
            const field = document.getElementById('password');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                setFieldState(field, 'neutral');
                return 0;
            }
            
            let strength = 0;
            let feedback = [];
            
            // Longueur
            if (password.length >= 8) strength++;
            else feedback.push('8 caractères minimum');
            
            // Minuscule
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('une minuscule');
            
            // Majuscule  
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('une majuscule');
            
            // Chiffre
            if (/\d/.test(password)) strength++;
            else feedback.push('un chiffre');
            
            // Caractère spécial (bonus)
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
            
            const strengthLabels = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
            const strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#198754'];
            
            const strengthIndex = Math.min(strength, 4);
            const strengthText = strengthLabels[strengthIndex];
            const strengthColor = strengthColors[strengthIndex];
            
            strengthDiv.innerHTML = `
                <div class="strength-bar">
                    <div class="strength-fill" style="width: ${(strength/4)*100}%; background-color: ${strengthColor}"></div>
                </div>
                <div class="strength-text" style="color: ${strengthColor}">${strengthText}</div>
                ${feedback.length > 0 ? `<div class="strength-feedback">Manque: ${feedback.join(', ')}</div>` : ''}
            `;
            
            if (strength >= 3) {
                setFieldState(field, 'success');
            } else if (strength >= 2) {
                setFieldState(field, 'warning');
            } else {
                setFieldState(field, 'error');
            }
            
            return strength;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            const field = document.getElementById('confirm_password');
            
            if (confirmPassword.length === 0) {
                matchDiv.innerHTML = '';
                setFieldState(field, 'neutral');
                return false;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<div class="match-success"><i class="fas fa-check"></i> Les mots de passe correspondent</div>';
                setFieldState(field, 'success');
                return true;
            } else {
                matchDiv.innerHTML = '<div class="match-error"><i class="fas fa-times"></i> Les mots de passe ne correspondent pas</div>';
                setFieldState(field, 'error');
                return false;
            }
        }
        
        function setFieldState(field, state, message) {
            field.classList.remove('field-success', 'field-error', 'field-warning', 'field-neutral');
            field.classList.add(`field-${state}`);
            
            // Gestion du message personnalisé si nécessaire
            if (message) {
                let hintDiv = field.parentNode.querySelector('.field-hint');
                if (hintDiv) {
                    hintDiv.textContent = message;
                    hintDiv.className = `field-hint hint-${state}`;
                }
            }
        }
        
        function validateForm() {
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            
            let isValid = true;
            
            if (!validateUsername(username)) isValid = false;
            if (!validateEmail(email)) isValid = false;
            if (checkPasswordStrength(password) < 3) isValid = false;
            if (!checkPasswordMatch()) isValid = false;
            
            if (!terms) {
                showAlert('Vous devez accepter les conditions d\'utilisation', 'error');
                isValid = false;
            }
            
            return isValid;
        }
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i> ${message}`;
            
            const form = document.querySelector('.auth-form');
            form.insertBefore(alertDiv, form.firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>