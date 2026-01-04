$(document).ready(function() {
    let numberBets = []; // Array of {number: int, amount: float}
    let colorBets = []; // Array of {type: string, amount: float, multiplier: int}
    let isSpinning = false;
    let currentRotation = 0;
    let maxBet = 100;
    let currentRound = null;
    let pollInterval = null;
    let bettingCountdownInterval = null;
    
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
        
        let targetStartingAngle = (360 - normalizedRotation) % 360;
        if (targetStartingAngle < 0) targetStartingAngle += 360;
        
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
            if (isSpinning || (currentRound && currentRound.status !== 'betting')) return;
            const index = parseInt($(this).data('index'));
            const type = $(this).data('type');
            if (type === 'number') {
                numberBets.splice(index, 1);
            } else {
                colorBets.splice(index, 1);
                $('.bet-btn').removeClass('active');
            }
            updateActiveBetsDisplay();
        });
    }
    
    // Poll server for current round state
    function pollRoundState() {
        $.get('../api/api.php?action=getRouletteRound', function(data) {
            if (data.success) {
                const round = data.round;
                
                if (!round) {
                    // No active round - worker might not be running
                    $('#rouletteResult').html('Waiting for next round...<br><small style="color: #999;">Make sure the game rounds worker is running</small>');
                    $('#spinBtn').hide();
                    $('#roundCountdown').show();
                    $('#countdownText').html('Waiting for next round...');
                    currentRound = null;
                    // Continue polling to catch when a round starts
                    return;
                }
                
                // Check if round changed
                const roundChanged = !currentRound || currentRound.id !== round.id || currentRound.status !== round.status;
                currentRound = round;
                
                // Update round info display
                if (round.status === 'betting') {
                    const timeLeft = round.time_until_betting_ends || 0;
                    $('#rouletteResult').html(`Round #${round.round_number} - Betting ends in ${Math.ceil(timeLeft)}s`);
                    // Hide spin button, show countdown in central mode
                    $('#spinBtn').hide();
                    $('#roundCountdown').show();
                    updateBettingCountdown(timeLeft);
                    // Reset spinning state when entering betting
                    if (isSpinning) {
                        isSpinning = false;
                        if (spinAnimationInterval) {
                            clearInterval(spinAnimationInterval);
                            spinAnimationInterval = null;
                        }
                    }
                } else if (round.status === 'spinning') {
                    $('#spinBtn').hide();
                    $('#roundCountdown').show();
                    const timeLeft = round.time_until_finish || 0;
                    if (timeLeft > 0) {
                        $('#countdownText').html(`Spinning... Result in: <span style="font-size: 1.5em; color: #ffc107;">${Math.ceil(timeLeft)}s</span>`);
                    } else {
                        $('#countdownText').html('Spinning...');
                    }
                    // Start spinning animation when entering spinning state
                    if (roundChanged || !isSpinning) {
                        startSpinningAnimation(round);
                    }
                    // Check if result is available
                    if (round.result_number !== null && round.result_number !== undefined) {
                        // Round finished, show result
                        showRoundResult(round.result_number);
                    }
                } else if (round.status === 'finished') {
                    if (round.result_number !== null) {
                        showRoundResult(round.result_number);
                    } else {
                        // Reset if no result yet
                        isSpinning = false;
                    }
                    // Wait for next round - continue polling
                }
                
                // Load history
                loadHistory();
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Failed to poll round state:', status, error);
            $('#rouletteResult').html('Error connecting to server<br><small style="color: #999;">Check console for details</small>');
        });
    }
    
    function updateBettingCountdown(seconds) {
        if (bettingCountdownInterval) {
            clearInterval(bettingCountdownInterval);
        }
        
        let timeLeft = Math.ceil(seconds);
        const updateCountdown = function() {
            if (currentRound && currentRound.status === 'betting') {
                if (timeLeft > 0) {
                    $('#rouletteResult').html(`Round #${currentRound.round_number} - Betting ends in ${timeLeft}s`);
                    $('#countdownText').html(`Next spin in: <span style="font-size: 1.5em; color: #667eea;">${timeLeft}s</span>`);
                    timeLeft--;
                } else {
                    clearInterval(bettingCountdownInterval);
                    bettingCountdownInterval = null;
                    // Poll will update when status changes
                }
            } else {
                clearInterval(bettingCountdownInterval);
                bettingCountdownInterval = null;
                if (currentRound && currentRound.status === 'spinning') {
                    $('#countdownText').html('Spinning...');
                }
            }
        };
        
        updateCountdown();
        bettingCountdownInterval = setInterval(updateCountdown, 1000);
    }
    
    let spinAnimationInterval = null;
    
    function startSpinningAnimation(round) {
        if (isSpinning && spinAnimationInterval) return;
        
        isSpinning = true;
        $('#spinBtn').prop('disabled', true).text('SPINNING...');
        
        // Clear any existing animation
        if (spinAnimationInterval) {
            clearInterval(spinAnimationInterval);
        }
        
        // Start wheel rotation animation
        const fullSpins = 5 + Math.random() * 3;
        const randomRotation = Math.random() * 360;
        let totalRotation = (fullSpins * 360) + randomRotation;
        
        // Reset wheel first
        $('#rouletteWheel').css({
            transition: 'none',
            transform: 'rotate(0deg)'
        });
        
        // Force reflow
        $('#rouletteWheel')[0].offsetHeight;
        
        // Animate wheel
        $('#rouletteWheel').css({
            transition: 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)',
            transform: `rotate(${totalRotation}deg)`
        });
        
        // Show spinning numbers in result display
        let spinCount = 0;
        const maxSpins = 40;
        spinAnimationInterval = setInterval(function() {
            const randomNum = Math.floor(Math.random() * 37);
            const color = getNumberColor(randomNum);
            $('#rouletteResult').html(`<span class="roulette-number roulette-${color}">${randomNum}</span>`);
            spinCount++;
            
            if (spinCount >= maxSpins) {
                clearInterval(spinAnimationInterval);
                spinAnimationInterval = null;
            }
        }, 100);
    }
    
    function showRoundResult(resultNum) {
        // Clear spinning animation
        if (spinAnimationInterval) {
            clearInterval(spinAnimationInterval);
            spinAnimationInterval = null;
        }
        
        const resultColor = getNumberColor(resultNum);
        $('#rouletteResult').html(`<span class="roulette-number roulette-${resultColor}">${resultNum}</span>`);
        
        // Animate wheel to result
        animateWheelToResult(resultNum);
        
        // Check user's bets and show results
        if (currentRound && currentRound.user_bets) {
            checkUserBets(currentRound.user_bets, resultNum);
        }
        
        isSpinning = false;
    }
    
    function animateWheelToResult(resultNum) {
        const anglePerNumber = 360 / rouletteNumbers.length;
        const winningIndex = rouletteNumbers.findIndex(n => n.num === resultNum);
        const pocketStartAngle = winningIndex * anglePerNumber;
        
        let rotationToTop = 0;
        let found = false;
        
        for (let i = 0; i < rouletteNumbers.length; i++) {
            const testRot = i * anglePerNumber;
            const testNum = getNumberAtTop(testRot);
            if (testNum === resultNum) {
                rotationToTop = testRot;
                found = true;
                break;
            }
        }
        
        if (!found) {
            rotationToTop = (360 - pocketStartAngle) % 360;
        }
        
        const fullSpins = Math.floor(5 + Math.random() * 3);
        let totalRotation = (fullSpins * 360) + rotationToTop;
        currentRotation = totalRotation % 360;
        
        $('#rouletteWheel').css({
            transition: 'none',
            transform: 'rotate(0deg)'
        });
        
        $('#rouletteWheel')[0].offsetHeight;
        
        $('#rouletteWheel').css({
            transition: 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)',
            transform: `rotate(${totalRotation}deg)`
        });
    }
    
    function checkUserBets(userBets, resultNum) {
        let totalWin = 0;
        let totalLoss = 0;
        const winningBets = [];
        const losingBets = [];
        
        userBets.forEach(function(bet) {
            let won = false;
            let payout = 0;
            
            if (bet.bet_type === 'number') {
                if (parseInt(bet.bet_value) === resultNum) {
                    won = true;
                    payout = bet.amount * bet.multiplier;
                }
            } else if (bet.bet_type === 'color' || bet.bet_type === 'range') {
                won = checkColorBetWin(bet.bet_value, resultNum);
                if (won) {
                    payout = bet.amount * bet.multiplier;
                }
            }
            
            if (won) {
                totalWin += payout;
                winningBets.push(bet);
            } else {
                totalLoss += bet.amount;
                losingBets.push(bet);
            }
        });
        
        const netResult = totalWin - totalLoss;
        
        if (totalWin > 0) {
            let message = `<div class="alert alert-success">🎉 You won $${netResult.toFixed(2)}!<br>`;
            if (winningBets.length > 0) {
                message += `Winning bets: ${winningBets.length}<br>`;
            }
            if (losingBets.length > 0) {
                message += `Lost: $${totalLoss.toFixed(2)} on ${losingBets.length} bet(s)`;
            }
            message += '</div>';
            $('#result').html(message);
            updateWinRateStats('roulette');
        } else if (totalLoss > 0) {
            $('#result').html(`<div class="alert alert-error">Lost $${totalLoss.toFixed(2)} on ${losingBets.length} bet(s)</div>`);
            updateWinRateStats('roulette');
        }
        
        // Clear local bets
        numberBets = [];
        colorBets = [];
        updateActiveBetsDisplay();
        
        // Update balance
        updateBalance();
    }
    
    function loadHistory() {
        $.get('../api/api.php?action=getRouletteHistory&limit=10', function(data) {
            if (data.success && data.history) {
                // Could display history here if needed
            }
        }, 'json');
    }
    
    // Handle color/range bet buttons
    $('.bet-btn').click(function() {
        if (!currentRound || currentRound.status !== 'betting') {
            $('#result').html('<div class="alert alert-error">Betting is not open</div>');
            return;
        }
        
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
            colorBets[existingIndex].amount = amount;
        } else {
            colorBets.push({type: betType, amount: amount, multiplier: multiplier});
        }
        
        $('.bet-btn').removeClass('active');
        $(this).addClass('active');
        
        updateActiveBetsDisplay();
        $('#result').html('');
        
        // In central mode, auto-place bets
        if (currentRound && currentRound.status === 'betting') {
            placeAllBets();
        }
    });
    
    $('#addNumberBetBtn').click(function() {
        if (!currentRound || currentRound.status !== 'betting') {
            $('#result').html('<div class="alert alert-error">Betting is not open</div>');
            return;
        }
        
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
            numberBets[existingIndex].amount = amount;
        } else {
            numberBets.push({number: number, amount: amount});
        }
        
        updateActiveBetsDisplay();
        $('#numberBet').val('');
        $('#result').html('');
        
        // In central mode, auto-place bets
        if (currentRound && currentRound.status === 'betting') {
            placeAllBets();
        }
    });
    
    $('#numberBet').keypress(function(e) {
        if (e.which === 13) {
            $('#addNumberBetBtn').click();
        }
    });
    
    // Place all bets when spin button is clicked (during betting phase)
    // Note: In central mode, this button is hidden and bets are placed automatically
    $('#spinBtn').click(function() {
        if (!currentRound || currentRound.status !== 'betting') {
            return;
        }
        
        if (numberBets.length === 0 && colorBets.length === 0) {
            $('#result').html('<div class="alert alert-error">Please add at least one bet</div>');
            return;
        }
        
        // In central mode, just place bets (spin happens automatically)
        placeAllBets();
    });
    
    // Function to place all bets (used in central mode)
    function placeAllBets() {
        if (!currentRound || currentRound.status !== 'betting') {
            return;
        }
        
        if (numberBets.length === 0 && colorBets.length === 0) {
            return;
        }
        
        const totalBetAmount = numberBets.reduce((sum, bet) => sum + bet.amount, 0) + 
                              colorBets.reduce((sum, bet) => sum + bet.amount, 0);
        
        if (totalBetAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Total bet amount ($' + totalBetAmount.toFixed(2) + ') exceeds maximum of $' + formatNumber(maxBet) + '</div>');
            return;
        }
        
        // Check balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < totalBetAmount) {
                $('#result').html('<div class="alert alert-error">Insufficient funds</div>');
                return;
            }
            
            // Place all bets
            let betsPlaced = 0;
            let betsToPlace = numberBets.length + colorBets.length;
            
            function placeNextBet() {
                if (betsPlaced >= betsToPlace) {
                    // All bets placed
                    updateBalance();
                    $('#result').html('<div class="alert alert-success">All bets placed!</div>');
                    return;
                }
                
                let betData = null;
                if (betsPlaced < numberBets.length) {
                    const bet = numberBets[betsPlaced];
                    betData = {
                        bet_type: 'number',
                        bet_value: bet.number.toString(),
                        amount: bet.amount,
                        multiplier: 36
                    };
                } else {
                    const bet = colorBets[betsPlaced - numberBets.length];
                    betData = {
                        bet_type: bet.type === 'red' || bet.type === 'black' || bet.type === 'green' ? 'color' : 'range',
                        bet_value: bet.type,
                        amount: bet.amount,
                        multiplier: bet.multiplier
                    };
                }
                
                $.post('../api/api.php?action=placeRouletteBet', betData, function(data) {
                    if (data.success) {
                        betsPlaced++;
                        placeNextBet();
                    } else {
                        $('#result').html('<div class="alert alert-error">' + (data.message || 'Failed to place bet') + '</div>');
                        // Stop trying to place more bets
                        betsPlaced = betsToPlace;
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error('Failed to place bet:', status, error);
                    $('#result').html('<div class="alert alert-error">Error placing bet. Please try again.</div>');
                    betsPlaced = betsToPlace;
                });
            }
            
            placeNextBet();
        }, 'json');
    });
    
    function updateBalance() {
        $.get('../api/api.php?action=getBalance', function(data) {
            if (data.success) {
                $('#balance').text(formatNumber(data.balance));
            }
        }, 'json');
    }
    
    function updateWinRateStats(game) {
        $.get('../api/api.php?action=getWinRates&game=' + game, function(data) {
            if (data.success && data.winRate) {
                $('#winRate').text(data.winRate.rate || 0);
                $('#gamesPlayed').text(data.winRate.total || 0);
                $('#wins').text(data.winRate.wins || 0);
            }
        }, 'json');
    }
    
    // Start polling
    pollRoundState();
    pollInterval = setInterval(pollRoundState, 2000); // Poll every 2 seconds
    
    // Update balance periodically
    setInterval(updateBalance, 5000);
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
        if (bettingCountdownInterval) {
            clearInterval(bettingCountdownInterval);
        }
    });
});
