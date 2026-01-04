$(document).ready(function() {
    const rows = 8;
    const cols = 9;
    let isDropping = false;
    let activeBalls = []; // Array to track multiple balls
    let maxBet = 100;
    
    // Multipliers for each slot (0-8, left to right) - will be loaded from settings
    let multipliers = [0.2, 0.5, 0.8, 1.0, 2.0, 1.0, 0.8, 0.5, 0.2];
    
    // Load max bet, default bet, and multipliers from settings
    $.get('../api/api.php?action=getSettings', function(data) {
        if (data.success) {
            if (data.settings.max_bet) {
                maxBet = data.settings.max_bet;
                $('#maxBet').text(maxBet);
                $('#betAmount').attr('max', maxBet);
            }
            if (data.settings.default_bet) {
                $('#betAmount').val(data.settings.default_bet);
            }
            if (data.settings.plinko_multipliers && Array.isArray(data.settings.plinko_multipliers) && data.settings.plinko_multipliers.length === 9) {
                multipliers = data.settings.plinko_multipliers;
                // Recreate board with new multipliers
                createBoard();
            }
        }
    }, 'json');
    
    // Create the Plinko board
    function createBoard() {
        const board = $('#plinkoBoard');
        board.empty();
        
        // Create pegs - properly centered
        for (let row = 0; row < rows; row++) {
            const pegsInRow = row + 1;
            const rowWidth = pegsInRow * 8; // Approximate width per peg
            const startOffset = (100 - rowWidth) / 2; // Center the row
            
            for (let col = 0; col <= row; col++) {
                const peg = $('<div class="plinko-peg"></div>');
                const leftPercent = startOffset + (col * (rowWidth / pegsInRow)) + 4; // 4% for peg width
                peg.css({
                    left: leftPercent + '%',
                    top: (row * 12 + 10) + '%'
                });
                board.append(peg);
            }
        }
        
        // Create slots
        const slotsContainer = $('#plinkoSlots');
        slotsContainer.empty();
        for (let i = 0; i < cols; i++) {
            const slot = $('<div class="plinko-slot"></div>');
            slot.text(multipliers[i].toFixed(1) + 'x');
            slot.css('left', (i * 11.11) + '%');
            slotsContainer.append(slot);
        }
    }
    
    // Drop the ball(s)
    function dropBall() {
        if (isDropping) return;
        
        const betAmount = parseFloat($('#betAmount').val());
        const ballCount = parseInt($('#ballCount').val()) || 1;
        
        if (betAmount < 1 || betAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        if (ballCount < 1 || ballCount > 10) {
            $('#result').html('<div class="alert alert-error">Number of balls must be between 1 and 10</div>');
            return;
        }
        
        const totalBet = betAmount * ballCount;
        
        // Check if user has enough balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < totalBet) {
                $('#result').html('<div class="alert alert-error">Insufficient funds. Your balance is $' + (data.success ? parseFloat(data.balance).toFixed(2) : '0.00') + '. Need $' + totalBet.toFixed(2) + '</div>');
                return;
            }
            
            isDropping = true;
            $('#dropBtn').prop('disabled', true);
            $('#result').html('');
            
            // Deduct total bet first
            $.post('../api/api.php?action=updateBalance', {
                amount: -totalBet,
                type: 'bet',
                description: `Plinko bet (${ballCount} ball${ballCount > 1 ? 's' : ''})`,
                game: 'plinko'
            }, function(data) {
                if (!data.success) {
                    $('#result').html('<div class="alert alert-error">' + (data.message || 'Insufficient funds') + '</div>');
                    isDropping = false;
                    $('#dropBtn').prop('disabled', false);
                    return;
                }
                
                $('#balance').text(parseFloat(data.balance).toFixed(2));
                
                // Create multiple balls with slight horizontal offset for visual separation
                const board = $('#plinkoBoard');
                activeBalls = [];
                let totalWins = 0;
                let completedBalls = 0;
                
                for (let i = 0; i < ballCount; i++) {
                    const ballId = 'ball_' + Date.now() + '_' + i;
                    const ballElement = $('<div class="plinko-ball"></div>');
                    ballElement.attr('data-ball-id', ballId);
                    board.append(ballElement);
                    
                    // Slight horizontal offset for visual separation (spread across center)
                    const offsetRange = 0.8; // Max offset from center
                    const offset = (i - (ballCount - 1) / 2) * (offsetRange / Math.max(1, ballCount - 1));
                    
                    const ballData = {
                        id: ballId,
                        element: ballElement,
                        position: {row: 0, col: 4.5 + offset},
                        currentRow: 0,
                        velocity: 0.1 + Math.random() * 0.05, // Slight variation in starting velocity
                        horizontalVelocity: 0,
                        completed: false
                    };
                    
                    activeBalls.push(ballData);
                    updateBallPosition(ballData);
                }
                
                // Animate all balls dropping with smooth physics
                const gravity = 0.02;
                const bounceDamping = 0.7;
                
                const dropInterval = setInterval(function() {
                    let allCompleted = true;
                    
                    activeBalls.forEach(function(ball) {
                        if (ball.completed) return;
                        
                        allCompleted = false;
                        
                        if (ball.currentRow >= rows) {
                            // Ball reached bottom, determine final slot
                            const finalSlot = Math.round(ball.position.col);
                            const finalSlotClamped = Math.max(0, Math.min(cols - 1, finalSlot));
                            const multiplier = multipliers[finalSlotClamped];
                            const winAmount = betAmount * multiplier;
                            
                            totalWins += winAmount;
                            ball.completed = true;
                            
                            // Highlight winning slot briefly
                            $('.plinko-slot').eq(finalSlotClamped).addClass('winning');
                            setTimeout(function() {
                                $('.plinko-slot').eq(finalSlotClamped).removeClass('winning');
                            }, 300);
                            
                            // Remove ball element
                            ball.element.remove();
                            completedBalls++;
                            
                            // If all balls completed, process results
                            if (completedBalls === ballCount) {
                                clearInterval(dropInterval);
                                
                                setTimeout(function() {
                                    // Update balance with total winnings
                                    if (totalWins > 0) {
                                        $.post('../api/api.php?action=updateBalance', {
                                            amount: totalWins,
                                            type: 'win',
                                            description: `Plinko win: ${ballCount} ball${ballCount > 1 ? 's' : ''}`,
                                            game: 'plinko'
                                        }, function(data) {
                                            if (data.success) {
                                                $('#balance').text(parseFloat(data.balance).toFixed(2));
                                                const netResult = totalWins - totalBet;
                                                if (netResult > 0) {
                                                    $('#result').html(`<div class="alert alert-success">🎉 You won $${totalWins.toFixed(2)}! Net: +$${netResult.toFixed(2)}</div>`);
                                                } else if (netResult < 0) {
                                                    $('#result').html(`<div class="alert alert-error">You won $${totalWins.toFixed(2)}, but lost $${Math.abs(netResult).toFixed(2)} overall</div>`);
                                                } else {
                                                    $('#result').html(`<div class="alert">Break even! Won $${totalWins.toFixed(2)}</div>`);
                                                }
                                            }
                                        }, 'json');
                                    } else {
                                        $('#result').html(`<div class="alert alert-error">Better luck next time! Lost $${totalBet.toFixed(2)}</div>`);
                                    }
                                    
                                    activeBalls = [];
                                    isDropping = false;
                                    $('#dropBtn').prop('disabled', false);
                                }, 500);
                            }
                            return;
                        }
                        
                        // Apply gravity
                        ball.velocity += gravity;
                        ball.currentRow += ball.velocity;
                        ball.position.row = ball.currentRow;
                        
                        // When ball hits a peg (every row), bounce randomly
                        if (Math.floor(ball.currentRow) > Math.floor(ball.position.row - ball.velocity)) {
                            // Simulate peg bounce - random horizontal direction
                            const bounceStrength = 0.3 + Math.random() * 0.4; // 0.3 to 0.7
                            ball.horizontalVelocity = (Math.random() < 0.5 ? -1 : 1) * bounceStrength;
                        }
                        
                        // Apply horizontal movement with damping
                        ball.position.col += ball.horizontalVelocity;
                        ball.horizontalVelocity *= 0.95; // Friction/damping
                        
                        // Keep ball within bounds (with slight bounce at edges)
                        if (ball.position.col < 0) {
                            ball.position.col = 0;
                            ball.horizontalVelocity *= -bounceDamping;
                        } else if (ball.position.col > cols - 1) {
                            ball.position.col = cols - 1;
                            ball.horizontalVelocity *= -bounceDamping;
                        }
                        
                        updateBallPosition(ball);
                    });
                    
                    if (allCompleted && completedBalls < ballCount) {
                        clearInterval(dropInterval);
                    }
                }, 50); // Faster updates for smoother animation
            }, 'json');
        }, 'json');
    }
    
    function updateBallPosition(ball) {
        if (!ball || !ball.element) return;
        
        const leftPercent = (ball.position.col / (cols - 1)) * 100;
        const topPercent = (ball.position.row / rows) * 100 + 10;
        
        ball.element.css({
            left: leftPercent + '%',
            top: topPercent + '%',
            transition: 'left 0.05s linear, top 0.05s linear'
        });
    }
    
    // Initialize board
    createBoard();
    
    // Drop button click
    $('#dropBtn').click(dropBall);
    
    // Update balance periodically
    setInterval(function() {
        $.get('../api/api.php?action=getBalance', function(data) {
            if (data.success) {
                $('#balance').text(parseFloat(data.balance).toFixed(2));
            }
        }, 'json');
    }, 5000);
});
