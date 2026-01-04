<?php
class Database {
    private $db;
    
    public function __construct() {
        // Get project root directory (one level up from includes/)
        $projectRoot = dirname(__DIR__);
        
        // Create data directory if it doesn't exist (in project root)
        $dataDir = $projectRoot . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }
        
        // Use data directory for database file
        $dbPath = $dataDir . '/casino.db';
        
        // Try to create the database connection
        try {
            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initializeDatabase();
        } catch (PDOException $e) {
            $errorMsg = "Database error: Unable to create database file.\n\n";
            $errorMsg .= "Please run the following commands on your server:\n";
            $errorMsg .= "sudo mkdir -p $dataDir\n";
            $errorMsg .= "sudo chown www-data:www-data $dataDir\n";
            $errorMsg .= "sudo chmod 755 $dataDir\n\n";
            $errorMsg .= "Or run: php " . $projectRoot . "/setup.php\n\n";
            $errorMsg .= "Original error: " . $e->getMessage();
            throw new Exception($errorMsg);
        }
    }
    
    private function initializeDatabase() {
        // Add admin column to users table if it doesn't exist
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                balance REAL DEFAULT 1000.00,
                is_admin INTEGER DEFAULT 0,
                default_bet REAL,
                dark_mode INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Try to add admin column if table already exists
        try {
            $this->db->exec("ALTER TABLE users ADD COLUMN is_admin INTEGER DEFAULT 0");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }
        
        // Try to add default_bet column if table already exists
        try {
            $this->db->exec("ALTER TABLE users ADD COLUMN default_bet REAL");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }
        
        // Try to add dark_mode column if table already exists
        try {
            $this->db->exec("ALTER TABLE users ADD COLUMN dark_mode INTEGER DEFAULT 0");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                amount REAL NOT NULL,
                description TEXT,
                game TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // Try to add game column if table already exists
        try {
            $this->db->exec("ALTER TABLE transactions ADD COLUMN game TEXT");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }
        
        // Settings table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Initialize default settings
        $this->initializeSettings();
    }
    
    private function initializeSettings() {
        $defaultSettings = [
            'max_deposit' => '10000',
            'max_bet' => '100',
            'starting_balance' => '1000',
            'default_bet' => '10',
            // Slots multipliers (3 of a kind)
            'slots_cherry_multiplier' => '2',
            'slots_lemon_multiplier' => '3',
            'slots_orange_multiplier' => '4',
            'slots_grape_multiplier' => '5',
            'slots_slot_multiplier' => '10',
            // Slots multipliers (2 of a kind)
            'slots_two_of_kind_multiplier' => '0.5',
            // Plinko multipliers (9 slots, comma-separated)
            'plinko_multipliers' => '0.2,0.5,0.8,1.0,2.0,1.0,0.8,0.5,0.2'
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    public function createUser($username, $email, $password) {
        $startingBalance = floatval($this->getSetting('starting_balance', 1000));
        
        // Check if this is the first user (make them admin)
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $isFirstUser = ($result['count'] == 0);
        
        $isAdmin = $isFirstUser ? 1 : 0;
        
        $stmt = $this->db->prepare("INSERT INTO users (username, email, password, balance, is_admin) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $startingBalance, $isAdmin]);
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
    
    public function addTransaction($userId, $type, $amount, $description = '', $game = null) {
        $stmt = $this->db->prepare("INSERT INTO transactions (user_id, type, amount, description, game) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $type, $amount, $description, $game]);
    }
    
    public function getTransactions($userId, $limit = 10) {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Admin functions
    public function getAllUsers() {
        $stmt = $this->db->prepare("SELECT id, username, email, balance, is_admin, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function setAdmin($userId, $isAdmin) {
        $stmt = $this->db->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
        return $stmt->execute([$isAdmin ? 1 : 0, $userId]);
    }
    
    public function setUserBalance($userId, $balance) {
        $stmt = $this->db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        return $stmt->execute([$balance, $userId]);
    }
    
    public function setUserDefaultBet($userId, $defaultBet) {
        $stmt = $this->db->prepare("UPDATE users SET default_bet = ? WHERE id = ?");
        return $stmt->execute([$defaultBet, $userId]);
    }
    
    public function setDarkMode($userId, $darkMode) {
        $stmt = $this->db->prepare("UPDATE users SET dark_mode = ? WHERE id = ?");
        return $stmt->execute([$darkMode ? 1 : 0, $userId]);
    }
    
    public function getDarkMode($userId) {
        $stmt = $this->db->prepare("SELECT dark_mode FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (bool)$result['dark_mode'] : false;
    }
    
    public function deleteUser($userId) {
        // Delete transactions first
        $stmt = $this->db->prepare("DELETE FROM transactions WHERE user_id = ?");
        $stmt->execute([$userId]);
        // Delete user
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$userId]);
    }
    
    // Settings functions
    public function getSetting($key, $default = null) {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    }
    
    public function setSetting($key, $value) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        return $stmt->execute([$key, $value]);
    }
    
    public function getAllSettings() {
        $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM settings");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
    
    // Win rate functions
    public function getWinRate($userId, $game = null) {
        $whereClause = "user_id = ? AND (type = 'win' OR type = 'bet')";
        $params = [$userId];
        
        if ($game !== null) {
            $whereClause .= " AND game = ?";
            $params[] = $game;
        }
        
        // Get total games played (bets)
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM transactions WHERE $whereClause AND type = 'bet'");
        $stmt->execute($params);
        $totalGames = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get total wins
        $stmt = $this->db->prepare("SELECT COUNT(*) as wins FROM transactions WHERE $whereClause AND type = 'win'");
        $stmt->execute($params);
        $wins = $stmt->fetch(PDO::FETCH_ASSOC)['wins'];
        
        if ($totalGames == 0) {
            return ['wins' => 0, 'total' => 0, 'rate' => 0];
        }
        
        $rate = ($wins / $totalGames) * 100;
        
        return [
            'wins' => $wins,
            'total' => $totalGames,
            'rate' => round($rate, 2)
        ];
    }
    
    public function getAllWinRates($userId) {
        $games = ['slots', 'blackjack', 'roulette', 'plinko'];
        $winRates = [];
        
        // Overall win rate
        $winRates['overall'] = $this->getWinRate($userId);
        
        // Per-game win rates
        foreach ($games as $game) {
            $winRates[$game] = $this->getWinRate($userId, $game);
        }
        
        return $winRates;
    }
}
?>
