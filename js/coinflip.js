$(document).ready(function() {
    let isFlipping = false;
    let selectedChoice = null;
    let maxBet = 100;
    let maxBetEnabled = true;
    let multiplier = 2.0; // Default 2x payout for coinflip
    
    // Load max bet, default bet, and multiplier from settings
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
            // Load coinflip multiplier if available
            if (data.settings.coinflip_multiplier) {
                multiplier = parseFloat(data.settings.coinflip_multiplier) || 2.0;
            }
        }
    }, 'json');
    
    // Handle choice selection (Heads or Tails)
    $('.coin-choice-btn').click(function() {
        if (isFlipping) return;
        
        selectedChoice = $(this).data('choice');
        
        // Update button styles
        $('.coin-choice-btn').removeClass('btn-selected').addClass('btn-primary');
        $(this).removeClass('btn-primary').addClass('btn-selected');
        
        // Show flip button
        $('#flipBtn').show();
        $('#result').html('');
    });
    
    function flipCoin(finalResult, duration) {
        const $coin = $('#coin');
        const startTime = Date.now();
        const totalRotations = 8; // Number of full rotations
        
        function animate() {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            if (progress < 1) {
                // Ease out animation for smooth deceleration
                const easeProgress = 1 - Math.pow(1 - progress, 3);
                const currentRotation = totalRotations * 360 * easeProgress;
                $coin.css('transform', `rotateY(${currentRotation}deg)`);
                requestAnimationFrame(animate);
            } else {
                // Set final result (normalize to 0 or 180 degrees)
                if (finalResult === 'heads') {
                    $coin.css('transform', 'rotateY(0deg)');
                } else {
                    $coin.css('transform', 'rotateY(180deg)');
                }
            }
        }
        animate();
    }
    
    $('#flipBtn').click(function() {
        if (isFlipping || !selectedChoice) return;
        
        const betAmount = parseFloat($('#betAmount').val());
        if (betAmount < 1 || (maxBetEnabled && betAmount > maxBet)) {
            const maxBetText = maxBetEnabled ? '$' + maxBet : 'unlimited';
            $('#result').html('<div class="alert alert-error">Bet must be at least $1' + (maxBetEnabled ? ' and not exceed $' + maxBet : '') + '</div>');
            return;
        }
        
        // Check if user has enough balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < betAmount) {
                $('#result').html('<div class="alert alert-error">Insufficient funds. Your balance is $' + (data.success ? formatNumber(data.balance) : '0.00') + '</div>');
                return;
            }
            
            isFlipping = true;
            $('#flipBtn').prop('disabled', true).text('FLIPPING...').addClass('game-disabled');
            $('.coin-choice-btn').prop('disabled', true).addClass('game-disabled');
            $('.game-container button, .game-container .btn').not('[onclick*="openModal"]').addClass('game-disabled');
            $('#result').html('');
            
            // Add beforeunload warning to prevent navigation during game
            $(window).on('beforeunload', function() {
                if (isFlipping) {
                    return 'A coinflip game is in progress. If you leave now, your bet may be lost. Are you sure you want to leave?';
                }
            });
            
            // Generate random result (heads or tails)
            const finalResult = Math.random() < 0.5 ? 'heads' : 'tails';
            
            // Flip animation duration
            const flipDuration = 2000;
            
            // Start flip animation
            flipCoin(finalResult, flipDuration);
            
            // Calculate result after animation completes
            setTimeout(function() {
                const won = selectedChoice === finalResult;
                
                // Always record the bet first
                $.post('../api/api.php?action=updateBalance', {
                    amount: -betAmount,
                    type: 'bet',
                    description: `Coinflip bet on ${selectedChoice}`,
                    game: 'coinflip'
                }, function(betData) {
                    if (betData.success) {
                        if (won) {
                            const winAmount = betAmount * multiplier;
                            // Then record the win
                            $.post('../api/api.php?action=updateBalance', {
                                amount: winAmount,
                                type: 'win',
                                description: `Coinflip win: ${finalResult.toUpperCase()} (${multiplier}x)`,
                                game: 'coinflip'
                            }, function(winData) {
                                if (winData.success) {
                                    $('#balance').text(formatNumber(winData.balance));
                                    $('#result').html(`<div class="alert alert-success">🎉 You won $${winAmount.toFixed(2)}! Result: ${finalResult.toUpperCase()} (${multiplier}x)</div>`);
                                    // Update stats after win
                                    updateWinRateStats('coinflip');
                                }
                            }, 'json');
                        } else {
                            // Loss - bet already recorded above
                            $('#balance').text(formatNumber(betData.balance));
                            $('#result').html(`<div class="alert alert-error">Better luck next time! Result: ${finalResult.toUpperCase()}, you chose ${selectedChoice.toUpperCase()}. Lost $${betAmount.toFixed(2)}</div>`);
                            // Update stats after loss
                            updateWinRateStats('coinflip');
                        }
                    } else {
                        $('#result').html(`<div class="alert alert-error">${betData.message}</div>`);
                    }
                    
                    isFlipping = false;
                    $('#flipBtn').prop('disabled', false).text('FLIP COIN').removeClass('game-disabled');
                    $('.coin-choice-btn').prop('disabled', false).removeClass('game-disabled');
                    $('.game-container button, .game-container .btn').not('[onclick*="openModal"]').removeClass('game-disabled');
                    
                    // Reset selection
                    selectedChoice = null;
                    $('.coin-choice-btn').removeClass('btn-selected').addClass('btn-primary');
                    $('#flipBtn').hide();
                    
                    // Remove beforeunload warning
                    $(window).off('beforeunload');
                }, 'json');
            }, flipDuration + 200); // Wait for animation to complete
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
