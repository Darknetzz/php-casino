$(document).ready(function() {
    const diceFaces = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];
    const diceValues = [1, 2, 3, 4, 5, 6];
    let isRolling = false;
    let maxBet = 100;
    let settings = {};
    
    // Multipliers for different combinations
    let multipliers = {
        3: 2,  // 3 of a kind
        4: 5,  // 4 of a kind
        5: 10, // 5 of a kind
        6: 20  // 6 of a kind
    };
    
    // Number of dice (will be set from settings, fallback to counting dice on page)
    let numDice = $('.dice').length || 6;
    
    // Load max bet, default bet, multipliers, and number of dice from settings
    $.get('../api/api.php?action=getSettings', function(data) {
        if (data.success) {
            settings = data.settings;
            if (data.settings.max_bet) {
                maxBet = data.settings.max_bet;
                $('#maxBet').text(maxBet);
                $('#betAmount').attr('max', maxBet);
            }
            if (data.settings.default_bet) {
                $('#betAmount').val(data.settings.default_bet);
            }
            if (data.settings.dice_multipliers) {
                multipliers = {
                    3: data.settings.dice_multipliers[3] || 2,
                    4: data.settings.dice_multipliers[4] || 5,
                    5: data.settings.dice_multipliers[5] || 10,
                    6: data.settings.dice_multipliers[6] || 20
                };
            }
            if (data.settings.dice_num_dice) {
                numDice = parseInt(data.settings.dice_num_dice) || 6;
            } else {
                // Fallback: count dice elements on page
                numDice = $('.dice').length || 6;
            }
        }
    }, 'json');
    
    function rollDice(diceId, duration, finalValue) {
        const $dice = $(diceId);
        const startTime = Date.now();
        
        $dice.addClass('rolling');
        
        function animate() {
            const elapsed = Date.now() - startTime;
            const progress = elapsed / duration;
            
            if (progress < 1) {
                // Show random dice face during roll
                const randomIndex = Math.floor(Math.random() * diceFaces.length);
                $dice.find('.dice-face').text(diceFaces[randomIndex]);
                requestAnimationFrame(animate);
            } else {
                // Show final value
                $dice.find('.dice-face').text(diceFaces[finalValue - 1]);
                $dice.removeClass('rolling');
            }
        }
        animate();
    }
    
    function calculateWin(diceValues) {
        // Count occurrences of each value
        const counts = {};
        diceValues.forEach(value => {
            counts[value] = (counts[value] || 0) + 1;
        });
        
        // Find the highest count
        const maxCount = Math.max(...Object.values(counts));
        
        // Return multiplier based on max count
        return multipliers[maxCount] || 0;
    }
    
    function getWinDescription(diceValues) {
        // Count occurrences of each value
        const counts = {};
        diceValues.forEach(value => {
            counts[value] = (counts[value] || 0) + 1;
        });
        
        // Find the highest count and which value
        let maxCount = 0;
        let maxValue = 0;
        for (const [value, count] of Object.entries(counts)) {
            if (count > maxCount) {
                maxCount = count;
                maxValue = parseInt(value);
            }
        }
        
        if (maxCount >= 3) {
            return `${maxCount} of a kind (${maxValue})`;
        }
        return 'No win';
    }
    
    $('#rollBtn').click(function() {
        if (isRolling) return;
        
        const betAmount = parseFloat($('#betAmount').val());
        if (betAmount < 1 || betAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        // Check if user has enough balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < betAmount) {
                $('#result').html('<div class="alert alert-error">Insufficient funds. Your balance is $' + (data.success ? formatNumber(data.balance) : '0.00') + '</div>');
                return;
            }
            
            isRolling = true;
            $('#rollBtn').prop('disabled', true).text('ROLLING...').addClass('game-disabled');
            $('.game-container button, .game-container .btn').addClass('game-disabled');
            $('#result').html('');
            
            // Add beforeunload warning to prevent navigation during game
            $(window).on('beforeunload', function() {
                if (isRolling) {
                    return 'A dice game is in progress. If you leave now, your bet may be lost. Are you sure you want to leave?';
                }
            });
            
            // Generate random dice values
            const finalValues = [];
            for (let i = 0; i < numDice; i++) {
                finalValues.push(Math.floor(Math.random() * 6) + 1);
            }
            
            // Roll animation duration
            const rollDuration = 1500;
            const staggerDelay = 100; // Stagger each dice roll slightly
            
            // Roll all dice with slight stagger
            for (let i = 0; i < numDice; i++) {
                setTimeout(() => {
                    rollDice('#dice' + (i + 1), rollDuration, finalValues[i]);
                }, i * staggerDelay);
            }
            
            // Calculate win after animation completes
            setTimeout(function() {
                const multiplier = calculateWin(finalValues);
                const winDescription = getWinDescription(finalValues);
                
                // Always record the bet first
                $.post('../api/api.php?action=updateBalance', {
                    amount: -betAmount,
                    type: 'bet',
                    description: 'Dice bet',
                    game: 'dice'
                }, function(betData) {
                    if (betData.success) {
                        if (multiplier > 0) {
                            const winAmount = betAmount * multiplier;
                            // Then record the win
                            $.post('../api/api.php?action=updateBalance', {
                                amount: winAmount,
                                type: 'win',
                                description: `Dice win: ${winDescription} (${multiplier}x)`,
                                game: 'dice'
                            }, function(winData) {
                                if (winData.success) {
                                    $('#balance').text(formatNumber(winData.balance));
                                    $('#result').html(`<div class="alert alert-success">🎉 You won $${winAmount.toFixed(2)}! (${winDescription} - ${multiplier}x)</div>`);
                                    // Update stats after win
                                    updateWinRateStats('dice');
                                }
                            }, 'json');
                        } else {
                            // Loss - bet already recorded above
                            $('#balance').text(formatNumber(betData.balance));
                            $('#result').html(`<div class="alert alert-error">Better luck next time! Lost $${betAmount.toFixed(2)}</div>`);
                            // Update stats after loss
                            updateWinRateStats('dice');
                        }
                    } else {
                        $('#result').html(`<div class="alert alert-error">${betData.message}</div>`);
                    }
                    
                    isRolling = false;
                    $('#rollBtn').prop('disabled', false).text('ROLL DICE').removeClass('game-disabled');
                    $('.game-container button, .game-container .btn').removeClass('game-disabled');
                    
                    // Remove beforeunload warning
                    $(window).off('beforeunload');
                }, 'json');
            }, rollDuration + (numDice * staggerDelay) + 200); // Wait for all dice to finish rolling
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
