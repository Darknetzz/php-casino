$(document).ready(function() {
    const symbols = ['🍒', '🍋', '🍊', '🍇', '🎰'];
    let isSpinning = false;
    
    function spinReel(reelId, duration, finalSymbol) {
        const $reel = $(reelId);
        const startTime = Date.now();
        let lastSymbol = '';
        let symbolCount = 0;
        
        // Add spinning animation class
        $reel.addClass('spinning');
        
        function animate() {
            const elapsed = Date.now() - startTime;
            const progress = elapsed / duration;
            
            if (progress < 1) {
                // Fast spinning at start, slow down near end
                const speed = 1 - Math.pow(progress, 3); // Ease out cubic
                const interval = Math.max(50, speed * 200);
                
                if (elapsed - (symbolCount * interval) >= interval) {
                    let randomSymbol;
                    // In last 20% of spin, start showing final symbol occasionally
                    if (progress > 0.8 && Math.random() < 0.3) {
                        randomSymbol = finalSymbol;
                    } else {
                        randomSymbol = symbols[Math.floor(Math.random() * symbols.length)];
                    }
                    
                    if (randomSymbol !== lastSymbol) {
                        $reel.text(randomSymbol);
                        lastSymbol = randomSymbol;
                    }
                    symbolCount++;
                }
                
                requestAnimationFrame(animate);
            } else {
                // Final symbol
                $reel.text(finalSymbol);
                $reel.removeClass('spinning');
            }
        }
        animate();
    }
    
    function getRandomSymbol() {
        return symbols[Math.floor(Math.random() * symbols.length)];
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
        if (betAmount < 1 || betAmount > 100) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $100</div>');
            return;
        }
        
        isSpinning = true;
        $('#spinBtn').prop('disabled', true);
        $('#result').html('');
        
        // Determine final symbols
        const s1 = getRandomSymbol();
        const s2 = getRandomSymbol();
        const s3 = getRandomSymbol();
        
        // Spin animation with staggered timing for visual effect
        const spinDuration = 2500;
        const reel1Delay = 0;
        const reel2Delay = 200;
        const reel3Delay = 400;
        
        setTimeout(() => spinReel('#reel1', spinDuration, s1), reel1Delay);
        setTimeout(() => spinReel('#reel2', spinDuration, s2), reel2Delay);
        setTimeout(() => spinReel('#reel3', spinDuration, s3), reel3Delay);
        
        setTimeout(function() {
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
        }, spinDuration);
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
