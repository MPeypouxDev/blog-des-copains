<?php
session_start();
require_once '../config/database.php';

// Paramètres de recherche
$search_query = $_GET['q'] ?? '';
$category_filter = $_GET['category'] ?? '';
$rating_min = $_GET['rating_min'] ?? '';
$rating_max = $_GET['rating_max'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

try {
    try {
    $pdo = getDB();
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
    
    // Construction de la requête dynamique
    $where_conditions = [];
    $params = [];
    
    // Recherche textuelle dans titre, description, genre
    if (!empty($search_query)) {
        $where_conditions[] = "(a.title LIKE ? OR a.description LIKE ? OR a.genre LIKE ?)";
        $search_term = '%' . $search_query . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Filtre par catégorie
    if (!empty($category_filter)) {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM anime_categories ac 
            JOIN categories c ON ac.category_id = c.id 
            WHERE ac.anime_id = a.id AND c.id = ?
        )";
        $params[] = $category_filter;
    }
    
    // Filtre par note moyenne
    if (!empty($rating_min)) {
        $where_conditions[] = "avg_rating >= ?";
        $params[] = (float)$rating_min;
    }
    
    if (!empty($rating_max)) {
        $where_conditions[] = "avg_rating <= ?";
        $params[] = (float)$rating_max;
    }
    
    // Filtre par statut
    if (!empty($status_filter)) {
        $where_conditions[] = "a.status = ?";
        $params[] = $status_filter;
    }
    
    // Construction de la clause WHERE
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Validation du tri
    $allowed_sorts = ['created_at', 'title', 'avg_rating', 'rating_count', 'episodes'];
    if (!in_array($sort_by, $allowed_sorts)) {
        $sort_by = 'created_at';
    }
    $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Requête principale avec pagination
    $sql = "
        SELECT 
            a.*,
            COALESCE(AVG(ur.rating), 0) as avg_rating,
            COUNT(ur.rating) as rating_count,
            GROUP_CONCAT(DISTINCT c.name ORDER BY c.name ASC) as categories
        FROM anime_posts a
        LEFT JOIN user_ratings ur ON a.id = ur.anime_id
        LEFT JOIN anime_categories ac ON a.id = ac.anime_id
        LEFT JOIN categories c ON ac.category_id = c.id
        $where_clause
        GROUP BY a.id
        HAVING 1=1 " . (!empty($rating_min) || !empty($rating_max) ? "" : "") . "
        ORDER BY $sort_by $sort_order
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Comptage total pour pagination
    $count_sql = "
        SELECT COUNT(DISTINCT a.id) as total
        FROM anime_posts a
        LEFT JOIN user_ratings ur ON a.id = ur.anime_id
        LEFT JOIN anime_categories ac ON a.id = ac.anime_id
        LEFT JOIN categories c ON ac.category_id = c.id
        $where_clause
    ";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $per_page);
    
    // Récupération des catégories pour le filtre
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
    
    // Statistiques de recherche
    $stats = [
        'min_rating' => $pdo->query("SELECT MIN(rating) FROM user_ratings")->fetchColumn() ?: 1,
        'max_rating' => $pdo->query("SELECT MAX(rating) FROM user_ratings")->fetchColumn() ?: 10,
        'total_animes' => $pdo->query("SELECT COUNT(*) FROM anime_posts")->fetchColumn()
    ];
    
} catch (PDOException $e) {
    die("Erreur de recherche : " . $e->getMessage());
}

// Fonction pour construire les URLs de pagination/tri
function buildUrl($params = []) {
    $current_params = $_GET;
    $merged_params = array_merge($current_params, $params);
    return 'search.php?' . http_build_query(array_filter($merged_params));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche Avancée - Blog des Copains</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .search-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .search-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .search-header h1 {
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .search-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .results-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .results-info {
            color: #666;
            font-size: 1.1rem;
        }
        
        .sort-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .sort-controls select {
            padding: 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .anime-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .anime-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .anime-image {
            height: 200px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .anime-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .rating-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0,0,0,0.8);
            color: #ffd43b;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .anime-content {
            padding: 1.5rem;
        }
        
        .anime-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .anime-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .anime-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .category-tag {
            background: #f8f9fa;
            color: #667eea;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .anime-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .anime-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.75rem 1rem;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .pagination a {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e0e0e0;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
            border: 2px solid #667eea;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-results i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .search-row {
                grid-template-columns: 1fr;
            }
            
            .results-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .sort-controls {
                justify-content: center;
            }
            
            .results-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="search-container">
        <!-- Header de recherche -->
        <div class="search-header">
            <h1><i class="fas fa-search"></i> Recherche Avancée</h1>
            
            <form class="search-form" method="GET" action="">
                <!-- Barre de recherche principale -->
                <div class="form-group">
                    <label><i class="fas fa-search"></i> Rechercher un animé</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" 
                           placeholder="Titre, description, genre...">
                </div>
                
                <div class="search-row">
                    <!-- Filtre par catégorie -->
                    <div class="form-group">
                        <label><i class="fas fa-tags"></i> Catégorie</label>
                        <select name="category">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" 
                                    <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Note minimale -->
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Note min.</label>
                        <input type="number" name="rating_min" min="<?= $stats['min_rating'] ?>" 
                               max="<?= $stats['max_rating'] ?>" step="0.1" 
                               value="<?= htmlspecialchars($rating_min) ?>" 
                               placeholder="<?= $stats['min_rating'] ?>">
                    </div>
                    
                    <!-- Note maximale -->
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Note max.</label>
                        <input type="number" name="rating_max" min="<?= $stats['min_rating'] ?>" 
                               max="<?= $stats['max_rating'] ?>" step="0.1" 
                               value="<?= htmlspecialchars($rating_max) ?>" 
                               placeholder="<?= $stats['max_rating'] ?>">
                    </div>
                    
                    <!-- Statut -->
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Statut</label>
                        <select name="status">
                            <option value="">Tous les statuts</option>
                            <option value="ongoing" <?= $status_filter == 'ongoing' ? 'selected' : '' ?>>En cours</option>
                            <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Terminé</option>
                            <option value="upcoming" <?= $status_filter == 'upcoming' ? 'selected' : '' ?>>À venir</option>
                        </select>
                    </div>
                </div>
                
                <div class="search-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                    <a href="search.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Section des résultats -->
        <div class="results-section">
            <div class="results-header">
                <div class="results-info">
                    <strong><?= number_format($total_results) ?></strong> résultat<?= $total_results > 1 ? 's' : '' ?> trouvé<?= $total_results > 1 ? 's' : '' ?>
                    <?php if (!empty($search_query)): ?>
                    pour "<strong><?= htmlspecialchars($search_query) ?></strong>"
                    <?php endif; ?>
                </div>
                
                <div class="sort-controls">
                    <label>Trier par :</label>
                    <select onchange="location.href='<?= buildUrl(['sort' => '']) ?>' + this.value + '&order=' + document.getElementById('sort-order').value">
                        <option value="created_at" <?= $sort_by == 'created_at' ? 'selected' : '' ?>>Date de création</option>
                        <option value="title" <?= $sort_by == 'title' ? 'selected' : '' ?>>Titre</option>
                        <option value="avg_rating" <?= $sort_by == 'avg_rating' ? 'selected' : '' ?>>Note moyenne</option>
                        <option value="rating_count" <?= $sort_by == 'rating_count' ? 'selected' : '' ?>>Popularité</option>
                        <option value="episodes" <?= $sort_by == 'episodes' ? 'selected' : '' ?>>Nombre d'épisodes</option>
                    </select>
                    
                    <select id="sort-order" onchange="location.href='<?= buildUrl(['order' => '']) ?>' + this.value + '&sort=' + document.querySelector('select').value">
                        <option value="DESC" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Décroissant</option>
                        <option value="ASC" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Croissant</option>
                    </select>
                </div>
            </div>
            
            <?php if (empty($results)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>Aucun résultat trouvé</h3>
                <p>Essayez de modifier vos critères de recherche</p>
            </div>
            <?php else: ?>
            
            <div class="results-grid">
                <?php foreach ($results as $anime): ?>
                <div class="anime-card">
                    <div class="anime-image">
                        <?php if (!empty($anime['image_url'])): ?>
                        <img src="<?= htmlspecialchars($anime['image_url']) ?>" alt="<?= htmlspecialchars($anime['title']) ?>">
                        <?php else: ?>
                        <i class="fas fa-film"></i>
                        <?php endif; ?>
                        
                        <?php if ($anime['avg_rating'] > 0): ?>
                        <div class="rating-badge">
                            <i class="fas fa-star"></i>
                            <?= number_format($anime['avg_rating'], 1) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="anime-content">
                        <h3 class="anime-title"><?= htmlspecialchars($anime['title']) ?></h3>
                        
                        <div class="anime-meta">
                            <?php if (!empty($anime['year'])): ?>
                            <span><i class="fas fa-calendar"></i> <?= $anime['year'] ?></span>
                            <?php endif; ?>
                            <?php if (!empty($anime['episodes'])): ?>
                            <span><i class="fas fa-tv"></i> <?= $anime['episodes'] ?> ép.</span>
                            <?php endif; ?>
                            <?php if ($anime['rating_count'] > 0): ?>
                            <span><i class="fas fa-heart"></i> <?= $anime['rating_count'] ?> notes</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($anime['categories'])): ?>
                        <div class="anime-categories">
                            <?php foreach (explode(',', $anime['categories']) as $category): ?>
                            <span class="category-tag"><?= trim(htmlspecialchars($category)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($anime['description'])): ?>
                        <p class="anime-description"><?= htmlspecialchars($anime['description']) ?></p>
                        <?php endif; ?>
                        
                        <div class="anime-actions">
                            <a href="../anime.php?id=<?= $anime['id'] ?>" class="btn-sm btn-outline">
                                <i class="fas fa-eye"></i> Voir détails
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="<?= buildUrl(['page' => $page - 1]) ?>">
                    <i class="fas fa-chevron-left"></i> Précédent
                </a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                <?php if ($i == $page): ?>
                <span class="current"><?= $i ?></span>
                <?php else: ?>
                <a href="<?= buildUrl(['page' => $i]) ?>"><?= $i ?></a>
                <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="<?= buildUrl(['page' => $page + 1]) ?>">
                    Suivant <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</body>
</html>