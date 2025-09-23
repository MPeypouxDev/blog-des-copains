-- Table des studios d'animation
CREATE TABLE studios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    founded_year YEAR NULL,
    website VARCHAR(255) NULL,
    description TEXT NULL,
    logo VARCHAR(255) NULL,
    country VARCHAR(50) DEFAULT 'Japan',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_name (name)
);

-- Table des catégories/genres
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#3498db',
    icon VARCHAR(50) NULL,
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_parent (parent_id)
);

-- Table des animés (structure principale)
CREATE TABLE anime_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    original_title VARCHAR(255) NULL,
    synopsis TEXT NULL,
    
    -- Médias
    poster_image VARCHAR(255) NULL,
    banner_image VARCHAR(255) NULL,
    trailer_url VARCHAR(500) NULL,
    
    -- Métadonnées
    release_year YEAR NULL,
    episodes INT NULL,
    duration_minutes INT NULL,
    status ENUM('ongoing', 'completed', 'upcoming', 'cancelled') DEFAULT 'completed',
    source ENUM('manga', 'light_novel', 'original', 'game', 'novel') NULL,
    
    -- Relations
    studio_id INT NULL,
    author_id INT NOT NULL,
    
    -- Stats
    view_count INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    rating_count INT DEFAULT 0,
    
    -- SEO
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    
    -- Publication
    is_published BOOLEAN DEFAULT TRUE,
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published (is_published),
    INDEX idx_featured (featured),
    INDEX idx_year (release_year),
    INDEX idx_rating (average_rating)
);