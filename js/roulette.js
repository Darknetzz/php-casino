$(document).ready(function() {
    let numberBets = []; // Array of {number: int, amount: float}
    let isSpinning = false;
    let currentRotation = 0;
    let maxBet = 100;
    
    // Load max bet and default bet from settings
    $.get('../api/api.php?action=getSettings', function(data) {
        if (data.success) {
            if (data.settings.max_bet) {
                maxBet = data.settings.max_bet;
                $('#maxBet').text(maxBet);
                $('#numberBetAmount').attr('max', maxBet);
            }
            if (data.settings.default_bet) {
                $('#numberBetAmount').val(data.settings.default_bet);
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
        // After rotating by rotation degrees, a pocket that started at angle A
        // will be at position (A + rotation) % 360
        // We want to find which pocket is at 0 (top)
        // So we need: (A + rotation) % 360 = 0, which means A = (360 - rotation) % 360
        const targetAngle = (360 - (rotation % 360)) % 360;
        const targetIndex = Math.round(targetAngle / anglePerNumber) % rouletteNumbers.length;
        return rouletteNumbers[targetIndex].num;
    }
    
    function updateActiveBetsDisplay() {
        const betsList = $('#activeBets');
        if (numberBets.length === 0) {
            betsList.html('<p style="color: #666; font-style: italic;">No bets placed yet</p>');
            $('#totalBetAmount').hide();
            return;
        }
        
        let html = '<div class="bets-list">';
        let totalBet = 0;
        numberBets.forEach(function(bet, index) {
            totalBet += bet.amount;
            html += `<div class="bet-item" data-index="${index}">
                <span>Number ${bet.number}: $${bet.amount.toFixed(2)}</span>
                <button class="btn-remove-bet" data-index="${index}">×</button>
            </div>`;
        });
        html += '</div>';
        betsList.html(html);
        
        $('#totalBetValue').text(totalBet.toFixed(2));
        $('#totalBetAmount').show();
        
        // Add click handlers for remove buttons
        $('.btn-remove-bet').click(function() {
            if (isSpinning) return;
            const index = parseInt($(this).data('index'));
            numberBets.splice(index, 1);
            updateActiveBetsDisplay();
        });
    }
    
    $('#addNumberBetBtn').click(function() {
        if (isSpinning) return;
        
        const number = parseInt($('#numberBet').val());
        const amount = parseFloat($('#numberBetAmount').val());
        
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
    $('#numberBet, #numberBetAmount').keypress(function(e) {
        if (e.which === 13) {
            $('#addNumberBetBtn').click();
        }
    });
    
    $('#spinBtn').click(function() {
        if (isSpinning) return;
        
        if (numberBets.length === 0) {
            $('#result').html('<div class="alert alert-error">Please add at least one bet before spinning</div>');
            return;
        }
        
        // Calculate total bet amount
        const totalBetAmount = numberBets.reduce((sum, bet) => sum + bet.amount, 0);
        
        // Check if user has enough balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < totalBetAmount) {
                $('#result').html('<div class="alert alert-error">Insufficient funds. Your balance is $' + (data.success ? parseFloat(data.balance).toFixed(2) : '0.00') + ', but you need $' + totalBetAmount.toFixed(2) + '</div>');
                return;
            }
            
            isSpinning = true;
            $('#spinBtn').prop('disabled', true);
            $('#result').html('');
            
            // Determine winning number
            const resultNum = Math.floor(Math.random() * 37);
            const resultColor = getNumberColor(resultNum);
            
            // Calculate rotation needed to land on winning number
            // The key insight: when we rotate the wheel container clockwise by R degrees,
            // a pocket that started at angle A will visually appear at angle (A - R) % 360
            // (because the wheel rotates, but the pointer stays fixed)
            // We want the winning pocket to appear at 0 (top, where pointer is)
            // So: (A - R) % 360 = 0, which means R = A (plus full spins)
            const winningIndex = rouletteNumbers.findIndex(n => n.num === resultNum);
            const anglePerNumber = 360 / rouletteNumbers.length;
            const pocketStartAngle = winningIndex * anglePerNumber;
            
            // To get pocket at angle A to appear at 0: rotate by A degrees clockwise
            // (plus full spins for animation effect)
            const fullSpins = 5 + Math.random() * 3; // 5-8 full spins
            const totalRotation = (fullSpins * 360) + pocketStartAngle;
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
                const winningBets = [];
                const losingBets = [];
                
                numberBets.forEach(function(bet) {
                    if (bet.number === resultNum) {
                        // Win! 36x multiplier
                        const winAmount = bet.amount * 36;
                        totalWin += winAmount;
                        winningBets.push({number: bet.number, amount: bet.amount, win: winAmount});
                    } else {
                        // Loss
                        totalLoss += bet.amount;
                        losingBets.push({number: bet.number, amount: bet.amount});
                    }
                });
                
                // Calculate net result
                const netResult = totalWin - totalLoss;
                
                if (netResult > 0) {
                    // Overall win
                    $.post('../api/api.php?action=updateBalance', {
                        amount: netResult,
                        type: 'win',
                        description: `Roulette win: ${winningBets.length} winning bet(s) on ${resultNum}`
                    }, function(data) {
                        if (data.success) {
                            $('#balance').text(parseFloat(data.balance).toFixed(2));
                            let message = `<div class="alert alert-success">🎉 You won $${netResult.toFixed(2)}!<br>`;
                            if (winningBets.length > 0) {
                                message += `Winning bets: `;
                                winningBets.forEach(b => message += `#${b.number} ($${b.amount.toFixed(2)} → $${b.win.toFixed(2)}), `);
                                message = message.slice(0, -2) + '<br>';
                            }
                            if (losingBets.length > 0) {
                                message += `Lost: $${totalLoss.toFixed(2)} on ${losingBets.length} bet(s)`;
                            }
                            message += '</div>';
                            $('#result').html(message);
                        }
                    }, 'json');
                } else if (netResult < 0) {
                    // Overall loss
                    $.post('../api/api.php?action=updateBalance', {
                        amount: netResult, // This is negative
                        type: 'bet',
                        description: `Roulette bet: ${numberBets.length} bet(s)`
                    }, function(data) {
                        if (data.success) {
                            $('#balance').text(parseFloat(data.balance).toFixed(2));
                            let message = `<div class="alert alert-error">Lost $${Math.abs(netResult).toFixed(2)}<br>`;
                            if (winningBets.length > 0) {
                                message += `Won: $${totalWin.toFixed(2)} on ${winningBets.length} bet(s)<br>`;
                            }
                            message += `Lost: $${totalLoss.toFixed(2)} on ${losingBets.length} bet(s)</div>`;
                            $('#result').html(message);
                        } else {
                            $('#result').html(`<div class="alert alert-error">${data.message}</div>`);
                        }
                    }, 'json');
                } else {
                    // Break even (shouldn't happen, but handle it)
                    $('#result').html('<div class="alert">Break even!</div>');
                }
                
                isSpinning = false;
                $('#spinBtn').prop('disabled', false);
                // Don't clear bets - they persist
            }
        }, 100);
        }, 'json');
    });
    
    // Update balance periodically
    setInterval(function() {
        $.get('../api/api.php?action=getBalance', function(data) {
            if (data.success) {
                $('#balance').text(parseFloat(data.balance).toFixed(2));
            }
        }, 'json');
    }, 5000);
});
