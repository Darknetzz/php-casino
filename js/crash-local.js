// This is a simplified local version - the full local implementation would be similar to the original
// For now, this file just prevents errors. The full local version should:
// 1. Generate crash points client-side using Math.random()
// 2. Allow users to start games individually
// 3. Handle cash-outs locally
// 4. Update balance via API after game ends

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
    let crashSpeed = 0.02;
    let crashMaxMultiplier = 0;
    let crashDistributionParam = 0.99;
    
    const canvas = document.getElementById('crashCanvas');
    const ctx = canvas.getContext('2d');
    let graphData = [];
    const graphUpdateInterval = 50;
    let lastGraphUpdate = 0;
    
    // Set canvas size
    function resizeCanvas() {
        const container = $('#crashGraph');
        canvas.width = container.width();
        canvas.height = 400;
    }
    
    resizeCanvas();
    $(window).resize(resizeCanvas);
    
    // Load settings
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
            updateProbabilityStats();
        }
    }, 'json');
    
    // Generate crash point locally
    function generateCrashPoint() {
        const r = Math.random();
        const param = Math.max(0.01, Math.min(0.999, crashDistributionParam));
        const crash = Math.max(1.00, 1 + (r * param) / (1 - r * param));
        return Math.round(crash * 100) / 100;
    }
    
    function calculateCrashProbability(beforeMultiplier) {
        const param = Math.max(0.01, Math.min(0.999, crashDistributionParam));
        if (beforeMultiplier <= 1.00) return 0;
        const r = (beforeMultiplier - 1) / (param * beforeMultiplier);
        return Math.min(1, Math.max(0, r)) * 100;
    }
    
    // Draw graph (same as centralized version)
    function drawGraph() {
        const width = canvas.width;
        const height = canvas.height;
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = '#f8f9fa';
        ctx.fillRect(0, 0, width, height);
        
        // Grid lines
        ctx.strokeStyle = '#ddd';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 10; i++) {
            const y = (height / 10) * i;
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(width, y);
            ctx.stroke();
        }
        
        // Multiplier lines
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
                ctx.fillStyle = '#999';
                ctx.font = '12px Arial';
                ctx.fillText(mult + 'x', 5, y - 5);
            }
        });
        
        // Crash line
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
            
            if (graphData.length > 0) {
                const lastX = width;
                const lastY = height - (height / 10) * (currentMultiplier - 1);
                ctx.fillStyle = '#667eea';
                ctx.beginPath();
                ctx.arc(lastX, lastY, 6, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        
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
    
    // Start game (local version)
    function startGame() {
        if (isGameActive) return;
        
        betAmount = parseFloat($('#betAmount').val());
        
        if (betAmount < 1 || betAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        $.get(getApiPath('getBalance'), function(data) {
            if (!data.success || data.balance < betAmount) {
                $('#result').html('<div class="alert alert-error">Insufficient funds</div>');
                return;
            }
            
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
                
                isGameActive = true;
                hasCashedOut = false;
                currentMultiplier = 1.00;
                cashOutMultiplier = 0;
                graphData = [1.00];
                lastGraphUpdate = Date.now();
                
                crashPoint = generateCrashPoint();
                console.log('Crash point:', crashPoint);
                
                $('#placeBetBtn').prop('disabled', true).text('Game In Progress...').addClass('game-disabled');
                $('#betAmount').prop('disabled', true);
                $('.game-container button, .game-container .btn').not('#cashOutBtn, [onclick*="openModal"]').addClass('game-disabled');
                $('#crashControls').show();
                $('#result').html('');
                
                animate();
            }, 'json');
        }, 'json');
    }
    
    function animate() {
        if (!isGameActive) return;
        
        const now = Date.now();
        const deltaTime = (now - lastGraphUpdate) / 1000;
        
        const speed = crashSpeed + (currentMultiplier - 1) * (crashSpeed * 0.05);
        currentMultiplier += speed * deltaTime;
        currentMultiplier = Math.round(currentMultiplier * 100) / 100;
        
        if (crashMaxMultiplier > 0 && currentMultiplier >= crashMaxMultiplier) {
            crash();
            return;
        }
        
        if (now - lastGraphUpdate >= graphUpdateInterval) {
            graphData.push(currentMultiplier);
            if (graphData.length > 200) {
                graphData.shift();
            }
            lastGraphUpdate = now;
        }
        
        $('#multiplierDisplay').text(currentMultiplier.toFixed(2) + 'x');
        
        if (hasCashedOut) {
            const winAmount = (betAmount * cashOutMultiplier).toFixed(2);
            $('#cashOutInfo').html('<div style="color: #28a745; font-weight: bold; margin-top: 10px;">Cashed out at ' + cashOutMultiplier.toFixed(2) + 'x<br>Win: $' + winAmount + '</div>');
        } else {
            const potentialWin = (betAmount * currentMultiplier).toFixed(2);
            $('#cashOutInfo').html('<div style="color: #666; margin-top: 10px;">Potential win: $' + potentialWin + '</div>');
        }
        
        if (currentMultiplier >= crashPoint) {
            crash();
            return;
        }
        
        drawGraph();
        animationFrame = requestAnimationFrame(animate);
    }
    
    function cashOut() {
        if (!isGameActive || hasCashedOut) return;
        
        cashOutMultiplier = currentMultiplier;
        hasCashedOut = true;
        
        const winAmount = betAmount * cashOutMultiplier;
        
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
        
        $('#cashOutBtn').prop('disabled', true);
    }
    
    function crash() {
        isGameActive = false;
        
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        
        crashHistory.unshift(crashPoint.toFixed(2) + 'x');
        if (crashHistory.length > maxHistoryItems) {
            crashHistory.pop();
        }
        updateHistory();
        
        drawGraph();
        
        if (!hasCashedOut) {
            $('#result').html('<div class="alert alert-error">Crashed at ' + crashPoint.toFixed(2) + 'x! You lost $' + betAmount.toFixed(2) + '</div>');
        } else {
            const winAmount = (betAmount * cashOutMultiplier).toFixed(2);
            $('#result').html('<div class="alert alert-success">Crashed at ' + crashPoint.toFixed(2) + 'x. You cashed out at ' + cashOutMultiplier.toFixed(2) + 'x and won $' + winAmount + '</div>');
        }
        
        setTimeout(function() {
            $('#placeBetBtn').prop('disabled', false).text('Place Bet').removeClass('game-disabled');
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
    
    function updateHistory() {
        if (crashHistory.length === 0) {
            $('#historyList').html('No history yet');
            return;
        }
        
        let html = '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        crashHistory.forEach(function(mult) {
            const multValue = parseFloat(mult);
            let color = '#dc3545';
            if (multValue >= 5) color = '#ffc107';
            if (multValue >= 10) color = '#28a745';
            
            html += '<span style="background: ' + color + '; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold;">' + mult + '</span>';
        });
        html += '</div>';
        $('#historyList').html(html);
    }
    
    function updateBalance() {
        $.get(getApiPath('getBalance'), function(data) {
            if (data.success) {
                if (typeof formatNumber === 'function') {
                    $('#balance').text(formatNumber(data.balance));
                } else {
                    $('#balance').text('$' + parseFloat(data.balance).toFixed(2));
                }
            }
        }, 'json');
    }
    
    function updateStats() {
        $.get(getApiPath('getWinRates') + '&game=crash', function(data) {
            if (data.success && data.winRate) {
                $('#winRate').text(data.winRate.rate || 0);
                $('#gamesPlayed').text(data.winRate.total || 0);
                $('#wins').text(data.winRate.wins || 0);
            }
        }, 'json');
    }
    
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
    
    $('#placeBetBtn').on('click', startGame);
    $('#cashOutBtn').on('click', cashOut);
    
    $('#betAmount').on('keypress', function(e) {
        if (e.which === 13 && !isGameActive) {
            startGame();
        }
    });
    
    drawGraph();
    updateProbabilityStats();
    
    setInterval(updateBalance, 5000);
});
