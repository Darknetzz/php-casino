<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blackjack - Casino</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h2>🎰 Casino</h2>
            <div class="nav-right">
                <span class="balance">Balance: $<span id="balance"><?php echo number_format($user['balance'], 2); ?></span></span>
                <a href="../index.php" class="btn btn-secondary">Home</a>
                <div class="user-menu">
                    <button class="user-menu-btn" id="userMenuBtn">
                        <span class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="../pages/profile.php" class="dropdown-item">
                            <span>👤</span> Profile
                        </a>
                        <?php if (isAdmin()): ?>
                        <a href="../pages/admin.php" class="dropdown-item">
                            <span>⚙️</span> Admin Panel
                        </a>
                        <?php endif; ?>
                        <a href="../pages/logout.php" class="dropdown-item">
                            <span>🚪</span> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="game-container">
            <h1>🃏 Blackjack</h1>
            
            <div class="blackjack-game">
                <div class="bet-controls">
                    <label>Bet Amount: $</label>
                    <input type="number" id="betAmount" min="1" value="10" step="1">
                    <small>Max: $<span id="maxBet">100</span></small>
                    <button id="newGameBtn" class="btn btn-primary">New Game</button>
                </div>
                
                <div class="blackjack-board">
                    <div class="dealer-section">
                        <h3>Dealer</h3>
                        <div class="hand" id="dealerHand"></div>
                        <div class="score" id="dealerScore">Score: 0</div>
                    </div>
                    
                    <div class="player-section">
                        <h3>You</h3>
                        <div class="hand" id="playerHand"></div>
                        <div class="score" id="playerScore">Score: 0</div>
                    </div>
                </div>
                
                <div class="game-controls" id="gameControls" style="display: none;">
                    <button id="hitBtn" class="btn btn-primary">Hit</button>
                    <button id="standBtn" class="btn btn-primary">Stand</button>
                </div>
                
                <div id="result" class="result-message"></div>
            </div>
            
            <div class="game-info">
                <h3>How to Play:</h3>
                <ul>
                    <li>Get as close to 21 as possible without going over</li>
                    <li>Face cards (J, Q, K) are worth 10</li>
                    <li>Aces are worth 1 or 11 (whichever is better)</li>
                    <li>Beat the dealer's hand to win 2x your bet</li>
                    <li>Blackjack (21 with first 2 cards) pays 2.5x</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // User menu dropdown toggle
            $('#userMenuBtn').on('click', function(e) {
                e.stopPropagation();
                $('.user-menu').toggleClass('active');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.user-menu').length) {
                    $('.user-menu').removeClass('active');
                }
            });
        });
    </script>
    <script src="../js/blackjack.js"></script>
</body>
</html>
