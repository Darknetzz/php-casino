/**
 * Global Notification System for Active Bets and Game Results
 * 
 * Monitors roulette and crash games for active bets and results
 * Shows notifications for bets and wins/losses on all pages
 */

$(document).ready(function() {
    // Track which page we're on
    const currentPage = window.location.pathname;
    const isRoulettePage = currentPage.includes('roulette.php');
    const isCrashPage = currentPage.includes('crash.php');
    
    // Track last known states to detect changes
    let lastRouletteRoundId = null;
    let lastCrashRoundId = null;
    let lastRouletteBets = [];
    let lastCrashBets = [];
    let lastRouletteResult = null;
    let lastCrashResult = null;
    
    // Store notifications for dropdown
    let notifications = [];
    let unreadCount = 0;
    
    // Polling interval (every 3 seconds)
    const POLL_INTERVAL = 3000;
    let pollInterval = null;
    let notificationPollInterval = null;
    
    /**
     * Load notifications from API
     */
    function loadNotificationsFromAPI(callback) {
        $.get(getApiPath('getNotifications') + '&limit=200', function(data) {
            if (data.success && data.notifications) {
                // Convert database format to our format
                notifications = data.notifications.map(n => ({
                    id: parseInt(n.id),
                    title: n.title,
                    message: n.message,
                    type: n.type,
                    game: n.game,
                    read: n.read === 1 || n.read === '1',
                    timestamp: new Date(n.created_at)
                }));
                
                unreadCount = notifications.filter(n => !n.read).length;
                
                // Update global references
                window.notifications = notifications;
                window.unreadCount = unreadCount;
                
                if (callback) callback();
            }
        }, 'json').fail(function() {
            console.error('Failed to load notifications');
            if (callback) callback();
        });
    }
    
    /**
     * Create notification via API
     */
    function createNotificationAPI(title, message, type, game, callback) {
        $.post(getApiPath('createNotification'), {
            title: title,
            message: message,
            type: type,
            game: game
        }, function(data) {
            if (data.success) {
                // Reload notifications to get the new one
                loadNotificationsFromAPI(function() {
                    updateNotificationDropdown();
                    if (callback) callback();
                });
            } else {
                console.error('Failed to create notification:', data.message);
                if (callback) callback();
            }
        }, 'json').fail(function() {
            console.error('Error creating notification');
            if (callback) callback();
        });
    }
    
    // Load notifications on page load
    loadNotificationsFromAPI(function() {
        updateNotificationDropdown();
    });
    
    /**
     * Get game URL based on current page location
     */
    function getGameUrl(game) {
        let basePath = '';
        if (window.location.pathname.includes('/games/') || window.location.pathname.includes('/pages/')) {
            basePath = '../';
        }
        
        if (game === 'roulette') {
            return basePath + 'games/roulette.php';
        } else if (game === 'crash') {
            return basePath + 'games/crash.php';
        }
        return '#';
    }
    
    /**
     * Add notification to database and update UI
     */
    function addNotification(title, message, type = 'info', game = null) {
        // Create notification via API
        createNotificationAPI(title, message, type, game, function() {
            // Find the newly created notification (it will be the first unread one)
            const newNotification = notifications.find(n => !n.read && n.title === title && n.message === message);
            if (newNotification) {
                // Show toast notification
                showNotificationToast(newNotification);
            }
        });
    }
    
    /**
     * Show a notification toast
     */
    function showNotificationToast(notificationData) {
        const notification = $('<div class="game-notification"></div>');
        notification.addClass('notification-' + notificationData.type);
        notification.attr('data-notification-id', notificationData.id);
        
        let icon = 'ℹ️';
        if (notificationData.type === 'success') icon = '🎉';
        else if (notificationData.type === 'error') icon = '❌';
        else if (notificationData.type === 'warning') icon = '⚠️';
        else if (notificationData.type === 'bet') icon = '💰';
        
        let gameIcon = '';
        if (notificationData.game === 'roulette') gameIcon = '🛞';
        else if (notificationData.game === 'crash') gameIcon = '🚀';
        
        notification.html(`
            <div class="notification-content">
                <div class="notification-header">
                    <span class="notification-icon">${gameIcon || icon}</span>
                    <span class="notification-title">${notificationData.title}</span>
                    <button class="notification-close">&times;</button>
                </div>
                <div class="notification-message">${notificationData.message}</div>
            </div>
        `);
        
        // Add click handler to close
        notification.find('.notification-close').on('click', function(e) {
            e.stopPropagation();
            markAsRead(notificationData.id);
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Add click handler to navigate to game
        if (notificationData.game) {
            notification.css('cursor', 'pointer');
            notification.on('click', function(e) {
                if (!$(e.target).hasClass('notification-close')) {
                    markAsRead(notificationData.id);
                    window.location.href = getGameUrl(notificationData.game);
                }
            });
        }
        
        // Auto-remove after 8 seconds
        setTimeout(function() {
            if (notification.is(':visible')) {
                markAsRead(notificationData.id);
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        }, 8000);
        
        // Add to container
        $('#notification-container').append(notification);
        
        // Animate in
        setTimeout(function() {
            notification.addClass('show');
        }, 10);
    }
    
    /**
     * Mark notification as read
     */
    function markAsRead(notificationId) {
        const notification = notifications.find(n => n.id === notificationId);
        if (notification && !notification.read) {
            // Update via API
            $.post(getApiPath('markNotificationAsRead'), {
                notification_id: notificationId
            }, function(data) {
                if (data.success) {
                    notification.read = true;
                    unreadCount = Math.max(0, unreadCount - 1);
                    
                    // Update global references
                    window.notifications = notifications;
                    window.unreadCount = unreadCount;
                    
                    updateNotificationDropdown();
                }
            }, 'json');
        }
    }
    
    /**
     * Mark all notifications as read
     */
    function markAllAsRead() {
        $.post(getApiPath('markAllNotificationsAsRead'), {}, function(data) {
            if (data.success) {
                notifications.forEach(n => {
                    if (!n.read) {
                        n.read = true;
                    }
                });
                unreadCount = 0;
                
                // Update global references
                window.notifications = notifications;
                window.unreadCount = unreadCount;
                
                updateNotificationDropdown();
            }
        }, 'json');
    }
    
    // Make markAllAsRead accessible globally
    window.markAllAsRead = markAllAsRead;
    
    /**
     * Update notification dropdown
     */
    function updateNotificationDropdown() {
        const dropdown = $('#notificationDropdown');
        const badge = $('#notificationBadge');
        const list = $('#notificationList');
        const emptyState = $('#notificationEmpty');
        
        // Update badge
        if (unreadCount > 0) {
            badge.text(unreadCount > 99 ? '99+' : unreadCount).show();
        } else {
            badge.hide();
        }
        
        // Update list
        if (notifications.length === 0) {
            list.hide();
            emptyState.show();
        } else {
            list.show();
            emptyState.hide();
            
            let html = '';
            notifications.slice(0, 20).forEach(function(notif) {
                const timeAgo = getTimeAgo(notif.timestamp);
                const readClass = notif.read ? 'notification-read' : 'notification-unread';
                const typeClass = 'notification-type-' + notif.type;
                
                let icon = 'ℹ️';
                if (notif.type === 'success') icon = '🎉';
                else if (notif.type === 'error') icon = '❌';
                else if (notif.type === 'warning') icon = '⚠️';
                else if (notif.type === 'bet') icon = '💰';
                
                let gameIcon = '';
                if (notif.game === 'roulette') gameIcon = '🛞';
                else if (notif.game === 'crash') gameIcon = '🚀';
                
                html += `
                    <div class="notification-dropdown-item ${readClass} ${typeClass}" data-id="${notif.id}">
                        <div class="notification-dropdown-icon">${gameIcon || icon}</div>
                        <div class="notification-dropdown-content">
                            <div class="notification-dropdown-title">${notif.title}</div>
                            <div class="notification-dropdown-message">${notif.message}</div>
                            <div class="notification-dropdown-time">${timeAgo}</div>
                        </div>
                    </div>
                `;
            });
            
            list.html(html);
            
            // Add click handlers
            list.find('.notification-dropdown-item').on('click', function() {
                const id = parseFloat($(this).data('id'));
                const notif = notifications.find(n => n.id === id);
                if (notif) {
                    markAsRead(id);
                    if (notif.game) {
                        window.location.href = getGameUrl(notif.game);
                    } else {
                        // Close dropdown if no game link
                        $('#notificationDropdown').hide();
                    }
                }
            });
        }
    }
    
    /**
     * Get time ago string
     */
    function getTimeAgo(timestamp) {
        const now = new Date();
        const diff = now - timestamp;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        
        if (days > 0) return days + 'd ago';
        if (hours > 0) return hours + 'h ago';
        if (minutes > 0) return minutes + 'm ago';
        return 'just now';
    }
    
    /**
     * Check for active bets and results
     */
    function checkActiveBetsAndResults() {
        // Always check for both games, regardless of current page
        checkRouletteBets();
        checkCrashBets();
    }
    
    /**
     * Check roulette bets and results
     */
    function checkRouletteBets() {
        $.get(getApiPath('getRouletteRound'), function(data) {
            if (data.success && data.round) {
                const round = data.round;
                const roundId = round.id;
                const userBets = round.user_bets || [];
                
                // Check if round changed
                if (roundId !== lastRouletteRoundId) {
                    lastRouletteRoundId = roundId;
                    lastRouletteBets = [];
                }
                
                // Check for new bets
                if (userBets.length > 0 && round.status === 'betting') {
                    const newBets = userBets.filter(bet => {
                        return !lastRouletteBets.some(lastBet => lastBet.id === bet.id);
                    });
                    
                    if (newBets.length > 0) {
                        const totalBet = newBets.reduce((sum, bet) => sum + parseFloat(bet.amount || 0), 0);
                        addNotification(
                            'Roulette Bet Placed',
                            `You placed a bet of $${formatNumber(totalBet)} on round #${round.round_number}`,
                            'bet',
                            'roulette'
                        );
                    }
                    
                    lastRouletteBets = userBets;
                }
                
                // Check for round result
                if (round.status === 'finished' && round.result_number !== null) {
                    const resultNum = round.result_number;
                    const resultKey = roundId + '-' + resultNum;
                    
                    // Only show if this is a new result
                    if (lastRouletteResult !== resultKey) {
                        lastRouletteResult = resultKey;
                        
                        // Check if user had bets
                        if (userBets.length > 0) {
                            let totalWin = 0;
                            let totalLoss = 0;
                            const winningBets = [];
                            const losingBets = [];
                            
                            userBets.forEach(function(bet) {
                                // Check won field (could be 0, 1, or boolean)
                                const won = bet.won === 1 || bet.won === true || bet.won === '1';
                                if (won && bet.payout) {
                                    totalWin += parseFloat(bet.payout || 0);
                                    winningBets.push(bet);
                                } else {
                                    totalLoss += parseFloat(bet.amount || 0);
                                    losingBets.push(bet);
                                }
                            });
                            
                            const netResult = totalWin - totalLoss;
                            
                            if (totalWin > 0) {
                                addNotification(
                                    'Roulette Win! 🎉',
                                    `Round #${round.round_number} result: ${resultNum}. You won $${formatNumber(netResult)}!`,
                                    'success',
                                    'roulette'
                                );
                            } else if (totalLoss > 0) {
                                addNotification(
                                    'Roulette Result',
                                    `Round #${round.round_number} result: ${resultNum}. You lost $${formatNumber(totalLoss)}.`,
                                    'error',
                                    'roulette'
                                );
                            }
                        }
                    }
                }
            }
        }, 'json').fail(function() {
            // Silently fail - don't spam errors
        });
    }
    
    /**
     * Check crash bets and results
     */
    function checkCrashBets() {
        $.get(getApiPath('getCrashRound'), function(data) {
            if (data.success && data.round) {
                const round = data.round;
                const roundId = round.id;
                const userBets = round.user_bets || [];
                
                // Check if round changed
                if (roundId !== lastCrashRoundId) {
                    lastCrashRoundId = roundId;
                    lastCrashBets = [];
                }
                
                // Check for new bets
                if (userBets.length > 0 && round.status === 'betting') {
                    const newBets = userBets.filter(bet => {
                        return !lastCrashBets.some(lastBet => lastBet.id === bet.id);
                    });
                    
                    if (newBets.length > 0) {
                        const totalBet = newBets.reduce((sum, bet) => sum + parseFloat(bet.bet_amount || bet.amount || 0), 0);
                        addNotification(
                            'Crash Bet Placed',
                            `You placed a bet of $${formatNumber(totalBet)} on round #${round.round_number}`,
                            'bet',
                            'crash'
                        );
                    }
                    
                    lastCrashBets = userBets;
                }
                
                // Check for round result
                if (round.status === 'finished' && round.crash_point) {
                    const crashPoint = parseFloat(round.crash_point);
                    const resultKey = roundId + '-' + crashPoint;
                    
                    // Only show if this is a new result
                    if (lastCrashResult !== resultKey) {
                        lastCrashResult = resultKey;
                        
                        // Check if user had bets
                        if (userBets.length > 0) {
                            const bet = userBets[0];
                            
                            // Check won field (could be 0, 1, or boolean)
                            const won = bet.won === 1 || bet.won === true || bet.won === '1';
                            const payout = parseFloat(bet.payout || 0);
                            
                            if (won && payout > 0) {
                                const cashOutMult = bet.cash_out_multiplier || 0;
                                addNotification(
                                    'Crash Win! 🎉',
                                    `Round #${round.round_number} crashed at ${crashPoint.toFixed(2)}x. You cashed out at ${cashOutMult.toFixed(2)}x and won $${formatNumber(payout)}!`,
                                    'success',
                                    'crash'
                                );
                            } else {
                                const betAmount = parseFloat(bet.bet_amount || bet.amount || 0);
                                addNotification(
                                    'Crash Result',
                                    `Round #${round.round_number} crashed at ${crashPoint.toFixed(2)}x. You lost $${formatNumber(betAmount)}.`,
                                    'error',
                                    'crash'
                                );
                            }
                        }
                    }
                }
            }
        }, 'json').fail(function() {
            // Silently fail - don't spam errors
        });
    }
    
    /**
     * Initialize notification container
     */
    function initNotificationContainer() {
        if ($('#notification-container').length === 0) {
            $('body').append('<div id="notification-container"></div>');
        }
    }
    
    /**
     * Start polling
     */
    function startPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
        
        // Initial check
        checkActiveBetsAndResults();
        
        // Poll every interval
        pollInterval = setInterval(checkActiveBetsAndResults, POLL_INTERVAL);
    }
    
    /**
     * Stop polling
     */
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }
    
    // Make functions accessible globally
    window.updateNotificationDropdown = updateNotificationDropdown;
    window.markAllAsRead = markAllAsRead;
    window.loadNotificationsFromAPI = loadNotificationsFromAPI;
    
    /**
     * Poll for unread notification count
     */
    function pollUnreadCount() {
        $.get(getApiPath('getUnreadNotificationCount'), function(data) {
            if (data.success) {
                const newCount = data.count;
                if (newCount !== unreadCount) {
                    // Count changed, reload notifications
                    loadNotificationsFromAPI(function() {
                        updateNotificationDropdown();
                    });
                }
            }
        }, 'json').fail(function() {
            // Silently fail
        });
    }
    
    // Initialize
    initNotificationContainer();
    updateNotificationDropdown();
    startPolling();
    
    // Poll for notification updates every 5 seconds
    notificationPollInterval = setInterval(pollUnreadCount, 5000);
    
    // Handle notification dropdown toggle
    $('#notificationBtn').on('click', function(e) {
        e.stopPropagation();
        const dropdown = $('#notificationDropdown');
        dropdown.toggle();
        
        // Close other dropdowns
        $('.games-menu').removeClass('active');
        $('.user-menu').removeClass('active');
    });
    
    // Mark all as read button
    $('#markAllReadBtn').on('click', function(e) {
        e.stopPropagation();
        markAllAsRead();
    });
    
    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#notificationBtn, #notificationDropdown').length) {
            $('#notificationDropdown').hide();
        }
    });
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        stopPolling();
        if (notificationPollInterval) {
            clearInterval(notificationPollInterval);
        }
    });
    
    // Pause polling when page is hidden (browser tab)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden - could pause polling but let's keep it running
            // in case user has multiple tabs
        } else {
            // Page is visible - ensure polling is running
            if (!pollInterval) {
                startPolling();
            }
        }
    });
});
