<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();

// Récupération des paramètres de filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$genre = isset($_GET['genre']) ? $_GET['genre'] : '';
$year_min = isset($_GET['year_min']) ? (int)$_GET['year_min'] : '';
$year_max = isset($_GET['year_max']) ? (int)$_GET['year_max'] : '';
$rating_min = isset($_GET['rating_min']) ? (float)$_GET['rating_min'] : '';
$studio = isset($_GET['studio']) ? $_GET['studio'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'title';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR synopsis LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($genre)) {
    $where_conditions[] = "genre = ?";
    $params[] = $genre;
}

if (!empty($year_min)) {
    $where_conditions[] = "year >= ?";
    $params[] = $year_min;
}

if (!empty($year_max)) {
    $where_conditions[] = "year <= ?";
    $params[] = $year_max;
}

if (!empty($rating_min)) {
    $where_conditions[] = "rating >= ?";
    $params[] = $rating_min;
}

if (!empty($studio)) {
    $where_conditions[] = "studio LIKE ?";
    $params[] = "%$studio%";
}

if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Requête pour compter le total
$count_sql = "SELECT COUNT(*) FROM anime $where_clause";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_results = $count_stmt->fetchColumn();
$total_pages = ceil($total_results / $per_page);

// Requête principale avec tri et pagination
$valid_sorts = ['title', 'year', 'rating', 'created_at'];
$valid_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $valid_sorts)) $sort_by = 'title';
if (!in_array($sort_order, $valid_orders)) $sort_order = 'ASC';

$sql = "SELECT * FROM anime $where_clause ORDER BY $sort_by $sort_order LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$animes = $stmt->fetchAll();

// Récupérer les genres disponibles
$genres_stmt = $db->query("SELECT DISTINCT genre FROM anime WHERE genre IS NOT NULL ORDER BY genre");
$available_genres = $genres_stmt->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les studios disponibles
$studios_stmt = $db->query("SELECT DISTINCT studio FROM anime WHERE studio IS NOT NULL ORDER BY studio");
$available_studios = $studios_stmt->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les années disponibles
$years_stmt = $db->query("SELECT MIN(year) as min_year, MAX(year) as max_year FROM anime");
$year_range = $years_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche et Filtres - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/filters.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- En-tête avec statistiques -->
            <div class="search-header">
                <h1><i class="fas fa-search"></i> Découvrir les Animés</h1>
                <div class="search-stats">
                    <span class="results-count"><?php echo $total_results; ?> résultat(s)</span>
                    <?php if (!empty($search)): ?>
                        <span class="search-term">pour "<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="filters-layout">
                <!-- Panel de filtres -->
                <aside class="filters-panel">
                    <div class="filters-header">
                        <h3><i class="fas fa-filter"></i> Filtres</h3>
                        <button class="reset-filters" onclick="resetAllFilters()">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>

                    <form id="filtersForm" method="GET" action="">
                        <!-- Recherche textuelle -->
                        <div class="filter-group">
                            <label for="search"><i class="fas fa-search"></i> Recherche</label>
                            <div class="search-input">
                                <input type="text" 
                                       id="search" 
                                       name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Titre, synopsis...">
                                <button type="button" class="clear-search" onclick="clearSearch()" <?php echo empty($search) ? 'style="display:none"' : ''; ?>>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Genre -->
                        <div class="filter-group">
                            <label for="genre"><i class="fas fa-tags"></i> Genre</label>
                            <select id="genre" name="genre">
                                <option value="">Tous les genres</option>
                                <?php foreach($available_genres as $g): ?>
                                    <option value="<?php echo htmlspecialchars($g); ?>" 
                                            <?php echo $genre === $g ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Année -->
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Année de sortie</label>
                            <div class="year-range">
                                <input type="number" 
                                       name="year_min" 
                                       placeholder="De" 
                                       min="<?php echo $year_range['min_year']; ?>" 
                                       max="<?php echo $year_range['max_year']; ?>"
                                       value="<?php echo $year_min; ?>">
                                <span>à</span>
                                <input type="number" 
                                       name="year_max" 
                                       placeholder="À" 
                                       min="<?php echo $year_range['min_year']; ?>" 
                                       max="<?php echo $year_range['max_year']; ?>"
                                       value="<?php echo $year_max; ?>">
                            </div>
                        </div>

                        <!-- Note minimum -->
                        <div class="filter-group">
                            <label for="rating_min"><i class="fas fa-star"></i> Note minimum</label>
                            <div class="rating-slider">
                                <input type="range" 
                                       id="rating_min" 
                                       name="rating_min" 
                                       min="0" 
                                       max="10" 
                                       step="0.5"
                                       value="<?php echo $rating_min ?: 0; ?>">
                                <div class="rating-value">
                                    <span id="ratingValue"><?php echo $rating_min ?: 0; ?></span>/10
                                </div>
                            </div>
                        </div>

                        <!-- Studio -->
                        <div class="filter-group">
                            <label for="studio"><i class="fas fa-building"></i> Studio</label>
                            <select id="studio" name="studio">
                                <option value="">Tous les studios</option>
                                <?php foreach($available_studios as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>" 
                                            <?php echo $studio === $s ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Statut -->
                        <div class="filter-group">
                            <label for="status"><i class="fas fa-play-circle"></i> Statut</label>
                            <select id="status" name="status">
                                <option value="">Tous les statuts</option>
                                <option value="ongoing" <?php echo $status === 'ongoing' ? 'selected' : ''; ?>>En cours</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Terminé</option>
                                <option value="upcoming" <?php echo $status === 'upcoming' ? 'selected' : ''; ?>>À venir</option>
                            </select>
                        </div>

                        <button type="submit" class="apply-filters">
                            <i class="fas fa-search"></i> Appliquer les filtres
                        </button>
                    </form>

                    <!-- Filtres rapides -->
                    <div class="quick-filters">
                        <h4>Filtres rapides</h4>
                        <div class="quick-filter-buttons">
                            <a href="?genre=shonen" class="quick-filter <?php echo $genre === 'shonen' ? 'active' : ''; ?>">Shonen</a>
                            <a href="?genre=seinen" class="quick-filter <?php echo $genre === 'seinen' ? 'active' : ''; ?>">Seinen</a>
                            <a href="?genre=shoujo" class="quick-filter <?php echo $genre === 'shoujo' ? 'active' : ''; ?>">Shoujo</a>
                            <a href="?rating_min=8" class="quick-filter <?php echo $rating_min >= 8 ? 'active' : ''; ?>">Top rated</a>
                            <a href="?year_min=2020" class="quick-filter <?php echo $year_min >= 2020 ? 'active' : ''; ?>">Récents</a>
                        </div>
                    </div>
                </aside>

                <!-- Résultats -->
                <main class="results-content">
                    <!-- Barre de tri -->
                    <div class="sort-bar">
                        <div class="view-options">
                            <button class="view-btn active" data-view="grid">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button class="view-btn" data-view="list">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>

                        <div class="sort-options">
                            <label for="sortBy">Trier par :</label>
                            <select id="sortBy" name="sort_by" form="filtersForm">
                                <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Titre</option>
                                <option value="year" <?php echo $sort_by === 'year' ? 'selected' : ''; ?>>Année</option>
                                <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Note</option>
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date d'ajout</option>
                            </select>

                            <select id="sortOrder" name="sort_order" form="filtersForm">
                                <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Croissant</option>
                                <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Décroissant</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filtres actifs -->
                    <?php if (!empty($search) || !empty($genre) || !empty($year_min) || !empty($year_max) || !empty($rating_min) || !empty($studio) || !empty($status)): ?>
                        <div class="active-filters">
                            <span class="active-filters-label">Filtres actifs :</span>
                            
                            <?php if (!empty($search)): ?>
                                <span class="filter-tag">
                                    Recherche: "<?php echo htmlspecialchars($search); ?>"
                                    <a href="<?php echo removeParam('search'); ?>"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($genre)): ?>
                                <span class="filter-tag">
                                    Genre: <?php echo htmlspecialchars($genre); ?>
                                    <a href="<?php echo removeParam('genre'); ?>"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($year_min) || !empty($year_max)): ?>
                                <span class="filter-tag">
                                    Année: <?php echo $year_min ?: '∞'; ?>-<?php echo $year_max ?: '∞'; ?>
                                    <a href="<?php echo removeParam(['year_min', 'year_max']); ?>"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($rating_min)): ?>
                                <span class="filter-tag">
                                    Note: ≥<?php echo $rating_min; ?>
                                    <a href="<?php echo removeParam('rating_min'); ?>"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Grille des résultats -->
                    <?php if (empty($animes)): ?>
                        <div class="no-results">
                            <div class="no-results-icon">
                                <i class="fas fa-search-minus"></i>
                            </div>
                            <h3>Aucun résultat trouvé</h3>
                            <p>Essayez de modifier vos critères de recherche ou de supprimer certains filtres.</p>
                            <button class="btn btn-primary" onclick="resetAllFilters()">
                                <i class="fas fa-refresh"></i> Réinitialiser les filtres
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="anime-grid" id="resultsGrid">
                            <?php foreach($animes as $anime): ?>
                                <article class="anime-card" data-anime-id="<?php echo $anime['id']; ?>">
                                    <div class="anime-image-container">
                                        <?php if ($anime['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($anime['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($anime['title']); ?>" 
                                                 class="anime-image">
                                        <?php else: ?>
                                            <div class="anime-image placeholder">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="anime-overlay">
                                            <div class="anime-actions">
                                                <button class="action-btn wishlist-btn" data-anime-id="<?php echo $anime['id']; ?>" title="Ajouter à la wishlist">
                                                    <i class="fas fa-heart"></i>
                                                </button>
                                                <a href="../reviews.php?anime=<?php echo $anime['id']; ?>" class="action-btn" title="Voir les critiques">
                                                    <i class="fas fa-comments"></i>
                                                </a>
                                                <button class="action-btn share-btn" data-title="<?php echo htmlspecialchars($anime['title']); ?>" title="Partager">
                                                    <i class="fas fa-share"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <?php if ($anime['rating']): ?>
                                            <div class="rating-badge">
                                                <i class="fas fa-star"></i>
                                                <?php echo $anime['rating']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="anime-info">
                                        <h3><?php echo htmlspecialchars($anime['title']); ?></h3>
                                        <div class="anime-meta">
                                            <span class="genre"><?php echo htmlspecialchars($anime['genre']); ?></span>
                                            <span class="year"><?php echo $anime['year']; ?></span>
                                            <?php if ($anime['studio']): ?>
                                                <span class="studio"><?php echo htmlspecialchars($anime['studio']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($anime['synopsis']): ?>
                                            <p class="synopsis"><?php echo htmlspecialchars(substr($anime['synopsis'], 0, 120)) . '...'; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php
                                $current_params = $_GET;
                                unset($current_params['page']);
                                $base_url = '?' . http_build_query($current_params);
                                $base_url .= empty($current_params) ? 'page=' : '&page=';
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo $base_url . ($page-1); ?>" class="page-btn">
                                        <i class="fas fa-chevron-left"></i> Précédent
                                    </a>
                                <?php endif; ?>
                                
                                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                    <a href="<?php echo $base_url . $i; ?>" 
                                       class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo $base_url . ($page+1); ?>" class="page-btn">
                                        Suivant <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <span class="page-info">
                                    Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/filters.js"></script>
    <script>
        // Fonctions PHP converties en JavaScript pour l'interactivité
        function resetAllFilters() {
            window.location.href = 'filters.php';
        }
        
        function clearSearch() {
            document.getElementById('search').value = '';
            document.getElementById('filtersForm').submit();
        }
        
        // Soumission automatique du formulaire lors des changements
        document.getElementById('sortBy').addEventListener('change', function() {
            document.getElementById('filtersForm').submit();
        });
        
        document.getElementById('sortOrder').addEventListener('change', function() {
            document.getElementById('filtersForm').submit();
        });
        
        // Slider de rating
        document.getElementById('rating_min').addEventListener('input', function() {
            document.getElementById('ratingValue').textContent = this.value;
        });
    </script>
</body>
</html>

<?php
// Fonction utilitaire pour supprimer un paramètre de l'URL
function removeParam($param) {
    $params = $_GET;
    if (is_array($param)) {
        foreach ($param as $p) {
            unset($params[$p]);
        }
    } else {
        unset($params[$param]);
    }
    return '?' . http_build_query($params);
}
?>