$(document).ready(function() {
    let selectedBet = null;
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
        // After rotating by rotation degrees, a pocket that started at angle A
        // will be at position (A + rotation) % 360
        // We want to find which pocket is at 0 (top)
        // So we need: (A + rotation) % 360 = 0, which means A = (360 - rotation) % 360
        const targetAngle = (360 - (rotation % 360)) % 360;
        const targetIndex = Math.round(targetAngle / anglePerNumber) % rouletteNumbers.length;
        return rouletteNumbers[targetIndex].num;
    }
    
    function checkWin(betType, resultNum) {
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
            case 'number':
                const betNum = parseInt($('#numberBet').val());
                return resultNum === betNum;
            default:
                return false;
        }
    }
    
    function getMultiplier(betType) {
        if (betType === 'green') return 14;
        if (betType === 'number') return 36;
        return 2;
    }
    
    $('.bet-btn').click(function() {
        if (isSpinning) return;
        $('.bet-btn').removeClass('active');
        $(this).addClass('active');
        selectedBet = $(this).data('bet');
        $('#numberBet').val('');
    });
    
    $('#numberBet').on('input', function() {
        if (isSpinning) return;
        $('.bet-btn').removeClass('active');
        const val = $(this).val();
        if (val !== '' && val >= 0 && val <= 36) {
            selectedBet = 'number';
        } else {
            selectedBet = null;
        }
    });
    
    $('#spinBtn').click(function() {
        if (isSpinning || !selectedBet) {
            if (!selectedBet) {
                $('#result').html('<div class="alert alert-error">Please select a bet first</div>');
            }
            return;
        }
        
        const betAmount = parseFloat($('#betAmount').val());
        if (betAmount < 1 || betAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        // Check if user has enough balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < betAmount) {
                $('#result').html('<div class="alert alert-error">Insufficient funds. Your balance is $' + (data.success ? parseFloat(data.balance).toFixed(2) : '0.00') + '</div>');
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
                
                const won = checkWin(selectedBet, resultNum);
                
                if (won) {
                    const multiplier = getMultiplier(selectedBet);
                    const winAmount = betAmount * multiplier;
                    
                    $.post('../api/api.php?action=updateBalance', {
                        amount: winAmount,
                        type: 'win',
                        description: `Roulette win: ${selectedBet} on ${resultNum} (${multiplier}x)`
                    }, function(data) {
                        if (data.success) {
                            $('#balance').text(parseFloat(data.balance).toFixed(2));
                            $('#result').html(`<div class="alert alert-success">🎉 You won $${winAmount.toFixed(2)}! (${multiplier}x multiplier)</div>`);
                        }
                    }, 'json');
                } else {
                    $.post('../api/api.php?action=updateBalance', {
                        amount: -betAmount,
                        type: 'bet',
                        description: 'Roulette bet'
                    }, function(data) {
                        if (data.success) {
                            $('#balance').text(parseFloat(data.balance).toFixed(2));
                            $('#result').html(`<div class="alert alert-error">Better luck next time! Lost $${betAmount.toFixed(2)}</div>`);
                        } else {
                            $('#result').html(`<div class="alert alert-error">${data.message}</div>`);
                        }
                    }, 'json');
                }
                
                isSpinning = false;
                $('#spinBtn').prop('disabled', false);
                selectedBet = null;
                $('.bet-btn').removeClass('active');
                $('#numberBet').val('');
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
