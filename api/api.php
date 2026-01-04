<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getBalance':
        $user = getCurrentUser();
        echo json_encode(['success' => true, 'balance' => $user['balance']]);
        break;
        
    case 'updateBalance':
        $amount = floatval($_POST['amount'] ?? 0);
        $type = $_POST['type'] ?? 'bet';
        $description = $_POST['description'] ?? '';
        $game = $_POST['game'] ?? null;
        
        $user = getCurrentUser();
        
        // Check max deposit for deposits
        if ($type === 'deposit') {
            $maxDeposit = floatval(getSetting('max_deposit', 10000));
            if ($amount > $maxDeposit) {
                echo json_encode(['success' => false, 'message' => 'Deposit amount exceeds maximum of $' . number_format($maxDeposit, 2)]);
                break;
            }
        }
        
        // Check max bet for bets
        if ($type === 'bet' && $amount < 0) {
            $maxBet = floatval(getSetting('max_bet', 100));
            if (abs($amount) > $maxBet) {
                echo json_encode(['success' => false, 'message' => 'Bet amount exceeds maximum of $' . number_format($maxBet, 2)]);
                break;
            }
        }
        
        $newBalance = $user['balance'] + $amount;
        
        if ($newBalance < 0) {
            echo json_encode(['success' => false, 'message' => 'Insufficient funds']);
        } else {
            $db->updateBalance($user['id'], $newBalance);
            $db->addTransaction($user['id'], $type, abs($amount), $description, $game);
            echo json_encode(['success' => true, 'balance' => $newBalance]);
        }
        break;
        
    case 'getSettings':
        $user = getCurrentUser();
        $globalDefaultBet = floatval(getSetting('default_bet', 10));
        $userDefaultBet = isset($user['default_bet']) && $user['default_bet'] !== null ? floatval($user['default_bet']) : null;
        $defaultBet = $userDefaultBet !== null ? $userDefaultBet : $globalDefaultBet;
        
        // Get slots symbols (dynamic)
        $slotsSymbolsJson = getSetting('slots_symbols', '[]');
        $slotsSymbols = json_decode($slotsSymbolsJson, true);
        if (empty($slotsSymbols) || !is_array($slotsSymbols)) {
            // Default symbols if none exist
            $slotsSymbols = [
                ['emoji' => '🍒', 'multiplier' => 2.0],
                ['emoji' => '🍋', 'multiplier' => 3.0],
                ['emoji' => '🍊', 'multiplier' => 4.0],
                ['emoji' => '🍇', 'multiplier' => 5.0],
                ['emoji' => '🎰', 'multiplier' => 10.0]
            ];
        }
        
        // Get custom combinations
        $slotsCustomCombinationsJson = getSetting('slots_custom_combinations', '[]');
        $slotsCustomCombinations = json_decode($slotsCustomCombinationsJson, true);
        if (empty($slotsCustomCombinations) || !is_array($slotsCustomCombinations)) {
            $slotsCustomCombinations = [];
        }
        
        // Get N-of-a-kind rules
        $slotsNOfKindRulesJson = getSetting('slots_n_of_kind_rules', '[]');
        $slotsNOfKindRules = json_decode($slotsNOfKindRulesJson, true);
        // Migrate old setting if rules are empty
        if (empty($slotsNOfKindRules) || !is_array($slotsNOfKindRules)) {
            $oldTwoOfKind = floatval(getSetting('slots_two_of_kind_multiplier', 0));
            if ($oldTwoOfKind > 0) {
                $slotsNOfKindRules = [
                    ['count' => 2, 'symbol' => 'any', 'multiplier' => $oldTwoOfKind]
                ];
            } else {
                $slotsNOfKindRules = [];
            }
        }
        
        $slotsMultipliers = [
            'symbols' => $slotsSymbols,
            'n_of_kind_rules' => $slotsNOfKindRules,
            'custom_combinations' => $slotsCustomCombinations
        ];
        
        // Get plinko multipliers
        $plinkoMultipliersStr = getSetting('plinko_multipliers', '0.2,0.5,0.8,1.0,2.0,1.0,0.8,0.5,0.2');
        $plinkoMultipliers = array_map('floatval', explode(',', $plinkoMultipliersStr));
        
        // Get number of dice
        $diceNumDice = intval(getSetting('dice_num_dice', 6));
        
        // Get dice multipliers from JSON or fallback to individual settings
        $diceMultipliersJson = getSetting('dice_multipliers', null);
        $diceMultipliers = [];
        if ($diceMultipliersJson) {
            $decoded = json_decode($diceMultipliersJson, true);
            if (is_array($decoded)) {
                // Convert all values to floats
                foreach ($decoded as $key => $value) {
                    $diceMultipliers[intval($key)] = floatval($value);
                }
            }
        }
        // Fallback to individual settings for backward compatibility
        if (empty($diceMultipliers)) {
            $diceMultipliers = [
                1 => floatval(getSetting('dice_1_of_kind_multiplier', 0)),
                2 => floatval(getSetting('dice_2_of_kind_multiplier', 0)),
                3 => floatval(getSetting('dice_3_of_kind_multiplier', 2)),
                4 => floatval(getSetting('dice_4_of_kind_multiplier', 5)),
                5 => floatval(getSetting('dice_5_of_kind_multiplier', 10)),
                6 => floatval(getSetting('dice_6_of_kind_multiplier', 20))
            ];
        }
        // Ensure we have multipliers for all dice counts up to dice_num_dice
        for ($i = 1; $i <= $diceNumDice; $i++) {
            if (!isset($diceMultipliers[$i])) {
                $diceMultipliers[$i] = 0.0;
            } else {
                $diceMultipliers[$i] = floatval($diceMultipliers[$i]);
            }
        }
        
        // Get crash settings
        $crashSpeed = floatval(getSetting('crash_speed', 0.02));
        $crashMaxMultiplier = floatval(getSetting('crash_max_multiplier', 0));
        $crashDistributionParam = floatval(getSetting('crash_distribution_param', 0.99));
        
        // Get blackjack settings
        $blackjackRegularMultiplier = floatval(getSetting('blackjack_regular_multiplier', 2.0));
        $blackjackBlackjackMultiplier = floatval(getSetting('blackjack_blackjack_multiplier', 2.5));
        $blackjackDealerStand = intval(getSetting('blackjack_dealer_stand', 17));
        
        $settings = [
            'max_bet' => floatval(getSetting('max_bet', 100)),
            'max_deposit' => floatval(getSetting('max_deposit', 10000)),
            'default_bet' => $defaultBet,
            'slots_multipliers' => $slotsMultipliers,
            'slots_num_reels' => intval(getSetting('slots_num_reels', 3)),
            'slots_win_row' => intval(getSetting('slots_win_row', 1)),
            'slots_bet_rows' => intval(getSetting('slots_bet_rows', 1)),
            'slots_duration' => intval(getSetting('slots_duration', 2500)),
            'plinko_multipliers' => $plinkoMultipliers,
            'plinko_duration' => intval(getSetting('plinko_duration', 350)),
            'dice_multipliers' => $diceMultipliers,
            'dice_num_dice' => $diceNumDice,
            'crash_speed' => $crashSpeed,
            'crash_max_multiplier' => $crashMaxMultiplier,
            'crash_distribution_param' => $crashDistributionParam,
            'blackjack_regular_multiplier' => $blackjackRegularMultiplier,
            'blackjack_blackjack_multiplier' => $blackjackBlackjackMultiplier,
            'blackjack_dealer_stand' => $blackjackDealerStand,
            'roulette_mode' => getSetting('roulette_mode', 'local'),
            'crash_mode' => getSetting('crash_mode', 'local')
        ];
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;
        
    case 'updateDefaultBet':
        $user = getCurrentUser();
        $defaultBet = isset($_POST['default_bet']) && $_POST['default_bet'] !== '' ? floatval($_POST['default_bet']) : null;
        
        if ($defaultBet !== null && $defaultBet < 1) {
            echo json_encode(['success' => false, 'message' => 'Default bet must be at least $1']);
            break;
        }
        
        $db->setUserDefaultBet($user['id'], $defaultBet);
        echo json_encode(['success' => true]);
        break;
        
    case 'getTransactions':
        $user = getCurrentUser();
        $transactions = $db->getTransactions($user['id'], 10);
        echo json_encode(['success' => true, 'transactions' => $transactions]);
        break;
        
    case 'getWinRates':
        $user = getCurrentUser();
        $game = $_GET['game'] ?? null;
        
        if ($game !== null) {
            $winRate = $db->getWinRate($user['id'], $game);
            echo json_encode(['success' => true, 'winRate' => $winRate]);
        } else {
            $winRates = $db->getAllWinRates($user['id']);
            echo json_encode(['success' => true, 'winRates' => $winRates]);
        }
        break;
        
    case 'getTotalWinLoss':
        $user = getCurrentUser();
        $winLoss = $db->getTotalWinLoss($user['id']);
        echo json_encode(['success' => true, 'winLoss' => $winLoss]);
        break;
        
    case 'getTotalDeposits':
        $user = getCurrentUser();
        $totalDeposits = $db->getTotalDeposits($user['id']);
        echo json_encode(['success' => true, 'totalDeposits' => $totalDeposits]);
        break;
        
    case 'resetStats':
        $user = getCurrentUser();
        $result = $db->resetStats($user['id']);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Stats reset successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reset stats']);
        }
        break;
        
    case 'getDarkMode':
        $user = getCurrentUser();
        $darkMode = $db->getDarkMode($user['id']);
        echo json_encode(['success' => true, 'darkMode' => $darkMode]);
        break;
        
    case 'updateDarkMode':
        $user = getCurrentUser();
        $darkMode = isset($_POST['darkMode']) ? (bool)$_POST['darkMode'] : false;
        $db->setDarkMode($user['id'], $darkMode);
        echo json_encode(['success' => true, 'darkMode' => $darkMode]);
        break;
    
    // Roulette round endpoints
    case 'getRouletteRound':
        require_once __DIR__ . '/../includes/provably_fair.php';
        $user = getCurrentUser();
        $round = $db->getCurrentRouletteRound();
        if ($round) {
            $now = time();
            $bettingEndsAt = strtotime($round['betting_ends_at']);
            $timeUntilBettingEnds = max(0, $bettingEndsAt - $now);
            
            // Calculate time until round starts/finishes
            $timeUntilStart = 0;
            $timeUntilFinish = 0;
            if ($round['status'] === 'betting') {
                $timeUntilStart = $timeUntilBettingEnds;
            } elseif ($round['status'] === 'spinning' && $round['started_at']) {
                $spinningDuration = intval(getSetting('roulette_spinning_duration', 4));
                $startedAt = strtotime($round['started_at']);
                $finishesAt = $startedAt + $spinningDuration;
                $timeUntilFinish = max(0, $finishesAt - $now);
            }
            
            // Get user's bets for this round
            $userBets = $db->getUserRouletteBetsForRound($round['id'], $user['id']);
            
            $round['time_until_betting_ends'] = $timeUntilBettingEnds;
            $round['time_until_start'] = $timeUntilStart;
            $round['time_until_finish'] = $timeUntilFinish;
            $round['user_bets'] = $userBets;
            
            // Don't reveal server seed until round is finished (for provably fair)
            if ($round['status'] !== 'finished') {
                unset($round['server_seed']);
            }
        }
        echo json_encode(['success' => true, 'round' => $round]);
        break;
    
    case 'placeRouletteBet':
        require_once __DIR__ . '/../includes/provably_fair.php';
        $round = $db->getCurrentRouletteRound();
        
        if (!$round || $round['status'] !== 'betting') {
            echo json_encode(['success' => false, 'message' => 'No active betting round']);
            break;
        }
        
        $betType = $_POST['bet_type'] ?? '';
        $betValue = $_POST['bet_value'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $multiplier = floatval($_POST['multiplier'] ?? 1);
        
        if ($amount < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid bet amount']);
            break;
        }
        
        $maxBet = floatval(getSetting('max_bet', 100));
        if ($amount > $maxBet) {
            echo json_encode(['success' => false, 'message' => 'Bet amount exceeds maximum of $' . number_format($maxBet, 2)]);
            break;
        }
        
        // Check balance
        if ($user['balance'] < $amount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient funds']);
            break;
        }
        
        // Deduct bet amount
        $newBalance = $user['balance'] - $amount;
        $db->updateBalance($user['id'], $newBalance);
        
        // Place bet
        $db->placeRouletteBet($round['id'], $user['id'], $betType, $betValue, $amount, $multiplier);
        
        echo json_encode(['success' => true, 'balance' => $newBalance]);
        break;
    
    case 'getRouletteHistory':
        $limit = intval($_GET['limit'] ?? 20);
        $history = $db->getRouletteRoundsHistory($limit);
        echo json_encode(['success' => true, 'history' => $history]);
        break;
    
    // Crash round endpoints
    case 'getCrashRound':
        require_once __DIR__ . '/../includes/provably_fair.php';
        $user = getCurrentUser();
        $round = $db->getCurrentCrashRound();
        if ($round) {
            $now = time();
            $bettingEndsAt = strtotime($round['betting_ends_at']);
            $timeUntilBettingEnds = max(0, $bettingEndsAt - $now);
            
            // Calculate time until round starts/finishes
            $timeUntilStart = 0;
            if ($round['status'] === 'betting') {
                $timeUntilStart = $timeUntilBettingEnds;
            }
            
            // Get user's bets for this round
            $userBets = $db->getUserCrashBetsForRound($round['id'], $user['id']);
            
            $round['time_until_betting_ends'] = $timeUntilBettingEnds;
            $round['time_until_start'] = $timeUntilStart;
            $round['user_bets'] = $userBets;
            
            // Don't reveal server seed until round is finished (for provably fair)
            // But reveal crash point when round is running so client can animate
            if ($round['status'] !== 'finished') {
                unset($round['server_seed']);
            }
        }
        echo json_encode(['success' => true, 'round' => $round]);
        break;
    
    case 'placeCrashBet':
        require_once __DIR__ . '/../includes/provably_fair.php';
        $round = $db->getCurrentCrashRound();
        
        if (!$round || $round['status'] !== 'betting') {
            echo json_encode(['success' => false, 'message' => 'No active betting round']);
            break;
        }
        
        $betAmount = floatval($_POST['bet_amount'] ?? 0);
        
        if ($betAmount < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid bet amount']);
            break;
        }
        
        $maxBet = floatval(getSetting('max_bet', 100));
        if ($betAmount > $maxBet) {
            echo json_encode(['success' => false, 'message' => 'Bet amount exceeds maximum of $' . number_format($maxBet, 2)]);
            break;
        }
        
        // Check balance
        if ($user['balance'] < $betAmount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient funds']);
            break;
        }
        
        // Deduct bet amount
        $newBalance = $user['balance'] - $betAmount;
        $db->updateBalance($user['id'], $newBalance);
        
        // Place bet
        $db->placeCrashBet($round['id'], $user['id'], $betAmount);
        
        echo json_encode(['success' => true, 'balance' => $newBalance]);
        break;
    
    case 'cashOutCrash':
        require_once __DIR__ . '/../includes/provably_fair.php';
        $round = $db->getCurrentCrashRound();
        
        if (!$round || $round['status'] !== 'running') {
            echo json_encode(['success' => false, 'message' => 'Round is not running']);
            break;
        }
        
        $multiplier = floatval($_POST['multiplier'] ?? 0);
        
        if ($multiplier < 1.00) {
            echo json_encode(['success' => false, 'message' => 'Invalid multiplier']);
            break;
        }
        
        // Get user's bet for this round
        $userBets = $db->getUserCrashBetsForRound($round['id'], $user['id']);
        if (empty($userBets)) {
            echo json_encode(['success' => false, 'message' => 'No bet found for this round']);
            break;
        }
        
        $bet = $userBets[0];
        
        // Check if already cashed out
        if ($bet['cash_out_multiplier'] && $bet['cash_out_multiplier'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Already cashed out']);
            break;
        }
        
        // Update cash out multiplier
        $db->updateCrashBetCashOut($bet['id'], $multiplier);
        
        echo json_encode(['success' => true, 'cash_out_multiplier' => $multiplier]);
        break;
    
    case 'getCrashHistory':
        $limit = intval($_GET['limit'] ?? 20);
        $history = $db->getCrashRoundsHistory($limit);
        echo json_encode(['success' => true, 'history' => $history]);
        break;
    
    // Admin endpoints for predicting rounds
    case 'getRouletteRoundAdmin':
        requireAdmin();
        require_once __DIR__ . '/../includes/provably_fair.php';
        $round = $db->getCurrentRouletteRound();
        if ($round) {
            $now = time();
            $bettingEndsAt = strtotime($round['betting_ends_at']);
            $timeUntilBettingEnds = max(0, $bettingEndsAt - $now);
            
            // Calculate time until round starts/finishes
            $timeUntilStart = 0;
            $timeUntilFinish = 0;
            if ($round['status'] === 'betting') {
                $timeUntilStart = $timeUntilBettingEnds;
            } elseif ($round['status'] === 'spinning' && $round['started_at']) {
                $spinningDuration = intval(getSetting('roulette_spinning_duration', 4));
                $startedAt = strtotime($round['started_at']);
                $finishesAt = $startedAt + $spinningDuration;
                $timeUntilFinish = max(0, $finishesAt - $now);
            }
            
            $round['time_until_betting_ends'] = $timeUntilBettingEnds;
            $round['time_until_start'] = $timeUntilStart;
            $round['time_until_finish'] = $timeUntilFinish;
            
            // Admins can see the server seed and predict the result
            if ($round['status'] === 'betting' || $round['status'] === 'spinning') {
                $predictedResult = ProvablyFair::generateRouletteResult($round['server_seed'], $round['client_seed'] ?? '');
                $round['predicted_result'] = $predictedResult;
            }
        }
        echo json_encode(['success' => true, 'round' => $round]);
        break;
    
    case 'getCrashRoundAdmin':
        requireAdmin();
        require_once __DIR__ . '/../includes/provably_fair.php';
        $round = $db->getCurrentCrashRound();
        if ($round) {
            $now = time();
            $bettingEndsAt = strtotime($round['betting_ends_at']);
            $timeUntilBettingEnds = max(0, $bettingEndsAt - $now);
            
            // Calculate time until round starts
            $timeUntilStart = 0;
            if ($round['status'] === 'betting') {
                $timeUntilStart = $timeUntilBettingEnds;
            }
            
            $round['time_until_betting_ends'] = $timeUntilBettingEnds;
            $round['time_until_start'] = $timeUntilStart;
            
            // Admins can see the server seed and predict the crash point
            if ($round['status'] === 'betting') {
                $distributionParam = floatval(getSetting('crash_distribution_param', 0.99));
                $predictedCrashPoint = ProvablyFair::generateCrashPoint($round['server_seed'], $round['client_seed'] ?? '', $distributionParam);
                $round['predicted_crash_point'] = $predictedCrashPoint;
            }
        }
        echo json_encode(['success' => true, 'round' => $round]);
        break;
    
    case 'getUpcomingPredictions':
        requireAdmin();
        require_once __DIR__ . '/../includes/provably_fair.php';
        $game = $_GET['game'] ?? 'roulette';
        $count = intval($_GET['count'] ?? 10);
        
        if (!in_array($game, ['roulette', 'crash'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid game']);
            break;
        }
        
        $predictions = ProvablyFair::getUpcomingPredictions($db, $game, $count);
        echo json_encode(['success' => true, 'predictions' => $predictions]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
