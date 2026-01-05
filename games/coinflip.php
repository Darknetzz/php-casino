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
            <div class="game-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h1>🪙 Coinflip</h1>
                <div class="game-header-actions" style="display: flex; gap: 8px;">
                    <button class="btn btn-outline-secondary" onclick="openModal('coinflipStatsModal')" title="View your stats">
                        📊 Stats
                    </button>
                    <button class="btn btn-outline-secondary" onclick="openModal('coinflipHowToPlayModal')" title="How to play">
                        ❓ How to Play
                    </button>
                    <button class="btn btn-outline-secondary" onclick="openModal('coinflipPayoutsModal')" title="Payouts">
                        💰 Payouts
                    </button>
                </div>
            </div>
            
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
                    const netWinLoss = data.winRate.netWinLoss || 0;
                    const netWinLossText = netWinLoss >= 0 ? '$' + netWinLoss.toFixed(2) : '-$' + Math.abs(netWinLoss).toFixed(2);
                    $('#netWinLoss').text(netWinLossText).css('color', netWinLoss >= 0 ? '#28a745' : '#dc3545');
                } else {
                    console.error('Failed to load stats:', data);
                    $('#winRate').text('0');
                    $('#gamesPlayed').text('0');
                    $('#wins').text('0');
                    $('#netWinLoss').text('$0.00').css('color', '#666');
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Error loading stats:', status, error, xhr);
                $('#winRate').text('0');
                $('#gamesPlayed').text('0');
                $('#wins').text('0');
                $('#netWinLoss').text('$0.00').css('color', '#666');
            });
        });
    </script>
    
    <!-- Stats Modal -->
    <div id="coinflipStatsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('coinflipStatsModal')">&times;</span>
            <h3>Your Coinflip Stats</h3>
            <div class="win-rate-section section" style="margin: 15px 0; background: #f8f9fa; border-radius: 8px;">
                <table class="slots-info-table" style="width: 100%; margin: 0;">
                    <tr>
                        <th style="text-align: left;">Stat</th>
                        <th style="text-align: right;">Value</th>
                    </tr>
                    <tr>
                        <td>Win Rate</td>
                        <td style="text-align: right;"><strong id="winRate">-</strong>%</td>
                    </tr>
                    <tr>
                        <td>Games Played</td>
                        <td style="text-align: right;"><strong id="gamesPlayed">-</strong></td>
                    </tr>
                    <tr>
                        <td>Wins</td>
                        <td style="text-align: right;"><strong id="wins">-</strong></td>
                    </tr>
                    <tr>
                        <td>Net Win/Loss</td>
                        <td style="text-align: right;"><strong id="netWinLoss">-</strong></td>
                    </tr>
                </table>
            </div>
            <p style="margin-top: 10px; color: #666; font-size: 13px;">
                Stats are based on your bets and wins in this game.
            </p>
        </div>
    </div>
    
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
