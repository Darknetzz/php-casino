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
            
            <!-- Admin Navigation Tabs -->
            <div class="admin-nav-tabs">
                <a href="admin.php?tab=multipliers" class="admin-tab">
                    <span>🎰</span> Game Settings
                </a>
                <a href="admin.php?tab=limits" class="admin-tab">
                    <span>🔒</span> Limits
                </a>
                <a href="admin.php?tab=settings" class="admin-tab">
                    <span>📊</span> Casino Settings
                </a>
                <a href="admin.php?tab=users" class="admin-tab">
                    <span>👥</span> User Management
                </a>
                <a href="admin.php?tab=rounds" class="admin-tab">
                    <span>🎯</span> Game Rounds
                </a>
                <a href="admin_predictions.php" class="admin-tab active">
                    <span>🔮</span> Predictions & History
                </a>
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
            
            function updatePredictionsAndHistory() {
                // Update history (always, regardless of mode)
                $.get('../api/api.php?action=getRouletteHistory&limit=10', function(data) {
                    if (data.success && data.history) {
                        let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Result</th><th>Finished</th></tr></thead><tbody>';
                        if (data.history.length === 0) {
                            html += '<tr><td colspan="3" style="text-align: center; color: #999;">No history yet</td></tr>';
                        } else {
                            data.history.forEach(function(round) {
                                const finishedTime = round.finished_at ? new Date(round.finished_at).toLocaleTimeString() : '-';
                                html += '<tr><td>#' + round.round_number + '</td><td><strong>' + (round.result_number !== null ? round.result_number : '-') + '</strong></td><td>' + finishedTime + '</td></tr>';
                            });
                        }
                        html += '</tbody></table>';
                        $('#rouletteHistoryTable').html(html);
                    }
                }, 'json');
                
                $.get('../api/api.php?action=getCrashHistory&limit=10', function(data) {
                    if (data.success && data.history) {
                        let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Crash Point</th><th>Finished</th></tr></thead><tbody>';
                        if (data.history.length === 0) {
                            html += '<tr><td colspan="3" style="text-align: center; color: #999;">No history yet</td></tr>';
                        } else {
                            data.history.forEach(function(round) {
                                const finishedTime = round.finished_at ? new Date(round.finished_at).toLocaleTimeString() : '-';
                                html += '<tr><td>#' + round.round_number + '</td><td><strong>' + (round.crash_point ? parseFloat(round.crash_point).toFixed(2) + 'x' : '-') + '</strong></td><td>' + finishedTime + '</td></tr>';
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
                                let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Predicted Result</th></tr></thead><tbody>';
                                data.predictions.forEach(function(pred) {
                                    const num = parseInt(pred.predicted_result, 10);
                                    const colors = getRouletteNumberColors(num);
                                    html += '<tr><td>#' + pred.round_number + '</td>';
                                    html += '<td><div style="display: inline-flex; align-items: center; gap: 8px;">';
                                    html += '<div style="width: 30px; height: 30px; border-radius: 50%; background-color: ' + colors.bg + '; color: ' + colors.text + '; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">';
                                    html += num;
                                    html += '</div></div></td></tr>';
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
                                let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Predicted Crash</th></tr></thead><tbody>';
                                data.predictions.forEach(function(pred) {
                                    const multValue = parseFloat(pred.predicted_crash_point);
                                    let color = '#dc3545'; // Red for low
                                    if (multValue >= 5) color = '#ffc107'; // Yellow for medium
                                    if (multValue >= 10) color = '#28a745'; // Green for high
                                    html += '<tr><td>#' + pred.round_number + '</td><td><strong style="color: ' + color + ';">' + parseFloat(pred.predicted_crash_point).toFixed(2) + 'x</strong></td></tr>';
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
