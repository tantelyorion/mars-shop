<?php
// config/database.php - Version améliorée avec gestion d'erreurs
class Database {
    private static $instance = null;
    private $conn;
    
    private $host = 'localhost';
    private $dbname = 'mars_shop';
    private $username = 'root';
    private $password = '';
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// Fonction globale pour la compatibilité
function getConnection() {
    return Database::getInstance()->getConnection();
}

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ajouter dans config/database.php à la fin
// Configuration AMEA OAuth
define('AMEA_CLIENT_ID', 'VOTRE_CLIENT_ID_AMEA');
define('AMEA_CLIENT_SECRET', 'VOTRE_CLIENT_SECRET_AMEA');
define('AMEA_REDIRECT_URI', 'https://mars-shop.com/oauth_amea_callback.php');

?>