<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

require_once __DIR__ . '/../includes/provably_fair.php';
require_once __DIR__ . '/../includes/functions.php';

$user = getCurrentUser();
$message = '';
$error = '';

// Get game modes
$rouletteMode = getSetting('roulette_mode', 'local');
$crashMode = getSetting('crash_mode', 'local');

// Get current rounds for monitor
$rouletteRound = $db->getCurrentRouletteRound();
$crashRound = $db->getCurrentCrashRound();

// Get upcoming predictions
$rouletteUpcoming = [];
$crashUpcoming = [];
if ($rouletteMode === 'central') {
    $rouletteUpcoming = ProvablyFair::getUpcomingPredictions($db, 'roulette', 10);
}
if ($crashMode === 'central') {
    $crashUpcoming = ProvablyFair::getUpcomingPredictions($db, 'crash', 10);
}

$pageTitle = 'Admin Panel - Predictions & History';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
    
    <div class="container">
        <div class="admin-panel">
            <h1>🔮 Predictions & History</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php renderAdminNav('predictions'); ?>
            
            <!-- Game Rounds Monitor Section -->
            <div class="admin-section section">
                <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Game Rounds Monitor</h3>
                <p style="margin-bottom: 20px;" class="admin-description">Monitor current game rounds and predict upcoming results (admin only).</p>
                <p style="margin-bottom: 20px; padding: 10px; background: rgba(102, 126, 234, 0.1); border-radius: 5px;" class="admin-description">
                    <strong>Current Modes:</strong> Roulette: <strong><?php echo ucfirst($rouletteMode); ?></strong> | Crash: <strong><?php echo ucfirst($crashMode); ?></strong><br>
                    <small>Central mode requires the game rounds worker to be running.</small>
                </p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <!-- Roulette Round -->
                    <div class="section rounds-card" style="padding: 20px; border-radius: 8px;">
                        <h3 style="margin-top: 0;" class="rounds-card-title">🛞 Roulette</h3>
                        <div id="rouletteRoundInfo" class="rounds-card-content">
                            <?php if ($rouletteMode === 'local'): ?>
                                <p style="color: #999;">Local mode - no synchronized rounds</p>
                            <?php elseif ($rouletteRound): ?>
                                <p><strong>Round #<?php echo $rouletteRound['round_number']; ?></strong></p>
                                <p>Status: <strong><?php echo ucfirst($rouletteRound['status']); ?></strong></p>
                                <?php 
                                $now = time();
                                if ($rouletteRound['status'] === 'betting'): 
                                    $bettingEndsAt = strtotime($rouletteRound['betting_ends_at']);
                                    $bettingEndsIn = max(0, $bettingEndsAt - $now);
                                    $spinningDuration = intval(getSetting('roulette_spinning_duration', 4));
                                    // Time until result = betting ends + spinning duration
                                    $resultIn = $bettingEndsIn + $spinningDuration;
                                    $predictedResult = ProvablyFair::generateRouletteResult($rouletteRound['server_seed'], $rouletteRound['client_seed'] ?? '');
                                ?>
                                    <p style="font-weight: bold; margin-top: 10px;">
                                        🔮 Predicted Result: <span style="font-size: 1.2em; color: <?php echo getRouletteNumberColor($predictedResult); ?>;"><?php echo $predictedResult; ?></span>
                                    </p>
                                    <p style="margin-top: 10px; font-size: 1.1em; color: #667eea;">
                                        Next spin in: <strong><?php echo ceil($resultIn); ?>s</strong>
                                    </p>
                                <?php elseif ($rouletteRound['status'] === 'spinning'): 
                                    $predictedResult = ProvablyFair::generateRouletteResult($rouletteRound['server_seed'], $rouletteRound['client_seed'] ?? '');
                                    $spinningDuration = intval(getSetting('roulette_spinning_duration', 4));
                                    $startedAt = strtotime($rouletteRound['started_at']);
                                    $finishesAt = $startedAt + $spinningDuration;
                                    $timeLeft = max(0, $finishesAt - $now);
                                ?>
                                    <p style="font-weight: bold; margin-top: 10px;">
                                        🔮 Predicted Result: <span style="font-size: 1.2em; color: <?php echo getRouletteNumberColor($predictedResult); ?>;"><?php echo $predictedResult; ?></span>
                                    </p>
                                    <p style="margin-top: 10px; font-size: 1.1em; color: #ffc107;">
                                        Spinning... Result in: <strong><?php echo ceil($timeLeft); ?>s</strong>
                                    </p>
                                <?php elseif ($rouletteRound['status'] === 'finished' && $rouletteRound['result_number'] !== null): ?>
                                    <p style="color: #667eea; font-weight: bold; margin-top: 10px;">
                                        Result: <span style="font-size: 1.2em;"><?php echo $rouletteRound['result_number']; ?></span>
                                    </p>
                                <?php endif; ?>
                                <p style="font-size: 0.9em; margin-top: 10px;" class="rounds-seed-hash">
                                    Server Seed Hash: <code style="font-size: 0.8em;"><?php echo substr($rouletteRound['server_seed_hash'], 0, 16); ?>...</code>
                                </p>
                            <?php else: ?>
                                <p>No active round - worker may not be running</p>
                            <?php endif; ?>
                        </div>
                        <div id="rouletteAllBets" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                            <h4 style="margin-top: 0; color: #667eea;">👥 All Players' Bets</h4>
                            <div id="rouletteAllBetsContent" style="max-height: 300px; overflow-y: auto;">
                                <p style="text-align: center; color: #999;">Loading bets...</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Crash Round -->
                    <div class="section rounds-card" style="padding: 20px; border-radius: 8px;">
                        <h3 style="margin-top: 0;" class="rounds-card-title">🚀 Crash</h3>
                        <div id="crashRoundInfo" class="rounds-card-content">
                            <?php if ($crashMode === 'local'): ?>
                                <p style="color: #999;">Local mode - no synchronized rounds</p>
                            <?php elseif ($crashRound): ?>
                                <p><strong>Round #<?php echo $crashRound['round_number']; ?></strong></p>
                                <p>Status: <strong><?php echo ucfirst($crashRound['status']); ?></strong></p>
                                <?php 
                                $now = time();
                                if ($crashRound['status'] === 'betting'): 
                                    $bettingEndsAt = strtotime($crashRound['betting_ends_at']);
                                    $timeLeft = max(0, $bettingEndsAt - $now);
                                    $distributionParam = floatval(getSetting('crash_distribution_param', 0.99));
                                    $predictedCrashPoint = ProvablyFair::generateCrashPoint($crashRound['server_seed'], $crashRound['client_seed'] ?? '', $distributionParam);
                                ?>
                                    <p style="color: #28a745; font-weight: bold; margin-top: 10px;">
                                        🔮 Predicted Crash Point: <span style="font-size: 1.2em;"><?php echo number_format($predictedCrashPoint, 2); ?>x</span>
                                    </p>
                                    <p style="margin-top: 10px; font-size: 1.1em; color: #667eea;">
                                        Next round in: <strong><?php echo ceil($timeLeft); ?>s</strong>
                                    </p>
                                <?php elseif ($crashRound['status'] === 'running' && $crashRound['crash_point']): ?>
                                    <p style="color: #ffc107; font-weight: bold; margin-top: 10px;">
                                        Crash Point: <span style="font-size: 1.2em;"><?php echo number_format($crashRound['crash_point'], 2); ?>x</span>
                                    </p>
                                    <p style="margin-top: 10px; font-size: 1.1em; color: #ffc107;">
                                        Round in progress...
                                    </p>
                                <?php elseif ($crashRound['status'] === 'finished' && $crashRound['crash_point']): ?>
                                    <p style="color: #667eea; font-weight: bold; margin-top: 10px;">
                                        Crashed at: <span style="font-size: 1.2em;"><?php echo number_format($crashRound['crash_point'], 2); ?>x</span>
                                    </p>
                                <?php endif; ?>
                                <p style="font-size: 0.9em; margin-top: 10px;" class="rounds-seed-hash">
                                    Server Seed Hash: <code style="font-size: 0.8em;"><?php echo substr($crashRound['server_seed_hash'], 0, 16); ?>...</code>
                                </p>
                            <?php else: ?>
                                <p>No active round - worker may not be running</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="admin-section section">
                <div class="section rounds-card" style="padding: 20px; border-radius: 8px; margin-top: 20px;">
                    <h3 style="margin-top: 0;" class="rounds-card-title">🔮 Upcoming Predictions (Next 10)</h3>
                    <p style="margin-bottom: 15px; font-size: 0.9em; color: #666;" class="admin-description">
                        <strong>Note:</strong> These are predictions based on deterministic seed generation for preview purposes. Actual rounds use random seeds, so these predictions are for reference only.
                    </p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h4>Roulette Predictions</h4>
                            <div id="rouletteUpcomingTable">
                                <?php if ($rouletteMode === 'local'): ?>
                                    <p style="color: #999; text-align: center;">Local mode - no predictions</p>
                                <?php elseif (empty($rouletteUpcoming)): ?>
                                    <p style="color: #999; text-align: center;">No predictions available</p>
                                <?php else: ?>
                                    <table class="admin-table" style="font-size: 0.9em;">
                                        <thead>
                                            <tr>
                                                <th>Round</th>
                                                <th>Predicted Result</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rouletteUpcoming as $prediction): 
                                                $number = intval($prediction['predicted_result']);
                                                $colors = getRouletteNumberColors($number, false);
                                            ?>
                                            <tr>
                                                <td>#<?php echo $prediction['round_number']; ?></td>
                                                <td>
                                                    <div style="display: inline-flex; align-items: center; gap: 8px;">
                                                        <div style="width: 30px; height: 30px; border-radius: 50%; background-color: <?php echo $colors['bg']; ?>; color: <?php echo $colors['text']; ?>; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                                                            <?php echo $number; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <h4>Crash Predictions</h4>
                            <div id="crashUpcomingTable">
                                <?php if ($crashMode === 'local'): ?>
                                    <p style="color: #999; text-align: center;">Local mode - no predictions</p>
                                <?php elseif (empty($crashUpcoming)): ?>
                                    <p style="color: #999; text-align: center;">No predictions available</p>
                                <?php else: ?>
                                    <table class="admin-table" style="font-size: 0.9em;">
                                        <thead>
                                            <tr>
                                                <th>Round</th>
                                                <th>Predicted Crash</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($crashUpcoming as $prediction): 
                                                $multValue = floatval($prediction['predicted_crash_point']);
                                                $color = '#dc3545'; // Red for low
                                                if ($multValue >= 5) $color = '#ffc107'; // Yellow for medium
                                                if ($multValue >= 10) $color = '#28a745'; // Green for high
                                            ?>
                                            <tr>
                                                <td>#<?php echo $prediction['round_number']; ?></td>
                                                <td><strong style="color: <?php echo $color; ?>;"><?php echo number_format($prediction['predicted_crash_point'], 2); ?>x</strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section rounds-card" style="padding: 20px; border-radius: 8px; margin-top: 20px;">
                    <h3 style="margin-top: 0;" class="rounds-card-title">📋 Recent History</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h4>Roulette (Last 10)</h4>
                            <div id="rouletteHistoryTable">
                                <table class="admin-table" style="font-size: 0.9em;">
                                    <thead>
                                        <tr>
                                            <th>Round</th>
                                            <th>Result</th>
                                            <th>Finished</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rouletteHistory = $db->getRouletteRoundsHistory(10);
                                        foreach ($rouletteHistory as $round): 
                                        ?>
                                        <tr>
                                            <td>#<?php echo $round['round_number']; ?></td>
                                            <td><strong><?php echo $round['result_number'] !== null ? $round['result_number'] : '-'; ?></strong></td>
                                            <td><?php echo $round['finished_at'] ? date('H:i:s', strtotime($round['finished_at'])) : '-'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4>Crash (Last 10)</h4>
                            <div id="crashHistoryTable">
                                <table class="admin-table" style="font-size: 0.9em;">
                                    <thead>
                                        <tr>
                                            <th>Round</th>
                                            <th>Crash Point</th>
                                            <th>Finished</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $crashHistory = $db->getCrashRoundsHistory(10);
                                        foreach ($crashHistory as $round): 
                                        ?>
                                        <tr>
                                            <td>#<?php echo $round['round_number']; ?></td>
                                            <td><strong><?php echo $round['crash_point'] ? number_format($round['crash_point'], 2) . 'x' : '-'; ?></strong></td>
                                            <td><?php echo $round['finished_at'] ? date('H:i:s', strtotime($round['finished_at'])) : '-'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            const rouletteMode = '<?php echo $rouletteMode; ?>';
            const crashMode = '<?php echo $crashMode; ?>';
            let rouletteRoundInterval = 60;
            let crashRoundInterval = 60;
            
            // Load round intervals from settings (for upcoming relative timestamps)
            $.get('../api/api.php?action=getSettings', function(data) {
                if (data.success && data.settings) {
                    if (data.settings.roulette_round_interval) {
                        rouletteRoundInterval = parseInt(data.settings.roulette_round_interval, 10) || rouletteRoundInterval;
                    }
                    if (data.settings.crash_round_interval) {
                        crashRoundInterval = parseInt(data.settings.crash_round_interval, 10) || crashRoundInterval;
                    }
                }
            }, 'json');
            
            // Function to get roulette number color (matches PHP function in includes/functions.php)
            // Red numbers match the actual roulette wheel: 1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36
            function getRouletteNumberColor(number) {
                const num = parseInt(number, 10);
                const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                const isDarkMode = document.body.classList.contains('dark-mode');
                if (num === 0) {
                    return '#28a745'; // Green
                } else if (redNumbers.includes(num)) {
                    return '#dc3545'; // Red
                } else {
                    return isDarkMode ? '#e0e0e0' : '#333333'; // Light gray in dark mode, dark gray in light mode
                }
            }
            
            // Function to get roulette number colors for circles (matches PHP getRouletteNumberColors)
            function getRouletteNumberColors(number) {
                const num = parseInt(number, 10);
                const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                const isDarkMode = document.body.classList.contains('dark-mode');
                if (num === 0) {
                    return { bg: '#28a745', text: '#ffffff' }; // Green
                } else if (redNumbers.includes(num)) {
                    return { bg: '#dc3545', text: '#ffffff' }; // Red
                } else {
                    return {
                        bg: isDarkMode ? '#2c2c2c' : '#1a1a1a',
                        text: '#ffffff'
                    }; // Black
                }
            }
            
            // Relative time helpers
            function getTimeAgo(date) {
                if (!date) return '-';
                const now = new Date();
                const diff = now - date;
                const seconds = Math.floor(diff / 1000);
                const minutes = Math.floor(seconds / 60);
                const hours = Math.floor(minutes / 60);
                const days = Math.floor(hours / 24);
                
                if (days > 0) return days + 'd ago';
                if (hours > 0) return hours + 'h ago';
                if (minutes > 0) return minutes + 'm ago';
                if (seconds >= 0) return 'just now';
                
                // Future timestamps fallback (should be rare for history)
                const futureSeconds = Math.abs(seconds);
                const futureMinutes = Math.floor(futureSeconds / 60);
                const futureHours = Math.floor(futureMinutes / 60);
                const futureDays = Math.floor(futureHours / 24);
                if (futureDays > 0) return 'in ' + futureDays + 'd';
                if (futureHours > 0) return 'in ' + futureHours + 'h';
                if (futureMinutes > 0) return 'in ' + futureMinutes + 'm';
                return 'soon';
            }
            
            function formatRelativeFromNow(secondsFromNow) {
                if (!isFinite(secondsFromNow)) return '-';
                const s = Math.floor(secondsFromNow);
                if (s <= 0) return 'now';
                const minutes = Math.floor(s / 60);
                const hours = Math.floor(minutes / 60);
                const days = Math.floor(hours / 24);
                if (days > 0) return 'in ' + days + 'd';
                if (hours > 0) return 'in ' + hours + 'h';
                if (minutes > 0) return 'in ' + minutes + 'm';
                return 'in ' + s + 's';
            }
            
            // Game Rounds Monitor functions
            let roundsPollInterval = null;
            
            function updateRouletteAllBetsDisplay(allBets, predictedResult) {
                const container = $('#rouletteAllBetsContent');
                if (!container.length) return;
                
                if (!allBets || allBets.length === 0) {
                    container.html('<p style="text-align: center; color: #999;">No bets placed yet</p>');
                    return;
                }
                
                // Group bets by user
                const betsByUser = {};
                allBets.forEach(function(bet) {
                    const userId = bet.user_id;
                    if (!betsByUser[userId]) {
                        betsByUser[userId] = {
                            username: bet.username || 'Unknown',
                            bets: []
                        };
                    }
                    betsByUser[userId].bets.push(bet);
                });
                
                // Helper function to check if a color/range bet wins
                function checkColorBetWin(betType, resultNum) {
                    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                    const resultColor = resultNum === 0 ? 'green' : (redNumbers.includes(resultNum) ? 'red' : 'black');
                    const isEven = resultNum !== 0 && resultNum % 2 === 0;
                    const isOdd = resultNum !== 0 && resultNum % 2 === 1;
                    
                    switch(betType) {
                        case 'red':
                            return resultColor === 'red';
                        case 'black':
                            return resultColor === 'black';
                        case 'green':
                            return resultNum === 0;
                        case 'even':
                            return isEven;
                        case 'odd':
                            return isOdd;
                        case 'low':
                            return resultNum >= 1 && resultNum <= 18;
                        case 'high':
                            return resultNum >= 19 && resultNum <= 36;
                        default:
                            return false;
                    }
                }
                
                let html = '<div style="display: flex; flex-direction: column; gap: 12px;">';
                
                // Display bets grouped by user
                Object.keys(betsByUser).forEach(function(userId) {
                    const userData = betsByUser[userId];
                    
                    // Consolidate bets by bet_type and bet_value for this user
                    const consolidatedBets = {};
                    userData.bets.forEach(function(bet) {
                        const key = bet.bet_type + '_' + bet.bet_value;
                        if (!consolidatedBets[key]) {
                            consolidatedBets[key] = {
                                bet_type: bet.bet_type,
                                bet_value: bet.bet_value,
                                amount: 0,
                                count: 0,
                                multiplier: bet.multiplier || 2
                            };
                        }
                        consolidatedBets[key].amount += parseFloat(bet.amount || 0);
                        consolidatedBets[key].count += 1;
                    });
                    
                    html += '<div style="border: 1px solid #ddd; border-radius: 6px; padding: 12px; background: #f9f9f9;">';
                    html += '<div style="font-weight: bold; margin-bottom: 8px; color: #667eea;">' + escapeHtml(userData.username) + '</div>';
                    html += '<div style="display: flex; flex-direction: column; gap: 6px;">';
                    
                    // Display consolidated bets
                    Object.keys(consolidatedBets).forEach(function(key) {
                        const bet = consolidatedBets[key];
                        
                        // Check if bet matches predicted result (only if predictedResult is provided)
                        let matchesPrediction = false;
                        if (predictedResult !== null && predictedResult !== undefined) {
                            if (bet.bet_type === 'number') {
                                matchesPrediction = parseInt(bet.bet_value) === parseInt(predictedResult);
                            } else if (bet.bet_type === 'color' || bet.bet_type === 'range') {
                                matchesPrediction = checkColorBetWin(bet.bet_value, parseInt(predictedResult));
                            }
                        }
                        
                        const sparkleIcon = matchesPrediction ? ' 🔮' : '';
                        const countText = bet.count > 1 ? ` (${bet.count}x)` : '';
                        const formatNumber = typeof window.formatNumber === 'function' ? window.formatNumber : function(n) { return parseFloat(n).toFixed(2); };
                        
                        if (bet.bet_type === 'number') {
                            const number = parseInt(bet.bet_value);
                            const colors = getRouletteNumberColors(number);
                            html += '<div style="display: flex; align-items: center; gap: 8px; padding: 6px; background: white; border-radius: 4px;">';
                            html += '<div style="width: 24px; height: 24px; border-radius: 50%; background-color: ' + colors.bg + '; color: ' + colors.text + '; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">' + number + '</div>';
                            html += '<span>Number ' + bet.bet_value + ': $' + formatNumber(bet.amount) + countText + sparkleIcon + '</span>';
                            html += '</div>';
                        } else if (bet.bet_type === 'color' || bet.bet_type === 'range') {
                            const betValue = bet.bet_value || '';
                            const betName = betValue.charAt(0).toUpperCase() + betValue.slice(1);
                            let colorClass = '';
                            if (betValue === 'red') {
                                colorClass = 'bet-item-red';
                            } else if (betValue === 'black') {
                                colorClass = 'bet-item-black';
                            } else if (betValue === 'green') {
                                colorClass = 'bet-item-green';
                            }
                            html += '<div class="bet-item ' + colorClass + '" style="display: flex; align-items: center; padding: 6px; background: white; border-radius: 4px;">';
                            html += '<span>' + betName + ': $' + formatNumber(bet.amount) + ' (' + parseInt(bet.multiplier) + 'x)' + countText + sparkleIcon + '</span>';
                            html += '</div>';
                        }
                    });
                    
                    html += '</div>';
                    html += '</div>';
                });
                
                html += '</div>';
                container.html(html);
            }
            
            function escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
            
            function updateRoundsMonitor() {
                // Only poll if in central mode
                if (rouletteMode === 'central') {
                    // Update roulette round
                    $.get('../api/api.php?action=getRouletteRoundAdmin', function(data) {
                        if (data.success) {
                            if (data.round) {
                                const round = data.round;
                                let html = '';
                                
                                if (round.status === 'betting') {
                                    const bettingEnds = round.time_until_betting_ends || 0;
                                    const resultTime = round.time_until_result || (bettingEnds + 4); // fallback to +4 if not provided
                                    
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Betting</strong></p>';
                                    if (round.predicted_result !== undefined) {
                                        const numberColor = getRouletteNumberColor(round.predicted_result);
                                        html += '<p style="font-weight: bold; margin-top: 10px;">';
                                        html += '🔮 Predicted Result: <span style="font-size: 1.2em; color: ' + numberColor + ';">' + round.predicted_result + '</span></p>';
                                    }
                                    html += '<p style="margin-top: 10px; font-size: 1.1em; color: #667eea;">';
                                    html += 'Next spin in: <strong>' + Math.ceil(resultTime) + 's</strong></p>';
                                } else if (round.status === 'spinning') {
                                    const timeLeft = round.time_until_finish || 0;
                                    
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Spinning</strong></p>';
                                    if (round.predicted_result !== undefined) {
                                        const numberColor = getRouletteNumberColor(round.predicted_result);
                                        html += '<p style="font-weight: bold; margin-top: 10px;">';
                                        html += '🔮 Predicted Result: <span style="font-size: 1.2em; color: ' + numberColor + ';">' + round.predicted_result + '</span></p>';
                                    }
                                    html += '<p style="margin-top: 10px; font-size: 1.1em; color: #ffc107;">';
                                    html += 'Spinning... Result in: <strong>' + Math.ceil(timeLeft) + 's</strong></p>';
                                } else if (round.status === 'finished' && round.result_number !== null) {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Finished</strong></p>';
                                    html += '<p style="color: #667eea; font-weight: bold; margin-top: 10px;">';
                                    html += 'Result: <span style="font-size: 1.2em;">' + round.result_number + '</span></p>';
                                } else {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>' + round.status.charAt(0).toUpperCase() + round.status.slice(1) + '</strong></p>';
                                }
                                
                                html += '<p style="font-size: 0.9em; margin-top: 10px;" class="rounds-seed-hash">';
                                html += 'Server Seed Hash: <code style="font-size: 0.8em;">' + (round.server_seed_hash ? round.server_seed_hash.substring(0, 16) : '') + '...</code></p>';
                                
                                $('#rouletteRoundInfo').html(html);
                                
                                // Update all bets display
                                if (round.all_bets && round.all_bets.length > 0) {
                                    updateRouletteAllBetsDisplay(round.all_bets, round.predicted_result);
                                } else {
                                    $('#rouletteAllBetsContent').html('<p style="text-align: center; color: #999;">No bets placed yet</p>');
                                }
                            } else {
                                $('#rouletteRoundInfo').html('<p>No active round - worker may not be running</p>');
                                $('#rouletteAllBetsContent').html('<p style="text-align: center; color: #999;">No active round</p>');
                            }
                        }
                    }, 'json').fail(function() {
                        console.error('Failed to update roulette round');
                    });
                } else {
                    $('#rouletteRoundInfo').html('<p style="color: #999;">Local mode - no synchronized rounds</p>');
                }
                
                // Only poll if in central mode
                if (crashMode === 'central') {
                    // Update crash round
                    $.get('../api/api.php?action=getCrashRoundAdmin', function(data) {
                        if (data.success) {
                            if (data.round) {
                                const round = data.round;
                                let html = '';
                                
                                if (round.status === 'betting') {
                                    const timeLeft = round.time_until_betting_ends || 0;
                                    
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Betting</strong></p>';
                                    if (round.predicted_crash_point !== undefined) {
                                        html += '<p style="color: #28a745; font-weight: bold; margin-top: 10px;">';
                                        html += '🔮 Predicted Crash Point: <span style="font-size: 1.2em;">' + parseFloat(round.predicted_crash_point).toFixed(2) + 'x</span></p>';
                                    }
                                    html += '<p style="margin-top: 10px; font-size: 1.1em; color: #667eea;">';
                                    html += 'Next round in: <strong>' + Math.ceil(timeLeft) + 's</strong></p>';
                                } else if (round.status === 'running' && round.crash_point) {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Running</strong></p>';
                                    html += '<p style="color: #ffc107; font-weight: bold; margin-top: 10px;">';
                                    html += 'Crash Point: <span style="font-size: 1.2em;">' + parseFloat(round.crash_point).toFixed(2) + 'x</span></p>';
                                    html += '<p style="margin-top: 10px; font-size: 1.1em; color: #ffc107;">Round in progress...</p>';
                                } else if (round.status === 'finished' && round.crash_point) {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Finished</strong></p>';
                                    html += '<p style="color: #667eea; font-weight: bold; margin-top: 10px;">';
                                    html += 'Crashed at: <span style="font-size: 1.2em;">' + parseFloat(round.crash_point).toFixed(2) + 'x</span></p>';
                                } else {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>' + round.status.charAt(0).toUpperCase() + round.status.slice(1) + '</strong></p>';
                                }
                                
                                html += '<p style="font-size: 0.9em; margin-top: 10px;" class="rounds-seed-hash">';
                                html += 'Server Seed Hash: <code style="font-size: 0.8em;">' + (round.server_seed_hash ? round.server_seed_hash.substring(0, 16) : '') + '...</code></p>';
                                
                                $('#crashRoundInfo').html(html);
                            } else {
                                $('#crashRoundInfo').html('<p>No active round - worker may not be running</p>');
                            }
                        }
                    }, 'json').fail(function() {
                        console.error('Failed to update crash round');
                    });
                } else {
                    $('#crashRoundInfo').html('<p style="color: #999;">Local mode - no synchronized rounds</p>');
                }
            }
            
            // Start polling for rounds monitor
            updateRoundsMonitor();
            roundsPollInterval = setInterval(updateRoundsMonitor, 2000); // Poll every 2 seconds
            
            // Cleanup on page unload
            $(window).on('beforeunload', function() {
                if (roundsPollInterval) {
                    clearInterval(roundsPollInterval);
                }
            });
            
            function updatePredictionsAndHistory() {
                // Update history (always, regardless of mode)
                $.get('../api/api.php?action=getRouletteHistory&limit=10', function(data) {
                    if (data.success && data.history) {
                        let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Result</th><th>Finished</th><th>When</th></tr></thead><tbody>';
                        if (data.history.length === 0) {
                            html += '<tr><td colspan="4" style="text-align: center; color: #999;">No history yet</td></tr>';
                        } else {
                            data.history.forEach(function(round) {
                                const finishedDate = round.finished_at ? new Date(round.finished_at) : null;
                                const finishedTime = finishedDate ? finishedDate.toLocaleTimeString() : '-';
                                const relative = finishedDate ? getTimeAgo(finishedDate) : '-';
                                html += '<tr><td>#' + round.round_number + '</td><td><strong>' + (round.result_number !== null ? round.result_number : '-') + '</strong></td><td>' + finishedTime + '</td><td>' + relative + '</td></tr>';
                            });
                        }
                        html += '</tbody></table>';
                        $('#rouletteHistoryTable').html(html);
                    }
                }, 'json');
                
                $.get('../api/api.php?action=getCrashHistory&limit=10', function(data) {
                    if (data.success && data.history) {
                        let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Crash Point</th><th>Finished</th><th>When</th></tr></thead><tbody>';
                        if (data.history.length === 0) {
                            html += '<tr><td colspan="4" style="text-align: center; color: #999;">No history yet</td></tr>';
                        } else {
                            data.history.forEach(function(round) {
                                const finishedDate = round.finished_at ? new Date(round.finished_at) : null;
                                const finishedTime = finishedDate ? finishedDate.toLocaleTimeString() : '-';
                                const relative = finishedDate ? getTimeAgo(finishedDate) : '-';
                                html += '<tr><td>#' + round.round_number + '</td><td><strong>' + (round.crash_point ? parseFloat(round.crash_point).toFixed(2) + 'x' : '-') + '</strong></td><td>' + finishedTime + '</td><td>' + relative + '</td></tr>';
                            });
                        }
                        html += '</tbody></table>';
                        $('#crashHistoryTable').html(html);
                    }
                }, 'json');
                
                // Update upcoming predictions
                if (rouletteMode === 'central') {
                    $.get('../api/api.php?action=getUpcomingPredictions&game=roulette&count=10', function(data) {
                        if (data.success && data.predictions) {
                            if (data.predictions.length === 0) {
                                $('#rouletteUpcomingTable').html('<p style="color: #999; text-align: center;">No predictions available</p>');
                            } else {
                                let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Predicted Result</th><th>When</th></tr></thead><tbody>';
                                data.predictions.forEach(function(pred, index) {
                                    const num = parseInt(pred.predicted_result, 10);
                                    const colors = getRouletteNumberColors(num);
                                    const secondsFromNow = rouletteRoundInterval * (index + 1);
                                    const when = formatRelativeFromNow(secondsFromNow);
                                    html += '<tr><td>#' + pred.round_number + '</td>';
                                    html += '<td><div style="display: inline-flex; align-items: center; gap: 8px;">';
                                    html += '<div style="width: 30px; height: 30px; border-radius: 50%; background-color: ' + colors.bg + '; color: ' + colors.text + '; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">';
                                    html += num;
                                    html += '</div></div></td><td>' + when + '</td></tr>';
                                });
                                html += '</tbody></table>';
                                $('#rouletteUpcomingTable').html(html);
                            }
                        }
                    }, 'json');
                }
                
                if (crashMode === 'central') {
                    $.get('../api/api.php?action=getUpcomingPredictions&game=crash&count=10', function(data) {
                        if (data.success && data.predictions) {
                            if (data.predictions.length === 0) {
                                $('#crashUpcomingTable').html('<p style="color: #999; text-align: center;">No predictions available</p>');
                            } else {
                                let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Predicted Crash</th><th>When</th></tr></thead><tbody>';
                                data.predictions.forEach(function(pred, index) {
                                    const multValue = parseFloat(pred.predicted_crash_point);
                                    let color = '#dc3545'; // Red for low
                                    if (multValue >= 5) color = '#ffc107'; // Yellow for medium
                                    if (multValue >= 10) color = '#28a745'; // Green for high
                                    const secondsFromNow = crashRoundInterval * (index + 1);
                                    const when = formatRelativeFromNow(secondsFromNow);
                                    html += '<tr><td>#' + pred.round_number + '</td><td><strong style="color: ' + color + ';">' + parseFloat(pred.predicted_crash_point).toFixed(2) + 'x</strong></td><td>' + when + '</td></tr>';
                                });
                                html += '</tbody></table>';
                                $('#crashUpcomingTable').html(html);
                            }
                        }
                    }, 'json');
                }
            }
            
            // Update immediately on page load
            updatePredictionsAndHistory();
            
            // Update every 5 seconds
            setInterval(updatePredictionsAndHistory, 5000);
        });
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
