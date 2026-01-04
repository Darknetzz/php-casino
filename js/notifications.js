/**
 * Global Notification System for Active Bets and Game Results
 * 
 * Monitors roulette and crash games for active bets and results
 * Shows notifications when user navigates away from game pages
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
    
    // Polling interval (every 3 seconds)
    const POLL_INTERVAL = 3000;
    let pollInterval = null;
    
    /**
     * Show a notification toast
     */
    function showNotification(title, message, type = 'info', game = null) {
        const notification = $('<div class="game-notification"></div>');
        notification.addClass('notification-' + type);
        
        let icon = 'ℹ️';
        if (type === 'success') icon = '🎉';
        else if (type === 'error') icon = '❌';
        else if (type === 'warning') icon = '⚠️';
        else if (type === 'bet') icon = '💰';
        
        let gameIcon = '';
        if (game === 'roulette') gameIcon = '🛞';
        else if (game === 'crash') gameIcon = '🚀';
        
        notification.html(`
            <div class="notification-content">
                <div class="notification-header">
                    <span class="notification-icon">${gameIcon || icon}</span>
                    <span class="notification-title">${title}</span>
                    <button class="notification-close">&times;</button>
                </div>
                <div class="notification-message">${message}</div>
            </div>
        `);
        
        // Add click handler to close
        notification.find('.notification-close').on('click', function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Add click handler to navigate to game
        if (game) {
            notification.css('cursor', 'pointer');
            notification.on('click', function(e) {
                if (!$(e.target).hasClass('notification-close')) {
                    if (game === 'roulette') {
                        window.location.href = '../games/roulette.php';
                    } else if (game === 'crash') {
                        window.location.href = '../games/crash.php';
                    }
                }
            });
        }
        
        // Auto-remove after 8 seconds
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 8000);
        
        // Add to container
        $('#notification-container').append(notification);
        
        // Animate in
        setTimeout(function() {
            notification.addClass('show');
        }, 10);
    }
    
    /**
     * Check for active bets and results
     */
    function checkActiveBetsAndResults() {
        // Only check if we're NOT on the game page
        if (!isRoulettePage) {
            checkRouletteBets();
        }
        
        if (!isCrashPage) {
            checkCrashBets();
        }
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
                        showNotification(
                            'Roulette Bet Placed',
                            `You placed a bet of $${totalBet.toFixed(2)} on round #${round.round_number}`,
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
                                showNotification(
                                    'Roulette Win! 🎉',
                                    `Round #${round.round_number} result: ${resultNum}. You won $${netResult.toFixed(2)}!`,
                                    'success',
                                    'roulette'
                                );
                            } else if (totalLoss > 0) {
                                showNotification(
                                    'Roulette Result',
                                    `Round #${round.round_number} result: ${resultNum}. You lost $${totalLoss.toFixed(2)}.`,
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
                        showNotification(
                            'Crash Bet Placed',
                            `You placed a bet of $${totalBet.toFixed(2)} on round #${round.round_number}`,
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
                                showNotification(
                                    'Crash Win! 🎉',
                                    `Round #${round.round_number} crashed at ${crashPoint.toFixed(2)}x. You cashed out at ${cashOutMult.toFixed(2)}x and won $${payout.toFixed(2)}!`,
                                    'success',
                                    'crash'
                                );
                            } else {
                                const betAmount = parseFloat(bet.bet_amount || bet.amount || 0);
                                showNotification(
                                    'Crash Result',
                                    `Round #${round.round_number} crashed at ${crashPoint.toFixed(2)}x. You lost $${betAmount.toFixed(2)}.`,
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
    
    // Initialize
    initNotificationContainer();
    startPolling();
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        stopPolling();
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
