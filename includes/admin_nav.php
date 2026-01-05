<?php
/**
 * Admin Navigation Component
 * 
 * Displays the main admin navigation tabs
 * 
 * @param string $currentTab The current active tab (multipliers, limits, settings, users, rounds, statistics, predictions)
 */
function renderAdminNav($currentTab = '') {
    $tabs = [
        'multipliers' => ['emoji' => '🎰', 'label' => 'Game Settings', 'url' => 'admin.php?tab=multipliers'],
        'limits' => ['emoji' => '🔒', 'label' => 'Limits', 'url' => 'admin.php?tab=limits'],
        'settings' => ['emoji' => '📊', 'label' => 'Casino Settings', 'url' => 'admin.php?tab=settings'],
        'users' => ['emoji' => '👥', 'label' => 'User Management', 'url' => 'admin.php?tab=users'],
        'rounds' => ['emoji' => '🎯', 'label' => 'Game Rounds', 'url' => 'admin.php?tab=rounds'],
        'statistics' => ['emoji' => '📈', 'label' => 'Statistics', 'url' => 'admin.php?tab=statistics'],
        'predictions' => ['emoji' => '🔮', 'label' => 'Predictions & History', 'url' => 'admin_predictions.php'],
    ];
    ?>
    <!-- Admin Navigation Tabs -->
    <div class="admin-nav-tabs">
        <?php foreach ($tabs as $tabKey => $tab): ?>
            <a href="<?php echo htmlspecialchars($tab['url']); ?>" class="admin-tab <?php echo $currentTab === $tabKey ? 'active' : ''; ?>">
                <span><?php echo htmlspecialchars($tab['emoji']); ?></span> <?php echo htmlspecialchars($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
?>
