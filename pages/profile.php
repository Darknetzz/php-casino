<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Casino</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h2>🎰 Casino</h2>
            <div class="nav-right">
                <span class="balance">Balance: $<span id="balance"><?php echo number_format($user['balance'], 2); ?></span></span>
                <a href="../index.php" class="btn btn-secondary">Home</a>
                <div class="user-menu">
                    <button class="user-menu-btn" id="userMenuBtn">
                        <span class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php" class="dropdown-item">
                            <span>👤</span> Profile
                        </a>
                        <?php if (isAdmin()): ?>
                        <a href="admin.php" class="dropdown-item">
                            <span>⚙️</span> Admin Panel
                        </a>
                        <?php endif; ?>
                        <a href="logout.php" class="dropdown-item">
                            <span>🚪</span> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
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
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // User menu dropdown toggle
            $('#userMenuBtn').on('click', function(e) {
                e.stopPropagation();
                $('.user-menu').toggleClass('active');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.user-menu').length) {
                    $('.user-menu').removeClass('active');
                }
            });
        });
        
        function setRefillAmount(amount) {
            $('#refill_amount').val(amount);
        }
        
        $(document).ready(function() {
            // Handle default bet form submission
            $('#defaultBetForm').on('submit', function(e) {
                e.preventDefault();
                
                const defaultBet = $('#default_bet').val();
                
                $.post('../api/api.php?action=updateDefaultBet', {
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
                
                $.post('../api/api.php?action=updateBalance', {
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
            
            // Update balance periodically
            setInterval(function() {
                $.get('../api/api.php?action=getBalance', function(data) {
                    if (data.success) {
                        $('#balance').text(parseFloat(data.balance).toFixed(2));
                        $('.balance-large').text('$' + parseFloat(data.balance).toFixed(2));
                    }
                }, 'json');
            }, 5000);
        });
    </script>
</body>
</html>
