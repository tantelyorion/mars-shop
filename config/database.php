<?php
// config/database.php - Version clean et optimisée (sans AMEA)

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'mars_shop');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Options PDO
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

/**
 * Classe Database - Singleton pour la connexion PDO
 */
final class Database {
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    
    private function __construct() {
        $this->connect();
    }
    
    private function __clone() {}
    
    public function __wakeup() {}
    
    /**
     * Établit la connexion à la base de données
     */
    private function connect(): void {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $GLOBALS['pdo_options']);
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }
    
    /**
     * Gère les erreurs de connexion
     */
    private function handleConnectionError(PDOException $e): void {
        $error = "Erreur de connexion à la base de données";
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $error .= ": " . $e->getMessage();
        }
        
        die($error);
    }
    
    /**
     * Récupère l'instance unique (Singleton)
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Récupère la connexion PDO
     */
    public function getConnection(): PDO {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Teste la connexion
     */
    public function isConnected(): bool {
        return $this->connection !== null;
    }
    
    /**
     * Ferme la connexion
     */
    public function close(): void {
        $this->connection = null;
    }
}

/**
 * Fonction globale pour récupérer la connexion (compatibilité)
 */
function getConnection(): PDO {
    return Database::getInstance()->getConnection();
}

/**
 * Démarrage de la session
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}