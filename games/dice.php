<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Dice';
$diceNumDice = intval(getSetting('dice_num_dice', 6));
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="game-container section">
            <div class="game-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h1>🎲 Dice Roll</h1>
                <button class="btn btn-outline-secondary" onclick="openModal('diceStatsModal')" title="View your stats">
                    📊 Stats
                </button>
            </div>
            
            <div class="dice-game">
                <div class="dice-container">
                    <div class="dice-grid">
                        <?php for ($i = 1; $i <= $diceNumDice; $i++): ?>
                        <div class="dice" id="dice<?php echo $i; ?>">
                            <div class="dice-face">⚀</div>
                        </div>
                        <?php endfor; ?>
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
    <div id="diceStatsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('diceStatsModal')">&times;</span>
            <h3>Your Dice Stats</h3>
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
    <div id="diceHowToPlayModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('diceHowToPlayModal')">&times;</span>
            <h3>How to Play:</h3>
            <table class="slots-info-table">
                <tr>
                    <td>1. Set your bet amount</td>
                </tr>
                <tr>
                    <td>2. Click ROLL DICE to roll <?php echo $diceNumDice; ?> dice</td>
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
