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
    
    // Generate crash point using a configurable distribution
    // This formula makes lower multipliers more likely, but allows for high multipliers
    // The distribution parameter controls the curve (higher = more low multipliers)
    function generateCrashPoint() {
        // Generate a random number between 0 and 1
        const r = Math.random();
        // Use a configurable formula that favors lower multipliers based on distribution param
        // Formula: crash = 1 + (r * param) / (1 - r * param)
        // When param is close to 1, lower multipliers are much more likely
        // When param is lower, higher multipliers become more common
        const param = Math.max(0.01, Math.min(0.999, crashDistributionParam));
        const crash = Math.max(1.00, 1 + (r * param) / (1 - r * param));
        return Math.round(crash * 100) / 100; // Round to 2 decimal places
    }
    
    // Calculate probability of crashing before a given multiplier
    // This is the inverse of the distribution function
    // Original: crash = 1 + (r * param) / (1 - r * param)
    // Solving for r: r = (crash - 1) / (param * crash)
    function calculateCrashProbability(beforeMultiplier) {
        const param = Math.max(0.01, Math.min(0.999, crashDistributionParam));
        if (beforeMultiplier <= 1.00) return 0;
        // Inverse formula: r = (multiplier - 1) / (param * multiplier)
        // This gives us the random value r that would produce this multiplier
        // The probability is simply this r value (since r is uniform 0-1)
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
    }
    
    // Start the crash game
    function startGame() {
        if (isGameActive) return;
        
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
            $.post(getApiPath('updateBalance'), {
                amount: -betAmount,
                type: 'bet',
                description: 'Crash bet',
                game: 'crash'
            }, function(data) {
                if (!data.success) {
                    $('#result').html('<div class="alert alert-error">' + (data.message || 'Failed to place bet') + '</div>');
                    return;
                }
                
                // Initialize game
                isGameActive = true;
                hasCashedOut = false;
                currentMultiplier = 1.00;
                cashOutMultiplier = 0;
                graphData = [1.00];
                lastGraphUpdate = Date.now();
                
                // Generate crash point
                crashPoint = generateCrashPoint();
                console.log('Crash point:', crashPoint);
                
                // Update UI
                $('#placeBetBtn').prop('disabled', true).text('Game In Progress...').addClass('game-disabled');
                $('#betAmount').prop('disabled', true);
                // Grey out all buttons except the cash out button and info buttons (players need to be able to cash out and view info!)
                $('.game-container button, .game-container .btn').not('#cashOutBtn, [onclick*="openModal"]').addClass('game-disabled');
                $('#crashControls').show();
                $('#result').html('');
                
                // Start animation
                animate();
            }, 'json');
        }, 'json');
    }
    
    // Animate the multiplier
    function animate() {
        if (!isGameActive) return;
        
        const now = Date.now();
        const deltaTime = (now - lastGraphUpdate) / 1000; // Convert to seconds
        
        // Increase multiplier (exponential growth for excitement)
        // Speed increases slightly as multiplier goes up, using configured crash speed
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
        
        // Update button text to show current multiplier
        if (isGameActive && !hasCashedOut) {
            $('#placeBetBtn').text('Game In Progress: ' + currentMultiplier.toFixed(2) + 'x');
        }
        
        if (hasCashedOut) {
            const winAmount = (betAmount * cashOutMultiplier).toFixed(2);
            $('#cashOutInfo').html('<div style="color: #28a745; font-weight: bold; margin-top: 10px;">Cashed out at ' + cashOutMultiplier.toFixed(2) + 'x<br>Win: $' + winAmount + '</div>');
            $('#placeBetBtn').text('Cashed Out - Waiting for Crash');
        } else {
            const potentialWin = (betAmount * currentMultiplier).toFixed(2);
            $('#cashOutInfo').html('<div style="color: #666; margin-top: 10px;">Potential win: $' + potentialWin + '</div>');
        }
        
        // Check if crashed
        if (currentMultiplier >= crashPoint) {
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
        if (!isGameActive || hasCashedOut) return;
        
        cashOutMultiplier = currentMultiplier;
        hasCashedOut = true;
        
        // Calculate win
        const winAmount = betAmount * cashOutMultiplier;
        
        // Update balance
        $.post(getApiPath('updateBalance'), {
            amount: winAmount,
            type: 'win',
            description: 'Crash cash out at ' + cashOutMultiplier.toFixed(2) + 'x',
            game: 'crash'
        }, function(data) {
            if (data.success) {
                $('#result').html('<div class="alert alert-success">Cashed out at ' + cashOutMultiplier.toFixed(2) + 'x! You won $' + winAmount.toFixed(2) + '</div>');
                updateBalance();
            }
        }, 'json');
        
        // Disable cash out button
        $('#cashOutBtn').prop('disabled', true);
        
        // Wait for crash to see result
        // Game will end when crash happens
    }
    
    // Handle crash
    function crash() {
        isGameActive = false;
        
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        
        // Add to history
        crashHistory.unshift(crashPoint.toFixed(2) + 'x');
        if (crashHistory.length > maxHistoryItems) {
            crashHistory.pop();
        }
        updateHistory();
        
        // Final graph update
        drawGraph();
        
        if (!hasCashedOut) {
            // Player lost
            $('#result').html('<div class="alert alert-error">Crashed at ' + crashPoint.toFixed(2) + 'x! You lost $' + betAmount.toFixed(2) + '</div>');
        } else {
            // Player already cashed out, show final result
            const winAmount = (betAmount * cashOutMultiplier).toFixed(2);
            $('#result').html('<div class="alert alert-success">Crashed at ' + crashPoint.toFixed(2) + 'x. You cashed out at ' + cashOutMultiplier.toFixed(2) + 'x and won $' + winAmount + '</div>');
        }
        
        // Reset UI
        setTimeout(function() {
            $('#placeBetBtn').prop('disabled', false).text('Place Bet').removeClass('game-disabled');
            $('#betAmount').prop('disabled', false);
            $('#crashControls').hide();
            $('#cashOutBtn').prop('disabled', false);
            // Remove disabled class from all buttons (cash out and info buttons were never disabled)
            $('.game-container button, .game-container .btn').not('[onclick*="openModal"]').removeClass('game-disabled');
            $('#cashOutInfo').html('');
            currentMultiplier = 1.00;
            graphData = [];
            drawGraph();
            updateBalance();
            updateStats();
        }, 3000);
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
    
    // Update balance display
    function updateBalance() {
        $.get(getApiPath('getBalance'), function(data) {
            if (data.success) {
                // Update balance in navbar (same pattern as other games)
                if (typeof formatNumber === 'function') {
                    $('#balance').text(formatNumber(data.balance));
                } else {
                    $('#balance').text('$' + parseFloat(data.balance).toFixed(2));
                }
                // Also update large balance if it exists
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
    $('#placeBetBtn').on('click', startGame);
    $('#cashOutBtn').on('click', cashOut);
    
    // Allow Enter key to place bet
    $('#betAmount').on('keypress', function(e) {
        if (e.which === 13 && !isGameActive) {
            startGame();
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
    // Probability stats will be updated after settings load
});
