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
        
        // Create pegs - properly centered with wider spacing
        for (let row = 0; row < rows; row++) {
            const pegsInRow = row + 1;
            const rowWidth = pegsInRow * 10; // Wider spacing per peg (increased from 8)
            const startOffset = (100 - rowWidth) / 2; // Center the row
            
            for (let col = 0; col <= row; col++) {
                const peg = $('<div class="plinko-peg"></div>');
                const leftPercent = startOffset + (col * (rowWidth / pegsInRow)) + 5; // Adjusted for wider spacing
                peg.css({
                    left: leftPercent + '%',
                    top: (row * 14 + 10) + '%' // Increased vertical spacing (from 12 to 14)
                });
                board.append(peg);
            }
        }
        
        // Create slots - align with bottom row of pegs
        const slotsContainer = $('#plinkoSlots');
        slotsContainer.empty();
        
            // Calculate bottom row peg positions to align slots
            const bottomRow = rows - 1; // Last row index (row 7)
            const bottomPegsInRow = bottomRow + 1; // 8 pegs
            const bottomRowWidth = bottomPegsInRow * 10; // Width of bottom row (matches wider spacing)
            const bottomStartOffset = (100 - bottomRowWidth) / 2; // Center offset
        
        // Calculate where slots should be positioned
        // Slots should align with the spaces between and around the bottom pegs
        // The bottom row spans from startOffset to startOffset + rowWidth
        // We need to position 9 slots across this area, extending slightly beyond for edge slots
        const slotAreaStart = bottomStartOffset - 2; // Start slightly before first peg
        const slotAreaEnd = bottomStartOffset + bottomRowWidth + 2; // End slightly after last peg
        const slotAreaWidth = slotAreaEnd - slotAreaStart;
        
        for (let i = 0; i < cols; i++) {
            const slot = $('<div class="plinko-slot"></div>');
            slot.text(multipliers[i].toFixed(1) + 'x');
            // Position slots evenly across the slot area, aligned with peg positions
            const slotPosition = slotAreaStart + (i / (cols - 1)) * slotAreaWidth;
            slot.css('left', slotPosition + '%');
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
                    
                    // Start at the top center of the triangle (row 0, col 0)
                    const ballData = {
                        id: ballId,
                        element: ballElement,
                        currentRow: 0,
                        currentCol: 0, // Column index within the current row (0 to currentRow)
                        completed: false,
                        stepDelay: 0 // Delay counter for step-by-step movement
                    };
                    
                    activeBalls.push(ballData);
                    updateBallPosition(ballData);
                }
                
                // Animate all balls dropping step by step
                const stepDelay = 350; // Milliseconds between steps (slower movement)
                
                const dropInterval = setInterval(function() {
                    let allCompleted = true;
                    
                    activeBalls.forEach(function(ball) {
                        if (ball.completed) return;
                        
                        allCompleted = false;
                        
                        // Wait for step delay
                        ball.stepDelay += 50; // Update interval is 50ms
                        if (ball.stepDelay < stepDelay) {
                            return;
                        }
                        ball.stepDelay = 0;
                        
                        // Check if ball reached the bottom
                        if (ball.currentRow >= rows) {
                            // Ball reached bottom, determine final slot
                            // Map the column position from the last peg row to slot index (0 to cols-1)
                            // At row (rows-1), there are 'rows' pegs (columns 0 to rows-1)
                            // We need to map to cols slots (0 to cols-1)
                            const lastPegRow = rows - 1;
                            const colRatio = ball.currentCol / Math.max(1, lastPegRow);
                            const finalSlot = Math.round(colRatio * (cols - 1));
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
                        
                        // Store previous position before moving - ensure it's valid
                        const previousRow = ball.currentRow;
                        let previousCol = ball.currentCol;
                        
                        // Ensure previousCol is an integer and within valid bounds
                        previousCol = Math.round(previousCol);
                        const maxColForPreviousRow = previousRow; // Max col for previous row
                        previousCol = Math.max(0, Math.min(maxColForPreviousRow, previousCol));
                        
                        // Move to next row (one step at a time)
                        ball.currentRow++;
                        const newRow = ball.currentRow;
                        const maxColForNewRow = newRow; // Maximum valid column for new row (0 to newRow)
                        
                        // From a peg at (previousRow, previousCol), the ball can only move to:
                        // - The peg directly below-left: (newRow, previousCol - 1) if previousCol > 0
                        // - The peg directly below-right: (newRow, previousCol) if previousCol <= newRow
                        // These are the only two valid positions directly below the current peg
                        
                        let newCol;
                        const canMoveLeft = previousCol > 0; // Can move to col (previousCol - 1)
                        const canMoveRight = previousCol <= newRow; // Can move to col (previousCol)
                        
                        if (!canMoveLeft && !canMoveRight) {
                            // Should never happen, but safety fallback
                            newCol = Math.max(0, Math.min(newRow, previousCol));
                        } else if (!canMoveLeft) {
                            // Can only move right (to same column)
                            newCol = previousCol;
                        } else if (!canMoveRight) {
                            // Can only move left (to previousCol - 1)
                            newCol = previousCol - 1;
                        } else {
                            // Can move either left or right - randomly choose
                            if (Math.random() < 0.5) {
                                // Move left (to previousCol - 1)
                                newCol = previousCol - 1;
                            } else {
                                // Move right (to same column, previousCol)
                                newCol = previousCol;
                            }
                        }
                        
                        // Final validation - ensure result is within bounds
                        newCol = Math.max(0, Math.min(maxColForNewRow, newCol));
                        
                        // Update ball's column position
                        ball.currentCol = newCol;
                        
                        updateBallPosition(ball);
                    });
                    
                    if (allCompleted && completedBalls < ballCount) {
                        clearInterval(dropInterval);
                    }
                }, 50); // Update interval
            }, 'json');
        }, 'json');
    }
    
    function updateBallPosition(ball) {
        if (!ball || !ball.element) return;
        
        // Calculate position within the triangle
        // For row r, there are r+1 pegs (columns 0 to r)
        const currentRow = ball.currentRow;
        const currentCol = ball.currentCol;
        
        if (currentRow >= rows) {
            // Ball reached bottom, map to final slot position
            // Use the same calculation as slot positioning for alignment
            const lastPegRow = rows - 1;
            const bottomPegsInRow = lastPegRow + 1;
            const bottomRowWidth = bottomPegsInRow * 10; // Matches wider spacing
            const bottomStartOffset = (100 - bottomRowWidth) / 2;
            const slotAreaStart = bottomStartOffset - 2;
            const slotAreaEnd = bottomStartOffset + bottomRowWidth + 2;
            const slotAreaWidth = slotAreaEnd - slotAreaStart;
            
            // Map column position to slot area
            const colRatio = currentCol / Math.max(1, lastPegRow);
            const slotPosition = slotAreaStart + (colRatio * slotAreaWidth);
            const topPercent = 100; // At the bottom
            ball.element.css({
                left: slotPosition + '%',
                top: topPercent + '%',
                transition: 'left 0.3s ease-out, top 0.3s ease-out'
            });
        } else {
            // Calculate position based on triangle structure
            const pegsInRow = currentRow + 1;
            const rowWidth = pegsInRow * 10; // Wider spacing per peg (matches peg creation)
            const startOffset = (100 - rowWidth) / 2; // Center the row
            const leftPercent = startOffset + (currentCol * (rowWidth / pegsInRow)) + 5; // Adjusted for wider spacing
            // Position ball clearly above the peg so it appears to hit the top of the peg
            // Pegs are at (row * 14 + 10)%, so we position ball about 3% higher to be clearly above
            const pegTopPercent = (currentRow * 14 + 10);
            const topPercent = pegTopPercent - 3; // Position clearly above the peg (increased from 1.5%)
            
            ball.element.css({
                left: leftPercent + '%',
                top: topPercent + '%',
                transition: 'left 0.3s ease-out, top 0.3s ease-out'
            });
        }
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
