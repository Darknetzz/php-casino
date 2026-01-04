<?php
// Determine the base path based on current directory
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], '/games/') !== false) {
    $basePath = '../';
} elseif (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    $basePath = '../';
} else {
    $basePath = '';
}
?>
<nav class="navbar">
    <div class="nav-container">
        <h2><a href="<?php echo $basePath; ?>index.php" style="text-decoration: none; color: inherit;">🎰 Casino</a></h2>
        <div class="nav-center">
            <div class="games-menu">
                <button class="games-menu-btn" id="gamesMenuBtn">
                    <span>🎮</span>
                    <span>Games</span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="games-dropdown" id="gamesDropdown">
                    <a href="<?php echo $basePath; ?>games/slots.php" class="dropdown-item">
                        <span>🎰</span> Slots
                    </a>
                    <a href="<?php echo $basePath; ?>games/blackjack.php" class="dropdown-item">
                        <span>🃏</span> Blackjack
                    </a>
                    <a href="<?php echo $basePath; ?>games/roulette.php" class="dropdown-item">
                        <span>🛞</span> Roulette
                    </a>
                    <a href="<?php echo $basePath; ?>games/plinko.php" class="dropdown-item">
                        <span>⚪</span> Plinko
                    </a>
                    <a href="<?php echo $basePath; ?>games/dice.php" class="dropdown-item">
                        <span>🎲</span> Dice Roll
                    </a>
                </div>
            </div>
        </div>
        <div class="nav-right">
            <button id="darkModeToggle" class="dark-mode-toggle" title="Toggle Dark Mode">
                <span id="darkModeIcon">🌙</span>
            </button>
            <span class="balance">Balance: $<span id="balance"><?php echo number_format($user['balance'], 2); ?></span></span>
            <?php if (strpos($_SERVER['PHP_SELF'], '/games/') !== false || strpos($_SERVER['PHP_SELF'], '/pages/') !== false): ?>
            <a href="<?php echo $basePath; ?>index.php" class="btn btn-secondary">Home</a>
            <?php endif; ?>
            <div class="user-menu">
                <button class="user-menu-btn" id="userMenuBtn">
                    <span class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                    <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <a href="<?php echo $basePath; ?>pages/profile.php" class="dropdown-item">
                        <span>👤</span> Profile
                    </a>
                    <?php if (isAdmin()): ?>
                    <a href="<?php echo $basePath; ?>pages/admin.php" class="dropdown-item">
                        <span>⚙️</span> Admin Panel
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo $basePath; ?>pages/logout.php" class="dropdown-item">
                        <span>🚪</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
