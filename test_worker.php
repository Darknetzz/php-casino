<?php
/**
 * Quick test to check if game rounds worker is needed
 * Access this file in your browser to see if rounds are being created
 */

require_once __DIR__ . '/includes/config.php';
requireLogin();
requireAdmin();

$db = new Database();

$rouletteRound = $db->getCurrentRouletteRound();
$crashRound = $db->getCurrentCrashRound();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Worker Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Game Rounds Worker Test</h1>
    
    <div class="status <?php echo $rouletteRound ? 'success' : 'error'; ?>">
        <strong>Roulette:</strong> <?php echo $rouletteRound ? "Round #{$rouletteRound['round_number']} ({$rouletteRound['status']})" : 'No active round'; ?>
    </div>
    
    <div class="status <?php echo $crashRound ? 'success' : 'error'; ?>">
        <strong>Crash:</strong> <?php echo $crashRound ? "Round #{$crashRound['round_number']} ({$crashRound['status']})" : 'No active round'; ?>
    </div>
    
    <?php if (!$rouletteRound && !$crashRound): ?>
    <div class="status error">
        <strong>⚠️ Worker Not Running</strong><br>
        No rounds are being created. You need to start the game rounds worker.<br><br>
        <strong>To start the worker:</strong><br>
        <code>php workers/game_rounds_worker.php</code><br><br>
        Or set up a cron job (see <code>workers/README.md</code>)
    </div>
    <?php else: ?>
    <div class="status success">
        <strong>✅ Worker is Running</strong><br>
        Rounds are being created successfully.
    </div>
    <?php endif; ?>
    
    <div class="status info">
        <strong>Note:</strong> This page checks if rounds exist. If no rounds are found, the worker needs to be started.
    </div>
</body>
</html>
