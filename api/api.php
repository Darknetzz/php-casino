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
        $settings = [
            'max_bet' => floatval(getSetting('max_bet', 100)),
            'max_deposit' => floatval(getSetting('max_deposit', 10000))
        ];
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;
        
    case 'getTransactions':
        $user = getCurrentUser();
        $transactions = $db->getTransactions($user['id'], 10);
        echo json_encode(['success' => true, 'transactions' => $transactions]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
