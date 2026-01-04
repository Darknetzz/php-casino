<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $errors = [];
        
        // Check if this is casino settings update
        if (isset($_POST['max_deposit'])) {
            $maxDeposit = floatval($_POST['max_deposit'] ?? 0);
            $maxBet = floatval($_POST['max_bet'] ?? 0);
            $startingBalance = floatval($_POST['starting_balance'] ?? 0);
            $defaultBet = floatval($_POST['default_bet'] ?? 0);
            
            if ($maxDeposit <= 0) $errors[] = 'Max Deposit must be greater than 0';
            if ($maxBet <= 0) $errors[] = 'Max Bet must be greater than 0';
            if ($startingBalance < 0) $errors[] = 'Starting Balance must be greater than or equal to 0';
            if ($defaultBet <= 0) $errors[] = 'Default Bet must be greater than 0';
            
            if (empty($errors)) {
                $db->setSetting('max_deposit', $maxDeposit);
                $db->setSetting('max_bet', $maxBet);
                $db->setSetting('starting_balance', $startingBalance);
                $db->setSetting('default_bet', $defaultBet);
                header('Location: admin.php?tab=settings&success=1');
                exit;
            }
        }
        
        // Check if this is multipliers update
        if (isset($_POST['slots_symbols']) || isset($_POST['plinko_multiplier_0']) || isset($_POST['dice_3_of_kind'])) {
            // Slots multipliers (dynamic symbols)
            if (isset($_POST['slots_symbols'])) {
                $slotsSymbolsJson = $_POST['slots_symbols'];
                $slotsSymbols = json_decode($slotsSymbolsJson, true);
                $slotsTwoOfKind = floatval($_POST['slots_two_of_kind_multiplier'] ?? 0);
                $slotsWinRow = isset($_POST['slots_win_row']) ? intval($_POST['slots_win_row']) : 1;
                $slotsBetRows = isset($_POST['slots_bet_rows']) ? intval($_POST['slots_bet_rows']) : 1;
                
                if (!is_array($slotsSymbols) || empty($slotsSymbols)) {
                    $errors[] = 'At least one slot symbol must be defined';
                } else {
                    foreach ($slotsSymbols as $index => $symbol) {
                        if (empty($symbol['emoji'])) {
                            $errors[] = "Symbol #" . ($index + 1) . " emoji cannot be empty";
                        }
                        if (!isset($symbol['multiplier']) || floatval($symbol['multiplier']) < 0) {
                            $errors[] = "Symbol #" . ($index + 1) . " multiplier must be greater than or equal to 0";
                        }
                    }
                }
                
                if ($slotsTwoOfKind < 0) $errors[] = 'Slots Two of Kind multiplier must be greater than or equal to 0';
                
                // Handle custom combinations (ordered array)
                $slotsCustomCombinationsJson = isset($_POST['slots_custom_combinations']) ? $_POST['slots_custom_combinations'] : '[]';
                $slotsCustomCombinations = json_decode($slotsCustomCombinationsJson, true);
                
                if (!is_array($slotsCustomCombinations)) {
                    $slotsCustomCombinations = [];
                } else {
                    // Get number of reels to validate against
                    $numReels = isset($_POST['slots_num_reels']) ? intval($_POST['slots_num_reels']) : 3;
                    foreach ($slotsCustomCombinations as $index => $combination) {
                        if (!isset($combination['symbols']) || !is_array($combination['symbols']) || empty($combination['symbols'])) {
                            $errors[] = "Custom combination #" . ($index + 1) . " must have at least one symbol";
                        } else {
                            // Validate that symbols array matches number of reels
                            if (count($combination['symbols']) !== $numReels) {
                                $errors[] = "Custom combination #" . ($index + 1) . " must have exactly " . $numReels . " symbols (one per reel)";
                            }
                            foreach ($combination['symbols'] as $symbolIndex => $symbol) {
                                if (empty($symbol)) {
                                    $errors[] = "Custom combination #" . ($index + 1) . ", position #" . ($symbolIndex + 1) . " emoji cannot be empty";
                                }
                            }
                        }
                        if (!isset($combination['multiplier']) || floatval($combination['multiplier']) < 0) {
                            $errors[] = "Custom combination #" . ($index + 1) . " multiplier must be greater than or equal to 0";
                        }
                    }
                }
                
                // Handle number of reels
                $slotsNumReels = isset($_POST['slots_num_reels']) ? intval($_POST['slots_num_reels']) : 3;
                if ($slotsNumReels < 3 || $slotsNumReels > 10) {
                    $errors[] = 'Number of reels must be between 3 and 10';
                }
                
                $slotsDuration = isset($_POST['slots_duration']) ? intval($_POST['slots_duration']) : 2500;
                if ($slotsDuration < 500 || $slotsDuration > 10000) {
                    $errors[] = 'Slots duration must be between 500 and 10000 milliseconds';
                }
                
                if (empty($errors)) {
                    $db->setSetting('slots_symbols', json_encode($slotsSymbols));
                    $db->setSetting('slots_two_of_kind_multiplier', $slotsTwoOfKind);
                    $db->setSetting('slots_custom_combinations', json_encode($slotsCustomCombinations));
                    $db->setSetting('slots_num_reels', $slotsNumReels);
                    $db->setSetting('slots_win_row', $slotsWinRow);
                    $db->setSetting('slots_bet_rows', $slotsBetRows);
                    $db->setSetting('slots_duration', $slotsDuration);
                }
            }
            
            // Plinko multipliers
            if (isset($_POST['plinko_multiplier_0'])) {
                $plinkoMultipliersArray = [];
                for ($i = 0; $i < 9; $i++) {
                    $plinkoMultipliersArray[] = floatval($_POST['plinko_multiplier_' . $i] ?? 0);
                }
                $plinkoMultipliers = implode(',', $plinkoMultipliersArray);
                
                if (count($plinkoMultipliersArray) !== 9) {
                    $errors[] = 'All 9 Plinko multipliers must be provided';
                } else {
                    foreach ($plinkoMultipliersArray as $i => $val) {
                        if ($val < 0 || !is_numeric($val)) {
                            $errors[] = "Plinko Slot $i multiplier must be a number greater than or equal to 0";
                        }
                    }
                }
                
                $plinkoDuration = isset($_POST['plinko_duration']) ? intval($_POST['plinko_duration']) : 350;
                if ($plinkoDuration < 50 || $plinkoDuration > 2000) {
                    $errors[] = 'Plinko duration must be between 50 and 2000 milliseconds';
                }
                
                if (empty($errors)) {
                    $db->setSetting('plinko_multipliers', $plinkoMultipliers);
                    $db->setSetting('plinko_duration', $plinkoDuration);
                }
            }
            
            // Dice multipliers
            if (isset($_POST['dice_3_of_kind'])) {
                $dice3OfKind = floatval($_POST['dice_3_of_kind'] ?? 0);
                $dice4OfKind = floatval($_POST['dice_4_of_kind'] ?? 0);
                $dice5OfKind = floatval($_POST['dice_5_of_kind'] ?? 0);
                $dice6OfKind = floatval($_POST['dice_6_of_kind'] ?? 0);
                
                if ($dice3OfKind < 0) $errors[] = 'Dice 3 of a kind multiplier must be greater than or equal to 0';
                if ($dice4OfKind < 0) $errors[] = 'Dice 4 of a kind multiplier must be greater than or equal to 0';
                if ($dice5OfKind < 0) $errors[] = 'Dice 5 of a kind multiplier must be greater than or equal to 0';
                if ($dice6OfKind < 0) $errors[] = 'Dice 6 of a kind multiplier must be greater than or equal to 0';
                
                if (empty($errors)) {
                    $db->setSetting('dice_3_of_kind_multiplier', $dice3OfKind);
                    $db->setSetting('dice_4_of_kind_multiplier', $dice4OfKind);
                    $db->setSetting('dice_5_of_kind_multiplier', $dice5OfKind);
                    $db->setSetting('dice_6_of_kind_multiplier', $dice6OfKind);
                }
            }
            
            if (empty($errors)) {
                // Determine which game was updated
                $gameParam = 'slots'; // default
                if (isset($_POST['slots_symbols'])) {
                    $gameParam = 'slots';
                } elseif (isset($_POST['plinko_multiplier_0'])) {
                    $gameParam = 'plinko';
                } elseif (isset($_POST['dice_3_of_kind'])) {
                    $gameParam = 'dice';
                }
                header('Location: admin.php?tab=multipliers&game=' . $gameParam . '&success=1');
                exit;
            }
        }
        
        if (!empty($errors)) {
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
$currentGame = $_GET['game'] ?? 'slots'; // Default to slots for multipliers subnav

// Check for success message
if (isset($_GET['success'])) {
    if ($currentTab === 'settings') {
        $message = 'Casino settings updated successfully!';
    } elseif ($currentTab === 'multipliers') {
        $message = 'Game settings updated successfully!';
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
                    <span>🎰</span> Game Settings
                </a>
                <a href="admin.php?tab=users" class="admin-tab <?php echo $currentTab === 'users' ? 'active' : ''; ?>">
                    <span>👥</span> User Management
                </a>
            </div>
            
            <!-- Settings Section -->
            <?php if ($currentTab === 'settings'): ?>
            <div class="admin-section section">
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
            <div class="admin-section section">
                <h2>🎰 Game Settings</h2>
                
                <!-- Game Subnav -->
                <div class="admin-subnav-tabs">
                    <a href="admin.php?tab=multipliers&game=slots" class="admin-subtab <?php echo $currentGame === 'slots' ? 'active' : ''; ?>">
                        <span>🎰</span> Slots
                    </a>
                    <a href="admin.php?tab=multipliers&game=plinko" class="admin-subtab <?php echo $currentGame === 'plinko' ? 'active' : ''; ?>">
                        <span>⚪</span> Plinko
                    </a>
                    <a href="admin.php?tab=multipliers&game=dice" class="admin-subtab <?php echo $currentGame === 'dice' ? 'active' : ''; ?>">
                        <span>🎲</span> Dice Roll
                    </a>
                </div>
                
                <!-- Slots Multipliers -->
                <?php if ($currentGame === 'slots'): ?>
                <form method="POST" action="admin.php?tab=multipliers&game=slots" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Slot Machine Symbols</h3>
                    <p style="margin-bottom: 15px; color: #666;">Add, edit, or remove slot symbols. Each symbol needs an emoji and a multiplier for 3-of-a-kind wins.</p>
                    <div id="slotsSymbolsContainer">
                        <table class="multiplier-table" id="slotsSymbolsTable">
                            <thead>
                                <tr>
                                    <th>Emoji</th>
                                    <th>Combination</th>
                                    <th>Multiplier (3 of a kind)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="slotsSymbolsBody">
                                <?php
                                $slotsSymbolsJson = $settings['slots_symbols'] ?? '[]';
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
                                foreach ($slotsSymbols as $index => $symbol):
                                ?>
                                <tr data-index="<?php echo $index; ?>">
                                    <td>
                                        <input type="text" class="slots-emoji-input" 
                                               value="<?php echo htmlspecialchars($symbol['emoji'] ?? ''); ?>" 
                                               maxlength="2" style="width: 80px; padding: 8px; font-size: 20px; text-align: center;" 
                                               placeholder="🎰" required oninput="updateSlotsCombination(this)">
                                    </td>
                                    <td class="slots-combination-display" style="font-size: 20px; text-align: center;">
                                        <?php 
                                        $emoji = htmlspecialchars($symbol['emoji'] ?? '');
                                        echo $emoji . $emoji . $emoji;
                                        ?>
                                    </td>
                                    <td>
                                        <input type="number" class="slots-multiplier-input" 
                                               value="<?php echo htmlspecialchars($symbol['multiplier'] ?? '1'); ?>" 
                                               min="0" step="0.1" style="width: 100px; padding: 8px;" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-secondary" onclick="removeSlotsSymbol(this)" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-secondary" onclick="addSlotsSymbol()" style="margin-top: 10px;">+ Add Symbol</button>
                        <input type="hidden" id="slots_symbols_json" name="slots_symbols" value="">
                    </div>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">2 of a Kind Multiplier</h4>
                    <div class="form-group">
                        <label for="slots_two_of_kind_multiplier">Multiplier for any 2 matching symbols:</label>
                        <input type="number" id="slots_two_of_kind_multiplier" name="slots_two_of_kind_multiplier" 
                               min="0" step="0.1" value="<?php echo htmlspecialchars($settings['slots_two_of_kind_multiplier'] ?? '0.5'); ?>" 
                               required style="width: 100px; padding: 8px;">
                    </div>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Custom Combinations</h4>
                    <p style="margin-bottom: 15px; color: #666;">Define custom winning combinations with exact order (e.g., 🔥🔥❤️). Symbols are matched in order from left to right.</p>
                    <div id="slotsCustomCombinationsContainer">
                        <table class="multiplier-table" id="slotsCustomCombinationsTable">
                            <thead>
                                <tr>
                                    <th>Symbols (Ordered)</th>
                                    <th>Multiplier</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="slotsCustomCombinationsBody">
                                <?php
                                $slotsCustomCombinationsJson = $settings['slots_custom_combinations'] ?? '[]';
                                $slotsCustomCombinations = json_decode($slotsCustomCombinationsJson, true);
                                $numReels = intval($settings['slots_num_reels'] ?? 3);
                                if (empty($slotsCustomCombinations) || !is_array($slotsCustomCombinations)) {
                                    $slotsCustomCombinations = [];
                                }
                                foreach ($slotsCustomCombinations as $index => $combination):
                                ?>
                                <tr data-index="<?php echo $index; ?>">
                                    <td>
                                        <div class="custom-combination-symbols" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                                            <?php
                                            $comboSymbols = isset($combination['symbols']) && is_array($combination['symbols']) ? $combination['symbols'] : [];
                                            // Convert old format ({emoji, count}) to new format (array of emojis)
                                            $emojisArray = [];
                                            foreach ($comboSymbols as $symbol) {
                                                if (is_array($symbol) && isset($symbol['emoji'])) {
                                                    // Old format: {emoji: '🔥', count: 2}
                                                    $emoji = $symbol['emoji'] ?? '';
                                                    $count = intval($symbol['count'] ?? 1);
                                                    for ($j = 0; $j < $count; $j++) {
                                                        $emojisArray[] = $emoji;
                                                    }
                                                } else if (is_string($symbol)) {
                                                    // New format: array of emojis
                                                    $emojisArray[] = $symbol;
                                                }
                                            }
                                            // Pad to numReels if needed
                                            while (count($emojisArray) < $numReels) {
                                                $emojisArray[] = '';
                                            }
                                            // Trim to numReels
                                            $emojisArray = array_slice($emojisArray, 0, $numReels);
                                            for ($i = 0; $i < $numReels; $i++) {
                                                $emoji = isset($emojisArray[$i]) && is_string($emojisArray[$i]) ? htmlspecialchars($emojisArray[$i]) : '';
                                                echo '<div style="display: flex; align-items: center; gap: 5px;">';
                                                echo '<span style="font-size: 14px; color: #666;">#' . ($i + 1) . '</span>';
                                                echo '<input type="text" class="custom-combo-emoji" data-position="' . $i . '" value="' . $emoji . '" maxlength="2" style="width: 60px; padding: 5px; font-size: 18px; text-align: center;" placeholder="🎰" required>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" class="custom-combo-multiplier" 
                                               value="<?php echo htmlspecialchars($combination['multiplier'] ?? '1'); ?>" 
                                               min="0" step="0.1" style="width: 100px; padding: 8px;" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-secondary" onclick="removeCustomCombination(this)" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-secondary" onclick="addCustomCombination()" style="margin-top: 10px;">+ Add Custom Combination</button>
                        <input type="hidden" id="slots_custom_combinations_json" name="slots_custom_combinations" value="">
                    </div>
                    
                    <h3 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Slots Settings</h3>
                    <div class="form-group">
                        <label for="slots_num_reels">Number of Reels:</label>
                        <input type="number" id="slots_num_reels" name="slots_num_reels" min="3" max="10" 
                               value="<?php echo htmlspecialchars($settings['slots_num_reels'] ?? '3'); ?>" 
                               required style="width: 100px; padding: 8px;">
                        <small style="display: block; margin-top: 5px; color: #666;">Number of reels/columns in the slot machine (3-10)</small>
                    </div>
                    <div class="form-group">
                        <label for="slots_bet_rows">Bet Rows:</label>
                        <select id="slots_bet_rows" name="slots_bet_rows" required style="width: 200px; padding: 8px;">
                            <option value="1" <?php echo (($settings['slots_bet_rows'] ?? '1') == '1') ? 'selected' : ''; ?>>Middle Row Only (1)</option>
                            <option value="3" <?php echo (($settings['slots_bet_rows'] ?? '1') == '3') ? 'selected' : ''; ?>>All Rows (3)</option>
                        </select>
                        <small style="display: block; margin-top: 5px; color: #666;">Which rows to check for winning combinations</small>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label for="slots_win_row">Winning Row (when Bet Rows = 1):</label>
                        <select id="slots_win_row" name="slots_win_row" required style="width: 200px; padding: 8px;">
                            <option value="0" <?php echo (($settings['slots_win_row'] ?? '1') == '0') ? 'selected' : ''; ?>>Top Row (0)</option>
                            <option value="1" <?php echo (($settings['slots_win_row'] ?? '1') == '1') ? 'selected' : ''; ?>>Middle Row (1) - Default</option>
                            <option value="2" <?php echo (($settings['slots_win_row'] ?? '1') == '2') ? 'selected' : ''; ?>>Bottom Row (2)</option>
                        </select>
                        <small style="display: block; margin-top: 5px; color: #666;">Which single row to check when Bet Rows is set to 1</small>
                    </div>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Animation Settings</h4>
                    <div class="form-group">
                        <label for="slots_duration">Spin Duration (milliseconds):</label>
                        <input type="number" id="slots_duration" name="slots_duration" min="500" max="10000" step="100" 
                               value="<?php echo htmlspecialchars($settings['slots_duration'] ?? '2500'); ?>" 
                               required style="width: 150px; padding: 8px;">
                        <small style="display: block; margin-top: 5px; color: #666;">Duration of the slot machine spin animation (500-10000ms)</small>
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 20px;">Update Slots Multipliers</button>
                </form>
                <?php endif; ?>
                
                <!-- Plinko Multipliers -->
                <?php if ($currentGame === 'plinko'): ?>
                <form method="POST" action="admin.php?tab=multipliers&game=plinko" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Plinko Multipliers</h3>
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
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Animation Settings</h4>
                    <div class="form-group">
                        <label for="plinko_duration">Step Delay (milliseconds):</label>
                        <input type="number" id="plinko_duration" name="plinko_duration" min="50" max="2000" step="50" 
                               value="<?php echo htmlspecialchars($settings['plinko_duration'] ?? '350'); ?>" 
                               required style="width: 150px; padding: 8px;">
                        <small style="display: block; margin-top: 5px; color: #666;">Delay between ball movement steps (50-2000ms). Lower = faster animation</small>
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 20px;">Update Plinko Multipliers</button>
                </form>
                <?php endif; ?>
                
                <!-- Dice Multipliers -->
                <?php if ($currentGame === 'dice'): ?>
                <form method="POST" action="admin.php?tab=multipliers&game=dice" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Dice Roll Multipliers</h3>
                    <p style="margin-bottom: 15px; color: #666;">Configure multipliers for matching dice combinations:</p>
                    <table class="multiplier-table">
                        <thead>
                            <tr>
                                <th>Combination</th>
                                <th>Multiplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>3 of a kind</td>
                                <td>
                                    <input type="number" id="dice_3_of_kind" name="dice_3_of_kind" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($settings['dice_3_of_kind_multiplier'] ?? '2'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <tr>
                                <td>4 of a kind</td>
                                <td>
                                    <input type="number" id="dice_4_of_kind" name="dice_4_of_kind" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($settings['dice_4_of_kind_multiplier'] ?? '5'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <tr>
                                <td>5 of a kind</td>
                                <td>
                                    <input type="number" id="dice_5_of_kind" name="dice_5_of_kind" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($settings['dice_5_of_kind_multiplier'] ?? '10'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <tr>
                                <td>6 of a kind</td>
                                <td>
                                    <input type="number" id="dice_6_of_kind" name="dice_6_of_kind" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($settings['dice_6_of_kind_multiplier'] ?? '20'); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 20px;">Update Dice Multipliers</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- User Management Section -->
            <?php if ($currentTab === 'users'): ?>
            <div class="admin-section section">
                <h2>👥 User Management</h2>
                <div class="users-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Balance</th>
                                <th>Total Deposits</th>
                                <th>Admin</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): 
                                $totalDeposits = $db->getTotalDeposits($u['id']);
                            ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>$<?php echo number_format($u['balance'], 2); ?></td>
                                <td>$<?php echo number_format($totalDeposits, 2); ?></td>
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
        
        // Slots symbols management
        function addSlotsSymbol() {
            const tbody = document.getElementById('slotsSymbolsBody');
            const index = tbody.children.length;
            const row = document.createElement('tr');
            row.setAttribute('data-index', index);
            row.innerHTML = `
                <td>
                    <input type="text" class="slots-emoji-input" 
                           value="🎰" maxlength="2" 
                           style="width: 80px; padding: 8px; font-size: 20px; text-align: center;" 
                           placeholder="🎰" required oninput="updateSlotsCombination(this)">
                </td>
                <td class="slots-combination-display" style="font-size: 20px; text-align: center;">🎰🎰🎰</td>
                <td>
                    <input type="number" class="slots-multiplier-input" 
                           value="1" min="0" step="0.1" 
                           style="width: 100px; padding: 8px;" required>
                </td>
                <td>
                    <button type="button" class="btn btn-secondary" onclick="removeSlotsSymbol(this)" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                </td>
            `;
            tbody.appendChild(row);
        }
        
        function removeSlotsSymbol(button) {
            const row = button.closest('tr');
            row.remove();
            updateSlotsSymbolsIndices();
        }
        
        function updateSlotsCombination(input) {
            const row = input.closest('tr');
            const emoji = input.value || '🎰';
            const combinationCell = row.querySelector('.slots-combination-display');
            combinationCell.textContent = emoji + emoji + emoji;
        }
        
        function updateSlotsSymbolsIndices() {
            const tbody = document.getElementById('slotsSymbolsBody');
            Array.from(tbody.children).forEach((row, index) => {
                row.setAttribute('data-index', index);
            });
        }
        
        // Update combination display when emoji changes
        $(document).on('input', '.slots-emoji-input', function() {
            updateSlotsCombination(this);
        });
        
        // Serialize slots symbols to JSON before form submission
        $('form').on('submit', function(e) {
            if ($(this).find('#slotsSymbolsTable').length > 0) {
                const symbols = [];
                $('#slotsSymbolsBody tr').each(function() {
                    const emoji = $(this).find('.slots-emoji-input').val().trim();
                    const multiplier = parseFloat($(this).find('.slots-multiplier-input').val()) || 0;
                    if (emoji) {
                        symbols.push({emoji: emoji, multiplier: multiplier});
                    }
                });
                $('#slots_symbols_json').val(JSON.stringify(symbols));
                
                // Serialize custom combinations (ordered array)
                const customCombinations = [];
                const numReels = parseInt($('#slots_num_reels').val()) || 3;
                $('#slotsCustomCombinationsBody tr').each(function() {
                    const symbols = [];
                    // Get symbols in order (one per reel)
                    $(this).find('.custom-combo-emoji').each(function() {
                        const emoji = $(this).val().trim();
                        symbols.push(emoji || '');
                    });
                    const multiplier = parseFloat($(this).find('.custom-combo-multiplier').val()) || 0;
                    // Only add if we have the right number of symbols
                    if (symbols.length === numReels && symbols.some(s => s)) {
                        customCombinations.push({symbols: symbols, multiplier: multiplier});
                    }
                });
                $('#slots_custom_combinations_json').val(JSON.stringify(customCombinations));
            }
        });
        
        // Custom combinations management (ordered array)
        function addCustomCombination() {
            const tbody = document.getElementById('slotsCustomCombinationsBody');
            const index = tbody.children.length;
            const numReels = parseInt($('#slots_num_reels').val()) || 3;
            const row = document.createElement('tr');
            row.setAttribute('data-index', index);
            
            let symbolsHtml = '<div class="custom-combination-symbols" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">';
            for (let i = 0; i < numReels; i++) {
                symbolsHtml += '<div style="display: flex; align-items: center; gap: 5px;">';
                symbolsHtml += '<span style="font-size: 14px; color: #666;">#' + (i + 1) + '</span>';
                symbolsHtml += '<input type="text" class="custom-combo-emoji" data-position="' + i + '" value="' + (i === 0 ? '🔥' : i === 1 ? '🔥' : '❤️') + '" maxlength="2" style="width: 60px; padding: 5px; font-size: 18px; text-align: center;" placeholder="🎰" required>';
                symbolsHtml += '</div>';
            }
            symbolsHtml += '</div>';
            
            row.innerHTML = `
                <td>
                    ${symbolsHtml}
                </td>
                <td>
                    <input type="number" class="custom-combo-multiplier" value="5" min="0" step="0.1" style="width: 100px; padding: 8px;" required>
                </td>
                <td>
                    <button type="button" class="btn btn-secondary" onclick="removeCustomCombination(this)" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                </td>
            `;
            tbody.appendChild(row);
            updateCustomCombinationsIndices();
        }
        
        function removeCustomCombination(button) {
            const row = button.closest('tr');
            row.remove();
            updateCustomCombinationsIndices();
        }
        
        function updateCustomCombinationsIndices() {
            const tbody = document.getElementById('slotsCustomCombinationsBody');
            Array.from(tbody.children).forEach((row, index) => {
                row.setAttribute('data-index', index);
            });
        }
        
        // Update custom combinations when number of reels changes
        $('#slots_num_reels').on('change', function() {
            const numReels = parseInt($(this).val()) || 3;
            // Update all existing combinations to have the right number of symbol inputs
            $('#slotsCustomCombinationsBody tr').each(function() {
                const $row = $(this);
                const $symbolsContainer = $row.find('.custom-combination-symbols');
                const currentInputs = $symbolsContainer.find('.custom-combo-emoji');
                const currentValues = currentInputs.map(function() { return $(this).val(); }).get();
                
                $symbolsContainer.empty();
                for (let i = 0; i < numReels; i++) {
                    const value = i < currentValues.length ? currentValues[i] : '';
                    $symbolsContainer.append(
                        $('<div style="display: flex; align-items: center; gap: 5px;">').html(
                            '<span style="font-size: 14px; color: #666;">#' + (i + 1) + '</span>' +
                            '<input type="text" class="custom-combo-emoji" data-position="' + i + '" value="' + value + '" maxlength="2" style="width: 60px; padding: 5px; font-size: 18px; text-align: center;" placeholder="🎰" required>'
                        )
                    );
                }
            });
        });
        
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
