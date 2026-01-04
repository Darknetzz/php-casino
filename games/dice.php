<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Dice';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="game-container section">
            <h1>🎲 Dice Roll</h1>
            
            <div class="dice-game">
                <div class="dice-container">
                    <div class="dice-grid">
                        <div class="dice" id="dice1">
                            <div class="dice-face">⚀</div>
                        </div>
                        <div class="dice" id="dice2">
                            <div class="dice-face">⚁</div>
                        </div>
                        <div class="dice" id="dice3">
                            <div class="dice-face">⚂</div>
                        </div>
                        <div class="dice" id="dice4">
                            <div class="dice-face">⚃</div>
                        </div>
                        <div class="dice" id="dice5">
                            <div class="dice-face">⚄</div>
                        </div>
                        <div class="dice" id="dice6">
                            <div class="dice-face">⚅</div>
                        </div>
                    </div>
                </div>
                
                <div class="dice-controls">
                    <div class="bet-controls">
                        <label>Bet Amount: $</label>
                        <input type="number" id="betAmount" min="1" value="10" step="1" class="bet-input-with-adjust">
                        <small>Max: $<span id="maxBet">100</span></small>
                    </div>
                    <button id="rollBtn" class="btn btn-primary btn-large">ROLL DICE</button>
                </div>
                
                <div id="result" class="result-message"></div>
            </div>
            
            <div class="win-rate-section section" style="margin: 20px 0; background: #f8f9fa; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; color: #667eea;">📊 Your Stats</h4>
                <div id="winRateDisplay" style="color: #666;">
                    <span>Win Rate: <strong id="winRate">-</strong>%</span>
                    <span style="margin-left: 20px;">Games Played: <strong id="gamesPlayed">-</strong></span>
                    <span style="margin-left: 20px;">Wins: <strong id="wins">-</strong></span>
                </div>
            </div>
            
            <div class="game-info section" style="text-align: center;">
                <button class="btn btn-secondary" onclick="openModal('diceHowToPlayModal')" style="margin: 5px;">How to Play</button>
                <button class="btn btn-secondary" onclick="openModal('dicePayoutsModal')" style="margin: 5px;">Payouts</button>
            </div>
        </div>
    </div>
    
    <script src="../js/dice.js"></script>
    <script>
        $(document).ready(function() {
            // Load win rate for dice
            const apiUrl = getApiPath('getWinRates') + '&game=dice';
            $.get(apiUrl, function(data) {
                console.log('Dice stats response:', data);
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
                console.error('Error loading stats:', status, error, xhr);
                $('#winRate').text('0');
                $('#gamesPlayed').text('0');
                $('#wins').text('0');
            });
        });
    </script>
    
    <!-- How to Play Modal -->
    <div id="diceHowToPlayModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('diceHowToPlayModal')">&times;</span>
            <h3>How to Play:</h3>
            <table class="slots-info-table">
                <tr>
                    <td>1. Set your bet amount</td>
                </tr>
                <tr>
                    <td>2. Click ROLL DICE to roll 6 dice</td>
                </tr>
                <tr>
                    <td>3. Match dice to win!</td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Payouts Modal -->
    <div id="dicePayoutsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('dicePayoutsModal')">&times;</span>
            <h3>Payouts:</h3>
            <table class="slots-payout-table">
                <thead>
                    <tr>
                        <th>Combination</th>
                        <th>Payout</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>3 of a kind</td>
                        <td>2x bet</td>
                    </tr>
                    <tr>
                        <td>4 of a kind</td>
                        <td>5x bet</td>
                    </tr>
                    <tr>
                        <td>5 of a kind</td>
                        <td>10x bet</td>
                    </tr>
                    <tr>
                        <td>6 of a kind</td>
                        <td>20x bet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
