<?php
class Database {
    private static $instance = null;
    private $connection;
    
    // Configuration WAMP (par défaut)
    private $host = 'localhost';
    private $dbname = 'anime_blog_db';
    private $username = 'root';
    private $password = ''; // Par défaut vide sur WAMP
    private $charset = 'utf8mb4';
    
    private function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// Fonction globale pour obtenir la connexion
function getDB() {
    return Database::getInstance()->getConnection();
}
?>