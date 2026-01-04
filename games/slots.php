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
                            <!-- Symbols will be dynamically loaded -->
                        </div>
                    </div>
                    <div class="reel-container">
                        <div class="reel" id="reel2">
                            <!-- Symbols will be dynamically loaded -->
                        </div>
                    </div>
                    <div class="reel-container">
                        <div class="reel" id="reel3">
                            <!-- Symbols will be dynamically loaded -->
                        </div>
                    </div>
                </div>
                
                <div class="slots-controls">
                    <div class="bet-controls">
                        <label>Bet Amount: $</label>
                        <input type="number" id="betAmount" min="1" value="10" step="1" class="bet-input-with-adjust">
                        <small>Max: $<span id="maxBet">100</span></small>
                    </div>
                    <button id="spinBtn" class="btn btn-primary btn-large">SPIN</button>
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
                        <!-- Payouts will be dynamically loaded by slots.js -->
                        <tr><td colspan="2" style="text-align: center; color: #999;">Loading payouts...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="../js/slots.js"></script>
    <script>
        $(document).ready(function() {
            // Load win rate for slots
            $.get(getApiPath('getWinRates') + '&game=slots', function(data) {
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
