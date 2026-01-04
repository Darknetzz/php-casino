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
                <a href="logout.php" class="btn btn-secondary">Logout</a>
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
                <h2>💰 Refill Balance</h2>
                <div id="refillMessage"></div>
                
                <form id="refillForm">
                    <div class="form-group">
                        <label for="refill_amount">Refill Amount ($)</label>
                        <input type="number" id="refill_amount" name="refill_amount" min="1" max="10000" step="0.01" required placeholder="Enter amount">
                        <small>Maximum refill: $10,000</small>
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
        function setRefillAmount(amount) {
            $('#refill_amount').val(amount);
        }
        
        $(document).ready(function() {
            // Handle refill form submission
            $('#refillForm').on('submit', function(e) {
                e.preventDefault();
                
                const amount = parseFloat($('#refill_amount').val());
                
                if (amount <= 0 || amount > 10000) {
                    $('#refillMessage').html('<div class="alert alert-error">Please enter a valid amount between $1 and $10,000</div>');
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
