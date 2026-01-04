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
    <title>Roulette - Casino</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h2>🎰 Casino</h2>
            <div class="nav-right">
                <span class="balance">Balance: $<span id="balance"><?php echo number_format($user['balance'], 2); ?></span></span>
                <a href="../index.php" class="btn btn-secondary">Home</a>
                <a href="../pages/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="game-container">
            <h1>🎲 Roulette</h1>
            
            <div class="roulette-game">
                <div class="bet-controls">
                    <label>Bet Amount: $</label>
                    <input type="number" id="betAmount" min="1" max="100" value="10" step="1">
                </div>
                
                <div class="bet-options">
                    <h3>Place Your Bet:</h3>
                    <div class="bet-buttons">
                        <button class="bet-btn" data-bet="red">Red (2x)</button>
                        <button class="bet-btn" data-bet="black">Black (2x)</button>
                        <button class="bet-btn" data-bet="green">Green (14x)</button>
                        <button class="bet-btn" data-bet="even">Even (2x)</button>
                        <button class="bet-btn" data-bet="odd">Odd (2x)</button>
                        <button class="bet-btn" data-bet="low">1-18 (2x)</button>
                        <button class="bet-btn" data-bet="high">19-36 (2x)</button>
                    </div>
                    <div class="number-bets">
                        <h4>Or bet on a specific number (36x):</h4>
                        <input type="number" id="numberBet" min="0" max="36" placeholder="0-36">
                    </div>
                </div>
                
                <div class="roulette-wheel">
                    <div id="rouletteResult" class="roulette-result">Click "Spin" to play</div>
                    <button id="spinBtn" class="btn btn-primary btn-large">SPIN</button>
                </div>
                
                <div id="result" class="result-message"></div>
            </div>
            
            <div class="game-info">
                <h3>How to Play:</h3>
                <ul>
                    <li>Choose your bet type and amount</li>
                    <li>Click SPIN to play</li>
                    <li>Red/Black/Even/Odd/Low/High pay 2x</li>
                    <li>Green (0) pays 14x</li>
                    <li>Specific number pays 36x</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/roulette.js"></script>
</body>
</html>
