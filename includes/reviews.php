<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$db = getDB();

// Récupérer l'ID de l'animé depuis l'URL
$anime_id = isset($_GET['anime']) ? (int)$_GET['anime'] : 1;

// Récupérer les informations de l'animé
$stmt = $db->prepare("
    SELECT a.*, 
           COUNT(r.id) as total_reviews,
           ROUND(AVG(r.rating), 1) as avg_rating
    FROM anime a
    LEFT JOIN reviews r ON a.id = r.anime_id
    WHERE a.id = ?
    GROUP BY a.id
");
$stmt->execute([$anime_id]);
$anime = $stmt->fetch();

if (!$anime) {
    header('Location: index.php');
    exit;
}

// Récupérer les critiques avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare("
    SELECT r.*, u.username, u.avatar, u.level,
           DATE_FORMAT(r.created_at, '%d/%m/%Y à %H:%i') as formatted_date
    FROM reviews r
    INNER JOIN users u ON r.user_id = u.id
    WHERE r.anime_id = ?
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute([$anime_id]);
$reviews = $stmt->fetchAll();

// Calculer le nombre total de pages
$stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE anime_id = ?");
$stmt->execute([$anime_id]);
$total_reviews = $stmt->fetchColumn();
$total_pages = ceil($total_reviews / $per_page);

// Traitement du formulaire de critique
if ($_POST && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    if ($rating >= 1 && $rating <= 10 && !empty($comment)) {
        // Vérifier si l'utilisateur a déjà commenté cet animé
        $stmt = $db->prepare("SELECT id FROM reviews WHERE user_id = ? AND anime_id = ?");
        $stmt->execute([$user_id, $anime_id]);
        
        if ($stmt->fetch()) {
            setFlashMessage('error', 'Vous avez déjà noté cet animé !');
        } else {
            $stmt = $db->prepare("
                INSERT INTO reviews (user_id, anime_id, rating, comment, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            if ($stmt->execute([$user_id, $anime_id, $rating, $comment])) {
                setFlashMessage('success', 'Votre critique a été ajoutée avec succès !');
                header("Location: reviews.php?anime=$anime_id");
                exit;
            }
        }
    } else {
        setFlashMessage('error', 'Veuillez remplir tous les champs correctement.');
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Critiques - <?php echo htmlspecialchars($anime['title']); ?> | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/reviews.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- En-tête de l'animé -->
            <div class="anime-header">
                <div class="anime-poster">
                    <?php if ($anime['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($anime['image_url']); ?>" alt="<?php echo htmlspecialchars($anime['title']); ?>">
                    <?php else: ?>
                        <div class="poster-placeholder">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="anime-info">
                    <h1><?php echo htmlspecialchars($anime['title']); ?></h1>
                    <div class="anime-meta">
                        <span class="genre"><?php echo htmlspecialchars($anime['genre']); ?></span>
                        <span class="year"><?php echo $anime['year']; ?></span>
                        <span class="studio"><?php echo htmlspecialchars($anime['studio'] ?? 'Studio inconnu'); ?></span>
                    </div>
                    
                    <div class="rating-overview">
                        <div class="main-rating">
                            <span class="rating-score"><?php echo $anime['avg_rating'] ?: 'N/A'; ?></span>
                            <div class="rating-stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= ($anime['avg_rating']/2) ? 'filled' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-count"><?php echo $anime['total_reviews']; ?> avis</span>
                        </div>
                    </div>
                    
                    <?php if ($anime['synopsis']): ?>
                        <div class="synopsis">
                            <h3>Synopsis</h3>
                            <p><?php echo htmlspecialchars($anime['synopsis']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="anime-actions">
                        <button class="btn btn-primary add-to-wishlist" data-anime-id="<?php echo $anime['id']; ?>">
                            <i class="fas fa-heart"></i> Ajouter à ma liste
                        </button>
                        <button class="btn btn-secondary share-anime">
                            <i class="fas fa-share"></i> Partager
                        </button>
                    </div>
                </div>
            </div>

            <!-- Section des critiques -->
            <div class="reviews-section">
                <div class="reviews-header">
                    <h2>
                        <i class="fas fa-comments"></i> 
                        Critiques de la communauté (<?php echo $anime['total_reviews']; ?>)
                    </h2>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button class="btn btn-primary" onclick="toggleReviewForm()">
                            <i class="fas fa-pen"></i> Écrire une critique
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Formulaire de critique -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="review-form-container" id="reviewForm" style="display: none;">
                        <form class="review-form" method="POST">
                            <div class="rating-input">
                                <label>Votre note :</label>
                                <div class="star-rating">
                                    <?php for($i = 1; $i <= 10; $i++): ?>
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" id="star-<?php echo $i; ?>" required>
                                        <label for="star-<?php echo $i; ?>" class="star">
                                            <i class="fas fa-star"></i>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-display">0/10</span>
                            </div>
                            
                            <div class="comment-input">
                                <label for="comment">Votre critique :</label>
                                <textarea name="comment" id="comment" rows="5" placeholder="Partagez votre opinion sur cet animé..." required></textarea>
                                <div class="char-counter">
                                    <span id="charCount">0</span>/1000 caractères
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Publier ma critique
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="toggleReviewForm()">
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="login-prompt">
                        <p>
                            <i class="fas fa-sign-in-alt"></i>
                            <a href="auth/login.php">Connectez-vous</a> pour écrire une critique !
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Distribution des notes -->
                <?php if ($anime['total_reviews'] > 0): ?>
                    <div class="rating-distribution">
                        <h3>Distribution des notes</h3>
                        <div class="rating-bars">
                            <?php
                            // Calculer la distribution des notes
                            $stmt = $db->prepare("
                                SELECT rating, COUNT(*) as count 
                                FROM reviews 
                                WHERE anime_id = ? 
                                GROUP BY rating 
                                ORDER BY rating DESC
                            ");
                            $stmt->execute([$anime_id]);
                            $distribution = $stmt->fetchAll();
                            
                            $dist_array = array_fill(1, 10, 0);
                            foreach ($distribution as $item) {
                                $dist_array[$item['rating']] = $item['count'];
                            }
                            ?>
                            
                            <?php for($i = 10; $i >= 1; $i--): ?>
                                <div class="rating-bar">
                                    <span class="rating-label"><?php echo $i; ?> étoiles</span>
                                    <div class="bar-container">
                                        <div class="bar-fill" style="width: <?php echo $anime['total_reviews'] > 0 ? ($dist_array[$i] / $anime['total_reviews'] * 100) : 0; ?>%"></div>
                                    </div>
                                    <span class="rating-count"><?php echo $dist_array[$i]; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Liste des critiques -->
                <div class="reviews-list">
                    <?php if (empty($reviews)): ?>
                        <div class="no-reviews">
                            <i class="fas fa-comment-slash"></i>
                            <h3>Aucune critique pour le moment</h3>
                            <p>Soyez le premier à partager votre avis sur cet animé !</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <article class="review-card">
                                <div class="review-header">
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php if ($review['avatar']): ?>
                                                <img src="<?php echo htmlspecialchars($review['avatar']); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($review['username']); ?></h4>
                                            <div class="user-level">
                                                <i class="fas fa-star"></i>
                                                Niveau <?php echo $review['level'] ?? 1; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="review-meta">
                                        <div class="review-rating">
                                            <span class="rating-score"><?php echo $review['rating']; ?>/10</span>
                                            <div class="rating-stars">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= ($review['rating']/2) ? 'filled' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <span class="review-date"><?php echo $review['formatted_date']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="review-content">
                                    <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                                
                                <div class="review-actions">
                                    <button class="action-btn like-btn">
                                        <i class="fas fa-thumbs-up"></i>
                                        Utile <span class="count">12</span>
                                    </button>
                                    <button class="action-btn reply-btn">
                                        <i class="fas fa-reply"></i>
                                        Répondre
                                    </button>
                                    <button class="action-btn report-btn">
                                        <i class="fas fa-flag"></i>
                                        Signaler
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?anime=<?php echo $anime_id; ?>&page=<?php echo $page-1; ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        <?php endif; ?>
                        
                        <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <a href="?anime=<?php echo $anime_id; ?>&page=<?php echo $i; ?>" 
                               class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?anime=<?php echo $anime_id; ?>&page=<?php echo $page+1; ?>" class="page-btn">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Animés recommandés -->
            <div class="recommended-section">
                <h3><i class="fas fa-magic"></i> Vous pourriez aussi aimer</h3>
                <div class="anime-recommendations">
                    <?php
                    // Récupérer des animés similaires
                    $stmt = $db->prepare("
                        SELECT * FROM anime 
                        WHERE genre = ? AND id != ? 
                        ORDER BY RAND() 
                        LIMIT 4
                    ");
                    $stmt->execute([$anime['genre'], $anime_id]);
                    $recommendations = $stmt->fetchAll();
                    ?>
                    
                    <?php foreach ($recommendations as $rec): ?>
                        <div class="recommendation-card">
                            <a href="reviews.php?anime=<?php echo $rec['id']; ?>">
                                <?php if ($rec['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($rec['image_url']); ?>" alt="<?php echo htmlspecialchars($rec['title']); ?>">
                                <?php else: ?>
                                    <div class="rec-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="rec-info">
                                    <h4><?php echo htmlspecialchars($rec['title']); ?></h4>
                                    <span class="rec-rating">
                                        <i class="fas fa-star"></i>
                                        <?php echo $rec['rating'] ?? 'N/A'; ?>
                                    </span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/reviews.js"></script>
    <script>
        function toggleReviewForm() {
            const form = document.getElementById('reviewForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        // Gestion des étoiles
        document.querySelectorAll('.star-rating input').forEach((radio, index) => {
            radio.addEventListener('change', function() {
                document.querySelector('.rating-display').textContent = this.value + '/10';
                
                // Mettre à jour l'affichage des étoiles
                document.querySelectorAll('.star-rating .star').forEach((star, starIndex) => {
                    if (starIndex <= index) {
                        star.classList.add('selected');
                    } else {
                        star.classList.remove('selected');
                    }
                });
            });
        });

        // Compteur de caractères
        document.getElementById('comment')?.addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('charCount').textContent = count;
            
            if (count > 1000) {
                this.value = this.value.substring(0, 1000);
                document.getElementById('charCount').textContent = 1000;
            }
        });
    </script>
</body>
</html>