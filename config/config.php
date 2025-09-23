<?php
// Configuration générale du site
define('SITE_NAME', 'Blog des Copains - Animés');
define('SITE_URL', 'http://localhost/blog-des-copains');
define('SITE_VERSION', '1.0.0');

// Configuration de sécurité
define('SECURITY_SALT', 'anime-blog-2025-secure-key');

// Dossiers
define('UPLOAD_DIR', 'assets/images/uploads/');
define('AVATAR_DIR', 'assets/images/users/');
define('ANIME_IMAGE_DIR', 'assets/images/anime/');

// Pagination
define('POSTS_PER_PAGE', 12);
define('COMMENTS_PER_PAGE', 10);

// Catégories d'animés
define('ANIME_GENRES', [
    'shonen' => 'Shonen',
    'seinen' => 'Seinen', 
    'shoujo' => 'Shoujo',
    'slice-of-life' => 'Slice of Life',
    'action' => 'Action',
    'romance' => 'Romance',
    'comedy' => 'Comédie',
    'drama' => 'Drame',
    'fantasy' => 'Fantasy',
    'sci-fi' => 'Science-Fiction'
]);

// Timezone
date_default_timezone_set('Europe/Paris');

// Messages flash
if (!isset($_SESSION)) {
    session_start();
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}
?>
