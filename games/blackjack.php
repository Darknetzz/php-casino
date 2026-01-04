<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Blackjack';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="game-container">
            <h1>🃏 Blackjack</h1>
            
            <div class="blackjack-game">
                <div class="bet-controls">
                    <label>Bet Amount: $</label>
                    <input type="number" id="betAmount" min="1" value="10" step="1" class="bet-input-with-adjust">
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
            
            <div class="win-rate-section" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; color: #667eea;">📊 Your Stats</h4>
                <div id="winRateDisplay" style="color: #666;">
                    <span>Win Rate: <strong id="winRate">-</strong>%</span>
                    <span style="margin-left: 20px;">Games Played: <strong id="gamesPlayed">-</strong></span>
                    <span style="margin-left: 20px;">Wins: <strong id="wins">-</strong></span>
                </div>
            </div>
            
            <div class="game-info">
                <h3>How to Play:</h3>
                <table class="slots-info-table">
                    <tr>
                        <td>Get as close to 21 as possible without going over</td>
                    </tr>
                    <tr>
                        <td>Face cards (J, Q, K) are worth 10</td>
                    </tr>
                    <tr>
                        <td>Aces are worth 1 or 11 (whichever is better)</td>
                    </tr>
                </table>
                <h4 style="margin-top: 20px; margin-bottom: 10px;">Payouts:</h4>
                <table class="slots-payout-table">
                    <thead>
                        <tr>
                            <th>Result</th>
                            <th>Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Beat the dealer</td>
                            <td>2x bet</td>
                        </tr>
                        <tr>
                            <td>Blackjack (21 with first 2 cards)</td>
                            <td>2.5x bet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="../js/blackjack.js"></script>
    <script>
        $(document).ready(function() {
            // Load win rate for blackjack
            $.get(getApiPath('getWinRates') + '&game=blackjack', function(data) {
                if (data.success && data.winRate) {
                    $('#winRate').text(data.winRate.rate || 0);
                    $('#gamesPlayed').text(data.winRate.total || 0);
                    $('#wins').text(data.winRate.wins || 0);
                } else {
                    console.error('Failed to load stats:', data);
                    $('#winRate').text('0');
                    $('#gamesPlayed').text('0');
                    $('#wins').text('0');
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Error loading stats:', status, error);
                $('#winRate').text('0');
                $('#gamesPlayed').text('0');
                $('#wins').text('0');
            });
        });
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
