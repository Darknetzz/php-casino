$(document).ready(function() {
    const rows = 8;
    const cols = 9;
    let isDropping = false;
    let activeBalls = []; // Array to track multiple balls
    let maxBet = 100;
    let maxBetEnabled = true;
    
    // Multipliers for each slot (0-8, left to right) - will be loaded from settings
    let multipliers = [0.2, 0.5, 0.8, 1.0, 2.0, 1.0, 0.8, 0.5, 0.2];
    let plinkoStepDelay = 350; // Default step delay in milliseconds
    
    // Load max bet, default bet, and multipliers from settings
    $.get('../api/api.php?action=getSettings', function(data) {
        if (data.success) {
            maxBetEnabled = data.settings.max_bet_enabled !== false;
            if (data.settings.max_bet && maxBetEnabled) {
                maxBet = data.settings.max_bet;
                $('#maxBet').text(maxBet);
                $('#betAmount').attr('max', maxBet);
            } else if (!maxBetEnabled) {
                $('#maxBet').text('Unlimited');
                $('#betAmount').removeAttr('max');
            }
            if (data.settings.default_bet) {
                $('#betAmount').val(data.settings.default_bet);
            }
            if (data.settings.plinko_duration) {
                plinkoStepDelay = parseInt(data.settings.plinko_duration) || 350;
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
        
        if (betAmount < 1 || (maxBetEnabled && betAmount > maxBet)) {
            const maxBetText = maxBetEnabled ? '$' + maxBet : 'unlimited';
            $('#result').html('<div class="alert alert-error">Bet must be at least $1' + (maxBetEnabled ? ' and not exceed $' + maxBet : '') + '</div>');
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
                $('#result').html('<div class="alert alert-error">Insufficient funds. Your balance is $' + (data.success ? formatNumber(data.balance) : '0.00') + '. Need $' + totalBet.toFixed(2) + '</div>');
                return;
            }
            
            isDropping = true;
            $('#dropBtn').prop('disabled', true).text('DROPPING...').addClass('game-disabled');
            $('.game-container button, .game-container .btn').not('[onclick*="openModal"]').addClass('game-disabled');
            $('#result').html('');
            
            // Add beforeunload warning to prevent navigation during game
            $(window).on('beforeunload', function() {
                if (isDropping) {
                    return 'A Plinko game is in progress. If you leave now, your bet may be lost. Are you sure you want to leave?';
                }
            });
            
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
                    $('#dropBtn').prop('disabled', false).text('DROP BALL(S)').removeClass('game-disabled');
                    $('.game-container button, .game-container .btn').not('[onclick*="openModal"]').removeClass('game-disabled');
                    $(window).off('beforeunload');
                    return;
                }
                
                $('#balance').text(formatNumber(data.balance));
                
                // Create multiple balls with slight horizontal offset for visual separation
                const board = $('#plinkoBoard');
                activeBalls = [];
                let totalWins = 0;
                let completedBalls = 0;
                
                for (let i = 0; i < ballCount; i++) {
                    const ballId = 'ball_' + Date.now() + '_' + i;
                    const ballElement = $('<div class="plinko-ball"></div>');
                    ballElement.attr('data-ball-id', ballId);
                    // Add counter element for overlapping balls
                    const counterElement = $('<span class="ball-counter"></span>');
                    ballElement.append(counterElement);
                    board.append(ballElement);
                    
                    // Start at the top of the triangle (row 0)
                    // For row 0, there's only 1 peg at col 0, so ball must start there
                    const ballData = {
                        id: ballId,
                        element: ballElement,
                        counterElement: counterElement,
                        currentRow: 0,
                        currentCol: 0, // Column index within the current row (0 to currentRow)
                        completed: false,
                        stepDelay: 0, // Delay counter for step-by-step movement
                        moveHistory: [] // Track moves for debugging
                    };
                    
                    console.log(`[Plinko Debug] ===== Ball ${ballId} created at row 0, col 0 =====`);
                    
                    activeBalls.push(ballData);
                    updateBallPosition(ballData);
                }
                // Update counters after all balls are created
                updateBallCounters();
                
                // Animate all balls dropping step by step
                const stepDelay = plinkoStepDelay; // Milliseconds between steps (slower movement)
                
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
                        
                        // Check if ball reached the last row of pegs (should stop here, not go to row 8)
                        // rows = 8 means pegs are at rows 0-7, so we stop when we reach row 7
                        if (ball.currentRow >= rows - 1) {
                            // Ball reached the last row of pegs, determine final slot
                            // The ball is at row (rows-1) which is the last row of pegs
                            // Map the column position directly to slot index (0 to cols-1)
                            const lastPegRow = rows - 1; // Row 7 (last row of pegs)
                            const ballCol = Math.round(ball.currentCol);
                            
                            // Map column from last peg row (0 to 7) to slots (0 to 8)
                            // Row 7 has 8 pegs (cols 0-7), we have 9 slots (0-8)
                            const colRatio = ballCol / Math.max(1, lastPegRow);
                            const finalSlot = Math.round(colRatio * (cols - 1));
                            const finalSlotClamped = Math.max(0, Math.min(cols - 1, finalSlot));
                            
                            console.log(`[Plinko Debug] Ball reached last peg row (${lastPegRow}) at col ${ballCol} -> slot ${finalSlotClamped}`);
                            const multiplier = multipliers[finalSlotClamped];
                            const winAmount = betAmount * multiplier;
                            
                            // Debug: Print move statistics
                            const leftMoves = ball.moveHistory.filter(m => m.direction === 'LEFT').length;
                            const rightMoves = ball.moveHistory.filter(m => m.direction === 'RIGHT').length;
                            const stayMoves = ball.moveHistory.filter(m => m.direction === 'STAY').length;
                            console.log(`[Plinko Debug] ===== Ball ${ball.id} completed =====`);
                            console.log(`[Plinko Debug] Total moves: ${ball.moveHistory.length}`);
                            console.log(`[Plinko Debug] LEFT moves: ${leftMoves}, RIGHT moves: ${rightMoves}, STAY moves: ${stayMoves}`);
                            console.log(`[Plinko Debug] Final slot: ${finalSlotClamped}, Multiplier: ${multiplier}x`);
                            console.log(`[Plinko Debug] ==========================================`);
                            
                            totalWins += winAmount;
                            ball.completed = true;
                            ball.multiplier = multiplier; // Store multiplier for this ball
                            
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
                                        // Collect multipliers from all balls
                                        const ballMultipliers = activeBalls.map(b => b.multiplier).filter(m => m !== undefined);
                                        const avgMultiplier = ballMultipliers.length > 0 
                                            ? (ballMultipliers.reduce((a, b) => a + b, 0) / ballMultipliers.length).toFixed(2)
                                            : '0';
                                        const multiplierText = ballMultipliers.length === 1 
                                            ? `${ballMultipliers[0]}x`
                                            : `${avgMultiplier}x avg (${ballMultipliers.map(m => m.toFixed(1)).join(', ')})`;
                                        
                                        $.post('../api/api.php?action=updateBalance', {
                                            amount: totalWins,
                                            type: 'win',
                                            description: `Plinko win: ${ballCount} ball${ballCount > 1 ? 's' : ''} (${multiplierText})`,
                                            game: 'plinko'
                                        }, function(data) {
                                            if (data.success) {
                                                $('#balance').text(formatNumber(data.balance));
                                                const netResult = totalWins - totalBet;
                                                if (netResult > 0) {
                                                    $('#result').html(`<div class="alert alert-success">🎉 You won $${totalWins.toFixed(2)}! Net: +$${netResult.toFixed(2)}</div>`);
                                                } else if (netResult < 0) {
                                                    $('#result').html(`<div class="alert alert-error">You won $${totalWins.toFixed(2)}, but lost $${Math.abs(netResult).toFixed(2)} overall</div>`);
                                                } else {
                                                    $('#result').html(`<div class="alert">Break even! Won $${totalWins.toFixed(2)}</div>`);
                                                }
                                                // Update stats after win
                                                updateWinRateStats('plinko');
                                            }
                                        }, 'json');
                                    } else {
                                        $('#result').html(`<div class="alert alert-error">Better luck next time! Lost $${totalBet.toFixed(2)}</div>`);
                                        // Update stats after loss
                                        updateWinRateStats('plinko');
                                    }
                                    
                                    activeBalls = [];
                                    isDropping = false;
                                    $('#dropBtn').prop('disabled', false).text('DROP BALL(S)').removeClass('game-disabled');
                                    $('.game-container button, .game-container .btn').not('[onclick*="openModal"]').removeClass('game-disabled');
                                    
                                    // Remove beforeunload warning
                                    $(window).off('beforeunload');
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
                        
                        // In Plinko, from a peg at (row r, col c), the ball can bounce to:
                        // - The peg directly below-left: (r+1, c) - same column, directly below
                        // - The peg directly below-right: (r+1, c+1) - one column to the right
                        // So from (previousRow, previousCol), can go to:
                        // - (newRow, previousCol) - below-left (same column)
                        // - (newRow, previousCol + 1) - below-right (one column right)
                        
                        let newCol;
                        const canMoveLeft = previousCol <= newRow; // Can move to col (previousCol) - directly below
                        const canMoveRight = (previousCol + 1) <= newRow; // Can move to col (previousCol + 1) - one right
                        
                        // DEBUG: Log the decision process
                        console.log(`[Plinko Debug] Ball at row ${previousRow}, col ${previousCol} -> moving to row ${newRow}`);
                        console.log(`[Plinko Debug] canMoveLeft: ${canMoveLeft} (to col ${previousCol}), canMoveRight: ${canMoveRight} (to col ${previousCol + 1})`);
                        
                        if (!canMoveLeft && !canMoveRight) {
                            // Should never happen, but safety fallback
                            console.warn(`[Plinko Debug] WARNING: Cannot move left or right! Using fallback.`);
                            newCol = Math.max(0, Math.min(newRow, previousCol));
                        } else if (!canMoveLeft) {
                            // Can only move right (to previousCol + 1)
                            console.log(`[Plinko Debug] Only right available -> col ${previousCol + 1}`);
                            newCol = previousCol + 1;
                        } else if (!canMoveRight) {
                            // Can only move left (to same column, previousCol)
                            console.log(`[Plinko Debug] Only left available -> col ${previousCol}`);
                            newCol = previousCol;
                        } else {
                            // Can move either left or right - randomly choose (50/50 chance)
                            // Use Math.random() which should be uniformly distributed
                            const randomValue = Math.random();
                            console.log(`[Plinko Debug] Random value: ${randomValue.toFixed(4)} (${randomValue < 0.5 ? 'LEFT' : 'RIGHT'})`);
                            if (randomValue < 0.5) {
                                // Move left (to same column, previousCol)
                                newCol = previousCol;
                                console.log(`[Plinko Debug] Chose LEFT -> col ${newCol}`);
                            } else {
                                // Move right (to previousCol + 1)
                                newCol = previousCol + 1;
                                console.log(`[Plinko Debug] Chose RIGHT -> col ${newCol}`);
                            }
                        }
                        
                        // Final validation - ensure result is within bounds
                        newCol = Math.max(0, Math.min(maxColForNewRow, newCol));
                        
                        // Track the move direction for statistics
                        const moveDirection = newCol < previousCol ? 'LEFT' : (newCol > previousCol ? 'RIGHT' : 'STAY');
                        ball.moveHistory.push({
                            from: {row: previousRow, col: previousCol},
                            to: {row: newRow, col: newCol},
                            direction: moveDirection
                        });
                        
                        console.log(`[Plinko Debug] Final position: row ${newRow}, col ${newCol} (${moveDirection})`);
                        console.log(`[Plinko Debug] ---`);
                        
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
        
        // Check if ball is at or past the last peg row (rows - 1 = row 7)
        if (currentRow >= rows - 1) {
            // Ball reached last peg row, map to final slot position
            // Use the same calculation as slot positioning for alignment
            const lastPegRow = rows - 1; // Row 7 (last row of pegs)
            const bottomPegsInRow = lastPegRow + 1; // 8 pegs in last row
            const bottomRowWidth = bottomPegsInRow * 10; // Matches wider spacing
            const bottomStartOffset = (100 - bottomRowWidth) / 2;
            const slotAreaStart = bottomStartOffset - 2;
            const slotAreaEnd = bottomStartOffset + bottomRowWidth + 2;
            const slotAreaWidth = slotAreaEnd - slotAreaStart;
            
            // Map column position from last peg row (0 to 7) to slot area
            const colRatio = currentCol / Math.max(1, lastPegRow);
            const slotPosition = slotAreaStart + (colRatio * slotAreaWidth);
            const topPercent = (lastPegRow * 14 + 10) + 3; // Position at last peg row, slightly below pegs
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
        
        // Update counters after position update
        updateBallCounters();
    }
    
    // Update counters on balls that are at the same position
    function updateBallCounters() {
        if (!activeBalls || activeBalls.length === 0) return;
        
        // Group balls by position (row, col rounded to avoid floating point issues)
        const positionGroups = {};
        activeBalls.forEach(function(ball) {
            if (ball.completed) return;
            
            const row = ball.currentRow;
            // Round column to nearest 0.01 to handle floating point precision
            const col = Math.round(ball.currentCol * 100) / 100;
            const positionKey = `${row},${col}`;
            
            if (!positionGroups[positionKey]) {
                positionGroups[positionKey] = [];
            }
            positionGroups[positionKey].push(ball);
        });
        
        // Update counters for each ball
        activeBalls.forEach(function(ball) {
            if (ball.completed || !ball.counterElement) return;
            
            const row = ball.currentRow;
            const col = Math.round(ball.currentCol * 100) / 100;
            const positionKey = `${row},${col}`;
            const ballsAtPosition = positionGroups[positionKey] || [];
            
            if (ballsAtPosition.length > 1) {
                // Multiple balls at this position - show counter with number of balls
                ball.counterElement.text(ballsAtPosition.length);
                ball.counterElement.css('display', 'flex');
            } else {
                // Only one ball at this position - hide counter
                ball.counterElement.css('display', 'none');
            }
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
                $('#balance').text(formatNumber(data.balance));
            }
        }, 'json');
    }, 5000);
});
