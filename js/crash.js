$(document).ready(function() {
    let maxBet = 100;
    let isGameActive = false;
    let currentMultiplier = 1.00;
    let crashPoint = 1.00;
    let betAmount = 0;
    let animationFrame = null;
    let cashOutMultiplier = 0;
    let hasCashedOut = false;
    let crashHistory = [];
    const maxHistoryItems = 10;
    let crashSpeed = 0.02; // Default speed
    let crashMaxMultiplier = 0; // 0 = unlimited
    let crashDistributionParam = 0.99; // Distribution curve parameter
    let currentRound = null;
    let pollInterval = null;
    let bettingCountdownInterval = null;
    let userBet = null;
    
    const canvas = document.getElementById('crashCanvas');
    const ctx = canvas.getContext('2d');
    let graphData = [];
    const graphUpdateInterval = 50; // Update graph every 50ms
    let lastGraphUpdate = 0;
    
    // Set canvas size
    function resizeCanvas() {
        const container = $('#crashGraph');
        canvas.width = container.width();
        canvas.height = 400;
    }
    
    resizeCanvas();
    $(window).resize(resizeCanvas);
    
    // Load max bet, default bet, and crash settings from settings
    $.get(getApiPath('getSettings'), function(data) {
        if (data.success) {
            if (data.settings.max_bet) {
                maxBet = data.settings.max_bet;
                $('#maxBet').text(maxBet);
                $('#betAmount').attr('max', maxBet);
            }
            if (data.settings.default_bet) {
                $('#betAmount').val(data.settings.default_bet);
            }
            if (data.settings.crash_speed !== undefined) {
                crashSpeed = parseFloat(data.settings.crash_speed) || 0.02;
            }
            if (data.settings.crash_max_multiplier !== undefined) {
                crashMaxMultiplier = parseFloat(data.settings.crash_max_multiplier) || 0;
            }
            if (data.settings.crash_distribution_param !== undefined) {
                crashDistributionParam = parseFloat(data.settings.crash_distribution_param) || 0.99;
            }
            
            // Update probability stats after loading settings
            if (typeof updateProbabilityStats === 'function') {
                updateProbabilityStats();
            }
        }
    }, 'json');
    
    // Calculate probability of crashing before a given multiplier
    function calculateCrashProbability(beforeMultiplier) {
        const param = Math.max(0.01, Math.min(0.999, crashDistributionParam));
        if (beforeMultiplier <= 1.00) return 0;
        const r = (beforeMultiplier - 1) / (param * beforeMultiplier);
        return Math.min(1, Math.max(0, r)) * 100; // Return as percentage
    }
    
    // Draw the crash graph
    function drawGraph() {
        const width = canvas.width;
        const height = canvas.height;
        
        // Clear canvas
        ctx.clearRect(0, 0, width, height);
        
        // Draw background
        ctx.fillStyle = '#f8f9fa';
        ctx.fillRect(0, 0, width, height);
        
        // Draw grid lines
        ctx.strokeStyle = '#ddd';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 10; i++) {
            const y = (height / 10) * i;
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(width, y);
            ctx.stroke();
        }
        
        // Draw multiplier lines (1x, 2x, 5x, 10x, etc.)
        ctx.strokeStyle = '#ccc';
        ctx.lineWidth = 1;
        const multipliers = [1, 2, 5, 10, 20, 50];
        multipliers.forEach(mult => {
            if (mult <= currentMultiplier || mult <= 10) {
                const y = height - (height / 10) * (mult - 1);
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(width, y);
                ctx.stroke();
                
                // Label
                ctx.fillStyle = '#999';
                ctx.font = '12px Arial';
                ctx.fillText(mult + 'x', 5, y - 5);
            }
        });
        
        // Draw the crash line
        if (graphData.length > 1) {
            ctx.strokeStyle = '#667eea';
            ctx.lineWidth = 3;
            ctx.beginPath();
            
            graphData.forEach((point, index) => {
                const x = (width / (graphData.length - 1)) * index;
                const y = height - (height / 10) * (point - 1);
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            
            ctx.stroke();
            
            // Draw point at current position
            if (graphData.length > 0) {
                const lastX = width;
                const lastY = height - (height / 10) * (currentMultiplier - 1);
                ctx.fillStyle = '#667eea';
                ctx.beginPath();
                ctx.arc(lastX, lastY, 6, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        
        // Draw cash out line if player cashed out
        if (hasCashedOut && cashOutMultiplier > 0) {
            const cashOutY = height - (height / 10) * (cashOutMultiplier - 1);
            ctx.strokeStyle = '#28a745';
            ctx.lineWidth = 2;
            ctx.setLineDash([5, 5]);
            ctx.beginPath();
            ctx.moveTo(0, cashOutY);
            ctx.lineTo(width, cashOutY);
            ctx.stroke();
            ctx.setLineDash([]);
        }
        
        // Draw crash point line if known
        if (currentRound && currentRound.crash_point && currentRound.status === 'finished') {
            const crashY = height - (height / 10) * (currentRound.crash_point - 1);
            ctx.strokeStyle = '#dc3545';
            ctx.lineWidth = 2;
            ctx.setLineDash([3, 3]);
            ctx.beginPath();
            ctx.moveTo(0, crashY);
            ctx.lineTo(width, crashY);
            ctx.stroke();
            ctx.setLineDash([]);
        }
    }
    
    // Poll server for current round state
    function pollRoundState() {
        $.get('../api/api.php?action=getCrashRound', function(data) {
            if (data.success) {
                const round = data.round;
                
                if (!round) {
                    // No active round
                    $('#multiplierDisplay').text('Waiting for next round...');
                    $('#placeBetBtn').hide();
                    $('#roundCountdown').show();
                    $('#countdownText').html('Waiting for next round...');
                    $('#crashControls').hide();
                    currentRound = null;
                    // Update status display
                    updateRoundStatusDisplay(null);
                    // Load history anyway
                    loadHistory();
                    // Continue polling to catch when a round starts
                    return;
                }
                
                // Check if round changed
                const roundChanged = !currentRound || currentRound.id !== round.id || currentRound.status !== round.status;
                currentRound = round;
                
                // Update round status display
                updateRoundStatusDisplay(round);
                
                // Update round info
                if (round.status === 'betting') {
                    const timeLeft = round.time_until_betting_ends || 0;
                    $('#multiplierDisplay').text(`Round #${round.round_number} - Betting ends in ${Math.ceil(timeLeft)}s`);
                    // Show place bet button and countdown when betting is open
                    $('#placeBetBtn').show();
                    $('#roundCountdown').show();
                    $('#crashControls').hide();
                    
                    // Only update countdown if round changed or countdown isn't running
                    // Also sync if there's a significant drift (more than 2 seconds difference)
                    if (roundChanged || !bettingCountdownInterval) {
                        updateBettingCountdown(timeLeft);
                    } else if (bettingCountdownInterval) {
                        // Sync countdown with server time if drift is significant
                        // This prevents the countdown from getting stuck
                        const currentDisplayed = parseInt($('#countdownText').text().match(/\d+/)?.[0] || '0');
                        const serverTime = Math.ceil(timeLeft);
                        if (Math.abs(currentDisplayed - serverTime) > 2) {
                            updateBettingCountdown(timeLeft);
                        }
                    }
                    
                    // Reset game state when entering betting phase
                    if (roundChanged) {
                        isGameActive = false;
                        hasCashedOut = false;
                        currentMultiplier = 1.00;
                        cashOutMultiplier = 0;
                        graphData = [];
                        userBet = null;
                    }
                    
                    // Check if user has a bet for this round
                    if (round.user_bets && round.user_bets.length > 0) {
                        userBet = round.user_bets[0];
                        betAmount = parseFloat(userBet.bet_amount || userBet.amount || 0);
                        // Update button text to show bet is placed
                        $('#placeBetBtn').prop('disabled', true).text('Bet Placed: $' + betAmount.toFixed(2)).show();
                    } else {
                        // No bet - show confirm button if betting is still open
                        const bettingEndsIn = round.time_until_betting_ends || 0;
                        if (bettingEndsIn > 0) {
                            $('#placeBetBtn').prop('disabled', false).text('Confirm Bet').show();
                        }
                    }
                } else if (round.status === 'running') {
                    // Round is running
                    if (round.crash_point) {
                        crashPoint = parseFloat(round.crash_point);
                    }
                    $('#placeBetBtn').hide();
                    $('#roundCountdown').show();
                    $('#countdownText').html('Round in progress...');
                    if (!isGameActive && userBet) {
                        // Start the game animation
                        startGameAnimation(round);
                    } else if (isGameActive) {
                        // Update crash point if it changed
                        if (round.crash_point) {
                            crashPoint = parseFloat(round.crash_point);
                        }
                    }
                } else if (round.status === 'finished') {
                    // Round finished
                    if (round.crash_point) {
                        crashPoint = parseFloat(round.crash_point);
                    }
                    $('#placeBetBtn').hide();
                    $('#roundCountdown').show();
                    $('#countdownText').html('Round finished. Waiting for next round...');
                    finishRound(round);
                }
                
                // Load history
                loadHistory();
            }
        }, 'json').fail(function() {
            console.error('Failed to poll round state');
        });
    }
    
    function updateBettingCountdown(seconds) {
        if (bettingCountdownInterval) {
            clearInterval(bettingCountdownInterval);
        }
        
        let timeLeft = Math.max(0, Math.ceil(seconds));
        const updateCountdown = function() {
            if (currentRound && currentRound.status === 'betting' && currentRound.id) {
                if (timeLeft > 0) {
                    $('#multiplierDisplay').text(`Round #${currentRound.round_number} - Betting ends in ${timeLeft}s`);
                    $('#countdownText').html(`Next round in: <span style="font-size: 1.5em; color: #667eea;">${timeLeft}s</span>`);
                    timeLeft--;
                } else {
                    clearInterval(bettingCountdownInterval);
                    bettingCountdownInterval = null;
                    // Poll will update when status changes
                    $('#multiplierDisplay').text(`Round #${currentRound.round_number} - Betting ended`);
                    $('#countdownText').html('Round starting...');
                }
            } else {
                clearInterval(bettingCountdownInterval);
                bettingCountdownInterval = null;
                if (currentRound && currentRound.status === 'running') {
                    $('#countdownText').html('Round in progress...');
                }
            }
        };
        
        updateCountdown();
        bettingCountdownInterval = setInterval(updateCountdown, 1000);
    }
    
    function startGameAnimation(round) {
        isGameActive = true;
        currentMultiplier = 1.00;
        graphData = [1.00];
        lastGraphUpdate = Date.now();
        
        if (round.crash_point) {
            crashPoint = parseFloat(round.crash_point);
        }
        
        $('#placeBetBtn').prop('disabled', true).text('Game In Progress...').addClass('game-disabled');
        $('#betAmount').prop('disabled', true);
        $('#crashControls').show();
        $('#cashOutBtn').prop('disabled', false);
        $('#result').html('');
        
        // Start animation
        animate();
    }
    
    // Animate the multiplier
    function animate() {
        if (!isGameActive) return;
        
        const now = Date.now();
        const deltaTime = (now - lastGraphUpdate) / 1000; // Convert to seconds
        
        // Increase multiplier (exponential growth)
        const speed = crashSpeed + (currentMultiplier - 1) * (crashSpeed * 0.05);
        currentMultiplier += speed * deltaTime;
        currentMultiplier = Math.round(currentMultiplier * 100) / 100;
        
        // Check max multiplier limit if set
        if (crashMaxMultiplier > 0 && currentMultiplier >= crashMaxMultiplier) {
            crash();
            return;
        }
        
        // Update graph data
        if (now - lastGraphUpdate >= graphUpdateInterval) {
            graphData.push(currentMultiplier);
            // Keep only last 200 points for performance
            if (graphData.length > 200) {
                graphData.shift();
            }
            lastGraphUpdate = now;
        }
        
        // Update display
        $('#multiplierDisplay').text(currentMultiplier.toFixed(2) + 'x');
        
        if (hasCashedOut) {
            const winAmount = (betAmount * cashOutMultiplier).toFixed(2);
            $('#cashOutInfo').html('<div style="color: #28a745; font-weight: bold; margin-top: 10px;">Cashed out at ' + cashOutMultiplier.toFixed(2) + 'x<br>Win: $' + winAmount + '</div>');
        } else {
            const potentialWin = (betAmount * currentMultiplier).toFixed(2);
            $('#cashOutInfo').html('<div style="color: #666; margin-top: 10px;">Potential win: $' + potentialWin + '</div>');
        }
        
        // Check if crashed
        if (crashPoint > 0 && currentMultiplier >= crashPoint) {
            crash();
            return;
        }
        
        // Draw graph
        drawGraph();
        
        // Continue animation
        animationFrame = requestAnimationFrame(animate);
    }
    
    // Handle cash out
    function cashOut() {
        if (!isGameActive || hasCashedOut || !userBet) return;
        
        cashOutMultiplier = currentMultiplier;
        hasCashedOut = true;
        
        // Send cash out to server
        $.post('../api/api.php?action=cashOutCrash', {
            multiplier: cashOutMultiplier
        }, function(data) {
            if (data.success) {
                $('#cashOutBtn').prop('disabled', true);
                updateBalance();
            } else {
                $('#result').html('<div class="alert alert-error">' + (data.message || 'Failed to cash out') + '</div>');
            }
        }, 'json');
        
        // Disable cash out button
        $('#cashOutBtn').prop('disabled', true);
    }
    
    // Handle crash
    function crash() {
        isGameActive = false;
        
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        
        // Final graph update
        drawGraph();
        
        // Results will be processed by the server
        // Just show the crash point
        if (crashPoint > 0) {
            $('#multiplierDisplay').text('Crashed at ' + crashPoint.toFixed(2) + 'x');
        }
        
        if (!hasCashedOut) {
            // Player lost
            $('#result').html('<div class="alert alert-error">Crashed at ' + crashPoint.toFixed(2) + 'x! You lost $' + betAmount.toFixed(2) + '</div>');
        } else {
            // Player already cashed out
            const winAmount = (betAmount * cashOutMultiplier).toFixed(2);
            $('#result').html('<div class="alert alert-success">Crashed at ' + crashPoint.toFixed(2) + 'x. You cashed out at ' + cashOutMultiplier.toFixed(2) + 'x and won $' + winAmount + '</div>');
        }
        
        // Reset UI after delay
        setTimeout(function() {
            $('#placeBetBtn').prop('disabled', false).text('Confirm Bet').removeClass('game-disabled').show();
            $('#betAmount').prop('disabled', false);
            $('#crashControls').hide();
            $('#cashOutBtn').prop('disabled', false);
            $('#cashOutInfo').html('');
            currentMultiplier = 1.00;
            graphData = [];
            drawGraph();
            updateBalance();
            updateStats();
        }, 3000);
    }
    
    function finishRound(round) {
        // Round finished, check results
        if (userBet) {
            if (userBet.won) {
                $('#result').html('<div class="alert alert-success">You won $' + (userBet.payout || 0).toFixed(2) + '!</div>');
            } else {
                $('#result').html('<div class="alert alert-error">You lost $' + userBet.bet_amount.toFixed(2) + '</div>');
            }
            updateBalance();
            updateStats();
        }
    }
    
    // Update history display
    function updateHistory() {
        if (crashHistory.length === 0) {
            $('#historyList').html('No history yet');
            return;
        }
        
        let html = '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        crashHistory.forEach(function(mult) {
            const multValue = parseFloat(mult);
            let color = '#dc3545'; // Red for low
            if (multValue >= 5) color = '#ffc107'; // Yellow for medium
            if (multValue >= 10) color = '#28a745'; // Green for high
            
            html += '<span style="background: ' + color + '; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold;">' + mult + '</span>';
        });
        html += '</div>';
        $('#historyList').html(html);
    }
    
    function loadHistory() {
        $.get('../api/api.php?action=getCrashHistory&limit=20', function(data) {
            if (data.success && data.history) {
                crashHistory = data.history.map(function(round) {
                    return round.crash_point ? round.crash_point.toFixed(2) + 'x' : '1.00x';
                });
                updateHistory();
                
                // Also update table format if section exists
                if ($('#historyList').length && $('#historyList').parent().hasClass('crash-history')) {
                    if (data.history.length === 0) {
                        $('#historyList').html('<p style="color: #999; text-align: center;">No history yet</p>');
                        return;
                    }
                    
                    let html = '<table style="width: 100%; border-collapse: collapse;">';
                    html += '<thead><tr style="border-bottom: 2px solid #ddd;"><th style="padding: 10px; text-align: left;">Round</th><th style="padding: 10px; text-align: left;">Crash Point</th><th style="padding: 10px; text-align: left;">Time</th></tr></thead>';
                    html += '<tbody>';
                    
                    data.history.forEach(function(round) {
                        const finishedTime = round.finished_at ? new Date(round.finished_at).toLocaleTimeString() : '-';
                        const crashPoint = round.crash_point ? parseFloat(round.crash_point).toFixed(2) + 'x' : '-';
                        const multValue = round.crash_point ? parseFloat(round.crash_point) : 0;
                        let color = '#dc3545'; // Red for low
                        if (multValue >= 5) color = '#ffc107'; // Yellow for medium
                        if (multValue >= 10) color = '#28a745'; // Green for high
                        
                        html += '<tr style="border-bottom: 1px solid #eee;">';
                        html += '<td style="padding: 8px;">#' + round.round_number + '</td>';
                        html += '<td style="padding: 8px;"><strong style="color: ' + color + ';">' + crashPoint + '</strong></td>';
                        html += '<td style="padding: 8px; color: #666; font-size: 0.9em;">' + finishedTime + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    $('#historyList').html(html);
                }
            }
        }, 'json').fail(function() {
            if ($('#historyList').length && $('#historyList').parent().hasClass('crash-history')) {
                $('#historyList').html('<p style="color: #999; text-align: center;">Error loading history</p>');
            }
        });
    }
    
    // Update balance display
    function updateBalance() {
        $.get(getApiPath('getBalance'), function(data) {
            if (data.success) {
                if (typeof formatNumber === 'function') {
                    $('#balance').text(formatNumber(data.balance));
                } else {
                    $('#balance').text('$' + parseFloat(data.balance).toFixed(2));
                }
                $('.balance-large').text('$' + (typeof formatNumber === 'function' ? formatNumber(data.balance) : parseFloat(data.balance).toFixed(2)));
            }
        }, 'json');
    }
    
    // Update stats
    function updateStats() {
        $.get(getApiPath('getWinRates') + '&game=crash', function(data) {
            if (data.success && data.winRate) {
                $('#winRate').text(data.winRate.rate || 0);
                $('#gamesPlayed').text(data.winRate.total || 0);
                $('#wins').text(data.winRate.wins || 0);
            }
        }, 'json');
    }
    
    // Event handlers
    $('#placeBetBtn').on('click', function() {
        if (!currentRound || currentRound.status !== 'betting') {
            $('#result').html('<div class="alert alert-error">Betting is not open</div>');
            return;
        }
        
        betAmount = parseFloat($('#betAmount').val());
        
        if (betAmount < 1 || betAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        // Check balance
        $.get(getApiPath('getBalance'), function(data) {
            if (!data.success || data.balance < betAmount) {
                $('#result').html('<div class="alert alert-error">Insufficient funds</div>');
                return;
            }
            
            // Place bet
            $.post('../api/api.php?action=placeCrashBet', {
                bet_amount: betAmount
            }, function(data) {
                if (data.success) {
                    // Fetch updated round data to get the server-side bet
                    $.get('../api/api.php?action=getCrashRound', function(roundData) {
                        if (roundData.success && roundData.round && roundData.round.user_bets && roundData.round.user_bets.length > 0) {
                            userBet = roundData.round.user_bets[0];
                            betAmount = parseFloat(userBet.bet_amount || userBet.amount || betAmount);
                        } else {
                            // Fallback to local data
                            userBet = {bet_amount: betAmount};
                        }
                        $('#placeBetBtn').prop('disabled', true).text('Bet Placed: $' + betAmount.toFixed(2)).show();
                    }, 'json');
                    $('#result').html('<div class="alert alert-success">Bet placed!</div>');
                    updateBalance();
                } else {
                    $('#result').html('<div class="alert alert-error">' + (data.message || 'Failed to place bet') + '</div>');
                }
            }, 'json');
        }, 'json');
    });
    
    $('#cashOutBtn').on('click', cashOut);
    
    // Allow Enter key to place bet
    $('#betAmount').on('keypress', function(e) {
        if (e.which === 13 && !isGameActive) {
            $('#placeBetBtn').click();
        }
    });
    
    // Update probability statistics
    function updateProbabilityStats() {
        if ($('#prob1_5').length) {
            $('#prob1_5').text(calculateCrashProbability(1.5).toFixed(1) + '%');
            $('#prob2_0').text(calculateCrashProbability(2.0).toFixed(1) + '%');
            $('#prob5_0').text(calculateCrashProbability(5.0).toFixed(1) + '%');
            $('#prob10_0').text(calculateCrashProbability(10.0).toFixed(1) + '%');
            $('#prob50_0').text(calculateCrashProbability(50.0).toFixed(1) + '%');
            $('#prob100_0').text(calculateCrashProbability(100.0).toFixed(1) + '%');
        }
    }
    
    // Initial graph draw and probability stats
    drawGraph();
    
    let statusCountdownInterval = null;
    
    function updateRoundStatusDisplay(round) {
        if (!$('#roundStatusDisplay').length) return; // Section doesn't exist (local mode)
        
        // Clear existing countdown interval
        if (statusCountdownInterval) {
            clearInterval(statusCountdownInterval);
            statusCountdownInterval = null;
        }
        
        if (!round) {
            $('#currentRoundNumber').text('-');
            $('#roundStatusText').text('Waiting for next round...');
            $('#countdownValue').text('Waiting...');
            return;
        }
        
        $('#currentRoundNumber').text('#' + round.round_number);
        
        let statusText = round.status.charAt(0).toUpperCase() + round.status.slice(1);
        
        if (round.status === 'betting') {
            statusText = 'Betting Phase';
            let timeLeft = Math.ceil(round.time_until_betting_ends || 0);
            const updateCountdown = function() {
                if (currentRound && currentRound.status === 'betting' && currentRound.id === round.id) {
                    $('#countdownValue').text(`Next round in: ${timeLeft}s`);
                    if (timeLeft > 0) {
                        timeLeft--;
                    } else {
                        clearInterval(statusCountdownInterval);
                        statusCountdownInterval = null;
                    }
                } else {
                    clearInterval(statusCountdownInterval);
                    statusCountdownInterval = null;
                }
            };
            updateCountdown();
            statusCountdownInterval = setInterval(updateCountdown, 1000);
        } else if (round.status === 'running') {
            statusText = 'Running';
            if (round.crash_point) {
                $('#countdownValue').text(`Crash point: ${parseFloat(round.crash_point).toFixed(2)}x`);
            } else {
                $('#countdownValue').text('Round in progress...');
            }
        } else if (round.status === 'finished') {
            statusText = 'Finished';
            if (round.crash_point) {
                $('#countdownValue').text(`Crashed at: ${parseFloat(round.crash_point).toFixed(2)}x`);
            } else {
                $('#countdownValue').text('Waiting for next round...');
            }
        }
        
        $('#roundStatusText').text(statusText);
    }
    
    // Start polling
    pollRoundState();
    pollInterval = setInterval(pollRoundState, 2000); // Poll every 2 seconds
    
    // Load history immediately
    loadHistory();
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
        if (bettingCountdownInterval) {
            clearInterval(bettingCountdownInterval);
        }
        if (statusCountdownInterval) {
            clearInterval(statusCountdownInterval);
        }
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
        }
    });
});
