<?php
// admin/dashboard.php
session_start();

// Vérification authentification admin
require_once '../config/database.php';
require_once 'includes/admin_auth.php';

// Vérifier si l'utilisateur est admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Statistiques générales
    $stats = [
        'total_animes' => $pdo->query("SELECT COUNT(*) FROM anime_posts")->fetchColumn(),
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_comments' => $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
        'pending_comments' => $pdo->query("SELECT COUNT(*) FROM comments WHERE is_approved = FALSE")->fetchColumn(),
        'flagged_comments' => $pdo->query("SELECT COUNT(*) FROM comments WHERE is_flagged = TRUE")->fetchColumn(),
        'total_ratings' => $pdo->query("SELECT COUNT(*) FROM user_ratings")->fetchColumn(),
        'avg_rating' => $pdo->query("SELECT AVG(rating) FROM user_ratings")->fetchColumn(),
        'new_users_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn()
    ];
    
    // Activité récente - derniers commentaires
    $recent_comments = $pdo->query("
        SELECT c.content, c.created_at, u.username, a.title as anime_title, c.is_approved
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        JOIN anime_posts a ON c.anime_id = a.id 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    // Top animés par note
    $top_animes = $pdo->query("
        SELECT a.title, AVG(r.rating) as avg_rating, COUNT(r.rating) as rating_count
        FROM anime_posts a
        LEFT JOIN user_ratings r ON a.id = r.anime_id
        GROUP BY a.id, a.title
        HAVING rating_count > 0
        ORDER BY avg_rating DESC
        LIMIT 5
    ")->fetchAll();
    
    // Utilisateurs les plus actifs
    $active_users = $pdo->query("
        SELECT u.username, 
               COUNT(DISTINCT c.id) as comment_count,
               COUNT(DISTINCT r.id) as rating_count,
               u.created_at
        FROM users u
        LEFT JOIN comments c ON u.id = c.user_id
        LEFT JOIN user_ratings r ON u.id = r.user_id
        GROUP BY u.id, u.username, u.created_at
        ORDER BY (comment_count + rating_count) DESC
        LIMIT 5
    ")->fetchAll();
    
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>