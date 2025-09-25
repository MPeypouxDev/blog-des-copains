<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <section class="hero">
            <h1>🎌 Blog des Copains - Monde des Animés</h1>
            <p>Découvrez les meilleurs animés avec vos amis !</p>
            <?php if(!isset($_SESSION['user_id'])): ?>
                <div class="hero-actions">
                    <a href="auth/register.php" class="btn btn-primary">Rejoindre la communauté</a>
                    <a href="auth/login.php" class="btn btn-secondary">Se connecter</a>
                </div>
            <?php endif; ?>
        </section>
        
        <section class="featured-anime">
            <h2>Animés en vedette</h2>
            <div class="anime-grid">
                <!-- Exemple de carte d'animé -->
                <article class="anime-card">
                    <img src="assets/img/one_piece_logo.jpg" alt="One Piece" class="anime-image">
                    <div class="anime-info">
                        <h3>One Piece</h3>
                        <p class="anime-genre">Shonen • Aventure</p>
                        <p class="anime-year">1999</p>
                        <div class="anime-rating">
                            ⭐ 9.2/10
                        </div>
                    </div>
                </article>
                
                <article class="anime-card">
                    <img src="assets/img/attack_on_titan_logo.jpg" alt="Attack on Titan" class="anime-image">
                    <div class="anime-info">
                        <h3>Attack on Titan</h3>
                        <p class="anime-genre">Seinen • Action</p>
                        <p class="anime-year">2013</p>
                        <div class="anime-rating">
                            ⭐ 9.0/10
                        </div>
                    </div>
                </article>
                
                <article class="anime-card">
                    <img src="assets/img/my_hero_academia_logo.jpg" alt="My Hero Academia" class="anime-image">
                    <div class="anime-info">
                        <h3>My Hero Academia</h3>
                        <p class="anime-genre">Shonen • Super-héros</p>
                        <p class="anime-year">2016</p>
                        <div class="anime-rating">
                            ⭐ 8.7/10
                        </div>
                    </div>
                </article>
            </div>
        </section>
        
        <section class="filters-preview">
            <h2>Parcourir par genre</h2>
            <div class="genre-filters">
                <button class="filter-btn active" data-genre="all">Tous</button>
                <button class="filter-btn" data-genre="shonen">Shonen</button>
                <button class="filter-btn" data-genre="seinen">Seinen</button>
                <button class="filter-btn" data-genre="shoujo">Shoujo</button>
                <button class="filter-btn" data-genre="slice-of-life">Slice of Life</button>
                <button class="filter-btn" data-genre="action">Action</button>
                <button class="filter-btn" data-genre="romance">Romance</button>
            </div>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
