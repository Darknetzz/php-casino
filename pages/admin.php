<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $maxDeposit = floatval($_POST['max_deposit'] ?? 0);
        $maxBet = floatval($_POST['max_bet'] ?? 0);
        $startingBalance = floatval($_POST['starting_balance'] ?? 0);
        $defaultBet = floatval($_POST['default_bet'] ?? 0);
        
        if ($maxDeposit > 0 && $maxBet > 0 && $startingBalance > 0 && $defaultBet > 0) {
            $db->setSetting('max_deposit', $maxDeposit);
            $db->setSetting('max_bet', $maxBet);
            $db->setSetting('starting_balance', $startingBalance);
            $db->setSetting('default_bet', $defaultBet);
            $message = 'Settings updated successfully!';
        } else {
            $error = 'All values must be greater than 0.';
        }
    } elseif (isset($_POST['update_user_balance'])) {
        $userId = intval($_POST['user_id'] ?? 0);
        $newBalance = floatval($_POST['new_balance'] ?? 0);
        
        if ($userId > 0 && $newBalance >= 0) {
            $db->setUserBalance($userId, $newBalance);
            $db->addTransaction($userId, 'admin', $newBalance, 'Admin balance adjustment');
            $message = 'User balance updated successfully!';
        } else {
            $error = 'Invalid user ID or balance.';
        }
    } elseif (isset($_POST['toggle_admin'])) {
        $userId = intval($_POST['user_id'] ?? 0);
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
        
        if ($userId > 0 && $userId != $_SESSION['user_id']) {
            $db->setAdmin($userId, $isAdmin);
            $message = 'User admin status updated!';
        } else {
            $error = 'Cannot change your own admin status.';
        }
    } elseif (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id'] ?? 0);
        
        if ($userId > 0 && $userId != $_SESSION['user_id']) {
            $db->deleteUser($userId);
            $message = 'User deleted successfully!';
        } else {
            $error = 'Cannot delete yourself.';
        }
    }
}

$settings = $db->getAllSettings();
$users = $db->getAllUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Casino</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="admin-panel">
            <h1>⚙️ Admin Panel</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Settings Section -->
            <div class="admin-section">
                <h2>📊 Casino Settings</h2>
                <form method="POST" action="admin.php" class="admin-form">
                    <div class="form-group">
                        <label for="max_deposit">Max Deposit ($)</label>
                        <input type="number" id="max_deposit" name="max_deposit" min="1" step="0.01" 
                               value="<?php echo htmlspecialchars($settings['max_deposit'] ?? '10000'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="max_bet">Max Bet ($)</label>
                        <input type="number" id="max_bet" name="max_bet" min="1" step="0.01" 
                               value="<?php echo htmlspecialchars($settings['max_bet'] ?? '100'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="starting_balance">Starting Balance ($)</label>
                        <input type="number" id="starting_balance" name="starting_balance" min="0" step="0.01" 
                               value="<?php echo htmlspecialchars($settings['starting_balance'] ?? '1000'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="default_bet">Default Bet ($)</label>
                        <input type="number" id="default_bet" name="default_bet" min="1" step="0.01" 
                               value="<?php echo htmlspecialchars($settings['default_bet'] ?? '10'); ?>" required>
                        <small>Default bet amount for all users (can be overridden in user profile)</small>
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary">Update Settings</button>
                </form>
            </div>
            
            <!-- User Management Section -->
            <div class="admin-section">
                <h2>👥 User Management</h2>
                <div class="users-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Balance</th>
                                <th>Admin</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>$<?php echo number_format($u['balance'], 2); ?></td>
                                <td><?php echo $u['is_admin'] ? 'Yes' : 'No'; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <button class="btn-small btn-secondary" onclick="editBalance(<?php echo $u['id']; ?>, <?php echo $u['balance']; ?>)">Edit Balance</button>
                                    <button class="btn-small btn-secondary" onclick="toggleAdmin(<?php echo $u['id']; ?>, <?php echo $u['is_admin']; ?>)">Toggle Admin</button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-small btn-danger" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Balance Modal -->
    <div id="balanceModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('balanceModal')">&times;</span>
            <h3>Edit User Balance</h3>
            <form method="POST" action="admin.php" id="balanceForm">
                <input type="hidden" name="user_id" id="balance_user_id">
                <div class="form-group">
                    <label for="new_balance">New Balance ($)</label>
                    <input type="number" id="new_balance" name="new_balance" min="0" step="0.01" required>
                </div>
                <button type="submit" name="update_user_balance" class="btn btn-primary">Update Balance</button>
            </form>
        </div>
    </div>
    
    <!-- Toggle Admin Modal -->
    <div id="adminModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('adminModal')">&times;</span>
            <h3>Toggle Admin Status</h3>
            <form method="POST" action="admin.php" id="adminForm">
                <input type="hidden" name="user_id" id="admin_user_id">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_admin" id="admin_checkbox" value="1">
                        Grant Admin Privileges
                    </label>
                </div>
                <button type="submit" name="toggle_admin" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            <h3>Delete User</h3>
            <p>Are you sure you want to delete user: <strong id="delete_username"></strong>?</p>
            <p class="warning">This action cannot be undone!</p>
            <form method="POST" action="admin.php" id="deleteForm">
                <input type="hidden" name="user_id" id="delete_user_id">
                <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                <button type="button" onclick="closeModal('deleteModal')" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/navbar.js"></script>
    <script>
        // Define functions globally so they can be called from onclick handlers
        function editBalance(userId, currentBalance) {
            $('#balance_user_id').val(userId);
            $('#new_balance').val(currentBalance);
            $('#balanceModal').show();
        }
        
        function toggleAdmin(userId, isAdmin) {
            $('#admin_user_id').val(userId);
            $('#admin_checkbox').prop('checked', isAdmin == 1);
            $('#adminModal').show();
        }
        
        function deleteUser(userId, username) {
            $('#delete_user_id').val(userId);
            $('#delete_username').text(username);
            $('#deleteModal').show();
        }
        
        function closeModal(modalId) {
            $('#' + modalId).hide();
        }
        
        $(document).ready(function() {
            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                }
            };
        });
    </script>
</body>
</html>
