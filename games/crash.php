<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Crash';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="game-container section">
            <h1>🚀 Crash</h1>
            
            <div class="crash-game">
                <div class="bet-controls">
                    <label>Bet Amount: $</label>
                    <input type="number" id="betAmount" min="1" value="10" step="1" class="bet-input-with-adjust">
                    <small>Max: $<span id="maxBet">100</span></small>
                    <button id="placeBetBtn" class="btn btn-primary">Confirm Bet</button>
                </div>
                
                <div class="crash-display-container">
                    <div class="crash-graph" id="crashGraph">
                        <canvas id="crashCanvas"></canvas>
                        <div class="crash-multiplier-display" id="multiplierDisplay">
                        <?php 
                        $crashMode = getSetting('crash_mode', 'local');
                        if ($crashMode === 'central') {
                            echo 'Waiting for next round...';
                        } else {
                            echo '1.00x';
                        }
                        ?>
                    </div>
                    </div>
                    <div class="crash-controls" id="crashControls" style="display: none;">
                        <button id="cashOutBtn" class="btn btn-success btn-large">CASH OUT</button>
                        <div class="cash-out-info" id="cashOutInfo"></div>
                    </div>
                    <div id="roundCountdown" style="<?php echo $crashMode === 'central' ? 'display: block;' : 'display: none;'; ?> text-align: center; margin-top: 15px; font-size: 18px; color: #667eea; font-weight: bold;">
                        <div id="countdownText">Waiting for next round...</div>
                    </div>
                </div>
                
                <?php if ($crashMode === 'central'): ?>
                <div class="round-info-section section" style="margin-top: 30px; border-radius: 8px; padding: 20px;">
                    <h3 style="margin-top: 0; color: #667eea; margin-bottom: 15px;">⏱️ Round Status</h3>
                    <div id="roundStatusDisplay" style="font-size: 1.1em;">
                        <div><strong>Current Round:</strong> <span id="currentRoundNumber">-</span></div>
                        <div style="margin-top: 10px;"><strong>Status:</strong> <span id="roundStatusText">Waiting...</span></div>
                        <div id="countdownDisplay" style="margin-top: 15px; font-size: 1.3em; color: #667eea; font-weight: bold;">
                            <span id="countdownValue">-</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="crash-history section" id="crashHistory" style="margin-top: 20px; border-radius: 8px; padding: 20px;">
                    <h3 style="margin-top: 0; color: #667eea; margin-bottom: 15px;">📋 Recent Rounds</h3>
                    <div id="historyList" style="max-height: 300px; overflow-y: auto;">
                        <?php if ($crashMode === 'central'): ?>
                        <p class="loading-text" style="text-align: center;">Loading history...</p>
                        <?php else: ?>
                        <p class="loading-text" style="text-align: center;">No history yet</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="crash-probability-section section" style="margin: 20px 0; border-radius: 8px; padding: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #667eea;">📈 Crash Probability Statistics</h4>
                    <div id="probabilityStats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                        <div class="prob-stat-card">
                            <div class="prob-label">Before 1.50x</div>
                            <div class="prob-value" id="prob1_5">-</div>
                        </div>
                        <div class="prob-stat-card">
                            <div class="prob-label">Before 2.00x</div>
                            <div class="prob-value" id="prob2_0">-</div>
                        </div>
                        <div class="prob-stat-card">
                            <div class="prob-label">Before 5.00x</div>
                            <div class="prob-value" id="prob5_0">-</div>
                        </div>
                        <div class="prob-stat-card">
                            <div class="prob-label">Before 10.00x</div>
                            <div class="prob-value" id="prob10_0">-</div>
                        </div>
                        <div class="prob-stat-card">
                            <div class="prob-label">Before 50.00x</div>
                            <div class="prob-value" id="prob50_0">-</div>
                        </div>
                        <div class="prob-stat-card">
                            <div class="prob-label">Before 100.00x</div>
                            <div class="prob-value" id="prob100_0">-</div>
                        </div>
                    </div>
                    <p style="margin-top: 15px; color: #666; font-size: 13px; text-align: center;">
                        These percentages show the probability of crashing before reaching each multiplier
                    </p>
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
                <button class="btn btn-secondary" onclick="openModal('crashHowToPlayModal')" style="margin: 5px;">How to Play</button>
                <button class="btn btn-secondary" onclick="openModal('crashInfoModal')" style="margin: 5px;">Game Info</button>
            </div>
        </div>
    </div>
    
    <?php 
    $crashMode = getSetting('crash_mode', 'local');
    if ($crashMode === 'central') {
        echo '<script src="../js/crash.js"></script>';
    } else {
        echo '<script src="../js/crash-local.js"></script>';
    }
    ?>
    <script>
        $(document).ready(function() {
            // Load win rate for crash
            $.get(getApiPath('getWinRates') + '&game=crash', function(data) {
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
    
    <!-- How to Play Modal -->
    <div id="crashHowToPlayModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('crashHowToPlayModal')">&times;</span>
            <h3>How to Play:</h3>
            <table class="slots-info-table">
                <tr>
                    <td>1. Set your bet amount and click "Confirm Bet"</td>
                </tr>
                <tr>
                    <td>2. Watch the multiplier rise from 1.00x</td>
                </tr>
                <tr>
                    <td>3. Click "CASH OUT" before it crashes to win</td>
                </tr>
                <tr>
                    <td>4. If you don't cash out in time, you lose your bet</td>
                </tr>
                <tr>
                    <td>5. The higher the multiplier when you cash out, the more you win!</td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Game Info Modal -->
    <div id="crashInfoModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('crashInfoModal')">&times;</span>
            <h3>Game Info:</h3>
            <table class="slots-payout-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cash Out</td>
                        <td>Win: Bet × Multiplier</td>
                    </tr>
                    <tr>
                        <td>Crash Before Cashing Out</td>
                        <td>Lose: Your bet amount</td>
                    </tr>
                    <tr>
                        <td>Minimum Multiplier</td>
                        <td>1.00x</td>
                    </tr>
                    <tr>
                        <td>Maximum Multiplier</td>
                        <td>Unlimited (but crashes are random)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
