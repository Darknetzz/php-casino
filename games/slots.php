<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Slots';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
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
                <table class="slots-info-table">
                    <tr>
                        <td>1. Set your bet amount</td>
                    </tr>
                    <tr>
                        <td>2. Click SPIN to play</td>
                    </tr>
                    <tr>
                        <td>3. Match 3 symbols to win!</td>
                    </tr>
                </table>
                <h4 style="margin-top: 20px; margin-bottom: 10px;">Payouts:</h4>
                <table class="slots-payout-table">
                    <thead>
                        <tr>
                            <th>Symbols</th>
                            <th>Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>🍒🍒🍒</td>
                            <td>2x bet</td>
                        </tr>
                        <tr>
                            <td>🍋🍋🍋</td>
                            <td>3x bet</td>
                        </tr>
                        <tr>
                            <td>🍊🍊🍊</td>
                            <td>4x bet</td>
                        </tr>
                        <tr>
                            <td>🍇🍇🍇</td>
                            <td>5x bet</td>
                        </tr>
                        <tr>
                            <td>🎰🎰🎰</td>
                            <td>10x bet</td>
                        </tr>
                        <tr>
                            <td>Any 2 matching symbols</td>
                            <td>0.5x bet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="../js/slots.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
