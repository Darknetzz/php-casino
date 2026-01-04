<?php
/**
 * Game Rounds Worker
 * 
 * This script manages automatic game rounds for roulette and crash.
 * It should be run continuously (via cron every few seconds or as a daemon).
 * 
 * Usage: php workers/game_rounds_worker.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/provably_fair.php';

$db = new Database();
$pf = new ProvablyFair();

// Settings
$rouletteBettingDuration = intval(getSetting('roulette_betting_duration', 30));
$rouletteSpinningDuration = intval(getSetting('roulette_spinning_duration', 4));
$rouletteRoundInterval = intval(getSetting('roulette_round_interval', 60));
$crashBettingDuration = intval(getSetting('crash_betting_duration', 30));
$crashRoundInterval = intval(getSetting('crash_round_interval', 60));
$crashDistributionParam = floatval(getSetting('crash_distribution_param', 0.99));

function processRouletteRound($db, $pf) {
    global $rouletteBettingDuration, $rouletteSpinningDuration;
    
    $currentRound = $db->getCurrentRouletteRound();
    
    if (!$currentRound) {
        // No active round, create a new one
        $roundNumber = ProvablyFair::getNextRoundNumber($db, 'roulette');
        $serverSeed = ProvablyFair::generateServerSeed();
        $serverSeedHash = ProvablyFair::hashServerSeed($serverSeed);
        $bettingEndsAt = date('Y-m-d H:i:s', time() + $rouletteBettingDuration);
        
        $roundId = $db->createRouletteRound($roundNumber, $serverSeed, $serverSeedHash, $bettingEndsAt);
        echo "Created roulette round #$roundNumber (ID: $roundId)\n";
        return;
    }
    
    $now = time();
    $bettingEndsAt = strtotime($currentRound['betting_ends_at']);
    
    if ($currentRound['status'] === 'betting' && $now >= $bettingEndsAt) {
        // Betting period ended, start spinning
        $db->updateRouletteRound($currentRound['id'], 'spinning');
        echo "Roulette round #{$currentRound['round_number']} started spinning\n";
    } elseif ($currentRound['status'] === 'spinning') {
        $startedAt = strtotime($currentRound['started_at']);
        if ($now >= $startedAt + $rouletteSpinningDuration) {
            // Spinning finished, calculate result
            $resultNumber = ProvablyFair::generateRouletteResult($currentRound['server_seed'], $currentRound['client_seed'] ?? '');
            $db->updateRouletteRound($currentRound['id'], 'finished', $resultNumber);
            
            // Process all bets for this round
            processRouletteBets($db, $currentRound['id'], $resultNumber);
            
            echo "Roulette round #{$currentRound['round_number']} finished with result: $resultNumber\n";
        }
    }
}

function processCrashRound($db, $pf) {
    global $crashBettingDuration, $crashDistributionParam;
    
    $currentRound = $db->getCurrentCrashRound();
    
    if (!$currentRound) {
        // No active round, create a new one
        $roundNumber = ProvablyFair::getNextRoundNumber($db, 'crash');
        $serverSeed = ProvablyFair::generateServerSeed();
        $serverSeedHash = ProvablyFair::hashServerSeed($serverSeed);
        $bettingEndsAt = date('Y-m-d H:i:s', time() + $crashBettingDuration);
        
        $roundId = $db->createCrashRound($roundNumber, $serverSeed, $serverSeedHash, $bettingEndsAt);
        echo "Created crash round #$roundNumber (ID: $roundId)\n";
        return;
    }
    
    $now = time();
    $bettingEndsAt = strtotime($currentRound['betting_ends_at']);
    
    if ($currentRound['status'] === 'betting' && $now >= $bettingEndsAt) {
        // Betting period ended, calculate crash point and start round
        $crashPoint = ProvablyFair::generateCrashPoint($currentRound['server_seed'], $currentRound['client_seed'] ?? '', $crashDistributionParam);
        $db->updateCrashRound($currentRound['id'], 'running', $crashPoint);
        echo "Crash round #{$currentRound['round_number']} started (crash point: {$crashPoint}x)\n";
    } elseif ($currentRound['status'] === 'running') {
        // Get the crash point if not already set
        if (!$currentRound['crash_point']) {
            $crashPoint = ProvablyFair::generateCrashPoint($currentRound['server_seed'], $currentRound['client_seed'] ?? '', $crashDistributionParam);
            $db->updateCrashRound($currentRound['id'], 'running', $crashPoint);
            $currentRound['crash_point'] = $crashPoint;
        }
        $startedAt = strtotime($currentRound['started_at']);
        // Crash rounds run for a fixed duration (simulate multiplier rising)
        // In a real implementation, this would be handled client-side with the server providing the crash point
        // For now, we'll mark it as finished after a reasonable time
        $crashDuration = 30; // seconds (enough time for multiplier to reach crash point)
        if ($now >= $startedAt + $crashDuration) {
            $db->updateCrashRound($currentRound['id'], 'finished');
            
            // Process all bets for this round
            processCrashBets($db, $currentRound['id'], $currentRound['crash_point']);
            
            echo "Crash round #{$currentRound['round_number']} finished (crashed at {$currentRound['crash_point']}x)\n";
        }
    }
}

function processRouletteBets($db, $roundId, $resultNumber) {
    $bets = $db->getRouletteBetsForRound($roundId);
    
    foreach ($bets as $bet) {
        $won = false;
        $payout = 0;
        
        // Check if bet won based on bet_type and bet_value
        if ($bet['bet_type'] === 'number') {
            // Direct number bet
            if (intval($bet['bet_value']) === $resultNumber) {
                $won = true;
                $payout = $bet['amount'] * $bet['multiplier'];
            }
        } elseif ($bet['bet_type'] === 'color' || $bet['bet_type'] === 'range') {
            // Color or range bet
            $betValue = $bet['bet_value'];
            $won = checkRouletteBetWin($betValue, $resultNumber);
            if ($won) {
                $payout = $bet['amount'] * $bet['multiplier'];
            }
        }
        
        // Update bet result
        $db->updateRouletteBetResult($bet['id'], $won, $payout);
        
        // Update user balance and create transaction
        $user = $db->getUserById($bet['user_id']);
        if ($user) {
            if ($won) {
                $newBalance = $user['balance'] + $payout;
                $db->updateBalance($bet['user_id'], $newBalance);
                $db->addTransaction($bet['user_id'], 'win', $payout, "Roulette round win: {$bet['bet_type']} {$bet['bet_value']} (result: $resultNumber)", 'roulette');
            } else {
                // Bet was already deducted when placed, just record the loss
                $db->addTransaction($bet['user_id'], 'bet', $bet['amount'], "Roulette round loss: {$bet['bet_type']} {$bet['bet_value']} (result: $resultNumber)", 'roulette');
            }
        }
    }
}

function checkRouletteBetWin($betType, $resultNumber) {
    // Get number color
    $rouletteNumbers = [
        0 => 'green', 32 => 'red', 15 => 'black', 19 => 'red', 4 => 'black', 21 => 'red',
        2 => 'black', 25 => 'red', 17 => 'black', 34 => 'red', 6 => 'black', 27 => 'red',
        13 => 'black', 36 => 'red', 11 => 'black', 30 => 'red', 8 => 'black', 23 => 'red',
        10 => 'black', 5 => 'red', 24 => 'black', 16 => 'red', 33 => 'black', 1 => 'red',
        20 => 'black', 14 => 'red', 31 => 'black', 9 => 'red', 22 => 'black', 18 => 'red',
        29 => 'black', 7 => 'red', 28 => 'black', 12 => 'red', 35 => 'black', 3 => 'red',
        26 => 'black'
    ];
    
    $resultColor = $rouletteNumbers[$resultNumber] ?? 'black';
    $isEven = $resultNumber !== 0 && $resultNumber % 2 === 0;
    $isOdd = $resultNumber !== 0 && $resultNumber % 2 === 1;
    
    switch ($betType) {
        case 'red':
            return $resultColor === 'red';
        case 'black':
            return $resultColor === 'black';
        case 'green':
            return $resultNumber === 0;
        case 'even':
            return $isEven;
        case 'odd':
            return $isOdd;
        case 'low':
            return $resultNumber >= 1 && $resultNumber <= 18;
        case 'high':
            return $resultNumber >= 19 && $resultNumber <= 36;
        default:
            return false;
    }
}

function processCrashBets($db, $roundId, $crashPoint) {
    $bets = $db->getCrashBetsForRound($roundId);
    
    foreach ($bets as $bet) {
        $won = false;
        $payout = 0;
        
        if ($bet['cash_out_multiplier'] && $bet['cash_out_multiplier'] > 0) {
            // User cashed out
            if ($bet['cash_out_multiplier'] < $crashPoint) {
                $won = true;
                $payout = $bet['bet_amount'] * $bet['cash_out_multiplier'];
            }
        } else {
            // User didn't cash out, lost
            $won = false;
        }
        
        // Update bet result
        $db->updateCrashBetResult($bet['id'], $won, $payout);
        
        // Update user balance and create transaction
        $user = $db->getUserById($bet['user_id']);
        if ($user) {
            if ($won) {
                $newBalance = $user['balance'] + $payout;
                $db->updateBalance($bet['user_id'], $newBalance);
                $db->addTransaction($bet['user_id'], 'win', $payout, "Crash round win: cashed out at {$bet['cash_out_multiplier']}x (crashed at {$crashPoint}x)", 'crash');
            } else {
                // Bet was already deducted when placed, just record the loss
                $db->addTransaction($bet['user_id'], 'bet', $bet['bet_amount'], "Crash round loss: crashed at {$crashPoint}x", 'crash');
            }
        }
    }
}

// Main loop
if (php_sapi_name() === 'cli') {
    // Run as CLI script
    echo "Game Rounds Worker started\n";
    echo "Processing rounds every 2 seconds...\n\n";
    
    while (true) {
        try {
            processRouletteRound($db, $pf);
            processCrashRound($db, $pf);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        
        sleep(2); // Check every 2 seconds
    }
} else {
    // Run as web request (for cron)
    processRouletteRound($db, $pf);
    processCrashRound($db, $pf);
    echo "Rounds processed";
}
?>
