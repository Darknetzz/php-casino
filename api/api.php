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
            $db->addTransaction($user['id'], $type, abs($amount), $description);
            echo json_encode(['success' => true, 'balance' => $newBalance]);
        }
        break;
        
    case 'getSettings':
        $user = getCurrentUser();
        $globalDefaultBet = floatval(getSetting('default_bet', 10));
        $userDefaultBet = isset($user['default_bet']) && $user['default_bet'] !== null ? floatval($user['default_bet']) : null;
        $defaultBet = $userDefaultBet !== null ? $userDefaultBet : $globalDefaultBet;
        
        // Get slots multipliers
        $slotsMultipliers = [
            'cherry' => floatval(getSetting('slots_cherry_multiplier', 2)),
            'lemon' => floatval(getSetting('slots_lemon_multiplier', 3)),
            'orange' => floatval(getSetting('slots_orange_multiplier', 4)),
            'grape' => floatval(getSetting('slots_grape_multiplier', 5)),
            'slot' => floatval(getSetting('slots_slot_multiplier', 10)),
            'two_of_kind' => floatval(getSetting('slots_two_of_kind_multiplier', 0.5))
        ];
        
        // Get plinko multipliers
        $plinkoMultipliersStr = getSetting('plinko_multipliers', '0.2,0.5,0.8,1.0,2.0,1.0,0.8,0.5,0.2');
        $plinkoMultipliers = array_map('floatval', explode(',', $plinkoMultipliersStr));
        
        $settings = [
            'max_bet' => floatval(getSetting('max_bet', 100)),
            'max_deposit' => floatval(getSetting('max_deposit', 10000)),
            'default_bet' => $defaultBet,
            'slots_multipliers' => $slotsMultipliers,
            'slots_win_row' => intval(getSetting('slots_win_row', 1)),
            'plinko_multipliers' => $plinkoMultipliers
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
