-- Animés populaires avec vraies données
INSERT INTO anime_posts (title, slug, original_title, synopsis, poster_image, release_year, episodes, duration_minutes, status, studio_id, author_id, average_rating, rating_count, featured) VALUES

-- One Piece
('One Piece', 'one-piece', 'ワンピース', 
'Monkey D. Luffy, un jeune pirate au corps élastique, explore Grand Line avec son équipage des Pirates du Chapeau de Paille pour trouver le trésor ultime connu sous le nom de "One Piece" et devenir le prochain Roi des Pirates.',
'one-piece-poster.jpg', 1999, 1000, 24, 'ongoing', 2, 1, 9.2, 15420, TRUE),

-- Attack on Titan
('L\'Attaque des Titans', 'attack-on-titan', '進撃の巨人',
'L\'humanité vit retranchée dans une cité cernée par d\'énormes murailles à cause des Titans, gigantesques humanoïdes qui dévorent les hommes. Eren, Mikasa et Armin rêvent de découvrir le monde extérieur.',
'attack-on-titan-poster.jpg', 2013, 75, 24, 'completed', 7, 1, 9.0, 23150, TRUE),

-- My Hero Academia  
('My Hero Academia', 'my-hero-academia', '僕のヒーローアカデミア',
'Dans un monde où 80% de la population possède des super-pouvoirs appelés "Alters", Izuku Midoriya rêve de devenir un héros malgré son absence de pouvoir.',
'my-hero-academia-poster.jpg', 2016, 138, 24, 'ongoing', 5, 1, 8.7, 18940, TRUE),

-- Demon Slayer
('Demon Slayer', 'demon-slayer', '鬼滅の刃',
'Tanjiro Kamado devient un chasseur de démons pour sauver sa sœur transformée en démon et venger sa famille massacrée.',
'demon-slayer-poster.jpg', 2019, 32, 24, 'ongoing', 8, 1, 8.9, 21300, TRUE),

-- Spirited Away
('Le Voyage de Chihiro', 'spirited-away', '千と千尋の神隠し',
'Chihiro, 10 ans, découvre un monde parallèle magique où elle doit travailler dans un établissement de bains pour esprits afin de sauver ses parents transformés en cochons.',
'spirited-away-poster.jpg', 2001, 1, 125, 'completed', 1, 1, 9.4, 31200, TRUE),

-- Jujutsu Kaisen
('Jujutsu Kaisen', 'jujutsu-kaisen', '呪術廻戦',
'Yuji Itadori, lycéen aux capacités physiques extraordinaires, rejoint une école d\'exorcisme après avoir avalé un doigt maudit.',
'jujutsu-kaisen-poster.jpg', 2020, 24, 24, 'ongoing', 6, 1, 8.8, 19500, TRUE);

-- Liaison animé-catégories  
INSERT INTO anime_categories (anime_id, category_id) VALUES
-- One Piece
(1, 1), (1, 5), (1, 6), (1, 9),  -- Shonen, Action, Adventure, Fantasy
-- Attack on Titan  
(2, 2), (2, 5), (2, 8), (2, 12), -- Seinen, Action, Drama, Thriller
-- My Hero Academia
(3, 1), (3, 5), (3, 16), (3, 10), -- Shonen, Action, School, Sci-Fi
-- Demon Slayer
(4, 1), (4, 5), (4, 11), (4, 14), -- Shonen, Action, Horror, Historical  
-- Spirited Away
(5, 9), (5, 13), (5, 6),          -- Fantasy, Slice of Life, Adventure
-- Jujutsu Kaisen  
(6, 1), (6, 5), (6, 13), (6, 16); -- Shonen, Action, Supernatural, School