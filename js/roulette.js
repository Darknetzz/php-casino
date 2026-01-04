$(document).ready(function() {
    let numberBets = []; // Array of {number: int, amount: float}
    let colorBets = []; // Array of {type: string, amount: float, multiplier: int}
    let isSpinning = false;
    let currentRotation = 0;
    let maxBet = 100;
    
    // Load max bet and default bet from settings
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
        }
    }, 'json');
    
    // Roulette numbers with colors (0 is green) - European roulette order
    const rouletteNumbers = [
        {num: 0, color: 'green'}, {num: 32, color: 'red'}, {num: 15, color: 'black'},
        {num: 19, color: 'red'}, {num: 4, color: 'black'}, {num: 21, color: 'red'},
        {num: 2, color: 'black'}, {num: 25, color: 'red'}, {num: 17, color: 'black'},
        {num: 34, color: 'red'}, {num: 6, color: 'black'}, {num: 27, color: 'red'},
        {num: 13, color: 'black'}, {num: 36, color: 'red'}, {num: 11, color: 'black'},
        {num: 30, color: 'red'}, {num: 8, color: 'black'}, {num: 23, color: 'red'},
        {num: 10, color: 'black'}, {num: 5, color: 'red'}, {num: 24, color: 'black'},
        {num: 16, color: 'red'}, {num: 33, color: 'black'}, {num: 1, color: 'red'},
        {num: 20, color: 'black'}, {num: 14, color: 'red'}, {num: 31, color: 'black'},
        {num: 9, color: 'red'}, {num: 22, color: 'black'}, {num: 18, color: 'red'},
        {num: 29, color: 'black'}, {num: 7, color: 'red'}, {num: 28, color: 'black'},
        {num: 12, color: 'red'}, {num: 35, color: 'black'}, {num: 3, color: 'red'},
        {num: 26, color: 'black'}
    ];
    
    // Create the roulette wheel with pockets in outer ring
    function createWheel() {
        const wheel = $('#rouletteWheel');
        wheel.empty();
        
        const totalNumbers = rouletteNumbers.length;
        const anglePerNumber = 360 / totalNumbers;
        const radius = 42; // Distance from center to pocket (percentage)
        
        rouletteNumbers.forEach((item, index) => {
            const angle = index * anglePerNumber;
            const angleRad = (angle - 90) * Math.PI / 180; // Convert to radians, adjust for top start (0 at top)
            
            // Calculate position for pocket on the outer ring
            const x = 50 + radius * Math.cos(angleRad);
            const y = 50 + radius * Math.sin(angleRad);
            
            const pocketDiv = $('<div class="roulette-pocket"></div>');
            pocketDiv.css({
                left: x + '%',
                top: y + '%',
                transform: `translate(-50%, -50%) rotate(${angle}deg)`
            });
            pocketDiv.addClass(`roulette-${item.color}`);
            
            // Create number text, counter-rotated to stay upright
            const numberText = $('<span></span>');
            numberText.text(item.num);
            numberText.css({
                transform: `rotate(${-angle}deg)`,
                display: 'inline-block',
                fontSize: '14px',
                lineHeight: '1',
                fontWeight: 'bold'
            });
            pocketDiv.append(numberText);
            
            pocketDiv.attr('data-number', item.num);
            pocketDiv.attr('data-angle', angle); // Store the angle for calculation
            wheel.append(pocketDiv);
        });
    }
    
    createWheel();
    
    function getNumberColor(num) {
        const entry = rouletteNumbers.find(n => n.num === num);
        return entry ? entry.color : 'black';
    }
    
    // Function to calculate which number is at the top after a given rotation
    function getNumberAtTop(rotation) {
        const anglePerNumber = 360 / rouletteNumbers.length;
        const normalizedRotation = ((rotation % 360) + 360) % 360; // Ensure 0-360
        
        // When wheel rotates clockwise by R, a pocket at starting angle A
        // will be at final angle (A + R) mod 360
        // To find which pocket is at top (0°) after rotation R:
        // We need (A + R) mod 360 = 0, which means A = (360 - R) mod 360
        
        // Calculate which starting angle ends up at 0°
        let targetStartingAngle = (360 - normalizedRotation) % 360;
        if (targetStartingAngle < 0) targetStartingAngle += 360;
        
        // Find which pocket index has this starting angle
        // Pockets are at: 0, anglePerNumber, 2*anglePerNumber, ..., (n-1)*anglePerNumber
        // Round to nearest pocket
        let targetIndex = Math.round(targetStartingAngle / anglePerNumber);
        targetIndex = targetIndex % rouletteNumbers.length;
        if (targetIndex < 0) targetIndex += rouletteNumbers.length;
        
        return rouletteNumbers[targetIndex].num;
    }
    
    function checkColorBetWin(betType, resultNum) {
        const resultColor = getNumberColor(resultNum);
        const isEven = resultNum !== 0 && resultNum % 2 === 0;
        const isOdd = resultNum !== 0 && resultNum % 2 === 1;
        
        switch(betType) {
            case 'red':
                return resultColor === 'red';
            case 'black':
                return resultColor === 'black';
            case 'green':
                return resultNum === 0;
            case 'even':
                return isEven;
            case 'odd':
                return isOdd;
            case 'low':
                return resultNum >= 1 && resultNum <= 18;
            case 'high':
                return resultNum >= 19 && resultNum <= 36;
            default:
                return false;
        }
    }
    
    function updateActiveBetsDisplay() {
        // Update number bets display
        const numberBetsList = $('#activeBets');
        if (numberBets.length === 0) {
            numberBetsList.html('<p>No number bets placed yet</p>');
        } else {
            let html = '<div class="bets-list">';
            numberBets.forEach(function(bet, index) {
                html += `<div class="bet-item" data-index="${index}">
                    <span>Number ${bet.number}: $${bet.amount.toFixed(2)}</span>
                    <button class="btn-remove-bet" data-index="${index}" data-type="number">×</button>
                </div>`;
            });
            html += '</div>';
            numberBetsList.html(html);
        }
        
        // Update color bets display
        const colorBetsList = $('#activeColorBets');
        if (colorBets.length === 0) {
            colorBetsList.html('<p>No color/range bets placed yet</p>');
        } else {
            let html = '<div class="bets-list">';
            colorBets.forEach(function(bet, index) {
                const betName = bet.type.charAt(0).toUpperCase() + bet.type.slice(1);
                let colorClass = '';
                if (bet.type === 'red') {
                    colorClass = 'bet-item-red';
                } else if (bet.type === 'black') {
                    colorClass = 'bet-item-black';
                } else if (bet.type === 'green') {
                    colorClass = 'bet-item-green';
                }
                html += `<div class="bet-item ${colorClass}" data-index="${index}">
                    <span>${betName}: $${bet.amount.toFixed(2)} (${bet.multiplier}x)</span>
                    <button class="btn-remove-bet" data-index="${index}" data-type="color">×</button>
                </div>`;
            });
            html += '</div>';
            colorBetsList.html(html);
        }
        
        // Calculate and display total
        let totalBet = 0;
        numberBets.forEach(bet => totalBet += bet.amount);
        colorBets.forEach(bet => totalBet += bet.amount);
        
        if (totalBet > 0) {
            $('#totalBetValue').text(totalBet.toFixed(2));
            $('#totalBetAmount').show();
        } else {
            $('#totalBetAmount').hide();
        }
        
        // Add click handlers for remove buttons
        $('.btn-remove-bet').click(function() {
            if (isSpinning) return;
            const index = parseInt($(this).data('index'));
            const type = $(this).data('type');
            if (type === 'number') {
                numberBets.splice(index, 1);
            } else {
                colorBets.splice(index, 1);
                // Also remove active class from button
                $('.bet-btn').removeClass('active');
            }
            updateActiveBetsDisplay();
        });
    }
    
    // Handle color/range bet buttons
    $('.bet-btn').click(function() {
        if (isSpinning) return;
        
        const betType = $(this).data('bet');
        const multiplier = parseInt($(this).data('multiplier'));
        const amount = parseFloat($('#betAmount').val());
        
        if (isNaN(amount) || amount < 1 || amount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet amount must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        // Check if this bet type already exists
        const existingIndex = colorBets.findIndex(b => b.type === betType);
        if (existingIndex !== -1) {
            // Update existing bet
            colorBets[existingIndex].amount = amount;
        } else {
            // Add new bet
            colorBets.push({type: betType, amount: amount, multiplier: multiplier});
        }
        
        // Toggle active state
        $('.bet-btn').removeClass('active');
        $(this).addClass('active');
        
        updateActiveBetsDisplay();
        $('#result').html('');
    });
    
    $('#addNumberBetBtn').click(function() {
        if (isSpinning) return;
        
        const number = parseInt($('#numberBet').val());
        const amount = parseFloat($('#betAmount').val());
        
        if (isNaN(number) || number < 0 || number > 36) {
            $('#result').html('<div class="alert alert-error">Please enter a valid number (0-36)</div>');
            return;
        }
        
        if (isNaN(amount) || amount < 1 || amount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet amount must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        // Check if number already has a bet
        const existingIndex = numberBets.findIndex(b => b.number === number);
        if (existingIndex !== -1) {
            // Update existing bet
            numberBets[existingIndex].amount = amount;
        } else {
            // Add new bet
            numberBets.push({number: number, amount: amount});
        }
        
        updateActiveBetsDisplay();
        $('#numberBet').val('');
        $('#result').html('');
    });
    
    // Allow Enter key to add bet
    $('#numberBet').keypress(function(e) {
        if (e.which === 13) {
            $('#addNumberBetBtn').click();
        }
    });
    
    $('#spinBtn').click(function() {
        if (isSpinning) return;
        
        if (numberBets.length === 0 && colorBets.length === 0) {
            $('#result').html('<div class="alert alert-error">Please add at least one bet before spinning</div>');
            return;
        }
        
        // Calculate total bet amount
        const totalBetAmount = numberBets.reduce((sum, bet) => sum + bet.amount, 0) + 
                              colorBets.reduce((sum, bet) => sum + bet.amount, 0);
        
        // Check if total bet amount exceeds maximum
        if (totalBetAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Total bet amount ($' + totalBetAmount.toFixed(2) + ') exceeds maximum of $' + formatNumber(maxBet) + '</div>');
            return;
        }
        
        // Check if user has enough balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < totalBetAmount) {
                $('#result').html('<div class="alert alert-error">Insufficient funds. Your balance is $' + (data.success ? formatNumber(data.balance) : '0.00') + ', but you need $' + totalBetAmount.toFixed(2) + '</div>');
                return;
            }
            
            isSpinning = true;
            $('#spinBtn').prop('disabled', true).text('SPINNING...').addClass('game-disabled');
            $('.game-container button, .game-container .btn').not('[onclick*="openModal"]').addClass('game-disabled');
            $('#result').html('');
            
            // Add beforeunload warning to prevent navigation during game
            $(window).on('beforeunload', function() {
                if (isSpinning) {
                    return 'A roulette game is in progress. If you leave now, your bet may be lost. Are you sure you want to leave?';
                }
            });
            
            // Determine winning number
            const resultNum = Math.floor(Math.random() * 37);
            const resultColor = getNumberColor(resultNum);
            
            // Calculate rotation needed to land on winning number
            const anglePerNumber = 360 / rouletteNumbers.length;
            const winningIndex = rouletteNumbers.findIndex(n => n.num === resultNum);
            const pocketStartAngle = winningIndex * anglePerNumber;
            
            // Use brute force: test all rotations to find the one that puts resultNum at top
            let rotationToTop = 0;
            let found = false;
            
            // Test at each pocket position (rough test first)
            for (let i = 0; i < rouletteNumbers.length; i++) {
                const testRot = i * anglePerNumber;
                const testNum = getNumberAtTop(testRot);
                if (testNum === resultNum) {
                    rotationToTop = testRot;
                    found = true;
                    break;
                }
            }
            
            // If not found, try with small offsets around each pocket
            if (!found) {
                for (let i = 0; i < rouletteNumbers.length; i++) {
                    const baseRot = i * anglePerNumber;
                    for (let offset = -anglePerNumber/4; offset <= anglePerNumber/4; offset += 1) {
                        const testRot = (baseRot + offset + 360) % 360;
                        const testNum = getNumberAtTop(testRot);
                        if (testNum === resultNum) {
                            rotationToTop = testRot;
                            found = true;
                            break;
                        }
                    }
                    if (found) break;
                }
            }
            
            if (!found) {
                // Fallback: try the calculated rotation
                rotationToTop = (360 - pocketStartAngle) % 360;
            }
            
            // Add full spins for animation (must be integer to preserve rotationToTop mod 360)
            const fullSpins = Math.floor(5 + Math.random() * 3); // 5-7 full spins (integer)
            let totalRotation = (fullSpins * 360) + rotationToTop;
            
            currentRotation = totalRotation % 360;
            
            // Reset wheel to 0 first to ensure we start from a known position
            $('#rouletteWheel').css({
                transition: 'none',
                transform: 'rotate(0deg)'
            });
            
            // Force a reflow to apply the reset
            $('#rouletteWheel')[0].offsetHeight;
            
            // Now animate to the target rotation
            $('#rouletteWheel').css({
                transition: 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)',
                transform: `rotate(${totalRotation}deg)`
            });
            
            // After animation completes, verify and correct if needed (silent - no console logs)
            $('#rouletteWheel').one('transitionend', function() {
                const finalRotation = totalRotation % 360;
                const calculatedNumber = getNumberAtTop(finalRotation);
                
                if (calculatedNumber !== resultNum) {
                    // Need to correct - calculate adjustment needed
                    const actualIndex = rouletteNumbers.findIndex(n => n.num === calculatedNumber);
                    const winningIndex = rouletteNumbers.findIndex(n => n.num === resultNum);
                    let indexDiff = (winningIndex - actualIndex + rouletteNumbers.length) % rouletteNumbers.length;
                    const angleAdjustment = indexDiff * anglePerNumber;
                    
                    // Update totalRotation for the correction
                    totalRotation = totalRotation + angleAdjustment;
                    
                    // Apply small corrective rotation
                    $('#rouletteWheel').css({
                        transition: 'transform 0.3s ease-out',
                        transform: `rotate(${totalRotation}deg)`
                    });
                }
            });
            
        
        // Show spinning result text
        let spinCount = 0;
        const maxSpins = 40;
        const spinInterval = setInterval(function() {
            const randomNum = Math.floor(Math.random() * 37);
            const color = getNumberColor(randomNum);
            $('#rouletteResult').html(`<span class="roulette-number roulette-${color}">${randomNum}</span>`);
            spinCount++;
            
            if (spinCount >= maxSpins) {
                clearInterval(spinInterval);
                
                // Show final result
                $('#rouletteResult').html(`<span class="roulette-number roulette-${resultColor}">${resultNum}</span>`);
                
                // Calculate wins and losses for all bets
                let totalWin = 0;
                let totalLoss = 0;
                const winningNumberBets = [];
                const losingNumberBets = [];
                const winningColorBets = [];
                const losingColorBets = [];
                
                // Check number bets
                numberBets.forEach(function(bet) {
                    if (bet.number === resultNum) {
                        // Win! 36x multiplier
                        const winAmount = bet.amount * 36;
                        totalWin += winAmount;
                        winningNumberBets.push({number: bet.number, amount: bet.amount, win: winAmount});
                    } else {
                        // Loss
                        totalLoss += bet.amount;
                        losingNumberBets.push({number: bet.number, amount: bet.amount});
                    }
                });
                
                // Check color/range bets
                colorBets.forEach(function(bet) {
                    if (checkColorBetWin(bet.type, resultNum)) {
                        // Win!
                        const winAmount = bet.amount * bet.multiplier;
                        totalWin += winAmount;
                        winningColorBets.push({type: bet.type, amount: bet.amount, win: winAmount, multiplier: bet.multiplier});
                    } else {
                        // Loss
                        totalLoss += bet.amount;
                        losingColorBets.push({type: bet.type, amount: bet.amount});
                    }
                });
                
                // Calculate net result and total bet amount
                const netResult = totalWin - totalLoss;
                const totalBets = numberBets.length + colorBets.length;
                const totalWins = winningNumberBets.length + winningColorBets.length;
                
                // Calculate total amount bet (sum of all bet amounts)
                let totalBetAmount = 0;
                numberBets.forEach(b => totalBetAmount += b.amount);
                colorBets.forEach(b => totalBetAmount += b.amount);
                
                // Always record the bet first
                $.post('../api/api.php?action=updateBalance', {
                    amount: -totalBetAmount,
                    type: 'bet',
                    description: `Roulette bet: ${totalBets} bet(s)`,
                    game: 'roulette'
                }, function(betData) {
                    if (betData.success) {
                        if (totalWin > 0) {
                            // Record the win
                            $.post('../api/api.php?action=updateBalance', {
                                amount: totalWin,
                                type: 'win',
                                description: `Roulette win: ${totalWins} winning bet(s)`,
                                game: 'roulette'
                            }, function(winData) {
                                if (winData.success) {
                                    $('#balance').text(formatNumber(winData.balance));
                                    let message = `<div class="alert alert-success">🎉 You won $${netResult.toFixed(2)}!<br>`;
                                    if (winningNumberBets.length > 0) {
                                        message += `Winning number bets: `;
                                        winningNumberBets.forEach(b => message += `#${b.number} ($${b.amount.toFixed(2)} → $${b.win.toFixed(2)}), `);
                                        message = message.slice(0, -2) + '<br>';
                                    }
                                    if (winningColorBets.length > 0) {
                                        message += `Winning color/range bets: `;
                                        winningColorBets.forEach(b => {
                                            const betName = b.type.charAt(0).toUpperCase() + b.type.slice(1);
                                            message += `${betName} ($${b.amount.toFixed(2)} → $${b.win.toFixed(2)}), `;
                                        });
                                        message = message.slice(0, -2) + '<br>';
                                    }
                                    if (losingNumberBets.length > 0 || losingColorBets.length > 0) {
                                        message += `Lost: $${totalLoss.toFixed(2)} on ${losingNumberBets.length + losingColorBets.length} bet(s)`;
                                    }
                                    message += '</div>';
                                    $('#result').html(message);
                                    // Update stats after win
                                    updateWinRateStats('roulette');
                                }
                            }, 'json');
                        } else {
                            // Loss - bet already recorded above
                            $('#balance').text(formatNumber(betData.balance));
                            let message = `<div class="alert alert-error">Lost $${totalBetAmount.toFixed(2)}<br>`;
                            message += `Lost: $${totalLoss.toFixed(2)} on ${losingNumberBets.length + losingColorBets.length} bet(s)</div>`;
                            $('#result').html(message);
                            // Update stats after loss
                            updateWinRateStats('roulette');
                        }
                    } else {
                        $('#result').html(`<div class="alert alert-error">${betData.message}</div>`);
                    }
                }, 'json');
                
                isSpinning = false;
                $('#spinBtn').prop('disabled', false).text('SPIN').removeClass('game-disabled');
                $('.game-container button, .game-container .btn').not('[onclick*="openModal"]').removeClass('game-disabled');
                
                // Remove beforeunload warning
                $(window).off('beforeunload');
                
                // Don't clear bets - they persist
            }
        }, 100);
        }, 'json');
    });
    
    // Update balance periodically
    setInterval(function() {
        $.get('../api/api.php?action=getBalance', function(data) {
            if (data.success) {
                $('#balance').text(formatNumber(data.balance));
            }
        }, 'json');
    }, 5000);
});
