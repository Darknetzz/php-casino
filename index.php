<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Welcome';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>
    
    <div class="container">
        <div class="welcome-section section">
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
                <div class="game-icon">🛞</div>
                <h3>Roulette</h3>
                <p>Bet on your lucky number!</p>
            </div>
            
            <div class="game-card" onclick="window.location.href='games/plinko.php'">
                <div class="game-icon">⚪</div>
                <h3>Plinko</h3>
                <p>Drop the ball and win!</p>
            </div>
            
            <div class="game-card" onclick="window.location.href='games/dice.php'">
                <div class="game-icon">🎲</div>
                <h3>Dice Roll</h3>
                <p>Roll 6 dice and match to win!</p>
            </div>
            
            <div class="game-card" onclick="window.location.href='games/coinflip.php'">
                <div class="game-icon">🪙</div>
                <h3>Coinflip</h3>
                <p>Choose heads or tails and flip!</p>
            </div>
            
            <div class="game-card" onclick="window.location.href='games/crash.php'">
                <div class="game-icon">🚀</div>
                <h3>Crash</h3>
                <p>Cash out before it crashes!</p>
            </div>
        </div>
        
        <div class="transactions-section section">
            <h2>Recent Transactions</h2>
            <div id="transactions">
                <p>Loading...</p>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            // Load transactions
            $.get(getApiPath('getTransactions'), function(data) {
                if (data.success) {
                    let html = '<table class="transactions-table"><thead><tr><th>Type</th><th>Amount</th><th>Description</th><th>Date</th></tr></thead><tbody>';
                    if (data.transactions.length === 0) {
                        html += '<tr><td colspan="4">No transactions yet</td></tr>';
                    } else {
                        data.transactions.forEach(function(t) {
                            const sign = t.type === 'win' || t.type === 'deposit' ? '+' : '-';
                            const color = t.type === 'win' || t.type === 'deposit' ? 'green' : 'red';
                            const amount = Math.abs(parseFloat(t.amount));
                            const formattedAmount = typeof formatNumber === 'function' ? formatNumber(amount) : amount.toFixed(2);
                            html += `<tr>
                                <td>${t.type}</td>
                                <td style="color: ${color}">${sign}$${formattedAmount}</td>
                                <td>${t.description || ''}</td>
                                <td>${new Date(t.created_at).toLocaleString()}</td>
                            </tr>`;
                        });
                    }
                    html += '</tbody></table>';
                    $('#transactions').html(html);
                }
            }, 'json');
        });
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>
