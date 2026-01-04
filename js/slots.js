$(document).ready(function() {
    const symbols = ['🍒', '🍋', '🍊', '🍇', '🎰'];
    let isSpinning = false;
    
    function spinReel(reelId, duration) {
        const $reel = $(reelId);
        const startTime = Date.now();
        
        function animate() {
            const elapsed = Date.now() - startTime;
            if (elapsed < duration) {
                $reel.text(symbols[Math.floor(Math.random() * symbols.length)]);
                requestAnimationFrame(animate);
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
        
        // Spin animation
        const spinDuration = 2000;
        spinReel('#reel1', spinDuration);
        spinReel('#reel2', spinDuration);
        spinReel('#reel3', spinDuration);
        
        setTimeout(function() {
            const s1 = getRandomSymbol();
            const s2 = getRandomSymbol();
            const s3 = getRandomSymbol();
            
            $('#reel1').text(s1);
            $('#reel2').text(s2);
            $('#reel3').text(s3);
            
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
