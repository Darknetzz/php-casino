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
            
            // Handle game mode settings
            $rouletteMode = $_POST['roulette_mode'] ?? 'local';
            $crashMode = $_POST['crash_mode'] ?? 'local';
            
            if (!in_array($rouletteMode, ['local', 'central'])) {
                $errors[] = 'Invalid roulette mode';
            }
            if (!in_array($crashMode, ['local', 'central'])) {
                $errors[] = 'Invalid crash mode';
            }
            
            if (empty($errors)) {
                $db->setSetting('max_deposit', $maxDeposit);
                $db->setSetting('max_bet', $maxBet);
                $db->setSetting('starting_balance', $startingBalance);
                $db->setSetting('default_bet', $defaultBet);
                $db->setSetting('roulette_mode', $rouletteMode);
                $db->setSetting('crash_mode', $crashMode);
                header('Location: admin.php?tab=settings&success=1');
                exit;
            }
        }
        
        // Check if this is multipliers update
        if (isset($_POST['slots_symbols']) || isset($_POST['plinko_multiplier_0']) || isset($_POST['dice_num_dice']) || isset($_POST['crash_speed']) || isset($_POST['blackjack_regular_multiplier'])) {
            // Slots multipliers (dynamic symbols)
            if (isset($_POST['slots_symbols'])) {
                $slotsSymbolsJson = $_POST['slots_symbols'];
                $slotsSymbols = json_decode($slotsSymbolsJson, true);
                // Handle N-of-a-kind rules
                $slotsNOfKindRulesJson = isset($_POST['slots_n_of_kind_rules']) ? $_POST['slots_n_of_kind_rules'] : '[]';
                $slotsNOfKindRules = json_decode($slotsNOfKindRulesJson, true);
                $slotsWinRow = isset($_POST['slots_win_row']) ? intval($_POST['slots_win_row']) : 1;
                
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
                
                // Validate N-of-a-kind rules (after validating symbols so we know what's available)
                if (!is_array($slotsNOfKindRules)) {
                    $slotsNOfKindRules = [];
                } else {
                    // Get available symbols for validation
                    $availableSymbols = [];
                    if (is_array($slotsSymbols)) {
                        foreach ($slotsSymbols as $symbol) {
                            if (!empty($symbol['emoji'])) {
                                $availableSymbols[] = trim($symbol['emoji']);
                            }
                        }
                    }
                    
                    foreach ($slotsNOfKindRules as $index => $rule) {
                        $count = intval($rule['count'] ?? 0);
                        $symbol = isset($rule['symbol']) ? trim($rule['symbol']) : '';
                        $multiplier = floatval($rule['multiplier'] ?? 0);
                        
                        if ($count < 1 || $count > 10) {
                            $errors[] = "N-of-a-kind rule #" . ($index + 1) . " count must be between 1 and 10";
                        }
                        if ($multiplier < 0) {
                            $errors[] = "N-of-a-kind rule #" . ($index + 1) . " multiplier must be greater than or equal to 0";
                        }
                        // Symbol must be "any" or one of the defined symbols
                        if ($symbol !== '' && strtolower($symbol) !== 'any' && !in_array($symbol, $availableSymbols)) {
                            $errors[] = "N-of-a-kind rule #" . ($index + 1) . " symbol must be 'any' or one of the defined symbols";
                        }
                    }
                }
                
                // Handle custom combinations (ordered array)
                $slotsCustomCombinationsJson = isset($_POST['slots_custom_combinations']) ? $_POST['slots_custom_combinations'] : '[]';
                $slotsCustomCombinations = json_decode($slotsCustomCombinationsJson, true);
                
                if (!is_array($slotsCustomCombinations)) {
                    $slotsCustomCombinations = [];
                } else {
                    // Get number of reels to validate against
                    $numReels = isset($_POST['slots_num_reels']) ? intval($_POST['slots_num_reels']) : 3;
                    foreach ($slotsCustomCombinations as $index => $combination) {
                        if (!isset($combination['symbols']) || !is_array($combination['symbols'])) {
                            $errors[] = "Custom combination #" . ($index + 1) . " must have a symbols array";
                        } else {
                            // Validate that symbols array matches number of reels
                            if (count($combination['symbols']) !== $numReels) {
                                $errors[] = "Custom combination #" . ($index + 1) . " must have exactly " . $numReels . " symbols (one per reel, empty allowed)";
                            }
                            // Check that at least one symbol is specified (not all empty)
                            $hasAtLeastOneSymbol = false;
                            foreach ($combination['symbols'] as $symbolIndex => $symbol) {
                                if (!empty($symbol) && trim($symbol) !== '') {
                                    $hasAtLeastOneSymbol = true;
                                    break;
                                }
                            }
                            if (!$hasAtLeastOneSymbol) {
                                $errors[] = "Custom combination #" . ($index + 1) . " must have at least one symbol specified (empty positions are allowed)";
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
                    $db->setSetting('slots_n_of_kind_rules', json_encode($slotsNOfKindRules));
                    $db->setSetting('slots_custom_combinations', json_encode($slotsCustomCombinations));
                    $db->setSetting('slots_num_reels', $slotsNumReels);
                    $db->setSetting('slots_win_row', $slotsWinRow);
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
            if (isset($_POST['dice_num_dice']) || isset($_POST['dice_3_of_kind'])) {
                $diceNumDice = intval($_POST['dice_num_dice'] ?? 6);
                
                if ($diceNumDice < 1 || $diceNumDice > 20) {
                    $errors[] = 'Number of dice must be between 1 and 20';
                }
                
                // Collect all multipliers (1 through dice_num_dice)
                $diceMultipliers = [];
                $hasErrors = false;
                for ($i = 1; $i <= $diceNumDice; $i++) {
                    $multiplier = floatval($_POST['dice_' . $i . '_of_kind'] ?? 0);
                    if ($multiplier < 0) {
                        $errors[] = "Dice $i of a kind multiplier must be greater than or equal to 0";
                        $hasErrors = true;
                    }
                    $diceMultipliers[$i] = $multiplier;
                }
                
                if (empty($errors) && !$hasErrors) {
                    // Store multipliers as JSON
                    $db->setSetting('dice_multipliers', json_encode($diceMultipliers));
                    $db->setSetting('dice_num_dice', $diceNumDice);
                }
            }
            
            // Crash settings
            if (isset($_POST['crash_speed'])) {
                $crashSpeed = floatval($_POST['crash_speed'] ?? 0.02);
                $crashMaxMultiplier = floatval($_POST['crash_max_multiplier'] ?? 0);
                $crashDistributionParam = floatval($_POST['crash_distribution_param'] ?? 0.99);
                $crashBettingDuration = intval($_POST['crash_betting_duration'] ?? 15);
                $crashRoundInterval = intval($_POST['crash_round_interval'] ?? 5);
                
                if ($crashSpeed <= 0 || $crashSpeed > 1) {
                    $errors[] = 'Crash speed must be between 0 and 1';
                }
                if ($crashMaxMultiplier < 0) {
                    $errors[] = 'Crash max multiplier must be greater than or equal to 0 (0 = unlimited)';
                }
                if ($crashDistributionParam <= 0 || $crashDistributionParam >= 1) {
                    $errors[] = 'Crash distribution parameter must be between 0 and 1 (exclusive)';
                }
                if ($crashBettingDuration < 5 || $crashBettingDuration > 300) {
                    $errors[] = 'Crash betting duration must be between 5 and 300 seconds';
                }
                if ($crashRoundInterval < 1 || $crashRoundInterval > 300) {
                    $errors[] = 'Crash round interval must be between 1 and 300 seconds';
                }
                
                if (empty($errors)) {
                    $db->setSetting('crash_speed', $crashSpeed);
                    $db->setSetting('crash_max_multiplier', $crashMaxMultiplier);
                    $db->setSetting('crash_distribution_param', $crashDistributionParam);
                    $db->setSetting('crash_betting_duration', $crashBettingDuration);
                    $db->setSetting('crash_round_interval', $crashRoundInterval);
                }
            }
            
            // Roulette settings
            if (isset($_POST['roulette_betting_duration'])) {
                $rouletteBettingDuration = intval($_POST['roulette_betting_duration'] ?? 15);
                $rouletteSpinningDuration = intval($_POST['roulette_spinning_duration'] ?? 4);
                $rouletteRoundInterval = intval($_POST['roulette_round_interval'] ?? 5);
                
                if ($rouletteBettingDuration < 5 || $rouletteBettingDuration > 300) {
                    $errors[] = 'Roulette betting duration must be between 5 and 300 seconds';
                }
                if ($rouletteSpinningDuration < 1 || $rouletteSpinningDuration > 30) {
                    $errors[] = 'Roulette spinning duration must be between 1 and 30 seconds';
                }
                if ($rouletteRoundInterval < 1 || $rouletteRoundInterval > 300) {
                    $errors[] = 'Roulette round interval must be between 1 and 300 seconds';
                }
                
                if (empty($errors)) {
                    $db->setSetting('roulette_betting_duration', $rouletteBettingDuration);
                    $db->setSetting('roulette_spinning_duration', $rouletteSpinningDuration);
                    $db->setSetting('roulette_round_interval', $rouletteRoundInterval);
                }
            }
            
            // Blackjack settings
            if (isset($_POST['blackjack_regular_multiplier'])) {
                $blackjackRegularMultiplier = floatval($_POST['blackjack_regular_multiplier'] ?? 2.0);
                $blackjackBlackjackMultiplier = floatval($_POST['blackjack_blackjack_multiplier'] ?? 2.5);
                $blackjackDealerStand = intval($_POST['blackjack_dealer_stand'] ?? 17);
                
                if ($blackjackRegularMultiplier < 0) {
                    $errors[] = 'Blackjack regular win multiplier must be greater than or equal to 0';
                }
                if ($blackjackBlackjackMultiplier < 0) {
                    $errors[] = 'Blackjack blackjack multiplier must be greater than or equal to 0';
                }
                if ($blackjackDealerStand < 1 || $blackjackDealerStand > 21) {
                    $errors[] = 'Dealer stand threshold must be between 1 and 21';
                }
                
                if (empty($errors)) {
                    $db->setSetting('blackjack_regular_multiplier', $blackjackRegularMultiplier);
                    $db->setSetting('blackjack_blackjack_multiplier', $blackjackBlackjackMultiplier);
                    $db->setSetting('blackjack_dealer_stand', $blackjackDealerStand);
                }
            }
            
            if (empty($errors)) {
                // Determine which game was updated
                $gameParam = 'slots'; // default
                if (isset($_POST['slots_symbols'])) {
                    $gameParam = 'slots';
                } elseif (isset($_POST['plinko_multiplier_0'])) {
                    $gameParam = 'plinko';
                } elseif (isset($_POST['dice_num_dice'])) {
                    $gameParam = 'dice';
                } elseif (isset($_POST['roulette_betting_duration'])) {
                    $gameParam = 'roulette';
                } elseif (isset($_POST['crash_speed'])) {
                    $gameParam = 'crash';
                } elseif (isset($_POST['blackjack_regular_multiplier'])) {
                    $gameParam = 'blackjack';
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
                <a href="admin.php?tab=rounds" class="admin-tab <?php echo $currentTab === 'rounds' ? 'active' : ''; ?>">
                    <span>🎯</span> Game Rounds
                </a>
            </div>
            
            <!-- Game Settings Subnav -->
            <?php if ($currentTab === 'multipliers'): ?>
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
                <a href="admin.php?tab=multipliers&game=roulette" class="admin-subtab <?php echo $currentGame === 'roulette' ? 'active' : ''; ?>">
                    <span>🛞</span> Roulette
                </a>
                <a href="admin.php?tab=multipliers&game=crash" class="admin-subtab <?php echo $currentGame === 'crash' ? 'active' : ''; ?>">
                    <span>🚀</span> Crash
                </a>
                <a href="admin.php?tab=multipliers&game=blackjack" class="admin-subtab <?php echo $currentGame === 'blackjack' ? 'active' : ''; ?>">
                    <span>🃏</span> Blackjack
                </a>
            </div>
            <?php endif; ?>
            
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
                    
                    <h3 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Game Modes</h3>
                    <p style="margin-bottom: 15px; color: #666;">Choose whether games run locally (client-side) or centrally (synchronized for all users).</p>
                    
                    <div class="form-group">
                        <label for="roulette_mode">Roulette Mode</label>
                        <select id="roulette_mode" name="roulette_mode" required>
                            <option value="local" <?php echo ($settings['roulette_mode'] ?? 'local') === 'local' ? 'selected' : ''; ?>>Local (Client-side)</option>
                            <option value="central" <?php echo ($settings['roulette_mode'] ?? 'local') === 'central' ? 'selected' : ''; ?>>Central (Synchronized)</option>
                        </select>
                        <small>Local: Users spin individually. Central: All users see the same synchronized rounds (requires worker).</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="crash_mode">Crash Mode</label>
                        <select id="crash_mode" name="crash_mode" required>
                            <option value="local" <?php echo ($settings['crash_mode'] ?? 'local') === 'local' ? 'selected' : ''; ?>>Local (Client-side)</option>
                            <option value="central" <?php echo ($settings['crash_mode'] ?? 'local') === 'central' ? 'selected' : ''; ?>>Central (Synchronized)</option>
                        </select>
                        <small>Local: Users start games individually. Central: All users see the same synchronized rounds (requires worker).</small>
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary">Update Settings</button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Game Multipliers Section -->
            <?php if ($currentTab === 'multipliers'): ?>
            <div class="admin-section section">
                <h2>🎰 Game Settings</h2>
                
                <!-- Slots Multipliers -->
                <?php if ($currentGame === 'slots'): ?>
                <form method="POST" action="admin.php?tab=multipliers&game=slots" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Slot Machine Symbols</h3>
                    <p style="margin-bottom: 15px; color: #666;">Add, edit, or remove slot symbols. Each symbol needs an emoji and a multiplier for all-of-a-kind wins. Adding a symbol will suggest a combination in the Custom Combinations section below.</p>
                    <div id="slotsSymbolsContainer">
                        <table class="multiplier-table" id="slotsSymbolsTable">
                            <thead>
                                <tr>
                                    <th>Emoji</th>
                                    <th>Multiplier (all of a kind)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="slotsSymbolsBody">
                                <?php
                                $slotsSymbolsJson = $settings['slots_symbols'] ?? '[]';
                                $slotsSymbols = json_decode($slotsSymbolsJson, true);
                                $numReelsForDisplay = intval($settings['slots_num_reels'] ?? 3);
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
                                               placeholder="🎰" required>
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
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">N-of-a-Kind Rules</h4>
                    <p style="margin-bottom: 15px; color: #666;">Define multipliers for N matching symbols. Select "any" to match any symbol, or select a specific symbol from the list above.</p>
                    <div id="slotsNOfKindContainer">
                        <table class="multiplier-table" id="slotsNOfKindTable" style="max-width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Count (N)</th>
                                    <th style="width: 35%;">Symbol</th>
                                    <th style="width: 25%;">Multiplier</th>
                                    <th style="width: 15%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="slotsNOfKindBody">
                                <?php
                                $slotsNOfKindRulesJson = $settings['slots_n_of_kind_rules'] ?? '[]';
                                $slotsNOfKindRules = json_decode($slotsNOfKindRulesJson, true);
                                // If empty and old setting exists, migrate it
                                if (empty($slotsNOfKindRules) || !is_array($slotsNOfKindRules)) {
                                    $oldTwoOfKind = floatval($settings['slots_two_of_kind_multiplier'] ?? 0);
                                    if ($oldTwoOfKind > 0) {
                                        $slotsNOfKindRules = [
                                            ['count' => 2, 'symbol' => 'any', 'multiplier' => $oldTwoOfKind]
                                        ];
                                    } else {
                                        $slotsNOfKindRules = [];
                                    }
                                }
                                foreach ($slotsNOfKindRules as $index => $rule):
                                    $ruleSymbol = htmlspecialchars($rule['symbol'] ?? 'any');
                                    if ($ruleSymbol === '' || strtolower($ruleSymbol) === 'any') {
                                        $ruleSymbol = 'any';
                                    }
                                ?>
                                <tr data-index="<?php echo $index; ?>">
                                    <td>
                                        <input type="number" class="n-of-kind-count" 
                                               value="<?php echo htmlspecialchars($rule['count'] ?? '2'); ?>" 
                                               min="1" max="10" style="width: 80px; padding: 8px;" required>
                                    </td>
                                    <td>
                                        <select class="n-of-kind-symbol" style="width: 120px; padding: 8px; font-size: 16px;">
                                            <option value="any" <?php echo ($ruleSymbol === 'any') ? 'selected' : ''; ?>>any</option>
                                            <?php foreach ($slotsSymbols as $symbol): ?>
                                                <?php $symbolEmoji = htmlspecialchars($symbol['emoji'] ?? ''); ?>
                                                <option value="<?php echo $symbolEmoji; ?>" <?php echo ($ruleSymbol === $symbolEmoji) ? 'selected' : ''; ?>><?php echo $symbolEmoji; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="n-of-kind-multiplier" 
                                               value="<?php echo htmlspecialchars($rule['multiplier'] ?? '1'); ?>" 
                                               min="0" step="0.1" style="width: 100px; padding: 8px;" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-secondary" onclick="removeNOfKindRule(this)" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-secondary" onclick="addNOfKindRule()" style="margin-top: 10px;">+ Add N-of-a-Kind Rule</button>
                        <input type="hidden" id="slots_n_of_kind_rules_json" name="slots_n_of_kind_rules" value="">
                    </div>
                    
                    <hr style="border: none; border-top: 2px solid #e0e0e0; margin: 30px 0 20px 0;">
                    
                    <h4 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Custom Combinations</h4>
                    <p style="margin-bottom: 15px; color: #666;">Define custom winning combinations with exact order (e.g., 🔥🔥❤️). Symbols are matched in order from left to right. Empty positions (leave blank) match any symbol. <strong>Adding a symbol above will automatically suggest an all-of-a-kind combination here.</strong></p>
                    <div id="slotsCustomCombinationsContainer">
                        <table class="multiplier-table" id="slotsCustomCombinationsTable" style="max-width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 60%;">Symbols (Ordered)</th>
                                    <th style="width: 20%;">Multiplier</th>
                                    <th style="width: 20%;">Actions</th>
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
                                    <td style="white-space: nowrap;">
                                        <div class="custom-combination-symbols" style="display: flex; flex-wrap: nowrap; gap: 10px; align-items: center;">
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
                                                echo '<input type="text" class="custom-combo-emoji" data-position="' . $i . '" value="' . $emoji . '" maxlength="2" style="width: 60px; padding: 5px; font-size: 18px; text-align: center;" placeholder="Any">';
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
                    
                    <hr style="border: none; border-top: 2px solid #e0e0e0; margin: 30px 0 20px 0;">
                    
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Slots Settings</h3>
                    <div class="form-group">
                        <label for="slots_num_reels">Number of Reels:</label>
                        <input type="number" id="slots_num_reels" name="slots_num_reels" min="3" max="10" 
                               value="<?php echo htmlspecialchars($settings['slots_num_reels'] ?? '3'); ?>" 
                               required style="width: 100px; padding: 8px;">
                        <small style="display: block; margin-top: 5px; color: #666;">Number of reels/columns in the slot machine (3-10)</small>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label for="slots_win_row">Winning Row (when Bet Rows = 1):</label>
                        <select id="slots_win_row" name="slots_win_row" required style="width: 200px; padding: 8px;">
                            <option value="0" <?php echo (($settings['slots_win_row'] ?? '1') == '0') ? 'selected' : ''; ?>>Top Row (0)</option>
                            <option value="1" <?php echo (($settings['slots_win_row'] ?? '1') == '1') ? 'selected' : ''; ?>>Middle Row (1) - Default</option>
                            <option value="2" <?php echo (($settings['slots_win_row'] ?? '1') == '2') ? 'selected' : ''; ?>>Bottom Row (2)</option>
                        </select>
                        <small style="display: block; margin-top: 5px; color: #666;">Which single row to check when betting on 1 row (for reference only - users choose bet rows in-game)</small>
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
                <?php if ($currentGame === 'dice'): 
                    // Load existing multipliers from JSON or fallback to individual settings
                    $diceMultipliersJson = $settings['dice_multipliers'] ?? null;
                    $diceMultipliers = [];
                    if ($diceMultipliersJson) {
                        $decoded = json_decode($diceMultipliersJson, true);
                        if (is_array($decoded)) {
                            $diceMultipliers = $decoded;
                        }
                    }
                    // Fallback to individual settings for backward compatibility
                    if (empty($diceMultipliers)) {
                        $diceMultipliers = [
                            1 => floatval($settings['dice_1_of_kind_multiplier'] ?? 0),
                            2 => floatval($settings['dice_2_of_kind_multiplier'] ?? 0),
                            3 => floatval($settings['dice_3_of_kind_multiplier'] ?? 2),
                            4 => floatval($settings['dice_4_of_kind_multiplier'] ?? 5),
                            5 => floatval($settings['dice_5_of_kind_multiplier'] ?? 10),
                            6 => floatval($settings['dice_6_of_kind_multiplier'] ?? 20)
                        ];
                    }
                    $diceNumDice = intval($settings['dice_num_dice'] ?? 6);
                ?>
                <form method="POST" action="admin.php?tab=multipliers&game=dice" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Dice Roll Settings</h3>
                    
                    <h4 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Dice Settings</h4>
                    <div class="form-group">
                        <label for="dice_num_dice">Number of Dice:</label>
                        <input type="number" id="dice_num_dice" name="dice_num_dice" min="1" max="20" 
                               value="<?php echo htmlspecialchars($diceNumDice); ?>" 
                               required style="width: 100px; padding: 8px;">
                        <small style="display: block; margin-top: 5px; color: #666;">Number of dice to roll (1-20). Changing this will update the multiplier table below.</small>
                    </div>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Multipliers</h4>
                    <p style="margin-bottom: 15px; color: #666;">Configure multipliers for matching dice combinations (1 through <?php echo $diceNumDice; ?> of a kind):</p>
                    <table class="multiplier-table" id="diceMultipliersTable">
                        <thead>
                            <tr>
                                <th>Combination</th>
                                <th>Multiplier</th>
                            </tr>
                        </thead>
                        <tbody id="diceMultipliersBody">
                            <?php for ($i = 1; $i <= $diceNumDice; $i++): 
                                $multiplier = isset($diceMultipliers[$i]) ? $diceMultipliers[$i] : 0;
                            ?>
                            <tr>
                                <td><?php echo $i; ?> of a kind</td>
                                <td>
                                    <input type="number" id="dice_<?php echo $i; ?>_of_kind" name="dice_<?php echo $i; ?>_of_kind" 
                                           min="0" step="0.1" value="<?php echo htmlspecialchars($multiplier); ?>" 
                                           required style="width: 100px; padding: 8px;">
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 20px;">Update Dice Settings</button>
                </form>
                
                <script>
                    // Update multiplier table when number of dice changes
                    $(document).ready(function() {
                        const $numDiceInput = $('#dice_num_dice');
                        const $multipliersBody = $('#diceMultipliersBody');
                        
                        // Store current multipliers
                        let currentMultipliers = {};
                        $multipliersBody.find('input[type="number"]').each(function() {
                            const name = $(this).attr('name');
                            const match = name.match(/dice_(\d+)_of_kind/);
                            if (match) {
                                currentMultipliers[parseInt(match[1])] = parseFloat($(this).val()) || 0;
                            }
                        });
                        
                        $numDiceInput.on('change', function() {
                            const newNumDice = parseInt($(this).val()) || 6;
                            if (newNumDice < 1 || newNumDice > 20) {
                                alert('Number of dice must be between 1 and 20');
                                $(this).val(6);
                                return;
                            }
                            
                            // Update table
                            $multipliersBody.empty();
                            for (let i = 1; i <= newNumDice; i++) {
                                const multiplier = currentMultipliers[i] !== undefined ? currentMultipliers[i] : 0;
                                const row = $('<tr>').html(
                                    '<td>' + i + ' of a kind</td>' +
                                    '<td>' +
                                    '<input type="number" id="dice_' + i + '_of_kind" name="dice_' + i + '_of_kind" ' +
                                    'min="0" step="0.1" value="' + multiplier + '" ' +
                                    'required style="width: 100px; padding: 8px;">' +
                                    '</td>'
                                );
                                $multipliersBody.append(row);
                            }
                            
                            // Update description
                            $('h4:contains("Multipliers")').next('p').text('Configure multipliers for matching dice combinations (1 through ' + newNumDice + ' of a kind):');
                        });
                    });
                </script>
                <?php endif; ?>
                
                <!-- Roulette Settings -->
                <?php if ($currentGame === 'roulette'): ?>
                <form method="POST" action="admin.php?tab=multipliers&game=roulette" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Roulette Game Settings</h3>
                    <p style="margin-bottom: 15px; color: #666;">Configure roulette game settings:</p>
                    
                    <h4 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Central Mode Settings (Synchronized Rounds)</h4>
                    <p style="margin-bottom: 15px; color: #666; font-size: 14px;">These settings only apply when Roulette Mode is set to "Central" in Casino Settings.</p>
                    <table class="multiplier-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Betting Duration (seconds)</td>
                                <td>
                                    <input type="number" id="roulette_betting_duration" name="roulette_betting_duration"
                                           min="5" max="300" step="1" 
                                           value="<?php echo htmlspecialchars($settings['roulette_betting_duration'] ?? '15'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>How long users can place bets before the wheel spins. Default: 15 seconds</td>
                            </tr>
                            <tr>
                                <td>Spinning Duration (seconds)</td>
                                <td>
                                    <input type="number" id="roulette_spinning_duration" name="roulette_spinning_duration"
                                           min="1" max="30" step="1" 
                                           value="<?php echo htmlspecialchars($settings['roulette_spinning_duration'] ?? '4'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>How long the wheel spins before showing the result. Default: 4 seconds</td>
                            </tr>
                            <tr>
                                <td>Round Interval (seconds)</td>
                                <td>
                                    <input type="number" id="roulette_round_interval" name="roulette_round_interval"
                                           min="1" max="300" step="1" 
                                           value="<?php echo htmlspecialchars($settings['roulette_round_interval'] ?? '5'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>Time between rounds. Default: 5 seconds</td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 20px;">Update Roulette Settings</button>
                </form>
                <?php endif; ?>
                
                <!-- Crash Settings -->
                <?php if ($currentGame === 'crash'): ?>
                <form method="POST" action="admin.php?tab=multipliers&game=crash" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Crash Game Settings</h3>
                    <p style="margin-bottom: 15px; color: #666;">Configure the crash game mechanics:</p>
                    <table class="multiplier-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Crash Speed</td>
                                <td>
                                    <input type="number" id="crash_speed" name="crash_speed"
                                           min="0.001" max="1" step="0.001" 
                                           value="<?php echo htmlspecialchars($settings['crash_speed'] ?? '0.02'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>How fast the multiplier increases (0.001 = slow, 1 = very fast). Default: 0.02</td>
                            </tr>
                            <tr>
                                <td>Max Multiplier</td>
                                <td>
                                    <input type="number" id="crash_max_multiplier" name="crash_max_multiplier"
                                           min="0" step="0.1" 
                                           value="<?php echo htmlspecialchars($settings['crash_max_multiplier'] ?? '0'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>Maximum multiplier before auto-crash (0 = unlimited). Default: 0 (unlimited)</td>
                            </tr>
                            <tr>
                                <td>Distribution Curve</td>
                                <td>
                                    <input type="number" id="crash_distribution_param" name="crash_distribution_param"
                                           min="0.01" max="0.999" step="0.001" 
                                           value="<?php echo htmlspecialchars($settings['crash_distribution_param'] ?? '0.99'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>Distribution curve parameter (0.01-0.999). Lower = more high multipliers, Higher = more low multipliers. Default: 0.99</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color: #667eea;">Central Mode Settings (Synchronized Rounds)</h4>
                    <p style="margin-bottom: 15px; color: #666; font-size: 14px;">These settings only apply when Crash Mode is set to "Central" in Casino Settings.</p>
                    <table class="multiplier-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Betting Duration (seconds)</td>
                                <td>
                                    <input type="number" id="crash_betting_duration" name="crash_betting_duration"
                                           min="5" max="300" step="1" 
                                           value="<?php echo htmlspecialchars($settings['crash_betting_duration'] ?? '15'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>How long users can place bets before the round starts. Default: 15 seconds</td>
                            </tr>
                            <tr>
                                <td>Round Interval (seconds)</td>
                                <td>
                                    <input type="number" id="crash_round_interval" name="crash_round_interval"
                                           min="1" max="300" step="1" 
                                           value="<?php echo htmlspecialchars($settings['crash_round_interval'] ?? '5'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>Time between rounds. Default: 5 seconds</td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <h4 style="margin-top: 0; color: #667eea;">Distribution Curve Explanation</h4>
                        <p style="margin-bottom: 10px; color: #666; font-size: 14px;">
                            The distribution parameter controls how likely high multipliers are:
                        </p>
                        <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 14px;">
                            <li><strong>0.99</strong> (default): Heavy bias toward low multipliers (~50% crash before 2x)</li>
                            <li><strong>0.95</strong>: More balanced, higher chance of big multipliers</li>
                            <li><strong>0.90</strong>: Even more balanced, more frequent high multipliers</li>
                            <li><strong>0.50</strong>: Very balanced, many high multipliers possible</li>
                        </ul>
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 20px;">Update Crash Settings</button>
                </form>
                <?php endif; ?>
                
                <!-- Blackjack Settings -->
                <?php if ($currentGame === 'blackjack'): ?>
                <form method="POST" action="admin.php?tab=multipliers&game=blackjack" class="admin-form">
                    <h3 style="margin-top: 20px; margin-bottom: 15px; color: #667eea;">Blackjack Game Settings</h3>
                    <p style="margin-bottom: 15px; color: #666;">Configure the blackjack game mechanics:</p>
                    <table class="multiplier-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Regular Win Multiplier</td>
                                <td>
                                    <input type="number" id="blackjack_regular_multiplier" name="blackjack_regular_multiplier"
                                           min="0" step="0.1" 
                                           value="<?php echo htmlspecialchars($settings['blackjack_regular_multiplier'] ?? '2.0'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>Multiplier for regular wins (beating dealer without blackjack). Default: 2.0 (2x bet)</td>
                            </tr>
                            <tr>
                                <td>Blackjack Multiplier</td>
                                <td>
                                    <input type="number" id="blackjack_blackjack_multiplier" name="blackjack_blackjack_multiplier"
                                           min="0" step="0.1" 
                                           value="<?php echo htmlspecialchars($settings['blackjack_blackjack_multiplier'] ?? '2.5'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>Multiplier for blackjack wins (21 with first 2 cards). Default: 2.5 (2.5x bet)</td>
                            </tr>
                            <tr>
                                <td>Dealer Stand Threshold</td>
                                <td>
                                    <input type="number" id="blackjack_dealer_stand" name="blackjack_dealer_stand"
                                           min="1" max="21" step="1" 
                                           value="<?php echo htmlspecialchars($settings['blackjack_dealer_stand'] ?? '17'); ?>"
                                           required style="width: 100px; padding: 8px;">
                                </td>
                                <td>Dealer must stand when reaching this score or higher (1-21). Default: 17</td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <h4 style="margin-top: 0; color: #667eea;">Blackjack Rules</h4>
                        <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 14px;">
                            <li><strong>Regular Win:</strong> Player beats dealer without going over 21. Payout = bet × Regular Win Multiplier</li>
                            <li><strong>Blackjack:</strong> Player gets 21 with first 2 cards (Ace + 10-value card). Payout = bet × Blackjack Multiplier</li>
                            <li><strong>Dealer Stand:</strong> Dealer must hit until reaching the stand threshold, then must stand</li>
                            <li><strong>Push:</strong> If player and dealer have the same score (both ≤ 21), bet is returned</li>
                        </ul>
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 20px;">Update Blackjack Settings</button>
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
            
            <!-- Game Rounds Section -->
            <?php if ($currentTab === 'rounds'): 
                require_once __DIR__ . '/../includes/provably_fair.php';
                $rouletteRound = $db->getCurrentRouletteRound();
                $crashRound = $db->getCurrentCrashRound();
                $rouletteMode = getSetting('roulette_mode', 'local');
                $crashMode = getSetting('crash_mode', 'local');
            ?>
            <div class="admin-section section">
                <h2>🎯 Game Rounds Monitor</h2>
                <p style="margin-bottom: 20px;" class="admin-description">Monitor current game rounds and predict upcoming results (admin only).</p>
                <p style="margin-bottom: 20px; padding: 10px; background: rgba(102, 126, 234, 0.1); border-radius: 5px;" class="admin-description">
                    <strong>Current Modes:</strong> Roulette: <strong><?php echo ucfirst($rouletteMode); ?></strong> | Crash: <strong><?php echo ucfirst($crashMode); ?></strong><br>
                    <small>Central mode requires the game rounds worker to be running. Change modes in Casino Settings.</small>
                </p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <!-- Roulette Round -->
                    <div class="section rounds-card" style="padding: 20px; border-radius: 8px;">
                        <h3 style="margin-top: 0;" class="rounds-card-title">🛞 Roulette</h3>
                        <div id="rouletteRoundInfo" class="rounds-card-content">
                            <?php if ($rouletteMode === 'local'): ?>
                                <p style="color: #999;">Local mode - no synchronized rounds</p>
                            <?php elseif ($rouletteRound): ?>
                                <p><strong>Round #<?php echo $rouletteRound['round_number']; ?></strong></p>
                                <p>Status: <strong><?php echo ucfirst($rouletteRound['status']); ?></strong></p>
                                <?php 
                                $now = time();
                                if ($rouletteRound['status'] === 'betting'): 
                                    $bettingEndsAt = strtotime($rouletteRound['betting_ends_at']);
                                    $timeLeft = max(0, $bettingEndsAt - $now);
                                    $predictedResult = ProvablyFair::generateRouletteResult($rouletteRound['server_seed'], $rouletteRound['client_seed'] ?? '');
                                ?>
                                    <p style="color: #28a745; font-weight: bold; margin-top: 10px;">
                                        🔮 Predicted Result: <span style="font-size: 1.2em;"><?php echo $predictedResult; ?></span>
                                    </p>
                                    <p style="margin-top: 10px; font-size: 1.1em; color: #667eea;">
                                        Next spin in: <strong><?php echo ceil($timeLeft); ?>s</strong>
                                    </p>
                                <?php elseif ($rouletteRound['status'] === 'spinning'): 
                                    $predictedResult = ProvablyFair::generateRouletteResult($rouletteRound['server_seed'], $rouletteRound['client_seed'] ?? '');
                                    $spinningDuration = intval(getSetting('roulette_spinning_duration', 4));
                                    $startedAt = strtotime($rouletteRound['started_at']);
                                    $finishesAt = $startedAt + $spinningDuration;
                                    $timeLeft = max(0, $finishesAt - $now);
                                ?>
                                    <p style="color: #ffc107; font-weight: bold; margin-top: 10px;">
                                        🔮 Predicted Result: <span style="font-size: 1.2em;"><?php echo $predictedResult; ?></span>
                                    </p>
                                    <p style="margin-top: 10px; font-size: 1.1em; color: #ffc107;">
                                        Spinning... Result in: <strong><?php echo ceil($timeLeft); ?>s</strong>
                                    </p>
                                <?php elseif ($rouletteRound['status'] === 'finished' && $rouletteRound['result_number'] !== null): ?>
                                    <p style="color: #667eea; font-weight: bold; margin-top: 10px;">
                                        Result: <span style="font-size: 1.2em;"><?php echo $rouletteRound['result_number']; ?></span>
                                    </p>
                                <?php endif; ?>
                                <p style="font-size: 0.9em; margin-top: 10px;" class="rounds-seed-hash">
                                    Server Seed Hash: <code style="font-size: 0.8em;"><?php echo substr($rouletteRound['server_seed_hash'], 0, 16); ?>...</code>
                                </p>
                            <?php else: ?>
                                <p>No active round - worker may not be running</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Crash Round -->
                    <div class="section rounds-card" style="padding: 20px; border-radius: 8px;">
                        <h3 style="margin-top: 0;" class="rounds-card-title">🚀 Crash</h3>
                        <div id="crashRoundInfo" class="rounds-card-content">
                            <?php if ($crashMode === 'local'): ?>
                                <p style="color: #999;">Local mode - no synchronized rounds</p>
                            <?php elseif ($crashRound): ?>
                                <p><strong>Round #<?php echo $crashRound['round_number']; ?></strong></p>
                                <p>Status: <strong><?php echo ucfirst($crashRound['status']); ?></strong></p>
                                <?php 
                                $now = time();
                                if ($crashRound['status'] === 'betting'): 
                                    $bettingEndsAt = strtotime($crashRound['betting_ends_at']);
                                    $timeLeft = max(0, $bettingEndsAt - $now);
                                    $distributionParam = floatval(getSetting('crash_distribution_param', 0.99));
                                    $predictedCrashPoint = ProvablyFair::generateCrashPoint($crashRound['server_seed'], $crashRound['client_seed'] ?? '', $distributionParam);
                                ?>
                                    <p style="color: #28a745; font-weight: bold; margin-top: 10px;">
                                        🔮 Predicted Crash Point: <span style="font-size: 1.2em;"><?php echo number_format($predictedCrashPoint, 2); ?>x</span>
                                    </p>
                                    <p style="margin-top: 10px; font-size: 1.1em; color: #667eea;">
                                        Next round in: <strong><?php echo ceil($timeLeft); ?>s</strong>
                                    </p>
                                <?php elseif ($crashRound['status'] === 'running' && $crashRound['crash_point']): ?>
                                    <p style="color: #ffc107; font-weight: bold; margin-top: 10px;">
                                        Crash Point: <span style="font-size: 1.2em;"><?php echo number_format($crashRound['crash_point'], 2); ?>x</span>
                                    </p>
                                    <p style="margin-top: 10px; font-size: 1.1em; color: #ffc107;">
                                        Round in progress...
                                    </p>
                                <?php elseif ($crashRound['status'] === 'finished' && $crashRound['crash_point']): ?>
                                    <p style="color: #667eea; font-weight: bold; margin-top: 10px;">
                                        Crashed at: <span style="font-size: 1.2em;"><?php echo number_format($crashRound['crash_point'], 2); ?>x</span>
                                    </p>
                                <?php endif; ?>
                                <p style="font-size: 0.9em; margin-top: 10px;" class="rounds-seed-hash">
                                    Server Seed Hash: <code style="font-size: 0.8em;"><?php echo substr($crashRound['server_seed_hash'], 0, 16); ?>...</code>
                                </p>
                            <?php else: ?>
                                <p>No active round - worker may not be running</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php 
                // Get upcoming predictions
                $rouletteUpcoming = [];
                $crashUpcoming = [];
                if ($rouletteMode === 'central') {
                    $rouletteUpcoming = ProvablyFair::getUpcomingPredictions($db, 'roulette', 10);
                }
                if ($crashMode === 'central') {
                    $crashUpcoming = ProvablyFair::getUpcomingPredictions($db, 'crash', 10);
                }
                ?>
                
                <div class="section rounds-card" style="padding: 20px; border-radius: 8px; margin-top: 20px;">
                    <h3 style="margin-top: 0;" class="rounds-card-title">🔮 Upcoming Predictions (Next 10)</h3>
                    <p style="margin-bottom: 15px; font-size: 0.9em; color: #666;" class="admin-description">
                        <strong>Note:</strong> These are predictions based on deterministic seed generation for preview purposes. Actual rounds use random seeds, so these predictions are for reference only.
                    </p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h4>Roulette Predictions</h4>
                            <div id="rouletteUpcomingTable">
                                <?php if ($rouletteMode === 'local'): ?>
                                    <p style="color: #999; text-align: center;">Local mode - no predictions</p>
                                <?php elseif (empty($rouletteUpcoming)): ?>
                                    <p style="color: #999; text-align: center;">No predictions available</p>
                                <?php else: ?>
                                    <table class="admin-table" style="font-size: 0.9em;">
                                        <thead>
                                            <tr>
                                                <th>Round</th>
                                                <th>Predicted Result</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rouletteUpcoming as $prediction): ?>
                                            <tr>
                                                <td>#<?php echo $prediction['round_number']; ?></td>
                                                <td><strong style="color: #28a745;"><?php echo $prediction['predicted_result']; ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <h4>Crash Predictions</h4>
                            <div id="crashUpcomingTable">
                                <?php if ($crashMode === 'local'): ?>
                                    <p style="color: #999; text-align: center;">Local mode - no predictions</p>
                                <?php elseif (empty($crashUpcoming)): ?>
                                    <p style="color: #999; text-align: center;">No predictions available</p>
                                <?php else: ?>
                                    <table class="admin-table" style="font-size: 0.9em;">
                                        <thead>
                                            <tr>
                                                <th>Round</th>
                                                <th>Predicted Crash</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($crashUpcoming as $prediction): 
                                                $multValue = floatval($prediction['predicted_crash_point']);
                                                $color = '#dc3545'; // Red for low
                                                if ($multValue >= 5) $color = '#ffc107'; // Yellow for medium
                                                if ($multValue >= 10) $color = '#28a745'; // Green for high
                                            ?>
                                            <tr>
                                                <td>#<?php echo $prediction['round_number']; ?></td>
                                                <td><strong style="color: <?php echo $color; ?>;"><?php echo number_format($prediction['predicted_crash_point'], 2); ?>x</strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section rounds-card" style="padding: 20px; border-radius: 8px; margin-top: 20px;">
                    <h3 style="margin-top: 0;" class="rounds-card-title">📋 Recent History</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h4>Roulette (Last 10)</h4>
                            <div id="rouletteHistoryTable">
                                <table class="admin-table" style="font-size: 0.9em;">
                                    <thead>
                                        <tr>
                                            <th>Round</th>
                                            <th>Result</th>
                                            <th>Finished</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rouletteHistory = $db->getRouletteRoundsHistory(10);
                                        foreach ($rouletteHistory as $round): 
                                        ?>
                                        <tr>
                                            <td>#<?php echo $round['round_number']; ?></td>
                                            <td><strong><?php echo $round['result_number'] !== null ? $round['result_number'] : '-'; ?></strong></td>
                                            <td><?php echo $round['finished_at'] ? date('H:i:s', strtotime($round['finished_at'])) : '-'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4>Crash (Last 10)</h4>
                            <div id="crashHistoryTable">
                                <table class="admin-table" style="font-size: 0.9em;">
                                    <thead>
                                        <tr>
                                            <th>Round</th>
                                            <th>Crash Point</th>
                                            <th>Finished</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $crashHistory = $db->getCrashRoundsHistory(10);
                                        foreach ($crashHistory as $round): 
                                        ?>
                                        <tr>
                                            <td>#<?php echo $round['round_number']; ?></td>
                                            <td><strong><?php echo $round['crash_point'] ? number_format($round['crash_point'], 2) . 'x' : '-'; ?></strong></td>
                                            <td><?php echo $round['finished_at'] ? date('H:i:s', strtotime($round['finished_at'])) : '-'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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
                           placeholder="🎰" required>
                </td>
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
            // Don't auto-suggest immediately - wait for user to enter emoji/multiplier
            // Update N-of-a-kind symbol dropdowns to include the new symbol
            updateNOfKindSymbolDropdowns();
        }
        
        function removeSlotsSymbol(button) {
            const row = button.closest('tr');
            row.remove();
            updateSlotsSymbolsIndices();
            // Update N-of-a-kind symbol dropdowns
            updateNOfKindSymbolDropdowns();
        }
        
        // Suggest a combination from a symbol (all-of-a-kind)
        function suggestCombinationFromSymbol(emoji, multiplier) {
            const numReels = parseInt($('#slots_num_reels').val()) || 3;
            const symbols = [];
            for (let i = 0; i < numReels; i++) {
                symbols.push(emoji);
            }
            
            // Check if this combination already exists
            let exists = false;
            $('#slotsCustomCombinationsBody tr').each(function() {
                const $row = $(this);
                const combinationSymbols = [];
                $row.find('.custom-combo-emoji').each(function() {
                    combinationSymbols.push($(this).val().trim());
                });
                if (combinationSymbols.length === symbols.length && 
                    combinationSymbols.every((s, i) => s === symbols[i])) {
                    exists = true;
                    return false; // break
                }
            });
            
            if (!exists) {
                // Add the combination
                addCustomCombinationWithValues(symbols, multiplier);
            }
        }
        
        // Add custom combination with specific values
        function addCustomCombinationWithValues(symbols, multiplier) {
            const tbody = document.getElementById('slotsCustomCombinationsBody');
            const index = tbody.children.length;
            const row = document.createElement('tr');
            row.setAttribute('data-index', index);
            
            let symbolsHtml = '<div class="custom-combination-symbols" style="display: flex; flex-wrap: nowrap; gap: 10px; align-items: center;">';
            for (let i = 0; i < symbols.length; i++) {
                symbolsHtml += '<div style="display: flex; align-items: center; gap: 5px;">';
                symbolsHtml += '<span style="font-size: 14px; color: #666;">#' + (i + 1) + '</span>';
                symbolsHtml += '<input type="text" class="custom-combo-emoji" data-position="' + i + '" value="' + (symbols[i] || '') + '" maxlength="2" style="width: 60px; padding: 5px; font-size: 18px; text-align: center;" placeholder="Any">';
                symbolsHtml += '</div>';
            }
            symbolsHtml += '</div>';
            
            row.innerHTML = `
                <td style="white-space: nowrap;">
                    ${symbolsHtml}
                </td>
                <td>
                    <input type="number" class="custom-combo-multiplier" value="${multiplier}" min="0" step="0.1" style="width: 100px; padding: 8px;" required>
                </td>
                <td>
                    <button type="button" class="btn btn-secondary" onclick="removeCustomCombination(this)" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                </td>
            `;
            tbody.appendChild(row);
            updateCustomCombinationsIndices();
        }
        
        function updateSlotsSymbolsIndices() {
            const tbody = document.getElementById('slotsSymbolsBody');
            Array.from(tbody.children).forEach((row, index) => {
                row.setAttribute('data-index', index);
            });
        }
        
        // When a symbol emoji changes, update suggested combination if it exists
        $(document).on('blur', '.slots-emoji-input', function() {
            const $row = $(this).closest('tr');
            const emoji = $(this).val().trim();
            const multiplier = parseFloat($row.find('.slots-multiplier-input').val()) || 0;
            
            if (emoji) {
                // Try to find and update the corresponding all-of-a-kind combination
                const numReels = parseInt($('#slots_num_reels').val()) || 3;
                let updated = false;
                
                $('#slotsCustomCombinationsBody tr').each(function() {
                    const $comboRow = $(this);
                    const comboSymbols = [];
                    $comboRow.find('.custom-combo-emoji').each(function() {
                        comboSymbols.push($(this).val().trim());
                    });
                    
                    // Check if this is an all-of-a-kind combination (all symbols the same)
                    if (comboSymbols.length === numReels && 
                        comboSymbols.length > 0 && 
                        comboSymbols.every(s => s === comboSymbols[0] && s !== '')) {
                        // Update it to the new emoji
                        $comboRow.find('.custom-combo-emoji').val(emoji);
                        $comboRow.find('.custom-combo-multiplier').val(multiplier);
                        updated = true;
                        return false; // break
                    }
                });
                
                // If not found, suggest a combination for this symbol
                if (!updated && multiplier > 0) {
                    suggestCombinationFromSymbol(emoji, multiplier);
                }
            }
        });
        
        // When multiplier changes, update corresponding combination
        $(document).on('blur', '.slots-multiplier-input', function() {
            const $row = $(this).closest('tr');
            const emoji = $row.find('.slots-emoji-input').val().trim();
            const multiplier = parseFloat($(this).val()) || 0;
            
            if (emoji) {
                const numReels = parseInt($('#slots_num_reels').val()) || 3;
                $('#slotsCustomCombinationsBody tr').each(function() {
                    const $comboRow = $(this);
                    const comboSymbols = [];
                    $comboRow.find('.custom-combo-emoji').each(function() {
                        comboSymbols.push($(this).val().trim());
                    });
                    
                    if (comboSymbols.length === numReels && 
                        comboSymbols.length > 0 && 
                        comboSymbols.every(s => s === comboSymbols[0] && s === emoji && s !== '')) {
                        $comboRow.find('.custom-combo-multiplier').val(multiplier);
                        return false; // break
                    }
                });
            }
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
                
                // Serialize custom combinations (ordered array, empty allowed)
                const customCombinations = [];
                const numReels = parseInt($('#slots_num_reels').val()) || 3;
                $('#slotsCustomCombinationsBody tr').each(function() {
                    const symbols = [];
                    // Get symbols in order (one per reel, empty strings allowed)
                    $(this).find('.custom-combo-emoji').each(function() {
                        const emoji = $(this).val().trim();
                        symbols.push(emoji || ''); // Allow empty strings
                    });
                    const multiplier = parseFloat($(this).find('.custom-combo-multiplier').val()) || 0;
                    // Only add if we have the right number of symbols and at least one is non-empty
                    if (symbols.length === numReels && symbols.some(s => s.trim() !== '')) {
                        customCombinations.push({symbols: symbols, multiplier: multiplier});
                    }
                });
                $('#slots_custom_combinations_json').val(JSON.stringify(customCombinations));
                
                // Serialize N-of-a-kind rules
                const nOfKindRules = [];
                $('#slotsNOfKindBody tr').each(function() {
                    const count = parseInt($(this).find('.n-of-kind-count').val()) || 2;
                    let symbol = $(this).find('.n-of-kind-symbol').val() || 'any';
                    symbol = symbol.trim();
                    if (symbol === '' || symbol.toLowerCase() === 'any') {
                        symbol = 'any';
                    }
                    const multiplier = parseFloat($(this).find('.n-of-kind-multiplier').val()) || 0;
                    if (count >= 1 && count <= 10 && multiplier >= 0) {
                        nOfKindRules.push({count: count, symbol: symbol, multiplier: multiplier});
                    }
                });
                $('#slots_n_of_kind_rules_json').val(JSON.stringify(nOfKindRules));
            }
        });
        
        // Custom combinations management (ordered array)
        function addCustomCombination() {
            const tbody = document.getElementById('slotsCustomCombinationsBody');
            const index = tbody.children.length;
            const numReels = parseInt($('#slots_num_reels').val()) || 3;
            const row = document.createElement('tr');
            row.setAttribute('data-index', index);
            
            let symbolsHtml = '<div class="custom-combination-symbols" style="display: flex; flex-wrap: nowrap; gap: 10px; align-items: center;">';
            for (let i = 0; i < numReels; i++) {
                symbolsHtml += '<div style="display: flex; align-items: center; gap: 5px;">';
                symbolsHtml += '<span style="font-size: 14px; color: #666;">#' + (i + 1) + '</span>';
                symbolsHtml += '<input type="text" class="custom-combo-emoji" data-position="' + i + '" value="' + (i === 0 ? '🔥' : i === 1 ? '🔥' : '❤️') + '" maxlength="2" style="width: 60px; padding: 5px; font-size: 18px; text-align: center;" placeholder="Any">';
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
        
        // N-of-a-kind rules management
        function addNOfKindRule() {
            const tbody = document.getElementById('slotsNOfKindBody');
            const index = tbody.children.length;
            const row = document.createElement('tr');
            row.setAttribute('data-index', index);
            
            // Get available symbols from the symbols table
            let symbolOptions = '<option value="any" selected>any</option>';
            $('#slotsSymbolsBody tr').each(function() {
                const emoji = $(this).find('.slots-emoji-input').val().trim();
                if (emoji) {
                    symbolOptions += '<option value="' + emoji + '">' + emoji + '</option>';
                }
            });
            
            row.innerHTML = `
                <td>
                    <input type="number" class="n-of-kind-count" value="2" min="1" max="10" style="width: 80px; padding: 8px;" required>
                </td>
                <td>
                    <select class="n-of-kind-symbol" style="width: 120px; padding: 8px; font-size: 16px;">
                        ${symbolOptions}
                    </select>
                </td>
                <td>
                    <input type="number" class="n-of-kind-multiplier" value="1" min="0" step="0.1" style="width: 100px; padding: 8px;" required>
                </td>
                <td>
                    <button type="button" class="btn btn-secondary" onclick="removeNOfKindRule(this)" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                </td>
            `;
            tbody.appendChild(row);
            updateNOfKindIndices();
        }
        
        // Update symbol dropdowns in N-of-a-kind rules when symbols are added/removed
        function updateNOfKindSymbolDropdowns() {
            // Get current symbols
            const availableSymbols = [];
            $('#slotsSymbolsBody tr').each(function() {
                const emoji = $(this).find('.slots-emoji-input').val().trim();
                if (emoji) {
                    availableSymbols.push(emoji);
                }
            });
            
            // Update all N-of-a-kind rule dropdowns
            $('#slotsNOfKindBody tr').each(function() {
                const $select = $(this).find('.n-of-kind-symbol');
                const currentValue = $select.val();
                
                // Rebuild options
                let options = '<option value="any">any</option>';
                availableSymbols.forEach(function(emoji) {
                    options += '<option value="' + emoji + '">' + emoji + '</option>';
                });
                
                $select.html(options);
                
                // Try to restore the previous value if it still exists
                if (currentValue && (currentValue === 'any' || availableSymbols.indexOf(currentValue) !== -1)) {
                    $select.val(currentValue);
                } else {
                    // If the symbol was removed, default to "any"
                    $select.val('any');
                }
            });
        }
        
        function removeNOfKindRule(button) {
            const row = button.closest('tr');
            row.remove();
            updateNOfKindIndices();
        }
        
        function updateNOfKindIndices() {
            const tbody = document.getElementById('slotsNOfKindBody');
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
                $symbolsContainer.css({'flex-wrap': 'nowrap'});
                for (let i = 0; i < numReels; i++) {
                    const value = i < currentValues.length ? currentValues[i] : '';
                    $symbolsContainer.append(
                        $('<div style="display: flex; align-items: center; gap: 5px;">').html(
                            '<span style="font-size: 14px; color: #666;">#' + (i + 1) + '</span>' +
                            '<input type="text" class="custom-combo-emoji" data-position="' + i + '" value="' + value + '" maxlength="2" style="width: 60px; padding: 5px; font-size: 18px; text-align: center;" placeholder="Any">'
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
            
            // Auto-update rounds monitor if on rounds tab
            <?php if ($currentTab === 'rounds'): ?>
            let roundsPollInterval = null;
            const rouletteMode = '<?php echo $rouletteMode; ?>';
            const crashMode = '<?php echo $crashMode; ?>';
            
            function updateRoundsMonitor() {
                // Only poll if in central mode
                if (rouletteMode === 'central') {
                    // Update roulette round
                    $.get('../api/api.php?action=getRouletteRoundAdmin', function(data) {
                        if (data.success) {
                            if (data.round) {
                                const round = data.round;
                                let html = '';
                                
                                if (round.status === 'betting') {
                                    const timeLeft = round.time_until_betting_ends || 0;
                                    
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Betting</strong></p>';
                                    if (round.predicted_result !== undefined) {
                                        html += '<p style="color: #28a745; font-weight: bold; margin-top: 10px;">';
                                        html += '🔮 Predicted Result: <span style="font-size: 1.2em;">' + round.predicted_result + '</span></p>';
                                    }
                                    html += '<p style="margin-top: 10px; font-size: 1.1em; color: #667eea;">';
                                    html += 'Next spin in: <strong>' + Math.ceil(timeLeft) + 's</strong></p>';
                                } else if (round.status === 'spinning') {
                                    const timeLeft = round.time_until_finish || 0;
                                    
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Spinning</strong></p>';
                                    if (round.predicted_result !== undefined) {
                                        html += '<p style="color: #ffc107; font-weight: bold; margin-top: 10px;">';
                                        html += '🔮 Predicted Result: <span style="font-size: 1.2em;">' + round.predicted_result + '</span></p>';
                                    }
                                    html += '<p style="margin-top: 10px; font-size: 1.1em; color: #ffc107;">';
                                    html += 'Spinning... Result in: <strong>' + Math.ceil(timeLeft) + 's</strong></p>';
                                } else if (round.status === 'finished' && round.result_number !== null) {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Finished</strong></p>';
                                    html += '<p style="color: #667eea; font-weight: bold; margin-top: 10px;">';
                                    html += 'Result: <span style="font-size: 1.2em;">' + round.result_number + '</span></p>';
                                } else {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>' + round.status.charAt(0).toUpperCase() + round.status.slice(1) + '</strong></p>';
                                }
                                
                                html += '<p style="font-size: 0.9em; margin-top: 10px;" class="rounds-seed-hash">';
                                html += 'Server Seed Hash: <code style="font-size: 0.8em;">' + (round.server_seed_hash ? round.server_seed_hash.substring(0, 16) : '') + '...</code></p>';
                                
                                $('#rouletteRoundInfo').html(html);
                            } else {
                                $('#rouletteRoundInfo').html('<p>No active round - worker may not be running</p>');
                            }
                        }
                    }, 'json').fail(function() {
                        console.error('Failed to update roulette round');
                    });
                } else {
                    $('#rouletteRoundInfo').html('<p style="color: #999;">Local mode - no synchronized rounds</p>');
                }
                
                // Only poll if in central mode
                if (crashMode === 'central') {
                    // Update crash round
                    $.get('../api/api.php?action=getCrashRoundAdmin', function(data) {
                        if (data.success) {
                            if (data.round) {
                                const round = data.round;
                                let html = '';
                                
                                if (round.status === 'betting') {
                                    const timeLeft = round.time_until_betting_ends || 0;
                                    
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Betting</strong></p>';
                                    if (round.predicted_crash_point !== undefined) {
                                        html += '<p style="color: #28a745; font-weight: bold; margin-top: 10px;">';
                                        html += '🔮 Predicted Crash Point: <span style="font-size: 1.2em;">' + parseFloat(round.predicted_crash_point).toFixed(2) + 'x</span></p>';
                                    }
                                    html += '<p style="margin-top: 10px; font-size: 1.1em; color: #667eea;">';
                                    html += 'Next round in: <strong>' + Math.ceil(timeLeft) + 's</strong></p>';
                                } else if (round.status === 'running' && round.crash_point) {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Running</strong></p>';
                                    html += '<p style="color: #ffc107; font-weight: bold; margin-top: 10px;">';
                                    html += 'Crash Point: <span style="font-size: 1.2em;">' + parseFloat(round.crash_point).toFixed(2) + 'x</span></p>';
                                    html += '<p style="margin-top: 10px; font-size: 1.1em; color: #ffc107;">Round in progress...</p>';
                                } else if (round.status === 'finished' && round.crash_point) {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>Finished</strong></p>';
                                    html += '<p style="color: #667eea; font-weight: bold; margin-top: 10px;">';
                                    html += 'Crashed at: <span style="font-size: 1.2em;">' + parseFloat(round.crash_point).toFixed(2) + 'x</span></p>';
                                } else {
                                    html = '<p><strong>Round #' + round.round_number + '</strong></p>';
                                    html += '<p>Status: <strong>' + round.status.charAt(0).toUpperCase() + round.status.slice(1) + '</strong></p>';
                                }
                                
                                html += '<p style="font-size: 0.9em; margin-top: 10px;" class="rounds-seed-hash">';
                                html += 'Server Seed Hash: <code style="font-size: 0.8em;">' + (round.server_seed_hash ? round.server_seed_hash.substring(0, 16) : '') + '...</code></p>';
                                
                                $('#crashRoundInfo').html(html);
                            } else {
                                $('#crashRoundInfo').html('<p>No active round - worker may not be running</p>');
                            }
                        }
                    }, 'json').fail(function() {
                        console.error('Failed to update crash round');
                    });
                } else {
                    $('#crashRoundInfo').html('<p style="color: #999;">Local mode - no synchronized rounds</p>');
                }
                
                // Update history (always, regardless of mode)
                $.get('../api/api.php?action=getRouletteHistory&limit=10', function(data) {
                    if (data.success && data.history) {
                        let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Result</th><th>Finished</th></tr></thead><tbody>';
                        if (data.history.length === 0) {
                            html += '<tr><td colspan="3" style="text-align: center; color: #999;">No history yet</td></tr>';
                        } else {
                            data.history.forEach(function(round) {
                                const finishedTime = round.finished_at ? new Date(round.finished_at).toLocaleTimeString() : '-';
                                html += '<tr><td>#' + round.round_number + '</td><td><strong>' + (round.result_number !== null ? round.result_number : '-') + '</strong></td><td>' + finishedTime + '</td></tr>';
                            });
                        }
                        html += '</tbody></table>';
                        $('#rouletteHistoryTable').html(html);
                    }
                }, 'json');
                
                $.get('../api/api.php?action=getCrashHistory&limit=10', function(data) {
                    if (data.success && data.history) {
                        let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Crash Point</th><th>Finished</th></tr></thead><tbody>';
                        if (data.history.length === 0) {
                            html += '<tr><td colspan="3" style="text-align: center; color: #999;">No history yet</td></tr>';
                        } else {
                            data.history.forEach(function(round) {
                                const finishedTime = round.finished_at ? new Date(round.finished_at).toLocaleTimeString() : '-';
                                html += '<tr><td>#' + round.round_number + '</td><td><strong>' + (round.crash_point ? parseFloat(round.crash_point).toFixed(2) + 'x' : '-') + '</strong></td><td>' + finishedTime + '</td></tr>';
                            });
                        }
                        html += '</tbody></table>';
                        $('#crashHistoryTable').html(html);
                    }
                }, 'json');
                
                // Update upcoming predictions
                if (rouletteMode === 'central') {
                    $.get('../api/api.php?action=getUpcomingPredictions&game=roulette&count=10', function(data) {
                        if (data.success && data.predictions) {
                            if (data.predictions.length === 0) {
                                $('#rouletteUpcomingTable').html('<p style="color: #999; text-align: center;">No predictions available</p>');
                            } else {
                                let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Predicted Result</th></tr></thead><tbody>';
                                data.predictions.forEach(function(pred) {
                                    html += '<tr><td>#' + pred.round_number + '</td><td><strong style="color: #28a745;">' + pred.predicted_result + '</strong></td></tr>';
                                });
                                html += '</tbody></table>';
                                $('#rouletteUpcomingTable').html(html);
                            }
                        }
                    }, 'json');
                }
                
                if (crashMode === 'central') {
                    $.get('../api/api.php?action=getUpcomingPredictions&game=crash&count=10', function(data) {
                        if (data.success && data.predictions) {
                            if (data.predictions.length === 0) {
                                $('#crashUpcomingTable').html('<p style="color: #999; text-align: center;">No predictions available</p>');
                            } else {
                                let html = '<table class="admin-table" style="font-size: 0.9em; width: 100%;"><thead><tr><th>Round</th><th>Predicted Crash</th></tr></thead><tbody>';
                                data.predictions.forEach(function(pred) {
                                    const multValue = parseFloat(pred.predicted_crash_point);
                                    let color = '#dc3545'; // Red for low
                                    if (multValue >= 5) color = '#ffc107'; // Yellow for medium
                                    if (multValue >= 10) color = '#28a745'; // Green for high
                                    html += '<tr><td>#' + pred.round_number + '</td><td><strong style="color: ' + color + ';">' + parseFloat(pred.predicted_crash_point).toFixed(2) + 'x</strong></td></tr>';
                                });
                                html += '</tbody></table>';
                                $('#crashUpcomingTable').html(html);
                            }
                        }
                    }, 'json');
                }
            }
            
            // Start polling
            updateRoundsMonitor();
            roundsPollInterval = setInterval(updateRoundsMonitor, 2000); // Poll every 2 seconds
            
            // Cleanup on page unload
            $(window).on('beforeunload', function() {
                if (roundsPollInterval) {
                    clearInterval(roundsPollInterval);
                }
            });
            <?php endif; ?>
        });
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
