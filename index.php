<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casino - Welcome</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>
            <p class="subtitle">Choose a game to play with your fake money</p>
        </div>
        
        <div class="games-grid">
            <div class="game-card" onclick="window.location.href='games/slots.php'">
                <div class="game-icon">🎰</div>
                <h3>Slots</h3>
                <p>Spin the reels and win big!</p>
            </div>
            
            <div class="game-card" onclick="window.location.href='games/blackjack.php'">
                <div class="game-icon">🃏</div>
                <h3>Blackjack</h3>
                <p>Beat the dealer to 21!</p>
            </div>
            
            <div class="game-card" onclick="window.location.href='games/roulette.php'">
                <div class="game-icon">🎲</div>
                <h3>Roulette</h3>
                <p>Bet on your lucky number!</p>
            </div>
            
            <div class="game-card" onclick="window.location.href='games/plinko.php'">
                <div class="game-icon">⚪</div>
                <h3>Plinko</h3>
                <p>Drop the ball and win!</p>
            </div>
        </div>
        
        <div class="transactions-section">
            <h2>Recent Transactions</h2>
            <div id="transactions">
                <p>Loading...</p>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/navbar.js"></script>
    <script>
        $(document).ready(function() {
            // Load transactions
            $.get('api/api.php?action=getTransactions', function(data) {
                if (data.success) {
                    let html = '<table class="transactions-table"><thead><tr><th>Type</th><th>Amount</th><th>Description</th><th>Date</th></tr></thead><tbody>';
                    if (data.transactions.length === 0) {
                        html += '<tr><td colspan="4">No transactions yet</td></tr>';
                    } else {
                        data.transactions.forEach(function(t) {
                            const sign = t.type === 'win' || t.type === 'deposit' ? '+' : '-';
                            const color = t.type === 'win' || t.type === 'deposit' ? 'green' : 'red';
                            html += `<tr>
                                <td>${t.type}</td>
                                <td style="color: ${color}">${sign}$${Math.abs(t.amount).toFixed(2)}</td>
                                <td>${t.description || ''}</td>
                                <td>${new Date(t.created_at).toLocaleString()}</td>
                            </tr>`;
                        });
                    }
                    html += '</tbody></table>';
                    $('#transactions').html(html);
                }
            }, 'json');
            
            // Update balance periodically
            setInterval(function() {
                $.get('api/api.php?action=getBalance', function(data) {
                    if (data.success) {
                        $('#balance').text(parseFloat(data.balance).toFixed(2));
                    }
                }, 'json');
            }, 5000);
        });
    </script>
</body>
</html>
