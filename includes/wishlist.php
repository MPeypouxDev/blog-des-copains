<?php
session_start();
require_once '../config.php';
require_once '../database.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Récupérer les données JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Action manquante']);
    exit;
}

try {
    switch ($data['action']) {
        case 'add':
            if (!isset($data['anime_id'])) {
                throw new Exception('ID animé manquant');
            }
            
            $anime_id = (int)$data['anime_id'];
            
            // Vérifier si l'animé existe
            $stmt = $db->prepare("SELECT id FROM anime WHERE id = ?");
            $stmt->execute([$anime_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Animé non trouvé');
            }
            
            // Vérifier si l'animé n'est pas déjà dans la wishlist
            $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND anime_id = ?");
            $stmt->execute([$user_id, $anime_id]);
            if ($stmt->fetch()) {
                throw new Exception('Animé déjà dans la wishlist');
            }
            
            // Ajouter à la wishlist
            $stmt = $db->prepare("INSERT INTO wishlist (user_id, anime_id, added_date) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $anime_id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Animé ajouté à la wishlist',
                'wishlist_count' => getWishlistCount($db, $user_id)
            ]);
            break;

        case 'remove':
            if (!isset($data['anime_id'])) {
                throw new Exception('ID animé manquant');
            }
            
            $anime_id = (int)$data['anime_id'];
            
            // Supprimer de la wishlist
            $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND anime_id = ?");
            $stmt->execute([$user_id, $anime_id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Animé non trouvé dans la wishlist');
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Animé retiré de la wishlist',
                'wishlist_count' => getWishlistCount($db, $user_id)
            ]);
            break;

        case 'toggle':
            if (!isset($data['anime_id'])) {
                throw new Exception('ID animé manquant');
            }
            
            $anime_id = (int)$data['anime_id'];
            
            // Vérifier si l'animé est déjà dans la wishlist
            $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND anime_id = ?");
            $stmt->execute([$user_id, $anime_id]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Supprimer
                $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND anime_id = ?");
                $stmt->execute([$user_id, $anime_id]);
                $action_performed = 'removed';
                $message = 'Animé retiré de la wishlist';
            } else {
                // Vérifier que l'animé existe
                $stmt = $db->prepare("SELECT id FROM anime WHERE id = ?");
                $stmt->execute([$anime_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('Animé non trouvé');
                }
                
                // Ajouter
                $stmt = $db->prepare("INSERT INTO wishlist (user_id, anime_id, added_date) VALUES (?, ?, NOW())");
                $stmt->execute([$user_id, $anime_id]);
                $action_performed = 'added';
                $message = 'Animé ajouté à la wishlist';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'action' => $action_performed,
                'in_wishlist' => ($action_performed === 'added'),
                'wishlist_count' => getWishlistCount($db, $user_id)
            ]);
            break;

        case 'get_status':
            if (!isset($data['anime_id'])) {
                throw new Exception('ID animé manquant');
            }
            
            $anime_id = (int)$data['anime_id'];
            
            $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND anime_id = ?");
            $stmt->execute([$user_id, $anime_id]);
            $in_wishlist = (bool)$stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'in_wishlist' => $in_wishlist,
                'wishlist_count' => getWishlistCount($db, $user_id)
            ]);
            break;

        case 'get_recommendations':
            // Obtenir des recommandations basées sur la wishlist de l'utilisateur
            $stmt = $db->prepare("
                SELECT DISTINCT a.* 
                FROM anime a
                WHERE a.genre IN (
                    SELECT DISTINCT w_anime.genre 
                    FROM wishlist w 
                    JOIN anime w_anime ON w.anime_id = w_anime.id 
                    WHERE w.user_id = ?
                )
                AND a.id NOT IN (
                    SELECT anime_id FROM wishlist WHERE user_id = ?
                )
                ORDER BY a.rating DESC, RAND()
                LIMIT 6
            ");
            $stmt->execute([$user_id, $user_id]);
            $recommendations = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'recommendations' => $recommendations
            ]);
            break;

        case 'export':
            // Exporter la wishlist en CSV
            $stmt = $db->prepare("
                SELECT a.title, a.genre, a.year, a.rating, a.studio, w.added_date
                FROM wishlist w 
                JOIN anime a ON w.anime_id = a.id 
                WHERE w.user_id = ? 
                ORDER BY w.added_date DESC
            ");
            $stmt->execute([$user_id]);
            $wishlist_items = $stmt->fetchAll();
            
            // Créer le contenu CSV
            $csv_content = "Titre,Genre,Année,Note,Studio,Date d'ajout\n";
            foreach ($wishlist_items as $item) {
                $csv_content .= sprintf(
                    '"%s","%s","%s","%s","%s","%s"' . "\n",
                    str_replace('"', '""', $item['title']),
                    str_replace('"', '""', $item['genre']),
                    $item['year'],
                    $item['rating'],
                    str_replace('"', '""', $item['studio'] ?? ''),
                    date('d/m/Y', strtotime($item['added_date']))
                );
            }
            
            // Sauvegarder temporairement le fichier
            $filename = 'wishlist_' . $user_id . '_' . date('Y-m-d') . '.csv';
            $filepath = '../temp/' . $filename;
            
            // Créer le dossier temp s'il n'existe pas
            if (!file_exists('../temp/')) {
                mkdir('../temp/', 0755, true);
            }
            
            file_put_contents($filepath, $csv_content);
            
            echo json_encode([
                'success' => true,
                'message' => 'Export généré',
                'download_url' => 'temp/' . $filename
            ]);
            break;

        case 'clear_all':
            // Vider complètement la wishlist (avec confirmation côté client)
            $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $deleted_count = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "$deleted_count animés supprimés de la wishlist",
                'wishlist_count' => 0
            ]);
            break;

        default:
            throw new Exception('Action non reconnue');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Fonction utilitaire pour compter les éléments de la wishlist
function getWishlistCount($db, $user_id) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetch()['count'];
}
?>