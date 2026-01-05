<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Blackjack';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="game-container section">
            <div class="game-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h1>🃏 Blackjack</h1>
                <button class="btn btn-outline-secondary" onclick="openModal('blackjackStatsModal')" title="View your stats">
                    📊 Stats
                </button>
            </div>
            
            <div class="blackjack-game">
                <div class="bet-controls">
                    <label>Bet Amount: $</label>
                    <input type="number" id="betAmount" min="1" value="10" step="1" class="bet-input-with-adjust">
                    <small>Max: $<span id="maxBet">100</span></small>
                    <button id="newGameBtn" class="btn btn-primary">New Game</button>
                </div>
                
                <div class="blackjack-board">
                    <div class="dealer-section section">
                        <h3>Dealer</h3>
                        <div class="hand" id="dealerHand"></div>
                        <div class="score" id="dealerScore">Score: 0</div>
                    </div>
                    
                    <div class="player-section section">
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
            
            <div class="game-info section" style="text-align: center;">
                <button class="btn btn-secondary" onclick="openModal('blackjackHowToPlayModal')" style="margin: 5px;">How to Play</button>
                <button class="btn btn-secondary" onclick="openModal('blackjackPayoutsModal')" style="margin: 5px;">Payouts</button>
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
                console.error('Error loading stats:', status, error);
                $('#winRate').text('0');
                $('#gamesPlayed').text('0');
                $('#wins').text('0');
                $('#netWinLoss').text('$0.00').css('color', '#666');
            });
        });
    </script>
    
    <!-- Stats Modal -->
    <div id="blackjackStatsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('blackjackStatsModal')">&times;</span>
            <h3>Your Blackjack Stats</h3>
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
    <div id="blackjackHowToPlayModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('blackjackHowToPlayModal')">&times;</span>
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
        </div>
    </div>
    
    <!-- Payouts Modal -->
    <div id="blackjackPayoutsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('blackjackPayoutsModal')">&times;</span>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
