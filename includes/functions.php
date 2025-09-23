<?php
/**
 * Fonctions utilitaires pour le blog des animés
 */

/**
 * Nettoyer et sécuriser les données d'entrée
 */
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifier si l'utilisateur est admin
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Rediriger vers une page
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Générer un slug à partir d'un titre
 */
function create_slug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

/**
 * Formater une date en français
 */
function format_date_fr($date) {
    $months = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

/**
 * Calculer la note moyenne (placeholder pour plus tard)
 */
function calculate_average_rating($anime_id) {
    // TODO: Implémenter avec la base de données
    return 0.0;
}

/**
 * Afficher les étoiles pour une note
 */
function display_rating_stars($rating) {
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5;
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
    
    $stars = str_repeat('⭐', $full_stars);
    if ($half_star) $stars .= '🌟';
    $stars .= str_repeat('☆', $empty_stars);
    
    return $stars;
}

/**
 * Protection CSRF (à implémenter plus tard)
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// TODO pour les développeurs :
// - Fonctions de gestion des images (upload, resize, etc.) - Dev 3
// - Fonctions de pagination - Dev 1 & 4  
// - Fonctions de recherche avancée - Dev 3
// - Fonctions de cache - Dev 1
?>
