<?php
class Database {
    private $db;
    
    public function __construct() {
        // Create data directory if it doesn't exist
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }
        
        // Use data directory for database file, fallback to current directory if needed
        $dbPath = $dataDir . '/casino.db';
        
        // Try to create the database connection
        try {
            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initializeDatabase();
        } catch (PDOException $e) {
            // If data directory doesn't work, try current directory as fallback
            if ($dataDir !== __DIR__ && strpos($e->getMessage(), 'unable to open') !== false) {
                $dbPath = __DIR__ . '/casino.db';
                try {
                    $this->db = new PDO('sqlite:' . $dbPath);
                    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->initializeDatabase();
                } catch (PDOException $e2) {
                    $errorMsg = "Database error: Unable to create database file.\n\n";
                    $errorMsg .= "Please run the following commands on your server:\n";
                    $errorMsg .= "sudo mkdir -p $dataDir\n";
                    $errorMsg .= "sudo chown www-data:www-data $dataDir\n";
                    $errorMsg .= "sudo chmod 755 $dataDir\n\n";
                    $errorMsg .= "Or run: php " . __DIR__ . "/setup.php\n\n";
                    $errorMsg .= "Original error: " . $e2->getMessage();
                    throw new Exception($errorMsg);
                }
            } else {
                $errorMsg = "Database error: Unable to create database file.\n\n";
                $errorMsg .= "Please run the following commands on your server:\n";
                $errorMsg .= "sudo mkdir -p $dataDir\n";
                $errorMsg .= "sudo chown www-data:www-data $dataDir\n";
                $errorMsg .= "sudo chmod 755 $dataDir\n\n";
                $errorMsg .= "Or run: php " . __DIR__ . "/setup.php\n\n";
                $errorMsg .= "Original error: " . $e->getMessage();
                throw new Exception($errorMsg);
            }
        }
    }
    
    private function initializeDatabase() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                balance REAL DEFAULT 1000.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                amount REAL NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    public function createUser($username, $email, $password) {
        $stmt = $this->db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        return $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    }
    
    public function getUserByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateBalance($userId, $newBalance) {
        $stmt = $this->db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        return $stmt->execute([$newBalance, $userId]);
    }
    
    public function addTransaction($userId, $type, $amount, $description = '') {
        $stmt = $this->db->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $type, $amount, $description]);
    }
    
    public function getTransactions($userId, $limit = 10) {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
