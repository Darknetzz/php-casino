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
        
        // Get dice multipliers
        $diceMultipliers = [
            3 => floatval(getSetting('dice_3_of_kind_multiplier', 2)),
            4 => floatval(getSetting('dice_4_of_kind_multiplier', 5)),
            5 => floatval(getSetting('dice_5_of_kind_multiplier', 10)),
            6 => floatval(getSetting('dice_6_of_kind_multiplier', 20))
        ];
        
        // Get crash settings
        $crashSpeed = floatval(getSetting('crash_speed', 0.02));
        $crashMaxMultiplier = floatval(getSetting('crash_max_multiplier', 0));
        
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
            'crash_speed' => $crashSpeed,
            'crash_max_multiplier' => $crashMaxMultiplier
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
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
