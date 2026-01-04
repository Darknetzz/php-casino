<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();

// Get current tab from URL
$currentTab = $_GET['tab'] ?? 'overview';

$pageTitle = 'Profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="profile-container section">
            <h1>👤 Your Profile</h1>
            
            <!-- Profile Navigation Tabs -->
            <div class="admin-nav-tabs">
                <a href="profile.php?tab=overview" class="admin-tab <?php echo $currentTab === 'overview' ? 'active' : ''; ?>">
                    <span>👤</span> Overview
                </a>
                <a href="profile.php?tab=statistics" class="admin-tab <?php echo $currentTab === 'statistics' ? 'active' : ''; ?>">
                    <span>📊</span> Statistics
                </a>
                <a href="profile.php?tab=settings" class="admin-tab <?php echo $currentTab === 'settings' ? 'active' : ''; ?>">
                    <span>⚙️</span> Settings
                </a>
            </div>
            
            <!-- Overview Section -->
            <?php if ($currentTab === 'overview'): ?>
            <div class="admin-section section">
                <h2>👤 Profile Information</h2>
                <div class="profile-info">
                    <div class="info-item">
                        <label>Username:</label>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Current Balance:</label>
                        <span class="balance-large">$<?php echo number_format($user['balance'], 2); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Member Since:</label>
                        <span><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Section -->
            <?php if ($currentTab === 'statistics'): ?>
            <div class="admin-section section">
                <div class="win-loss-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0;">💰 Total Win/Loss</h2>
                        <button id="resetWinLossBtn" class="btn btn-secondary btn-sm">Reset Win/Loss</button>
                    </div>
                    <div id="winLossDisplay" class="win-loss-info">
                        <div class="win-loss-item">
                            <label>Total Bets:</label>
                            <span id="totalBets" class="win-loss-value">$0.00</span>
                        </div>
                        <div class="win-loss-item">
                            <label>Total Wins:</label>
                            <span id="totalWins" class="win-loss-value">$0.00</span>
                        </div>
                        <div class="win-loss-item">
                            <label>Net Win/Loss:</label>
                            <span id="netWinLoss" class="win-loss-value">$0.00</span>
                        </div>
                    </div>
                </div>
                
                <div class="win-rates-section" style="margin-top: 40px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0;">📊 Win Rates</h2>
                        <button id="resetWinRatesBtn" class="btn btn-secondary btn-sm">Reset Win Rates</button>
                    </div>
                    <div id="winRatesDisplay" class="win-rates-grid">
                        <div class="win-rate-card">
                            <h4>Overall</h4>
                            <div class="win-rate-value" id="overallRate">-</div>
                            <div class="win-rate-games" id="overallGames">- games</div>
                        </div>
                        <div class="win-rate-card">
                            <h4>🎰 Slots</h4>
                            <div class="win-rate-value" id="slotsRate">-</div>
                            <div class="win-rate-games" id="slotsGames">- games</div>
                        </div>
                        <div class="win-rate-card">
                            <h4>🃏 Blackjack</h4>
                            <div class="win-rate-value" id="blackjackRate">-</div>
                            <div class="win-rate-games" id="blackjackGames">- games</div>
                        </div>
                        <div class="win-rate-card">
                            <h4>🛞 Roulette</h4>
                            <div class="win-rate-value" id="rouletteRate">-</div>
                            <div class="win-rate-games" id="rouletteGames">- games</div>
                        </div>
                        <div class="win-rate-card">
                            <h4>⚪ Plinko</h4>
                            <div class="win-rate-value" id="plinkoRate">-</div>
                            <div class="win-rate-games" id="plinkoGames">- games</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Settings Section -->
            <?php if ($currentTab === 'settings'): ?>
            <div class="admin-section section">
                <div class="refill-section">
                    <h2>⚙️ Default Bet Amount</h2>
                    <div id="defaultBetMessage"></div>
                    
                    <form id="defaultBetForm">
                        <div class="form-group">
                            <label for="default_bet">Default Bet ($)</label>
                            <input type="number" id="default_bet" name="default_bet" min="1" step="0.01" 
                                   value="<?php echo htmlspecialchars($user['default_bet'] ?? ''); ?>" 
                                   placeholder="<?php echo htmlspecialchars(getSetting('default_bet', 10)); ?>">
                            <small>Leave empty to use global default (<?php echo htmlspecialchars(getSetting('default_bet', 10)); ?>). This will be used as the default bet amount in all games.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Default Bet</button>
                    </form>
                </div>
                
                <div class="refill-section" style="margin-top: 40px;">
                    <h2>💰 Refill Balance</h2>
                    <div id="refillMessage"></div>
                    
                    <form id="refillForm">
                        <div class="form-group">
                            <label for="refill_amount">Refill Amount ($)</label>
                            <input type="number" id="refill_amount" name="refill_amount" min="1" step="0.01" required placeholder="Enter amount">
                            <small>Maximum refill: $<span id="maxDeposit"><?php echo number_format(getSetting('max_deposit', 10000), 2); ?></span></small>
                        </div>
                        <button type="submit" class="btn btn-primary">Refill Balance</button>
                    </form>
                    
                    <div class="quick-refill">
                        <p>Quick Refill:</p>
                        <div class="quick-refill-buttons">
                            <button type="button" class="btn btn-secondary" onclick="setRefillAmount(100)">$100</button>
                            <button type="button" class="btn btn-secondary" onclick="setRefillAmount(500)">$500</button>
                            <button type="button" class="btn btn-secondary" onclick="setRefillAmount(1000)">$1,000</button>
                            <button type="button" class="btn btn-secondary" onclick="setRefillAmount(5000)">$5,000</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function setRefillAmount(amount) {
            $('#refill_amount').val(amount);
        }
        
        $(document).ready(function() {
            <?php if ($currentTab === 'settings'): ?>
            // Handle default bet form submission
            $('#defaultBetForm').on('submit', function(e) {
                e.preventDefault();
                
                const defaultBet = $('#default_bet').val();
                
                $.post(getApiPath('updateDefaultBet'), {
                    default_bet: defaultBet
                }, function(data) {
                    if (data.success) {
                        $('#defaultBetMessage').html('<div class="alert alert-success">Default bet updated successfully!</div>');
                        setTimeout(function() {
                            $('#defaultBetMessage').html('');
                        }, 3000);
                    } else {
                        $('#defaultBetMessage').html('<div class="alert alert-error">' + (data.message || 'Failed to update default bet') + '</div>');
                    }
                }, 'json');
            });
            
            // Handle refill form submission
            $('#refillForm').on('submit', function(e) {
                e.preventDefault();
                
                const amount = parseFloat($('#refill_amount').val());
                const maxDeposit = parseFloat($('#maxDeposit').text().replace(/,/g, ''));
                
                if (amount <= 0 || amount > maxDeposit) {
                    $('#refillMessage').html('<div class="alert alert-error">Please enter a valid amount between $1 and $' + maxDeposit.toFixed(2) + '</div>');
                    return;
                }
                
                $.post(getApiPath('updateBalance'), {
                    amount: amount,
                    type: 'deposit',
                    description: 'Balance refill'
                }, function(data) {
                    if (data.success) {
                        $('#refillMessage').html('<div class="alert alert-success">Balance refilled successfully! Added $' + amount.toFixed(2) + '</div>');
                        $('#balance').text(formatNumber(data.balance));
                        $('.balance-large').text('$' + formatNumber(data.balance));
                        $('#refill_amount').val('');
                        setTimeout(function() {
                            $('#refillMessage').html('');
                        }, 3000);
                    } else {
                        $('#refillMessage').html('<div class="alert alert-error">' + (data.message || 'Failed to refill balance') + '</div>');
                    }
                }, 'json');
            });
            <?php endif; ?>
            
            // Update balance periodically (common.js handles this, but we also update balance-large)
            // Only update balance-large if it exists (on overview tab)
            setInterval(function() {
                $.get(getApiPath('getBalance'), function(data) {
                    if (data.success) {
                        $('#balance').text(formatNumber(data.balance));
                        if ($('.balance-large').length) {
                            $('.balance-large').text('$' + formatNumber(data.balance));
                        }
                    }
                }, 'json');
            }, 5000);
        });
        
        // Function to load total win/loss
        function loadWinLoss() {
            $.get(getApiPath('getTotalWinLoss'), function(data) {
                if (data.success && data.winLoss) {
                    const winLoss = data.winLoss;
                    $('#totalBets').text('$' + winLoss.totalBets.toFixed(2));
                    $('#totalWins').text('$' + winLoss.totalWins.toFixed(2));
                    
                    const netAmount = winLoss.netWinLoss;
                    const $netElement = $('#netWinLoss');
                    $netElement.text('$' + Math.abs(netAmount).toFixed(2));
                    
                    // Color code the net win/loss
                    if (netAmount > 0) {
                        $netElement.css('color', '#28a745').text('+$' + netAmount.toFixed(2));
                    } else if (netAmount < 0) {
                        $netElement.css('color', '#dc3545').text('-$' + Math.abs(netAmount).toFixed(2));
                    } else {
                        $netElement.css('color', '#666');
                    }
                } else {
                    console.error('Failed to load win/loss:', data);
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Error loading win/loss:', status, error);
            });
        }
        
        // Function to load win rates
        function loadWinRates() {
            $.get(getApiPath('getWinRates'), function(data) {
                if (data.success && data.winRates) {
                    const rates = data.winRates;
                    
                    // Overall
                    if (rates.overall) {
                        $('#overallRate').text(rates.overall.rate + '%');
                        $('#overallGames').text(rates.overall.total + ' games (' + rates.overall.wins + ' wins)');
                    } else {
                        $('#overallRate').text('0%');
                        $('#overallGames').text('0 games (0 wins)');
                    }
                    
                    // Slots
                    if (rates.slots) {
                        $('#slotsRate').text(rates.slots.rate + '%');
                        $('#slotsGames').text(rates.slots.total + ' games (' + rates.slots.wins + ' wins)');
                    } else {
                        $('#slotsRate').text('0%');
                        $('#slotsGames').text('0 games (0 wins)');
                    }
                    
                    // Blackjack
                    if (rates.blackjack) {
                        $('#blackjackRate').text(rates.blackjack.rate + '%');
                        $('#blackjackGames').text(rates.blackjack.total + ' games (' + rates.blackjack.wins + ' wins)');
                    } else {
                        $('#blackjackRate').text('0%');
                        $('#blackjackGames').text('0 games (0 wins)');
                    }
                    
                    // Roulette
                    if (rates.roulette) {
                        $('#rouletteRate').text(rates.roulette.rate + '%');
                        $('#rouletteGames').text(rates.roulette.total + ' games (' + rates.roulette.wins + ' wins)');
                    } else {
                        $('#rouletteRate').text('0%');
                        $('#rouletteGames').text('0 games (0 wins)');
                    }
                    
                    // Plinko
                    if (rates.plinko) {
                        $('#plinkoRate').text(rates.plinko.rate + '%');
                        $('#plinkoGames').text(rates.plinko.total + ' games (' + rates.plinko.wins + ' wins)');
                    } else {
                        $('#plinkoRate').text('0%');
                        $('#plinkoGames').text('0 games (0 wins)');
                    }
                } else {
                    console.error('Failed to load stats:', data);
                    // Set all to 0
                    $('.win-rate-value').text('0%');
                    $('.win-rate-games').text('0 games (0 wins)');
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Error loading stats:', status, error);
                // Set all to 0
                $('.win-rate-value').text('0%');
                $('.win-rate-games').text('0 games (0 wins)');
            });
        }
        
        // Load statistics only when on statistics tab
        $(document).ready(function() {
            <?php if ($currentTab === 'statistics'): ?>
            loadWinLoss();
            loadWinRates();
            
            // Reset win/loss button
            $('#resetWinLossBtn').on('click', function() {
                if (!confirm('Are you sure you want to reset your win/loss statistics? This will delete all bet and win transaction records. This action cannot be undone.')) {
                    return;
                }
                
                $.post(getApiPath('resetStats'), {}, function(data) {
                    if (data.success) {
                        alert('Win/Loss statistics reset successfully!');
                        loadWinLoss();
                    } else {
                        alert('Failed to reset statistics: ' + (data.message || 'Unknown error'));
                    }
                }, 'json');
            });
            
            // Reset win rates button (same as reset win/loss since they use the same data)
            $('#resetWinRatesBtn').on('click', function() {
                if (!confirm('Are you sure you want to reset your win rate statistics? This will delete all bet and win transaction records. This action cannot be undone.')) {
                    return;
                }
                
                $.post(getApiPath('resetStats'), {}, function(data) {
                    if (data.success) {
                        alert('Win rate statistics reset successfully!');
                        loadWinRates();
                        loadWinLoss();
                    } else {
                        alert('Failed to reset statistics: ' + (data.message || 'Unknown error'));
                    }
                }, 'json');
            });
            <?php endif; ?>
        });
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
