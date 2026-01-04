<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Notifications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
    
    <div class="container">
        <div class="notifications-page-container section">
            <h1>🔔 Notifications</h1>
            
            <div class="notifications-page-controls">
                <button class="btn btn-secondary" id="markAllReadBtn">Mark All as Read</button>
                <button class="btn btn-secondary" id="clearAllBtn">Clear All</button>
            </div>
            
            <div class="notifications-page-list" id="notificationsPageList">
                <div class="notifications-loading">
                    <p>Loading notifications...</p>
                </div>
            </div>
            
            <div class="notifications-empty" id="notificationsEmpty" style="display: none;">
                <div class="empty-state">
                    <div class="empty-icon">🔔</div>
                    <h3>No notifications yet</h3>
                    <p>You'll see notifications here when you place bets or when rounds finish.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        /**
         * Load notifications from API
         */
        function loadNotifications(callback) {
            $.get(getApiPath('getNotifications') + '&limit=200', function(data) {
                if (data.success && data.notifications) {
                    // Convert database format to our format
                    const notifications = data.notifications.map(n => ({
                        id: parseInt(n.id),
                        title: n.title,
                        message: n.message,
                        type: n.type,
                        game: n.game,
                        read: n.read === 1 || n.read === '1',
                        timestamp: new Date(n.created_at)
                    }));
                    if (callback) callback(notifications);
                } else {
                    if (callback) callback([]);
                }
            }, 'json').fail(function() {
                console.error('Failed to load notifications');
                if (callback) callback([]);
            });
        }
        
        /**
         * Get game URL
         */
        function getGameUrl(game) {
            if (game === 'roulette') {
                return '../games/roulette.php';
            } else if (game === 'crash') {
                return '../games/crash.php';
            }
            return '#';
        }
        
        /**
         * Format timestamp
         */
        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            
            if (days > 0) {
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            } else if (hours > 0) {
                return hours + 'h ago';
            } else if (minutes > 0) {
                return minutes + 'm ago';
            } else {
                return 'just now';
            }
        }
        
        /**
         * Render notifications
         */
        function renderNotifications() {
            loadNotifications(function(notifications) {
                const list = $('#notificationsPageList');
                const empty = $('#notificationsEmpty');
                
                if (notifications.length === 0) {
                    list.hide();
                    empty.show();
                    return;
                }
                
                list.show();
                empty.hide();
                
                // Sort by timestamp (newest first)
                const sorted = notifications.sort((a, b) => {
                    return new Date(b.timestamp) - new Date(a.timestamp);
                });
            
            let html = '';
            sorted.forEach(function(notif) {
                const readClass = notif.read ? 'notification-read' : 'notification-unread';
                
                let icon = 'ℹ️';
                if (notif.type === 'success') icon = '🎉';
                else if (notif.type === 'error') icon = '❌';
                else if (notif.type === 'warning') icon = '⚠️';
                else if (notif.type === 'bet') icon = '💰';
                
                let gameIcon = '';
                if (notif.game === 'roulette') gameIcon = '🛞';
                else if (notif.game === 'crash') gameIcon = '🚀';
                
                const typeClass = 'notification-type-' + notif.type;
                const clickable = notif.game ? 'notification-clickable' : '';
                
                html += `
                    <div class="notification-page-item ${readClass} ${typeClass} ${clickable}" data-id="${notif.id}">
                        <div class="notification-page-icon">${gameIcon || icon}</div>
                        <div class="notification-page-content">
                            <div class="notification-page-title">${notif.title}</div>
                            <div class="notification-page-message">${notif.message}</div>
                            <div class="notification-page-time">${formatTimestamp(notif.timestamp)}</div>
                        </div>
                        ${notif.game ? '<div class="notification-page-arrow">→</div>' : ''}
                    </div>
                `;
            });
            
                list.html(html);
                
                // Add click handlers
                list.find('.notification-clickable').on('click', function() {
                    const id = parseFloat($(this).data('id'));
                    const notif = notifications.find(n => n.id === id);
                    if (notif && notif.game) {
                        window.location.href = getGameUrl(notif.game);
                    }
                });
            });
        }
        
        /**
         * Mark all as read
         */
        $('#markAllReadBtn').on('click', function() {
            $.post(getApiPath('markAllNotificationsAsRead'), {}, function(data) {
                if (data.success) {
                    renderNotifications();
                    
                    // Update badge in navbar if notification system is loaded
                    if (typeof window.markAllAsRead === 'function') {
                        window.markAllAsRead();
                    } else if (typeof window.updateNotificationDropdown === 'function') {
                        window.updateNotificationDropdown();
                    }
                } else {
                    alert('Failed to mark all notifications as read');
                }
            }, 'json');
        });
        
        /**
         * Clear all notifications
         */
        $('#clearAllBtn').on('click', function() {
            if (confirm('Are you sure you want to clear all notifications? This cannot be undone.')) {
                $.post(getApiPath('deleteAllNotifications'), {}, function(data) {
                    if (data.success) {
                        renderNotifications();
                        
                        // Update badge in navbar if notification system is loaded
                        if (typeof window.updateNotificationDropdown === 'function') {
                            // Reload notifications in the global system
                            if (typeof window.loadNotificationsFromAPI === 'function') {
                                window.loadNotificationsFromAPI(function() {
                                    window.updateNotificationDropdown();
                                });
                            } else {
                                window.updateNotificationDropdown();
                            }
                        }
                    } else {
                        alert('Failed to clear all notifications');
                    }
                }, 'json');
            }
        });
        
        // Initial render
        renderNotifications();
    });
    </script>
    
<?php include __DIR__ . '/../includes/footer.php'; ?>
