$(document).ready(function() {
    const rows = 8;
    const cols = 9;
    let isDropping = false;
    let ballPosition = {row: 0, col: 4.5}; // Start in the middle
    let ballElement = null;
    let maxBet = 100;
    
    // Load max bet from settings
    $.get('../api/api.php?action=getSettings', function(data) {
        if (data.success && data.settings.max_bet) {
            maxBet = data.settings.max_bet;
            $('#maxBet').text(maxBet);
            $('#betAmount').attr('max', maxBet);
        }
    }, 'json');
    
    // Multipliers for each slot (0-8, left to right)
    const multipliers = [0.2, 0.5, 0.8, 1.0, 2.0, 1.0, 0.8, 0.5, 0.2];
    
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
    
    // Drop the ball
    function dropBall() {
        if (isDropping) return;
        
        const betAmount = parseFloat($('#betAmount').val());
        if (betAmount < 1 || betAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        isDropping = true;
        $('#dropBtn').prop('disabled', true);
        $('#result').html('');
        
        // Deduct bet first
        $.post('../api/api.php?action=updateBalance', {
            amount: -betAmount,
            type: 'bet',
            description: 'Plinko bet'
        }, function(data) {
            if (!data.success) {
                $('#result').html('<div class="alert alert-error">' + (data.message || 'Insufficient funds') + '</div>');
                isDropping = false;
                $('#dropBtn').prop('disabled', false);
                return;
            }
            
            $('#balance').text(parseFloat(data.balance).toFixed(2));
            
            // Create ball element
            const board = $('#plinkoBoard');
            ballElement = $('<div class="plinko-ball"></div>');
            board.append(ballElement);
            
            ballPosition = {row: 0, col: 4.5};
            updateBallPosition();
            
            // Animate ball drop with smooth physics
            let currentRow = 0;
            let velocity = 0.1; // Vertical velocity
            let horizontalVelocity = 0;
            const gravity = 0.02;
            const bounceDamping = 0.7;
            
            const dropInterval = setInterval(function() {
                if (currentRow >= rows) {
                    clearInterval(dropInterval);
                    // Ball reached bottom, determine final slot
                    const finalSlot = Math.round(ballPosition.col);
                    const finalSlotClamped = Math.max(0, Math.min(cols - 1, finalSlot));
                    const multiplier = multipliers[finalSlotClamped];
                    const winAmount = betAmount * multiplier;
                    
                    // Highlight winning slot
                    $('.plinko-slot').removeClass('winning');
                    $('.plinko-slot').eq(finalSlotClamped).addClass('winning');
                    
                    setTimeout(function() {
                        if (multiplier > 0) {
                            $.post('../api/api.php?action=updateBalance', {
                                amount: winAmount,
                                type: 'win',
                                description: `Plinko win: ${multiplier.toFixed(1)}x multiplier`
                            }, function(data) {
                                if (data.success) {
                                    $('#balance').text(parseFloat(data.balance).toFixed(2));
                                    $('#result').html(`<div class="alert alert-success">🎉 You won $${winAmount.toFixed(2)}! (${multiplier.toFixed(1)}x multiplier)</div>`);
                                }
                            }, 'json');
                        } else {
                            $('#result').html(`<div class="alert alert-error">Better luck next time! Lost $${betAmount.toFixed(2)}</div>`);
                        }
                        
                        ballElement.remove();
                        $('.plinko-slot').removeClass('winning');
                        isDropping = false;
                        $('#dropBtn').prop('disabled', false);
                    }, 500);
                    return;
                }
                
                // Apply gravity
                velocity += gravity;
                currentRow += velocity;
                ballPosition.row = currentRow;
                
                // When ball hits a peg (every row), bounce randomly
                if (Math.floor(currentRow) > Math.floor(ballPosition.row - velocity)) {
                    // Simulate peg bounce - random horizontal direction
                    const bounceStrength = 0.3 + Math.random() * 0.4; // 0.3 to 0.7
                    horizontalVelocity = (Math.random() < 0.5 ? -1 : 1) * bounceStrength;
                }
                
                // Apply horizontal movement with damping
                ballPosition.col += horizontalVelocity;
                horizontalVelocity *= 0.95; // Friction/damping
                
                // Keep ball within bounds (with slight bounce at edges)
                if (ballPosition.col < 0) {
                    ballPosition.col = 0;
                    horizontalVelocity *= -bounceDamping;
                } else if (ballPosition.col > cols - 1) {
                    ballPosition.col = cols - 1;
                    horizontalVelocity *= -bounceDamping;
                }
                
                updateBallPosition();
            }, 50); // Faster updates for smoother animation
        }, 'json');
    }
    
    function updateBallPosition() {
        if (!ballElement) return;
        
        const leftPercent = (ballPosition.col / (cols - 1)) * 100;
        const topPercent = (ballPosition.row / rows) * 100 + 10;
        
        ballElement.css({
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
