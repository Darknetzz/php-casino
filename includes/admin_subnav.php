<?php
/**
 * Admin Subnav Component
 * 
 * Displays the game selector subnav for the multipliers tab
 * 
 * @param string $currentGame The current active game (slots, plinko, dice, roulette, crash, blackjack)
 * @param string $baseUrl The base URL for the links (default: admin.php?tab=multipliers)
 */
function renderAdminSubnav($currentGame = 'slots', $baseUrl = 'admin.php?tab=multipliers') {
    $games = [
        'slots' => ['emoji' => '🎰', 'label' => 'Slots'],
        'plinko' => ['emoji' => '⚪', 'label' => 'Plinko'],
        'dice' => ['emoji' => '🎲', 'label' => 'Dice Roll'],
        'roulette' => ['emoji' => '🛞', 'label' => 'Roulette'],
        'crash' => ['emoji' => '🚀', 'label' => 'Crash'],
        'blackjack' => ['emoji' => '🃏', 'label' => 'Blackjack'],
    ];
    ?>
    <!-- Game Settings Subnav -->
    <div class="admin-subnav-tabs">
        <?php foreach ($games as $gameKey => $game): ?>
            <a href="<?php echo htmlspecialchars($baseUrl . '&game=' . $gameKey); ?>" class="admin-subtab <?php echo $currentGame === $gameKey ? 'active' : ''; ?>">
                <span><?php echo htmlspecialchars($game['emoji']); ?></span> <?php echo htmlspecialchars($game['label']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
?>
