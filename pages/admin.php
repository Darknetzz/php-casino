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
        
        // Slots multipliers
        $slotsCherry = floatval($_POST['slots_cherry_multiplier'] ?? 0);
        $slotsLemon = floatval($_POST['slots_lemon_multiplier'] ?? 0);
        $slotsOrange = floatval($_POST['slots_orange_multiplier'] ?? 0);
        $slotsGrape = floatval($_POST['slots_grape_multiplier'] ?? 0);
        $slotsSlot = floatval($_POST['slots_slot_multiplier'] ?? 0);
        $slotsTwoOfKind = floatval($_POST['slots_two_of_kind_multiplier'] ?? 0);
        
        // Plinko multipliers - collect from individual inputs
        $plinkoMultipliersArray = [];
        for ($i = 0; $i < 9; $i++) {
            $plinkoMultipliersArray[] = floatval($_POST['plinko_multiplier_' . $i] ?? 0);
        }
        $plinkoMultipliers = implode(',', $plinkoMultipliersArray);
        
        // Validate all values
        $allValid = true;
        $errors = [];
        
        if ($maxDeposit <= 0) $errors[] = 'Max Deposit must be greater than 0';
        if ($maxBet <= 0) $errors[] = 'Max Bet must be greater than 0';
        if ($startingBalance < 0) $errors[] = 'Starting Balance must be greater than or equal to 0';
        if ($defaultBet <= 0) $errors[] = 'Default Bet must be greater than 0';
        if ($slotsCherry < 0) $errors[] = 'Slots Cherry multiplier must be greater than or equal to 0';
        if ($slotsLemon < 0) $errors[] = 'Slots Lemon multiplier must be greater than or equal to 0';
        if ($slotsOrange < 0) $errors[] = 'Slots Orange multiplier must be greater than or equal to 0';
        if ($slotsGrape < 0) $errors[] = 'Slots Grape multiplier must be greater than or equal to 0';
        if ($slotsSlot < 0) $errors[] = 'Slots Slot multiplier must be greater than or equal to 0';
        if ($slotsTwoOfKind < 0) $errors[] = 'Slots Two of Kind multiplier must be greater than or equal to 0';
        
        // Check plinko multipliers
        if (count($plinkoMultipliersArray) !== 9) {
            $errors[] = 'All 9 Plinko multipliers must be provided';
        } else {
            foreach ($plinkoMultipliersArray as $i => $val) {
                if ($val < 0 || !is_numeric($val)) {
                    $errors[] = "Plinko Slot $i multiplier must be a number greater than or equal to 0";
                }
            }
        }
        
        if (empty($errors)) {
            $db->setSetting('max_deposit', $maxDeposit);
            $db->setSetting('max_bet', $maxBet);
            $db->setSetting('starting_balance', $startingBalance);
            $db->setSetting('default_bet', $defaultBet);
            
                // Save slots multipliers
                $db->setSetting('slots_cherry_multiplier', $slotsCherry);
                $db->setSetting('slots_lemon_multiplier', $slotsLemon);
                $db->setSetting('slots_orange_multiplier', $slotsOrange);
                $db->setSetting('slots_grape_multiplier', $slotsGrape);
                $db->setSetting('slots_slot_multiplier', $slotsSlot);
                $db->setSetting('slots_two_of_kind_multiplier', $slotsTwoOfKind);
                $db->setSetting('slots_win_row', $slotsWinRow);
            
            // Save plinko multipliers
            $db->setSetting('plinko_multipliers', $plinkoMultipliers);
            
            $message = 'Settings updated successfully!';
        } else {
            $error = implode('<br>', $errors);
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

// Get current tab from URL
$currentTab = $_GET['tab'] ?? 'settings';

// Check for success message
if (isset($_GET['success'])) {
    if ($currentTab === 'settings') {
        $message = 'Casino settings updated successfully!';
    } elseif ($currentTab === 'multipliers') {
        $message = 'Game multipliers updated successfully!';
    } elseif ($currentTab === 'users') {
        $message = 'User updated successfully!';
    }
}

$pageTitle = 'Admin Panel';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="admin-panel">
            <h1>⚙️ Admin Panel</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Admin Navigation Tabs -->
            <div class="admin-nav-tabs">
                <a href="admin.php?tab=settings" class="admin-tab <?php echo $currentTab === 'settings' ? 'active' : ''; ?>">
                    <span>📊</span> Casino Settings
                </a>
                <a href="admin.php?tab=multipliers" class="admin-tab <?php echo $currentTab === 'multipliers' ? 'active' : ''; ?>">
                    <span>🎰</span> Game Multipliers
                </a>
                <a href="admin.php?tab=users" class="admin-tab <?php echo $currentTab === 'users' ? 'active' : ''; ?>">
                    <span>👥</span> User Management
                </a>
            </div>
            
            <!-- Settings Section -->
            <?php if ($currentTab === 'settings'): ?>
            <div class="admin-section">
                <h2>📊 Casino Settings</h2>
                <form method="POST" action="admin.php?tab=settings" class="admin-form">
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
            <?php endif; ?>
            
            <!-- Game Multipliers Section -->
            <?php if ($currentTab === 'multipliers'): ?>
            <div class="admin-section">
                <h2>🎰 Game Multipliers</h2>
                <form method="POST" action="admin.php?tab=multipliers" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Slot Machine Multipliers</h3>
                    <table class="multiplier-table">
                        <thead>
                            <tr>
                                <th>Symbol</th>
                                <th>Combination</th>
                                <th>Multiplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>🍒</td>
                                <td>🍒🍒🍒</td>
                                <td>
                                    <input type="number" id="slots_cherry_multiplier" name="slots_cherry_multiplier" 
                                           min="0.1" step="0.1" value="<?php echo htmlspecialchars($settings['slots_cherry_multiplier'] ?? '2'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <tr>
                                <td>🍋</td>
                                <td>🍋🍋🍋</td>
                                <td>
                                    <input type="number" id="slots_lemon_multiplier" name="slots_lemon_multiplier" 
                                           min="0.1" step="0.1" value="<?php echo htmlspecialchars($settings['slots_lemon_multiplier'] ?? '3'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <tr>
                                <td>🍊</td>
                                <td>🍊🍊🍊</td>
                                <td>
                                    <input type="number" id="slots_orange_multiplier" name="slots_orange_multiplier" 
                                           min="0.1" step="0.1" value="<?php echo htmlspecialchars($settings['slots_orange_multiplier'] ?? '4'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <tr>
                                <td>🍇</td>
                                <td>🍇🍇🍇</td>
                                <td>
                                    <input type="number" id="slots_grape_multiplier" name="slots_grape_multiplier" 
                                           min="0.1" step="0.1" value="<?php echo htmlspecialchars($settings['slots_grape_multiplier'] ?? '5'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <tr>
                                <td>🎰</td>
                                <td>🎰🎰🎰</td>
                                <td>
                                    <input type="number" id="slots_slot_multiplier" name="slots_slot_multiplier" 
                                           min="0.1" step="0.1" value="<?php echo htmlspecialchars($settings['slots_slot_multiplier'] ?? '10'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <tr style="background: #f0f0f0;">
                                <td colspan="2" style="font-weight: 600;">Any 2 of a kind</td>
                                <td>
                                    <input type="number" id="slots_two_of_kind_multiplier" name="slots_two_of_kind_multiplier" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($settings['slots_two_of_kind_multiplier'] ?? '0.5'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h3 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Slots Settings</h3>
                    <div class="form-group">
                        <label for="slots_win_row">Winning Row:</label>
                        <select id="slots_win_row" name="slots_win_row" required style="width: 200px; padding: 8px;">
                            <option value="0" <?php echo (($settings['slots_win_row'] ?? '1') == '0') ? 'selected' : ''; ?>>Top Row (0)</option>
                            <option value="1" <?php echo (($settings['slots_win_row'] ?? '1') == '1') ? 'selected' : ''; ?>>Middle Row (1) - Default</option>
                            <option value="2" <?php echo (($settings['slots_win_row'] ?? '1') == '2') ? 'selected' : ''; ?>>Bottom Row (2)</option>
                        </select>
                        <small style="display: block; margin-top: 5px; color: #666;">Which row to check for winning combinations</small>
                    </div>
                    
                    <h3 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Plinko Multipliers</h3>
                    <p style="margin-bottom: 15px; color: #666;">Configure multipliers for each slot (symmetric pairs):</p>
                    <table class="multiplier-table">
                        <thead>
                            <tr>
                                <th>Slot Position</th>
                                <th>Left Multiplier</th>
                                <th>Right Multiplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $plinkoMultipliers = explode(',', $settings['plinko_multipliers'] ?? '0.2,0.5,0.8,1.0,2.0,1.0,0.8,0.5,0.2');
                            $slotNames = ['Left Edge', 'Near Left', 'Left-Mid', 'Left-Center', 'Center', 'Right-Center', 'Right-Mid', 'Near Right', 'Right Edge'];
                            // Pair symmetric slots: (0,8), (1,7), (2,6), (3,5), and center (4) alone
                            $pairs = [
                                [0, 8, 'Edge'],
                                [1, 7, 'Near Edge'],
                                [2, 6, 'Mid'],
                                [3, 5, 'Center'],
                            ];
                            foreach ($pairs as $pair): 
                                $leftIdx = $pair[0];
                                $rightIdx = $pair[1];
                                $pairName = $pair[2];
                            ?>
                            <tr>
                                <td><?php echo $slotNames[$leftIdx]; ?> (<?php echo $leftIdx; ?>) / <?php echo $slotNames[$rightIdx]; ?> (<?php echo $rightIdx; ?>)</td>
                                <td>
                                    <input type="number" name="plinko_multiplier_<?php echo $leftIdx; ?>" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($plinkoMultipliers[$leftIdx] ?? '0.2'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>
                                    <input type="number" name="plinko_multiplier_<?php echo $rightIdx; ?>" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($plinkoMultipliers[$rightIdx] ?? '0.2'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td><?php echo $slotNames[4]; ?> (<?php echo 4; ?>)</td>
                                <td colspan="2">
                                    <input type="number" name="plinko_multiplier_4" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($plinkoMultipliers[4] ?? '2.0'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 20px;">Update Multipliers</button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- User Management Section -->
            <?php if ($currentTab === 'users'): ?>
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
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Balance Modal -->
    <div id="balanceModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('balanceModal')">&times;</span>
            <h3>Edit User Balance</h3>
            <form method="POST" action="admin.php?tab=users" id="balanceForm">
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
            <form method="POST" action="admin.php?tab=users" id="adminForm">
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
            <form method="POST" action="admin.php?tab=users" id="deleteForm">
                <input type="hidden" name="user_id" id="delete_user_id">
                <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                <button type="button" onclick="closeModal('deleteModal')" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>
    
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
