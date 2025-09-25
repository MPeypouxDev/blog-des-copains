-- =============================================================================
-- STRUCTURE DE BASE DE DONNÉES POUR LE SYSTÈME D'AUTHENTIFICATION
-- Blog des Copains - Système d'animés
-- =============================================================================

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS `anime_blog_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `anime_blog_db`;

-- =============================================================================
-- TABLE PRINCIPALE DES UTILISATEURS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL UNIQUE,
    `email` varchar(100) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    
    -- Informations profil
    `first_name` varchar(50) DEFAULT NULL,
    `last_name` varchar(50) DEFAULT NULL,
    `avatar` varchar(255) DEFAULT NULL,
    `bio` text DEFAULT NULL,
    `birth_date` date DEFAULT NULL,
    `location` varchar(100) DEFAULT NULL,
    `website` varchar(255) DEFAULT NULL,
    
    -- Statut et vérification
    `is_active` tinyint(1) DEFAULT 1,
    `is_verified` tinyint(1) DEFAULT 0,
    `verification_token` varchar(64) DEFAULT NULL,
    `email_verified_at` timestamp NULL DEFAULT NULL,
    
    -- Sécurité et connexions
    `failed_attempts` int(11) DEFAULT 0,
    `last_attempt` timestamp NULL DEFAULT NULL,
    `password_reset_token` varchar(64) DEFAULT NULL,
    `password_reset_expires` timestamp NULL DEFAULT NULL,
    `two_factor_secret` varchar(32) DEFAULT NULL,
    `two_factor_enabled` tinyint(1) DEFAULT 0,
    
    -- Statistiques
    `login_count` int(11) DEFAULT 0,
    `last_login` timestamp NULL DEFAULT NULL,
    `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Préférences
    `timezone` varchar(50) DEFAULT 'Europe/Paris',
    `language` varchar(10) DEFAULT 'fr',
    `theme` enum('light','dark','auto') DEFAULT 'auto',
    `newsletter` tinyint(1) DEFAULT 1,
    `notifications_email` tinyint(1) DEFAULT 1,
    `privacy_level` enum('public','friends','private') DEFAULT 'public',
    
    -- Métadonnées
    `role` enum('user','moderator','admin') DEFAULT 'user',
    `level` int(11) DEFAULT 1,
    `experience_points` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_verified` (`is_verified`),
    INDEX `idx_last_activity` (`last_activity`),
    INDEX `idx_role` (`role`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE DES TOKENS "SE SOUVENIR DE MOI"
-- =============================================================================

CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `selector` varchar(32) NOT NULL,
    `token` varchar(64) NOT NULL,
    `expires_at` timestamp NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `last_used` timestamp NULL DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_token` (`user_id`, `selector`),
    INDEX `idx_selector` (`selector`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_user_id` (`user_id`),
    
    CONSTRAINT `fk_remember_tokens_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE DES SESSIONS ACTIVES
-- =============================================================================

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` varchar(128) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `payload` longtext NOT NULL,
    `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_last_activity` (`last_activity`),
    
    CONSTRAINT `fk_user_sessions_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE DES LOGS D'AUTHENTIFICATION
-- =============================================================================

CREATE TABLE IF NOT EXISTS `auth_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `action` enum('login','logout','register','password_reset','failed_login','account_locked') NOT NULL,
    `status` enum('success','failed','pending') NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `details` json DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_ip_address` (`ip_address`),
    
    CONSTRAINT `fk_auth_logs_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE DES TENTATIVES DE CONNEXION SUSPECTES
-- =============================================================================

CREATE TABLE IF NOT EXISTS `suspicious_activities` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` text DEFAULT NULL,
    `attempted_email` varchar(100) DEFAULT NULL,
    `activity_type` enum('brute_force','invalid_token','suspicious_location','rapid_requests') NOT NULL,
    `severity` enum('low','medium','high','critical') DEFAULT 'medium',
    `attempts_count` int(11) DEFAULT 1,
    `blocked_until` timestamp NULL DEFAULT NULL,
    `first_attempt` timestamp DEFAULT CURRENT_TIMESTAMP,
    `last_attempt` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_blocked` tinyint(1) DEFAULT 0,
    
    PRIMARY KEY (`id`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_activity_type` (`activity_type`),
    INDEX `idx_blocked` (`is_blocked`),
    INDEX `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE POUR LES CODES 2FA (SI IMPLÉMENTÉ PLUS TARD)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `two_factor_codes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `code` varchar(10) NOT NULL,
    `type` enum('email','sms','app') NOT NULL,
    `expires_at` timestamp NOT NULL,
    `used_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_code` (`code`),
    INDEX `idx_expires` (`expires_at`),
    
    CONSTRAINT `fk_two_factor_codes_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLES POUR LE CONTENU DU BLOG (EXISTANTES, MISE À JOUR SI NÉCESSAIRE)
-- =============================================================================

-- Table des animés (probablement existante)
CREATE TABLE IF NOT EXISTS `anime` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `slug` varchar(255) NOT NULL UNIQUE,
    `genre` varchar(100) NOT NULL,
    `year` int(4) NOT NULL,
    `rating` decimal(3,1) DEFAULT NULL,
    `synopsis` text,
    `image_url` varchar(500),
    `studio` varchar(100),
    `status` enum('ongoing','completed','upcoming') DEFAULT 'completed',
    `episodes` int(11),
    `duration` int(11),
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_slug` (`slug`),
    INDEX `idx_genre` (`genre`),
    INDEX `idx_year` (`year`),
    INDEX `idx_rating` (`rating`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`),
    
    CONSTRAINT `fk_anime_creator` 
        FOREIGN KEY (`created_by`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des critiques
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `anime_id` int(11) NOT NULL,
    `rating` decimal(2,1) NOT NULL CHECK (`rating` >= 0 AND `rating` <= 10),
    `comment` text NOT NULL,
    `is_spoiler` tinyint(1) DEFAULT 0,
    `likes_count` int(11) DEFAULT 0,
    `dislikes_count` int(11) DEFAULT 0,
    `is_featured` tinyint(1) DEFAULT 0,
    `moderation_status` enum('pending','approved','rejected') DEFAULT 'approved',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_anime_review` (`user_id`, `anime_id`),
    INDEX `idx_anime_id` (`anime_id`),
    INDEX `idx_rating` (`rating`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_featured` (`is_featured`),
    INDEX `idx_moderation` (`moderation_status`),
    
    CONSTRAINT `fk_reviews_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reviews_anime` 
        FOREIGN KEY (`anime_id`) 
        REFERENCES `anime` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des wishlists
CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `anime_id` int(11) NOT NULL,
    `priority` enum('low','medium','high') DEFAULT 'medium',
    `notes` text DEFAULT NULL,
    `watch_status` enum('to_watch','watching','completed','dropped','on_hold') DEFAULT 'to_watch',
    `personal_rating` decimal(3,1) DEFAULT NULL,
    `favorite` tinyint(1) DEFAULT 0,
    `added_date` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_anime_wishlist` (`user_id`, `anime_id`),
    INDEX `idx_anime_id` (`anime_id`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_watch_status` (`watch_status`),
    INDEX `idx_favorite` (`favorite`),
    INDEX `idx_added_date` (`added_date`),
    
    CONSTRAINT `fk_wishlist_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_wishlist_anime` 
        FOREIGN KEY (`anime_id`) 
        REFERENCES `anime` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DONNÉES D'EXEMPLE POUR TESTER
-- =============================================================================

-- Insertion de quelques animés de test
INSERT IGNORE INTO `anime` (`id`, `title`, `slug`, `genre`, `year`, `rating`, `synopsis`, `studio`, `episodes`) VALUES
(1, 'Attack on Titan', 'attack-on-titan', 'action', 2013, 9.0, 'Des géants mangent des humains, l\'humanité se bat pour survivre.', 'Mappa', 87),
(2, 'One Piece', 'one-piece', 'shonen', 1999, 9.2, 'Les aventures de Monkey D. Luffy et de son équipage de pirates.', 'Toei Animation', 1000),
(3, 'My Hero Academia', 'my-hero-academia', 'shonen', 2016, 8.7, 'Dans un monde où presque tout le monde a des super-pouvoirs.', 'Bones', 154),
(4, 'Spirited Away', 'spirited-away', 'fantasy', 2001, 9.3, 'Une petite fille découvre un monde magique rempli d\'esprits.', 'Studio Ghibli', 1),
(5, 'Death Note', 'death-note', 'thriller', 2006, 8.9, 'Un lycéen trouve un carnet qui tue quiconque dont le nom y est écrit.', 'Madhouse', 37),
(6, 'Demon Slayer', 'demon-slayer', 'action', 2019, 8.8, 'Un jeune garçon devient chasseur de démons pour sauver sa sœur.', 'Ufotable', 44);

-- Utilisateur administrateur par défaut (mot de passe: Admin123!)
INSERT IGNORE INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_active`, `is_verified`, `email_verified_at`) VALUES
(1, 'admin', 'admin@blogdescopains.fr', '$argon2id$v=19$m=65536,t=4,p=3$YWRtaW4xMjM$8vJ2lJ5kF3mN9pQ7rS6tU8wX2yZ3aB4cD5eF6gH7iJ8k', 'admin', 1, 1, NOW());

-- =============================================================================
-- PROCÉDURES STOCKÉES UTILES
-- =============================================================================

DELIMITER //

-- Procédure pour nettoyer les sessions expirées
CREATE PROCEDURE IF NOT EXISTS `CleanExpiredSessions`()
BEGIN
    -- Suppression des tokens "se souvenir de moi" expirés
    DELETE FROM `remember_tokens` WHERE `expires_at` < NOW();
    
    -- Suppression des sessions inactives (plus de 30 jours)
    DELETE FROM `user_sessions` WHERE `last_activity` < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Réinitialisation des tentatives de connexion anciennes (plus de 24h)
    UPDATE `users` 
    SET `failed_attempts` = 0, `last_attempt` = NULL 
    WHERE `last_attempt` < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    -- Suppression des codes 2FA expirés
    DELETE FROM `two_factor_codes` WHERE `expires_at` < NOW();
    
    -- Nettoyage des activités suspectes anciennes (plus de 7 jours)
    DELETE FROM `suspicious_activities` 
    WHERE `last_attempt` < DATE_SUB(NOW(), INTERVAL 7 DAY) 
    AND `is_blocked` = 0;
    
    -- Suppression des logs d'auth anciens (plus de 90 jours)
    DELETE FROM `auth_logs` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 90 DAY);
END //

-- Procédure pour obtenir les statistiques utilisateur
CREATE PROCEDURE IF NOT EXISTS `GetUserStats`(IN userId INT)
BEGIN
    SELECT 
        u.id,
        u.username,
        u.email,
        u.role,
        u.level,
        u.experience_points,
        u.login_count,
        u.last_login,
        u.created_at,
        
        -- Statistiques des reviews
        (SELECT COUNT(*) FROM reviews WHERE user_id = userId) as total_reviews,
        (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE user_id = userId) as avg_rating_given,
        
        -- Statistiques wishlist
        (SELECT COUNT(*) FROM wishlist WHERE user_id = userId) as wishlist_count,
        (SELECT COUNT(*) FROM wishlist WHERE user_id = userId AND favorite = 1) as favorites_count,
        
        -- Dernières activités
        (SELECT COUNT(*) FROM auth_logs WHERE user_id = userId AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as logins_last_30_days
        
    FROM users u WHERE u.id = userId;
END //

-- Fonction pour vérifier si un utilisateur est bloqué
CREATE FUNCTION IF NOT EXISTS `IsUserBlocked`(userEmail VARCHAR(100)) 
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE blocked BOOLEAN DEFAULT FALSE;
    DECLARE attempt_count INT DEFAULT 0;
    DECLARE last_attempt_time TIMESTAMP;
    
    SELECT failed_attempts, last_attempt 
    INTO attempt_count, last_attempt_time
    FROM users 
    WHERE email = userEmail OR username = userEmail;
    
    -- Bloqué si plus de 5 tentatives dans les 15 dernières minutes
    IF attempt_count >= 5 AND last_attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN
        SET blocked = TRUE;
    END IF;
    
    RETURN blocked;
END //

-- Procédure pour enregistrer une activité d'authentification
CREATE PROCEDURE IF NOT EXISTS `LogAuthActivity`(
    IN userId INT, 
    IN userEmail VARCHAR(100), 
    IN action_type VARCHAR(50), 
    IN status_type VARCHAR(20),
    IN ip_addr VARCHAR(45),
    IN user_agent_str TEXT,
    IN details_json JSON
)
BEGIN
    INSERT INTO auth_logs (user_id, email, action, status, ip_address, user_agent, details, created_at)
    VALUES (userId, userEmail, action_type, status_type, ip_addr, user_agent_str, details_json, NOW());
END //

DELIMITER ;

-- =============================================================================
-- VUES UTILES
-- =============================================================================

-- Vue pour les statistiques des utilisateurs actifs
CREATE OR REPLACE VIEW `active_users_stats` AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.last_activity,
    u.login_count,
    COUNT(DISTINCT r.id) as reviews_count,
    COUNT(DISTINCT w.id) as wishlist_count,
    ROUND(AVG(r.rating), 1) as avg_rating_given
FROM users u
LEFT JOIN reviews r ON u.id = r.user_id
LEFT JOIN wishlist w ON u.id = w.user_id
WHERE u.is_active = 1 
  AND u.last_activity > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY u.id, u.username, u.email, u.role, u.last_activity, u.login_count;

-- Vue pour les sessions actives
CREATE OR REPLACE VIEW `current_sessions` AS
SELECT 
    s.id as session_id,
    s.user_id,
    u.username,
    s.ip_address,
    s.last_activity,
    TIMESTAMPDIFF(MINUTE, s.last_activity, NOW()) as minutes_inactive,
    CASE 
        WHEN s.last_activity > DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 'active'
        WHEN s.last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'idle'
        ELSE 'expired'
    END as session_status
FROM user_sessions s
LEFT JOIN users u ON s.user_id = u.id
WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Vue pour les tentatives de connexion récentes
CREATE OR REPLACE VIEW `recent_login_attempts` AS
SELECT 
    al.id,
    al.user_id,
    al.email,
    al.action,
    al.status,
    al.ip_address,
    al.created_at,
    u.username,
    u.failed_attempts,
    CASE 
        WHEN u.failed_attempts >= 5 THEN 'blocked'
        WHEN u.failed_attempts >= 3 THEN 'warning'
        ELSE 'normal'
    END as risk_level
FROM auth_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND al.action IN ('login', 'failed_login')
ORDER BY al.created_at DESC;

-- =============================================================================
-- TRIGGERS POUR LA SÉCURITÉ ET LES LOGS
-- =============================================================================

DELIMITER //

-- Trigger pour loguer les modifications d'utilisateurs sensibles
CREATE TRIGGER IF NOT EXISTS `users_security_log` 
AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
    -- Log des changements de rôle
    IF OLD.role != NEW.role THEN
        INSERT INTO auth_logs (user_id, action, status, details, created_at)
        VALUES (NEW.id, 'role_change', 'success', 
                JSON_OBJECT('old_role', OLD.role, 'new_role', NEW.role), NOW());
    END IF;
    
    -- Log des activations/désactivations
    IF OLD.is_active != NEW.is_active THEN
        INSERT INTO auth_logs (user_id, action, status, details, created_at)
        VALUES (NEW.id, IF(NEW.is_active, 'account_activated', 'account_deactivated'), 'success',
                JSON_OBJECT('previous_status', OLD.is_active, 'new_status', NEW.is_active), NOW());
    END IF;
    
    -- Log des changements d'email
    IF OLD.email != NEW.email THEN
        INSERT INTO auth_logs (user_id, email, action, status, details, created_at)
        VALUES (NEW.id, NEW.email, 'email_change', 'success',
                JSON_OBJECT('old_email', OLD.email, 'new_email', NEW.email), NOW());
    END IF;
END //

-- Trigger pour nettoyer automatiquement les tokens expirés
CREATE TRIGGER IF NOT EXISTS `cleanup_expired_tokens`
AFTER INSERT ON `remember_tokens`
FOR EACH ROW
BEGIN
    -- 1% de chance de nettoyer les tokens expirés à chaque insertion
    IF RAND() < 0.01 THEN
        DELETE FROM remember_tokens WHERE expires_at < NOW();
    END IF;
END //

DELIMITER ;

-- =============================================================================
-- INDEX POUR LES PERFORMANCES
-- =============================================================================

-- Index composés pour les requêtes courantes
CREATE INDEX IF NOT EXISTS `idx_users_active_last_activity` ON users (`is_active`, `last_activity`);
CREATE INDEX IF NOT EXISTS `idx_auth_logs_user_action_date` ON auth_logs (`user_id`, `action`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_remember_tokens_user_expires` ON remember_tokens (`user_id`, `expires_at`);
CREATE INDEX IF NOT EXISTS `idx_suspicious_ip_blocked` ON suspicious_activities (`ip_address`, `is_blocked`);
CREATE INDEX IF NOT EXISTS `idx_reviews_anime_rating` ON reviews (`anime_id`, `rating`);
CREATE INDEX IF NOT EXISTS `idx_wishlist_user_status` ON wishlist (`user_id`, `watch_status`);

-- =============================================================================
-- ÉVÉNEMENT PLANIFIÉ POUR LA MAINTENANCE AUTOMATIQUE
-- =============================================================================

SET GLOBAL event_scheduler = ON;

DELIMITER //

CREATE EVENT IF NOT EXISTS `daily_cleanup`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    CALL CleanExpiredSessions();
    
    -- Mise à jour des statistiques utilisateur
    UPDATE users u 
    SET experience_points = (
        SELECT COALESCE(
            (COUNT(DISTINCT r.id) * 10) + 
            (COUNT(DISTINCT w.id) * 5) + 
            (u.login_count * 1), 0
        )
        FROM reviews r, wishlist w 
        WHERE r.user_id = u.id OR w.user_id = u.id
    );
    
    -- Calcul des niveaux basé sur l'expérience
    UPDATE users 
    SET level = GREATEST(1, FLOOR(SQRT(experience_points / 100)) + 1)
    WHERE experience_points > 0;
END //

DELIMITER ;

-- =============================================================================
-- PERMISSIONS ET UTILISATEURS (À ADAPTER SELON L'ENVIRONNEMENT)
-- =============================================================================

-- Création d'un utilisateur pour l'application (optionnel, pour la production)
-- CREATE USER IF NOT EXISTS 'blog_app'@'localhost' IDENTIFIED BY 'MotDePasseSecurise123!';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON anime_blog_db.* TO 'blog_app'@'localhost';
-- FLUSH PRIVILEGES;

-- =============================================================================
-- FINALISATION
-- =============================================================================

-- Activation des événements planifiés
SET GLOBAL event_scheduler = ON;

-- Message de confirmation
SELECT 'Base de données du système d\'authentification créée avec succès!' as message;

-- Affichage des statistiques initiales
SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM anime) as total_anime,
    (SELECT COUNT(*) FROM reviews) as total_reviews,
    (SELECT COUNT(*) FROM wishlist) as total_wishlist_items;