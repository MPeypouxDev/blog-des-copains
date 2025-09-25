<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once '../config/database.php';

try {
    try {
    $pdo = getDB();
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'search';
    
    switch ($action) {
        case 'search':
            handleSearch($pdo);
            break;
        case 'suggestions':
            handleSuggestions($pdo);
            break;
        case 'filters':
            handleFilters($pdo);
            break;
        case 'stats':
            handleStats($pdo);
            break;
        default:
            throw new Exception('Action non supportée');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleSearch($pdo) {
    // Paramètres de recherche
    $search_query = $_GET['q'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $rating_min = $_GET['rating_min'] ?? '';
    $rating_max = $_GET['rating_max'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $genre_filter = $_GET['genre'] ?? '';
    $year_filter = $_GET['year'] ?? '';
    $sort_by = $_GET['sort'] ?? 'created_at';
    $sort_order = $_GET['order'] ?? 'DESC';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = min(50, max(1, (int)($_GET['per_page'] ?? 12)));
    $offset = ($page - 1) * $per_page;
    
    // Construction dynamique de la requête
    $where_conditions = [];
    $params = [];
    
    // Recherche textuelle avec pondération
    if (!empty($search_query)) {
        $where_conditions[] = "(
            MATCH(a.title, a.description) AGAINST(? IN NATURAL LANGUAGE MODE) 
            OR a.title LIKE ? 
            OR a.description LIKE ? 
            OR a.genre LIKE ?
        )";
        $search_term = '%' . $search_query . '%';
        $params[] = $search_query;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Filtre par catégorie
    if (!empty($category_filter)) {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM anime_categories ac 
            WHERE ac.anime_id = a.id AND ac.category_id = ?
        )";
        $params[] = $category_filter;
    }
    
    // Filtre par genre
    if (!empty($genre_filter)) {
        $where_conditions[] = "FIND_IN_SET(?, REPLACE(a.genre, ' ', ''))";
        $params[] = str_replace(' ', '', $genre_filter);
    }
    
    // Filtre par année
    if (!empty($year_filter)) {
        $where_conditions[] = "a.year = ?";
        $params[] = (int)$year_filter;
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
    $allowed_sorts = [
        'created_at' => 'a.created_at',
        'updated_at' => 'a.updated_at', 
        'title' => 'a.title',
        'year' => 'a.year',
        'episodes' => 'a.episodes',
        'avg_rating' => 'avg_rating',
        'rating_count' => 'rating_count',
        'relevance' => !empty($search_query) ? 'MATCH(a.title, a.description) AGAINST(? IN NATURAL LANGUAGE MODE)' : 'a.created_at'
    ];
    
    $sort_column = $allowed_sorts[$sort_by] ?? $allowed_sorts['created_at'];
    $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Ajout du paramètre pour le tri par pertinence
    $relevance_params = [];
    if ($sort_by === 'relevance' && !empty($search_query)) {
        $relevance_params[] = $search_query;
    }
    
    // Filtre par note avec agrégation
    $having_conditions = [];
    $having_params = [];
    
    if (!empty($rating_min)) {
        $having_conditions[] = "avg_rating >= ?";
        $having_params[] = (float)$rating_min;
    }
    
    if (!empty($rating_max)) {
        $having_conditions[] = "avg_rating <= ?";
        $having_params[] = (float)$rating_max;
    }
    
    $having_clause = '';
    if (!empty($having_conditions)) {
        $having_clause = 'HAVING ' . implode(' AND ', $having_conditions);
    }
    
    // Requête principale optimisée
    $sql = "
        SELECT 
            a.*,
            COALESCE(AVG(ur.rating), 0) as avg_rating,
            COUNT(ur.rating) as rating_count,
            COUNT(DISTINCT c.id) as comment_count,
            GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name ASC) as categories,
            GROUP_CONCAT(DISTINCT cat.id ORDER BY cat.name ASC) as category_ids
        FROM anime_posts a
        LEFT JOIN user_ratings ur ON a.id = ur.anime_id AND ur.is_public = TRUE
        LEFT JOIN comments c ON a.id = c.anime_id AND c.is_approved = TRUE
        LEFT JOIN anime_categories ac ON a.id = ac.anime_id
        LEFT JOIN categories cat ON ac.category_id = cat.id
        $where_clause
        GROUP BY a.id
        $having_clause
        ORDER BY $sort_column $sort_order
        LIMIT $per_page OFFSET $offset
    ";
    
    // Fusion des paramètres
    $all_params = array_merge($relevance_params, $params, $having_params);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $results = $stmt->fetchAll();
    
    // Comptage total pour pagination
    $count_sql = "
        SELECT COUNT(DISTINCT a.id) as total
        FROM anime_posts a
        LEFT JOIN user_ratings ur ON a.id = ur.anime_id AND ur.is_public = TRUE
        LEFT JOIN anime_categories ac ON a.id = ac.anime_id
        LEFT JOIN categories cat ON ac.category_id = cat.id
        $where_clause
        GROUP BY a.id
        $having_clause
    ";
    
    // Pour le count, on retire les paramètres de pertinence
    $count_params = array_merge($params, $having_params);
    
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ($count_sql) as subquery");
    $count_stmt->execute($count_params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $per_page);
    
    // Formatage des résultats
    $formatted_results = array_map(function($anime) {
        return [
            'id' => (int)$anime['id'],
            'title' => $anime['title'],
            'description' => $anime['description'],
            'genre' => $anime['genre'],
            'year' => (int)$anime['year'],
            'episodes' => (int)$anime['episodes'],
            'status' => $anime['status'],
            'image_url' => $anime['image_url'],
            'avg_rating' => round((float)$anime['avg_rating'], 1),
            'rating_count' => (int)$anime['rating_count'],
            'comment_count' => (int)$anime['comment_count'],
            'categories' => $anime['categories'] ? explode(',', $anime['categories']) : [],
            'category_ids' => $anime['category_ids'] ? array_map('intval', explode(',', $anime['category_ids'])) : [],
            'created_at' => $anime['created_at'],
            'updated_at' => $anime['updated_at']
        ];
    }, $results);
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_results,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_results' => (int)$total_results,
            'total_pages' => (int)$total_pages,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ],
        'filters_applied' => [
            'search_query' => $search_query,
            'category' => $category_filter,
            'rating_min' => $rating_min,
            'rating_max' => $rating_max,
            'status' => $status_filter,
            'genre' => $genre_filter,
            'year' => $year_filter,
            'sort_by' => $sort_by,
            'sort_order' => $sort_order
        ]
    ]);
}

function handleSuggestions($pdo) {
    $query = $_GET['q'] ?? '';
    $limit = min(10, max(1, (int)($_GET['limit'] ?? 5)));
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'suggestions' => []]);
        return;
    }
    
    // Suggestions d'animés
    $sql = "
        SELECT DISTINCT 
            a.id,
            a.title,
            a.year,
            a.image_url,
            COALESCE(AVG(ur.rating), 0) as avg_rating,
            MATCH(a.title, a.description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
        FROM anime_posts a
        LEFT JOIN user_ratings ur ON a.id = ur.anime_id
        WHERE a.title LIKE ? OR a.description LIKE ?
        GROUP BY a.id
        ORDER BY relevance DESC, avg_rating DESC
        LIMIT ?
    ";
    
    $search_term = '%' . $query . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$query, $search_term, $search_term, $limit]);
    $anime_suggestions = $stmt->fetchAll();
    
    // Suggestions de genres
    $genre_sql = "
        SELECT DISTINCT 
            TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(genre, ',', numbers.n), ',', -1)) as genre_name
        FROM anime_posts
        CROSS JOIN (
            SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
        ) numbers
        WHERE TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(genre, ',', numbers.n), ',', -1)) LIKE ?
        AND CHAR_LENGTH(genre) - CHAR_LENGTH(REPLACE(genre, ',', '')) >= numbers.n - 1
        LIMIT 3
    ";
    
    $stmt = $pdo->prepare($genre_sql);
    $stmt->execute([$search_term]);
    $genre_suggestions = $stmt->fetchColumn() ?: [];
    
    $formatted_suggestions = [
        'animes' => array_map(function($anime) {
            return [
                'id' => (int)$anime['id'],
                'title' => $anime['title'],
                'year' => (int)$anime['year'],
                'image_url' => $anime['image_url'],
                'avg_rating' => round((float)$anime['avg_rating'], 1),
                'type' => 'anime'
            ];
        }, $anime_suggestions),
        'genres' => array_map(function($genre) {
            return [
                'name' => trim($genre),
                'type' => 'genre'
            ];
        }, is_array($genre_suggestions) ? $genre_suggestions : [$genre_suggestions])
    ];
    
    echo json_encode([
        'success' => true,
        'suggestions' => $formatted_suggestions
    ]);
}

function handleFilters($pdo) {
    // Récupération des options de filtrage disponibles
    
    // Catégories
    $categories = $pdo->query("
        SELECT c.id, c.name, COUNT(ac.anime_id) as anime_count
        FROM categories c
        LEFT JOIN anime_categories ac ON c.id = ac.category_id
        LEFT JOIN anime_posts a ON ac.anime_id = a.id
        GROUP BY c.id, c.name
        ORDER BY anime_count DESC, c.name ASC
    ")->fetchAll();
    
    // Genres uniques
    $genres_result = $pdo->query("
        SELECT DISTINCT 
            TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(genre, ',', numbers.n), ',', -1)) as genre_name,
            COUNT(*) as anime_count
        FROM anime_posts
        CROSS JOIN (
            SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
        ) numbers
        WHERE CHAR_LENGTH(genre) - CHAR_LENGTH(REPLACE(genre, ',', '')) >= numbers.n - 1
        AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(genre, ',', numbers.n), ',', -1)) != ''
        GROUP BY genre_name
        ORDER BY anime_count DESC, genre_name ASC
    ")->fetchAll();
    
    // Années disponibles
    $years = $pdo->query("
        SELECT DISTINCT year, COUNT(*) as anime_count
        FROM anime_posts 
        WHERE year IS NOT NULL AND year > 0
        GROUP BY year 
        ORDER BY year DESC
    ")->fetchAll();
    
    // Statuts disponibles
    $statuses = $pdo->query("
        SELECT DISTINCT status, COUNT(*) as anime_count
        FROM anime_posts 
        WHERE status IS NOT NULL
        GROUP BY status
        ORDER BY anime_count DESC
    ")->fetchAll();
    
    // Plage de notes
    $rating_range = $pdo->query("
        SELECT 
            MIN(rating) as min_rating,
            MAX(rating) as max_rating,
            AVG(rating) as avg_rating,
            COUNT(*) as total_ratings
        FROM user_ratings
        WHERE is_public = TRUE
    ")->fetch();
    
    echo json_encode([
        'success' => true,
        'filters' => [
            'categories' => array_map(function($cat) {
                return [
                    'id' => (int)$cat['id'],
                    'name' => $cat['name'],
                    'anime_count' => (int)$cat['anime_count']
                ];
            }, $categories),
            'genres' => array_map(function($genre) {
                return [
                    'name' => trim($genre['genre_name']),
                    'anime_count' => (int)$genre['anime_count']
                ];
            }, $genres_result),
            'years' => array_map(function($year) {
                return [
                    'year' => (int)$year['year'],
                    'anime_count' => (int)$year['anime_count']
                ];
            }, $years),
            'statuses' => array_map(function($status) {
                return [
                    'status' => $status['status'],
                    'anime_count' => (int)$status['anime_count']
                ];
            }, $statuses),
            'rating_range' => [
                'min' => $rating_range ? round((float)$rating_range['min_rating'], 1) : 1.0,
                'max' => $rating_range ? round((float)$rating_range['max_rating'], 1) : 10.0,
                'avg' => $rating_range ? round((float)$rating_range['avg_rating'], 1) : 5.0,
                'total_ratings' => $rating_range ? (int)$rating_range['total_ratings'] : 0
            ]
        ]
    ]);
}

function handleStats($pdo) {
    // Statistiques générales de recherche
    
    $stats = [
        'total_animes' => $pdo->query("SELECT COUNT(*) FROM anime_posts")->fetchColumn(),
        'total_categories' => $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
        'total_ratings' => $pdo->query("SELECT COUNT(*) FROM user_ratings WHERE is_public = TRUE")->fetchColumn(),
        'avg_rating' => $pdo->query("SELECT AVG(rating) FROM user_ratings WHERE is_public = TRUE")->fetchColumn(),
        'most_popular_genre' => $pdo->query("
            SELECT 
                TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(genre, ',', numbers.n), ',', -1)) as genre_name,
                COUNT(*) as count
            FROM anime_posts
            CROSS JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) numbers
            WHERE CHAR_LENGTH(genre) - CHAR_LENGTH(REPLACE(genre, ',', '')) >= numbers.n - 1
            GROUP BY genre_name
            ORDER BY count DESC
            LIMIT 1
        ")->fetch(),
        'recent_searches' => [
            // Ici tu pourrais tracker les recherches populaires
            'top_keywords' => ['action', 'romance', 'shonen', 'seinen', 'comedy']
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_animes' => (int)$stats['total_animes'],
            'total_categories' => (int)$stats['total_categories'],
            'total_ratings' => (int)$stats['total_ratings'],
            'avg_rating' => $stats['avg_rating'] ? round((float)$stats['avg_rating'], 1) : 0,
            'most_popular_genre' => $stats['most_popular_genre'] ? $stats['most_popular_genre']['genre_name'] : 'Action',
            'recent_searches' => $stats['recent_searches']
        ]
    ]);
}
?>