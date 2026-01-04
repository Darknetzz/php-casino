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
                email TEXT UNIQUE,
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
        
        // Migrate email column to allow NULL (for existing databases)
        // SQLite doesn't support ALTER COLUMN, so we need to recreate the table
        try {
            // Check if email column has NOT NULL constraint by trying to insert NULL
            // If it fails, we need to migrate
            $this->db->exec("PRAGMA table_info(users)");
            $stmt = $this->db->query("PRAGMA table_info(users)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $emailColumn = null;
            foreach ($columns as $col) {
                if ($col['name'] === 'email') {
                    $emailColumn = $col;
                    break;
                }
            }
            
            // If email column exists and has NOT NULL, we need to migrate
            // Since SQLite doesn't support ALTER COLUMN, we'll handle NULL in application code
            // The UNIQUE constraint in SQLite allows multiple NULLs, so we're good
        } catch (PDOException $e) {
            // Ignore errors
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
        
        // Game rounds tables for synchronized games
        $this->initializeGameRoundsTables();
    }
    
    private function initializeGameRoundsTables() {
        // Roulette rounds table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS roulette_rounds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                round_number INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'betting',
                result_number INTEGER,
                server_seed TEXT NOT NULL,
                client_seed TEXT,
                server_seed_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                betting_ends_at DATETIME,
                started_at DATETIME,
                finished_at DATETIME
            )
        ");
        
        // Crash rounds table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS crash_rounds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                round_number INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'betting',
                crash_point REAL,
                server_seed TEXT NOT NULL,
                client_seed TEXT,
                server_seed_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                betting_ends_at DATETIME,
                started_at DATETIME,
                finished_at DATETIME
            )
        ");
        
        // Roulette bets table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS roulette_bets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                round_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                bet_type TEXT NOT NULL,
                bet_value TEXT,
                amount REAL NOT NULL,
                multiplier REAL NOT NULL,
                won INTEGER DEFAULT 0,
                payout REAL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (round_id) REFERENCES roulette_rounds(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // Crash bets table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS crash_bets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                round_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                bet_amount REAL NOT NULL,
                cash_out_multiplier REAL,
                won INTEGER DEFAULT 0,
                payout REAL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (round_id) REFERENCES crash_rounds(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // Notifications table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT 'info',
                game TEXT,
                read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // Create indexes for performance
        try {
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_roulette_rounds_status ON roulette_rounds(status)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_roulette_rounds_number ON roulette_rounds(round_number)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_crash_rounds_status ON crash_rounds(status)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_crash_rounds_number ON crash_rounds(round_number)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_roulette_bets_round ON roulette_bets(round_id)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_roulette_bets_user ON roulette_bets(user_id)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_crash_bets_round ON crash_bets(round_id)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_crash_bets_user ON crash_bets(user_id)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(read)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_created ON notifications(created_at)");
        } catch (PDOException $e) {
            // Indexes might already exist, ignore
        }
    }
    
    private function initializeSettings() {
        $defaultSettings = [
            'max_deposit' => '10000',
            'max_bet' => '100',
            'max_deposit_enabled' => '1',
            'max_bet_enabled' => '1',
            'starting_balance' => '1000',
            'default_bet' => '10',
            // Slots multipliers (stored as JSON array of {emoji, multiplier})
            'slots_symbols' => json_encode([
                ['emoji' => '🍒', 'multiplier' => 2.0],
                ['emoji' => '🍋', 'multiplier' => 3.0],
                ['emoji' => '🍊', 'multiplier' => 4.0],
                ['emoji' => '🍇', 'multiplier' => 5.0],
                ['emoji' => '🎰', 'multiplier' => 10.0]
            ]),
            // Slots multipliers (2 of a kind)
            'slots_two_of_kind_multiplier' => '1.0',
            // Slots number of reels/columns
            'slots_num_reels' => '3',
            // Slots win row (0 = top, 1 = middle, 2 = bottom)
            'slots_win_row' => '1',
            // Slots bet rows (1 = middle row only, 3 = all rows)
            'slots_bet_rows' => '1',
            // Slots spin duration (milliseconds)
            'slots_duration' => '2500',
            // Plinko multipliers (9 slots, comma-separated)
            'plinko_multipliers' => '0.2,0.5,0.8,1.0,2.0,1.0,0.8,0.5,0.2',
            // Plinko step delay (milliseconds between ball movement steps)
            'plinko_duration' => '350',
            // Blackjack regular win multiplier
            'blackjack_regular_multiplier' => '2.0',
            // Blackjack blackjack multiplier (21 with first 2 cards)
            'blackjack_blackjack_multiplier' => '2.5',
            // Blackjack dealer stand threshold
            'blackjack_dealer_stand' => '17',
            // Roulette round settings
            'roulette_betting_duration' => '30', // seconds
            'roulette_spinning_duration' => '4', // seconds
            'roulette_round_interval' => '60', // seconds between rounds
            // Crash round settings
            'crash_betting_duration' => '30', // seconds
            'crash_round_interval' => '60', // seconds between rounds
            // Game mode settings
            'roulette_mode' => 'local', // 'local' or 'central'
            'crash_mode' => 'local', // 'local' or 'central'
            // Worker settings
            'worker_interval' => '1', // seconds between worker checks
            // General casino settings
            'site_name' => 'Casino', // Site name/title
            'maintenance_mode' => '0' // '0' = disabled, '1' = enabled
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
        
        // Convert empty email to NULL
        $email = trim($email ?? '');
        $email = $email === '' ? null : $email;
        
        // Check email uniqueness if email is provided
        if ($email !== null) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists.');
            }
        }
        
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
        $params = [$userId];
        
        if ($game !== null) {
            // For specific game, only count transactions with that game
            $gameClause = "AND game = ?";
            $params[] = $game;
        } else {
            // For overall stats, count all transactions with a game value (exclude NULL for consistency)
            $gameClause = "AND game IS NOT NULL";
        }
        
        // Get total games played (bets)
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ? AND type = 'bet' $gameClause");
        $stmt->execute($params);
        $totalGames = intval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        
        // Get all win transactions with descriptions to filter by multiplier
        $stmt = $this->db->prepare("SELECT description FROM transactions WHERE user_id = ? AND type = 'win' $gameClause");
        $stmt->execute($params);
        $winTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count only wins with multiplier >= 1
        $wins = 0;
        foreach ($winTransactions as $win) {
            $description = $win['description'] ?? '';
            
            // Extract multiplier from description
            // Patterns: "Xx", "X.x", "(Xx)", "(X.x)", "Xx avg", "0.5x - 2 of a kind", etc.
            $multiplier = null;
            
            // Try to find multiplier in various formats - look for number followed by 'x'
            // This handles: "0.5x", "(0.5x)", "2x", "2.0x avg", "0.5x - 2 of a kind", etc.
            if (preg_match('/(\d+\.?\d*)\s*x/i', $description, $matches)) {
                $multiplier = floatval($matches[1]);
            }
            
            // Only count as win if multiplier >= 1 (or if no multiplier found, count it to be safe)
            if ($multiplier === null || $multiplier >= 1) {
                $wins++;
            }
        }
        
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
        $games = ['slots', 'blackjack', 'roulette', 'plinko', 'dice'];
        $winRates = [];
        
        // Overall win rate
        $winRates['overall'] = $this->getWinRate($userId);
        
        // Per-game win rates
        foreach ($games as $game) {
            $winRates[$game] = $this->getWinRate($userId, $game);
        }
        
        return $winRates;
    }
    
    public function getTotalWinLoss($userId) {
        // Get all bet transactions
        $stmt = $this->db->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'bet' AND game IS NOT NULL");
        $stmt->execute([$userId]);
        $betResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalBets = floatval($betResult['total'] ?? 0);
        
        // Get all win transactions with their descriptions to filter by multiplier
        $stmt = $this->db->prepare("SELECT amount, description FROM transactions WHERE user_id = ? AND type = 'win' AND game IS NOT NULL");
        $stmt->execute([$userId]);
        $winTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalWins = 0;
        
        foreach ($winTransactions as $win) {
            $winAmount = floatval($win['amount']);
            $description = $win['description'] ?? '';
            
            // Check if description contains multiplier info
            $multiplier = null;
            if (preg_match('/(\d+\.?\d*)x/i', $description, $matches)) {
                $multiplier = floatval($matches[1]);
            }
            
            // Only count wins with multiplier >= 1
            // If no multiplier found in description, we'll need to check against bets
            // For now, if multiplier is not found, we'll include it (could be improved)
            if ($multiplier === null || $multiplier >= 1) {
                $totalWins += $winAmount;
            }
            // If multiplier < 1, don't count it (like 0.5x for 2-of-a-kind)
        }
        
        $netWinLoss = $totalWins - $totalBets;
        
        return [
            'totalBets' => round($totalBets, 2),
            'totalWins' => round($totalWins, 2),
            'netWinLoss' => round($netWinLoss, 2)
        ];
    }
    
    public function resetStats($userId) {
        // Delete all bet and win transactions for the user
        $stmt = $this->db->prepare("DELETE FROM transactions WHERE user_id = ? AND type IN ('bet', 'win') AND game IS NOT NULL");
        return $stmt->execute([$userId]);
    }
    
    public function getTotalDeposits($userId) {
        // Get starting balance (the balance users get when they're created)
        $startingBalance = floatval($this->getSetting('starting_balance', 1000));
        
        // Get all deposit transactions
        $stmt = $this->db->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'deposit'");
        $stmt->execute([$userId]);
        $depositResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalDeposits = floatval($depositResult['total'] ?? 0);
        
        // Total deposit = starting balance + all deposit transactions
        $totalDeposit = $startingBalance + $totalDeposits;
        
        return round($totalDeposit, 2);
    }
    
    // Roulette rounds functions
    public function getCurrentRouletteRound() {
        $stmt = $this->db->prepare("SELECT * FROM roulette_rounds WHERE status IN ('betting', 'spinning') ORDER BY round_number DESC LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getRecentFinishedRouletteRoundWithUserBet($userId, $minutesAgo = 5) {
        // Get the most recent finished round that has user bets and finished within the last N minutes
        $stmt = $this->db->prepare("
            SELECT DISTINCT rr.* 
            FROM roulette_rounds rr
            INNER JOIN roulette_bets rb ON rr.id = rb.round_id
            WHERE rr.status = 'finished' 
            AND rb.user_id = ?
            AND rr.finished_at >= datetime('now', '-' || ? || ' minutes')
            ORDER BY rr.round_number DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $minutesAgo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createRouletteRound($roundNumber, $serverSeed, $serverSeedHash, $bettingEndsAt) {
        $stmt = $this->db->prepare("INSERT INTO roulette_rounds (round_number, server_seed, server_seed_hash, betting_ends_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$roundNumber, $serverSeed, $serverSeedHash, $bettingEndsAt]);
        return $this->db->lastInsertId();
    }
    
    public function updateRouletteRound($roundId, $status, $resultNumber = null, $clientSeed = null) {
        $updates = ['status = ?'];
        $params = [$status];
        
        if ($resultNumber !== null) {
            $updates[] = 'result_number = ?';
            $params[] = $resultNumber;
        }
        if ($clientSeed !== null) {
            $updates[] = 'client_seed = ?';
            $params[] = $clientSeed;
        }
        if ($status === 'spinning') {
            $updates[] = 'started_at = CURRENT_TIMESTAMP';
        } elseif ($status === 'finished') {
            $updates[] = 'finished_at = CURRENT_TIMESTAMP';
        }
        
        $params[] = $roundId;
        $stmt = $this->db->prepare("UPDATE roulette_rounds SET " . implode(', ', $updates) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function getRouletteRound($roundId) {
        $stmt = $this->db->prepare("SELECT * FROM roulette_rounds WHERE id = ?");
        $stmt->execute([$roundId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getRouletteRoundsHistory($limit = 20) {
        $stmt = $this->db->prepare("SELECT * FROM roulette_rounds WHERE status = 'finished' ORDER BY round_number DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function placeRouletteBet($roundId, $userId, $betType, $betValue, $amount, $multiplier) {
        $stmt = $this->db->prepare("INSERT INTO roulette_bets (round_id, user_id, bet_type, bet_value, amount, multiplier) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$roundId, $userId, $betType, $betValue, $amount, $multiplier]);
    }
    
    public function getRouletteBetsForRound($roundId) {
        $stmt = $this->db->prepare("SELECT * FROM roulette_bets WHERE round_id = ?");
        $stmt->execute([$roundId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRouletteBetsForRoundWithUsers($roundId) {
        $stmt = $this->db->prepare("
            SELECT rb.*, u.username, u.id as user_id 
            FROM roulette_bets rb 
            INNER JOIN users u ON rb.user_id = u.id 
            WHERE rb.round_id = ? 
            ORDER BY rb.created_at ASC
        ");
        $stmt->execute([$roundId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserRouletteBetsForRound($roundId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM roulette_bets WHERE round_id = ? AND user_id = ?");
        $stmt->execute([$roundId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateRouletteBetResult($betId, $won, $payout) {
        $stmt = $this->db->prepare("UPDATE roulette_bets SET won = ?, payout = ? WHERE id = ?");
        return $stmt->execute([$won ? 1 : 0, $payout, $betId]);
    }
    
    // Crash rounds functions
    public function getCurrentCrashRound() {
        $stmt = $this->db->prepare("SELECT * FROM crash_rounds WHERE status IN ('betting', 'running') ORDER BY round_number DESC LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getRecentFinishedCrashRoundWithUserBet($userId, $minutesAgo = 5) {
        // Get the most recent finished round that has user bets and finished within the last N minutes
        $stmt = $this->db->prepare("
            SELECT DISTINCT cr.* 
            FROM crash_rounds cr
            INNER JOIN crash_bets cb ON cr.id = cb.round_id
            WHERE cr.status = 'finished' 
            AND cb.user_id = ?
            AND cr.finished_at >= datetime('now', '-' || ? || ' minutes')
            ORDER BY cr.round_number DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $minutesAgo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createCrashRound($roundNumber, $serverSeed, $serverSeedHash, $bettingEndsAt) {
        $stmt = $this->db->prepare("INSERT INTO crash_rounds (round_number, server_seed, server_seed_hash, betting_ends_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$roundNumber, $serverSeed, $serverSeedHash, $bettingEndsAt]);
        return $this->db->lastInsertId();
    }
    
    public function updateCrashRound($roundId, $status, $crashPoint = null, $clientSeed = null) {
        $updates = ['status = ?'];
        $params = [$status];
        
        if ($crashPoint !== null) {
            $updates[] = 'crash_point = ?';
            $params[] = $crashPoint;
        }
        if ($clientSeed !== null) {
            $updates[] = 'client_seed = ?';
            $params[] = $clientSeed;
        }
        if ($status === 'running') {
            $updates[] = 'started_at = CURRENT_TIMESTAMP';
        } elseif ($status === 'finished') {
            $updates[] = 'finished_at = CURRENT_TIMESTAMP';
        }
        
        $params[] = $roundId;
        $stmt = $this->db->prepare("UPDATE crash_rounds SET " . implode(', ', $updates) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function getCrashRound($roundId) {
        $stmt = $this->db->prepare("SELECT * FROM crash_rounds WHERE id = ?");
        $stmt->execute([$roundId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getCrashRoundsHistory($limit = 20) {
        $stmt = $this->db->prepare("SELECT * FROM crash_rounds WHERE status = 'finished' ORDER BY round_number DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function placeCrashBet($roundId, $userId, $betAmount) {
        $stmt = $this->db->prepare("INSERT INTO crash_bets (round_id, user_id, bet_amount) VALUES (?, ?, ?)");
        return $stmt->execute([$roundId, $userId, $betAmount]);
    }
    
    public function getCrashBetsForRound($roundId) {
        $stmt = $this->db->prepare("SELECT * FROM crash_bets WHERE round_id = ?");
        $stmt->execute([$roundId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCrashBetsForRoundWithUsers($roundId) {
        $stmt = $this->db->prepare("
            SELECT cb.*, u.username, u.id as user_id 
            FROM crash_bets cb 
            INNER JOIN users u ON cb.user_id = u.id 
            WHERE cb.round_id = ? 
            ORDER BY cb.created_at ASC
        ");
        $stmt->execute([$roundId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserCrashBetsForRound($roundId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM crash_bets WHERE round_id = ? AND user_id = ?");
        $stmt->execute([$roundId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateCrashBetCashOut($betId, $cashOutMultiplier) {
        $stmt = $this->db->prepare("UPDATE crash_bets SET cash_out_multiplier = ? WHERE id = ?");
        return $stmt->execute([$cashOutMultiplier, $betId]);
    }
    
    public function updateCrashBetResult($betId, $won, $payout) {
        $stmt = $this->db->prepare("UPDATE crash_bets SET won = ?, payout = ? WHERE id = ?");
        return $stmt->execute([$won ? 1 : 0, $payout, $betId]);
    }
    
    // Notifications functions
    public function createNotification($userId, $title, $message, $type = 'info', $game = null) {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, type, game) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $title, $message, $type, $game]);
    }
    
    public function getUserNotifications($userId, $limit = 200, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUnreadNotificationCount($userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND read = 0
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['count'] ?? 0);
    }
    
    public function markNotificationAsRead($notificationId, $userId) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET read = 1 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notificationId, $userId]);
    }
    
    public function markAllNotificationsAsRead($userId) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET read = 1 
            WHERE user_id = ? AND read = 0
        ");
        return $stmt->execute([$userId]);
    }
    
    public function deleteNotification($notificationId, $userId) {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notificationId, $userId]);
    }
    
    public function deleteAllNotifications($userId) {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE user_id = ?
        ");
        return $stmt->execute([$userId]);
    }
}
?>
