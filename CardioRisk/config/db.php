<?php
/**
 * Database Connection Class
 * Singleton pattern for efficient database connection management
 * Uses PDO for secure, prepared statement-based queries
 */

class Database {
    
    // Database configuration
    private $host = 'localhost';
    private $db_name = 'cardiorisk';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    
    // PDO instance
    private $conn = null;
    
    // Singleton instance
    private static $instance = null;
    
    /**
     * Private constructor to prevent direct instantiation
     * Implements singleton pattern
     */
    private function __construct() {
        try {
            // Build DSN (Data Source Name)
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            
            // PDO options for security and performance
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // Fetch associative arrays by default
                PDO::ATTR_EMULATE_PREPARES   => false,                     // Use real prepared statements
                PDO::ATTR_PERSISTENT         => false,                     // Don't use persistent connections
            ];
            
            // Create PDO connection
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            // Log error and show user-friendly message
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please contact system administrator.");
        }
    }
    
    /**
     * Get singleton instance
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     * @return PDO
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function to get database connection quickly
 * @return PDO
 */
function getDB() {
    return Database::getInstance()->getConnection();
}
?>
