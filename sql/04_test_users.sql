-- Utilisateurs de test (mots de passe : "password123")
INSERT INTO users (username, email, password, role, avatar, email_verified) VALUES
('admin', 'admin@blogdescopains.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'admin-avatar.jpg', TRUE),
('mpeypoux', 'mpeypoux@blogdescopains.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'mpeypoux-avatar.jpg', TRUE),
('damota', 'damota@blogdescopains.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator', 'damota-avatar.jpg', TRUE),
('luca', 'luca@blogdescopains.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator', 'luca-avatar.jpg', TRUE),
('momo', 'momo@blogdescopains.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator', 'momo-avatar.jpg', TRUE),
('otaku_master', 'otaku@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'default-avatar.jpg', TRUE),
('anime_lover', 'animelovers@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'default-avatar.jpg', TRUE),
('shonen_fan', 'shonen@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'default-avatar.jpg', TRUE);

-- Commentaires d'exemple
INSERT INTO comments (content, anime_id, user_id) VALUES
('One Piece est vraiment le meilleur manga de tous les temps ! L\'aventure ne s\'arrête jamais !', 1, 6),
('L\'animation de Attack on Titan est juste époustouflante, surtout dans les dernières saisons.', 2, 7),
('My Hero Academia m\'a vraiment motivé à croire en mes rêves !', 3, 8),
('Le film de Miyazaki est un chef-d\'œuvre absolu. Chaque image est une œuvre d\'art.', 5, 6),
('Demon Slayer a une animation de combat incroyable, quel régal visuel !', 4, 7);

-- Réponses aux commentaires
INSERT INTO comments (content, anime_id, user_id, parent_id) VALUES
('Totalement d\'accord ! Oda est un génie du storytelling.', 1, 7, 1),
('Les scènes d\'action de WIT Studio étaient exceptionnelles !', 2, 8, 2),
('Plus Ultra ! 💪', 3, 6, 3);

-- Notes utilisateurs
INSERT INTO user_ratings (user_id, anime_id, rating, review) VALUES
(6, 1, 9.5, 'Une aventure épique sans fin, des personnages attachants et un worldbuilding incroyable.'),
(6, 2, 9.0, 'Sombre et intense, avec des retournements de situation hallucinants.'),
(7, 1, 9.0, 'Très bon shonen mais parfois trop long dans certains arcs.'),
(7, 3, 8.5, 'Excellent message sur la persévérance et l\'héroïsme.'),
(8, 4, 9.2, 'Animation sublime et histoire touchante sur la famille et le sacrifice.');

-- Favoris utilisateurs
INSERT INTO user_favorites (user_id, anime_id, status, progress) VALUES
(6, 1, 'watching', 850),
(6, 2, 'completed', 75),
(6, 3, 'watching', 120),
(7, 1, 'plan_to_watch', 0),
(7, 2, 'completed', 75),
(8, 4, 'completed', 32),
(8, 5, 'completed', 1);