<header class="main-header">
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                🎌 Blog des Copains
            </a>
            
            <ul class="nav-menu" id="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">Accueil</a>
                </li>
                <li class="nav-item">
                    <a href="pages/categories.php" class="nav-link">Catégories</a>
                </li>
                <li class="nav-item">
                    <a href="pages/search.php" class="nav-link">Recherche</a>
                </li>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            👤 <?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="auth/profile.php">Mon Profil</a></li>
                            <li><a href="auth/my-reviews.php">Mes Avis</a></li>
                            <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                                <li class="dropdown-divider"></li>
                                <li><a href="admin/dashboard.php">🔧 Administration</a></li>
                            <?php endif; ?>
                            <li class="dropdown-divider"></li>
                            <li><a href="auth/logout.php">Déconnexion</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="auth/login.php" class="nav-link">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a href="auth/register.php" class="nav-link btn-register">Inscription</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="hamburger" id="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>
    
    <!-- Messages flash -->
    <?php if($success = getFlashMessage('success')): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if($error = getFlashMessage('error')): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
</header>
