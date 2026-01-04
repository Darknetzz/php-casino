<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="profile-container">
            <h1>👤 Your Profile</h1>
            
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
            
            <div class="win-rates-section">
                <h2>📊 Win Rates</h2>
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
                        <h4>🎲 Roulette</h4>
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
            
            <div class="refill-section">
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
    </div>
    
    <script>
        function setRefillAmount(amount) {
            $('#refill_amount').val(amount);
        }
        
        $(document).ready(function() {
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
                        $('#balance').text(parseFloat(data.balance).toFixed(2));
                        $('.balance-large').text('$' + parseFloat(data.balance).toFixed(2));
                        $('#refill_amount').val('');
                        setTimeout(function() {
                            $('#refillMessage').html('');
                        }, 3000);
                    } else {
                        $('#refillMessage').html('<div class="alert alert-error">' + (data.message || 'Failed to refill balance') + '</div>');
                    }
                }, 'json');
            });
            
            // Update balance periodically (common.js handles this, but we also update balance-large)
            setInterval(function() {
                $.get(getApiPath('getBalance'), function(data) {
                    if (data.success) {
                        $('#balance').text(parseFloat(data.balance).toFixed(2));
                        $('.balance-large').text('$' + parseFloat(data.balance).toFixed(2));
                    }
                }, 'json');
            }, 5000);
        });
        
        // Load win rates
        $(document).ready(function() {
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
        });
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
