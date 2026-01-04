<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Coinflip';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="game-container section">
            <h1>🪙 Coinflip</h1>
            
            <div class="coinflip-game">
                <div class="coinflip-container">
                    <div class="coin" id="coin">
                        <div class="coin-face coin-heads">
                            <div class="coin-content">H</div>
                        </div>
                        <div class="coin-face coin-tails">
                            <div class="coin-content">T</div>
                        </div>
                    </div>
                </div>
                
                <div class="coinflip-controls">
                    <div class="bet-controls">
                        <label>Bet Amount: $</label>
                        <input type="number" id="betAmount" min="1" value="10" step="1" class="bet-input-with-adjust">
                        <small>Max: $<span id="maxBet">100</span></small>
                    </div>
                    
                    <div class="choice-controls" style="margin-top: 20px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: bold;">Choose your side:</label>
                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <button id="headsBtn" class="btn btn-primary btn-large coin-choice-btn" data-choice="heads">Heads</button>
                            <button id="tailsBtn" class="btn btn-primary btn-large coin-choice-btn" data-choice="tails">Tails</button>
                        </div>
                    </div>
                    
                    <button id="flipBtn" class="btn btn-primary btn-large" style="margin-top: 20px; display: none;">FLIP COIN</button>
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
                <button class="btn btn-secondary" onclick="openModal('coinflipHowToPlayModal')" style="margin: 5px;">How to Play</button>
                <button class="btn btn-secondary" onclick="openModal('coinflipPayoutsModal')" style="margin: 5px;">Payouts</button>
            </div>
        </div>
    </div>
    
    <script src="../js/coinflip.js"></script>
    <script>
        $(document).ready(function() {
            // Load win rate for coinflip
            const apiUrl = getApiPath('getWinRates') + '&game=coinflip';
            $.get(apiUrl, function(data) {
                console.log('Coinflip stats response:', data);
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
    <div id="coinflipHowToPlayModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('coinflipHowToPlayModal')">&times;</span>
            <h3>How to Play:</h3>
            <table class="slots-info-table">
                <tr>
                    <td>1. Set your bet amount</td>
                </tr>
                <tr>
                    <td>2. Choose Heads or Tails</td>
                </tr>
                <tr>
                    <td>3. Click FLIP COIN to flip</td>
                </tr>
                <tr>
                    <td>4. If your choice matches the result, you win!</td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Payouts Modal -->
    <div id="coinflipPayoutsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('coinflipPayoutsModal')">&times;</span>
            <h3>Payouts:</h3>
            <table class="slots-payout-table">
                <thead>
                    <tr>
                        <th>Result</th>
                        <th>Payout</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Win (Heads or Tails)</td>
                        <td>2x bet</td>
                    </tr>
                    <tr>
                        <td>Loss</td>
                        <td>0x bet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
