<?php
require_once 'config.php';
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
        $newBalance = $user['balance'] + $amount;
        
        if ($newBalance < 0) {
            echo json_encode(['success' => false, 'message' => 'Insufficient funds']);
        } else {
            $db->updateBalance($user['id'], $newBalance);
            $db->addTransaction($user['id'], $type, abs($amount), $description);
            echo json_encode(['success' => true, 'balance' => $newBalance]);
        }
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
