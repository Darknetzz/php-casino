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
                    <a href="<?php echo $basePath; ?>games/crash.php" class="dropdown-item">
                        <span>🚀</span> Crash
                    </a>
                </div>
            </div>
        </div>
        <div class="nav-right">
            <span class="balance">Balance: $<span id="balance"><?php echo number_format($user['balance'], 2); ?></span></span>
            <div class="notification-menu">
                <button class="notification-btn" id="notificationBtn" title="Notifications">
                    <span class="notification-icon">🔔</span>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div class="notification-dropdown" id="notificationDropdown" style="display: none;">
                    <div class="notification-dropdown-header">
                        <h3>Notifications</h3>
                        <div style="display: flex; gap: 8px;">
                            <a href="<?php echo $basePath; ?>pages/notifications.php" class="notification-view-all" style="font-size: 12px; color: #667eea; text-decoration: none; padding: 4px 8px; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='rgba(102, 126, 234, 0.1)'" onmouseout="this.style.background='transparent'">View All</a>
                            <button class="notification-mark-all" id="markAllReadBtn">Mark all read</button>
                        </div>
                    </div>
                    <div class="notification-dropdown-list" id="notificationList"></div>
                    <div class="notification-dropdown-empty" id="notificationEmpty" style="display: none;">
                        <p>No notifications</p>
                    </div>
                </div>
            </div>
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
                    <button id="darkModeToggle" class="dropdown-item" style="width: 100%; text-align: left; background: none; border: none; cursor: pointer; color: inherit; font-size: inherit; font-family: inherit; margin: 0;" title="Toggle Dark Mode">
                        <span id="darkModeIcon">🌙</span> Dark Mode
                    </button>
                    <a href="<?php echo $basePath; ?>pages/logout.php" class="dropdown-item">
                        <span>🚪</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
