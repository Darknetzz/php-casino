$(document).ready(function() {
    const symbols = ['🍒', '🍋', '🍊', '🍇', '🎰'];
    let isSpinning = false;
    let maxBet = 100;
    
    // Load max bet from settings
    $.get('../api/api.php?action=getSettings', function(data) {
        if (data.success && data.settings.max_bet) {
            maxBet = data.settings.max_bet;
            $('#maxBet').text(maxBet);
            $('#betAmount').attr('max', maxBet);
        }
    }, 'json');
    
    function spinReel(reelId, duration, finalSymbol) {
        const $reel = $(reelId);
        const startTime = Date.now();
        
        // Ensure we have exactly 3 symbols, all different
        let $symbols = $reel.find('.symbol');
        if ($symbols.length !== 3) {
            $reel.empty();
            const threeSymbols = getThreeDifferentSymbols();
            for (let i = 0; i < 3; i++) {
                $reel.append($('<div class="symbol">' + threeSymbols[i] + '</div>'));
            }
            $symbols = $reel.find('.symbol');
        }
        
        // Add spinning animation class
        $reel.addClass('spinning');
        
        function animate() {
            const elapsed = Date.now() - startTime;
            const progress = elapsed / duration;
            
            if (progress < 1) {
                // Update all 3 symbols during spin - they're all visible
                // Ensure no two consecutive symbols are the same
                const $symbols = $reel.find('.symbol');
                let prevSymbol = '';
                
                $symbols.each(function(index) {
                    let newSymbol;
                    // In last 20% of spin, start showing final symbol in middle occasionally
                    if (progress > 0.8 && index === 1 && Math.random() < 0.3) {
                        newSymbol = finalSymbol;
                    } else {
                        // Get random symbol different from previous
                        if (prevSymbol) {
                            newSymbol = getRandomSymbolDifferentFrom(prevSymbol);
                        } else {
                            newSymbol = getRandomSymbol();
                        }
                    }
                    $(this).text(newSymbol);
                    prevSymbol = newSymbol;
                });
                
                requestAnimationFrame(animate);
            } else {
                // Final symbols - all 3 remain visible, middle is winning
                // Ensure no two consecutive symbols are the same
                const $symbols = $reel.find('.symbol');
                const topSymbol = getRandomSymbolDifferentFrom(finalSymbol);
                let bottomSymbol = getRandomSymbolDifferentFrom(finalSymbol);
                // Make sure bottom is also different from top
                while (bottomSymbol === topSymbol) {
                    bottomSymbol = getRandomSymbolDifferentFrom(finalSymbol);
                }
                
                $symbols.eq(0).text(topSymbol); // Top (visible)
                $symbols.eq(1).text(finalSymbol); // Middle (winning, visible)
                $symbols.eq(2).text(bottomSymbol); // Bottom (visible)
                $reel.removeClass('spinning');
                // Reset position to show all 3 symbols (top at 0, middle at 120px, bottom at 240px)
                $reel.css('transform', 'translateY(0)');
            }
        }
        animate();
    }
    
    function getRandomSymbol() {
        return symbols[Math.floor(Math.random() * symbols.length)];
    }
    
    function getRandomSymbolDifferentFrom(excludeSymbol) {
        const available = symbols.filter(s => s !== excludeSymbol);
        return available[Math.floor(Math.random() * available.length)];
    }
    
    function getThreeDifferentSymbols() {
        const s1 = getRandomSymbol();
        let s2 = getRandomSymbolDifferentFrom(s1);
        let s3 = getRandomSymbolDifferentFrom(s2);
        // Make sure s3 is also different from s1
        while (s3 === s1) {
            s3 = getRandomSymbolDifferentFrom(s2);
        }
        return [s1, s2, s3];
    }
    
    function calculateWin(s1, s2, s3) {
        if (s1 === s2 && s2 === s3) {
            const multipliers = {
                '🍒': 2,
                '🍋': 3,
                '🍊': 4,
                '🍇': 5,
                '🎰': 10
            };
            return multipliers[s1] || 0;
        }
        return 0;
    }
    
    $('#spinBtn').click(function() {
        if (isSpinning) return;
        
        const betAmount = parseFloat($('#betAmount').val());
        if (betAmount < 1 || betAmount > maxBet) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $' + maxBet + '</div>');
            return;
        }
        
        isSpinning = true;
        $('#spinBtn').prop('disabled', true);
        $('#result').html('');
        
        // Determine final symbols
        const s1 = getRandomSymbol();
        const s2 = getRandomSymbol();
        const s3 = getRandomSymbol();
        
        // Spin animation with staggered timing for visual effect (left to right, slower stops)
        const spinDuration = 2500;
        const reel1Delay = 0;
        const reel2Delay = 200;
        const reel3Delay = 400;
        
        // Staggered stop times (left to right, each stops slower)
        const reel1StopTime = spinDuration;
        const reel2StopTime = spinDuration + 300;
        const reel3StopTime = spinDuration + 600;
        
        setTimeout(() => spinReel('#reel1', reel1StopTime, s1), reel1Delay);
        setTimeout(() => spinReel('#reel2', reel2StopTime, s2), reel2Delay);
        setTimeout(() => spinReel('#reel3', reel3StopTime, s3), reel3Delay);
        
        setTimeout(function() {
            // Get middle symbols (the winning ones) - wait for all reels to stop
            const s1 = $('#reel1 .symbol').eq(1).text();
            const s2 = $('#reel2 .symbol').eq(1).text();
            const s3 = $('#reel3 .symbol').eq(1).text();
            
            const multiplier = calculateWin(s1, s2, s3);
            
            if (multiplier > 0) {
                const winAmount = betAmount * multiplier;
                $.post('../api/api.php?action=updateBalance', {
                    amount: winAmount,
                    type: 'win',
                    description: `Slots win: ${s1}${s2}${s3} (${multiplier}x)`
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
                    description: 'Slots bet'
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
        }, reel3StopTime + 100); // Wait for all reels to stop
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
