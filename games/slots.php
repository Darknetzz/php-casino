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
    <title>Slots - Casino</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="game-container">
            <h1>🎰 Slot Machine</h1>
            
            <div class="slots-machine">
                <div class="slots-reels">
                    <div class="reel-container">
                        <div class="reel" id="reel1">
                            <div class="symbol">🍋</div>
                            <div class="symbol">🍒</div>
                            <div class="symbol">🍊</div>
                        </div>
                    </div>
                    <div class="reel-container">
                        <div class="reel" id="reel2">
                            <div class="symbol">🍇</div>
                            <div class="symbol">🍋</div>
                            <div class="symbol">🎰</div>
                        </div>
                    </div>
                    <div class="reel-container">
                        <div class="reel" id="reel3">
                            <div class="symbol">🍊</div>
                            <div class="symbol">🍇</div>
                            <div class="symbol">🍒</div>
                        </div>
                    </div>
                </div>
                
                <div class="slots-controls">
                    <div class="bet-controls">
                        <label>Bet Amount: $</label>
                        <input type="number" id="betAmount" min="1" value="10" step="1">
                        <small>Max: $<span id="maxBet">100</span></small>
                    </div>
                    <button id="spinBtn" class="btn btn-primary btn-large">SPIN</button>
                </div>
                
                <div id="result" class="result-message"></div>
            </div>
            
            <div class="game-info">
                <h3>How to Play:</h3>
                <ul>
                    <li>Set your bet amount</li>
                    <li>Click SPIN to play</li>
                    <li>Match 3 symbols to win!</li>
                    <li>🍒🍒🍒 = 2x bet | 🍋🍋🍋 = 3x bet | 🍊🍊🍊 = 4x bet | 🍇🍇🍇 = 5x bet | 🎰🎰🎰 = 10x bet</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/navbar.js"></script>
    <script src="../js/slots.js"></script>
</body>
</html>
