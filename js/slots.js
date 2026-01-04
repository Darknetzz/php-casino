$(document).ready(function() {
    let symbols = [];
    let isSpinning = false;
    let maxBet = 100;
    let settings = {};
    let multipliers = {};
    let twoOfKindMultiplier = 0.5;
    
    // Function to update total bet display (defined early so it's available everywhere)
    function updateTotalBetDisplay() {
        const betAmount = parseFloat($('#betAmount').val()) || 0;
        const betRows = parseInt($('#betRows').val()) || 1;
        const totalBet = betRows === 3 ? betAmount * 3 : betAmount;
        
        $('#totalBetAmount').text(totalBet.toFixed(2));
        
        if (betRows === 3) {
            $('#baseBetAmount').text(betAmount.toFixed(2));
            $('#totalBetNote').show();
        } else {
            $('#totalBetNote').hide();
        }
    }
    
    // Load max bet, default bet, and multipliers from settings
    $.get('../api/api.php?action=getSettings', function(data) {
        if (data.success) {
            settings = data.settings;
            if (data.settings.max_bet) {
                maxBet = data.settings.max_bet;
                $('#maxBet').text(maxBet);
                $('#betAmount').attr('max', maxBet);
            }
            if (data.settings.default_bet) {
                $('#betAmount').val(data.settings.default_bet).trigger('change');
            }
            if (data.settings.slots_multipliers && data.settings.slots_multipliers.symbols) {
                // Build symbols array and multipliers map from dynamic configuration
                symbols = [];
                multipliers = {};
                data.settings.slots_multipliers.symbols.forEach(function(symbol) {
                    symbols.push(symbol.emoji);
                    multipliers[symbol.emoji] = parseFloat(symbol.multiplier) || 0;
                });
                twoOfKindMultiplier = data.settings.slots_multipliers.two_of_kind || 0.5;
                
                // Initialize reels with symbols
                initializeReels();
                
                // Update payouts table
                updatePayoutsTable();
            }
            
            // Update total bet display after all settings are loaded
            setTimeout(function() {
                updateTotalBetDisplay();
            }, 150);
        }
    }, 'json');
    
    function initializeReels() {
        // Initialize each reel with 3 different symbols
        ['#reel1', '#reel2', '#reel3'].forEach(function(reelId) {
            const $reel = $(reelId);
            $reel.empty();
            const threeSymbols = getThreeDifferentSymbols();
            for (let i = 0; i < 3; i++) {
                $reel.append($('<div class="symbol">' + threeSymbols[i] + '</div>'));
            }
        });
    }
    
    function updatePayoutsTable() {
        const tbody = $('.slots-payout-table tbody');
        tbody.empty();
        
        // Add 3-of-a-kind payouts
        symbols.forEach(function(emoji) {
            const multiplier = multipliers[emoji] || 0;
            tbody.append(
                $('<tr>').append(
                    $('<td>').text(emoji + emoji + emoji)
                ).append(
                    $('<td>').text(multiplier + 'x bet')
                )
            );
        });
        
        // Add 2-of-a-kind payout
        tbody.append(
            $('<tr>').append(
                $('<td>').text('Any 2 matching symbols')
            ).append(
                $('<td>').text(twoOfKindMultiplier + 'x bet')
            )
        );
    }
    
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
        // Trim whitespace and ensure we have valid symbols
        s1 = (s1 || '').trim();
        s2 = (s2 || '').trim();
        s3 = (s3 || '').trim();
        
        // Check for 3 of a kind
        if (s1 === s2 && s2 === s3 && s1 !== '') {
            return multipliers[s1] || 0;
        }
        
        // Check for 2 of a kind (exactly 2 matching, not all 3)
        if ((s1 === s2 && s1 !== s3 && s1 !== '') || 
            (s1 === s3 && s1 !== s2 && s1 !== '') || 
            (s2 === s3 && s2 !== s1 && s2 !== '')) {
            return twoOfKindMultiplier;
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
        
        // Get bet rows selection
        const betRows = parseInt($('#betRows').val()) || 1;
        const actualBetAmount = betRows === 3 ? betAmount * 3 : betAmount; // Betting on all 3 rows costs 3x
        
        // Check if user has enough balance for the actual bet amount
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < actualBetAmount) {
                const requiredText = betRows === 3 ? `$${actualBetAmount.toFixed(2)} ($${betAmount.toFixed(2)} × 3 rows)` : `$${actualBetAmount.toFixed(2)}`;
                $('#result').html('<div class="alert alert-error">Insufficient funds. You need ' + requiredText + ' but your balance is $' + (data.success ? formatNumber(data.balance) : '0.00') + '</div>');
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
            // Wait for all reels to stop
            // Get bet rows from user selection (1 = one row, 3 = all rows)
            const betRows = parseInt($('#betRows').val()) || 1;
            const actualBetAmount = betRows === 3 ? betAmount * 3 : betAmount; // Betting on all 3 rows costs 3x
            let totalWin = 0;
            let winDescriptions = [];
            
            if (betRows === 1) {
                // Check only middle row (row 1)
                const s1 = $('#reel1 .symbol').eq(1).text().trim();
                const s2 = $('#reel2 .symbol').eq(1).text().trim();
                const s3 = $('#reel3 .symbol').eq(1).text().trim();
                
                const multiplier = calculateWin(s1, s2, s3);
                
                if (multiplier > 0) {
                    totalWin = betAmount * multiplier; // Use original bet amount, not multiplied
                    let winType = '';
                    if (s1 === s2 && s2 === s3) {
                        winType = '3 of a kind';
                    } else {
                        winType = '2 of a kind';
                    }
                    winDescriptions.push(`Middle row: ${s1}${s2}${s3} (${multiplier}x - ${winType})`);
                }
            } else if (betRows === 3) {
                // Check all 3 rows
                const rows = [0, 1, 2]; // top, middle, bottom
                rows.forEach(function(row) {
                    const s1 = $('#reel1 .symbol').eq(row).text().trim();
                    const s2 = $('#reel2 .symbol').eq(row).text().trim();
                    const s3 = $('#reel3 .symbol').eq(row).text().trim();
                    
                    const multiplier = calculateWin(s1, s2, s3);
                    
                    if (multiplier > 0) {
                        const rowWin = betAmount * multiplier; // Use original bet amount per row
                        totalWin += rowWin;
                        let winType = '';
                        if (s1 === s2 && s2 === s3) {
                            winType = '3 of a kind';
                        } else {
                            winType = '2 of a kind';
                        }
                        const rowName = row === 0 ? 'Top' : (row === 1 ? 'Middle' : 'Bottom');
                        winDescriptions.push(`${rowName} row: ${s1}${s2}${s3} (${multiplier}x - ${winType})`);
                    }
                });
            }
            
            // Always record the bet first (use actual bet amount which may be 3x if betting on all rows)
            $.post('../api/api.php?action=updateBalance', {
                amount: -actualBetAmount,
                type: 'bet',
                description: betRows === 3 ? `Slots bet (${betAmount} × 3 rows = ${actualBetAmount})` : 'Slots bet',
                game: 'slots'
            }, function(betData) {
                if (betData.success) {
                    if (totalWin > 0) {
                        const winAmount = totalWin;
                        // Then record the win
                        $.post('../api/api.php?action=updateBalance', {
                            amount: winAmount,
                            type: 'win',
                            description: `Slots win: ${winDescriptions.join('; ')}`,
                            game: 'slots'
                        }, function(winData) {
                            if (winData.success) {
                                $('#balance').text(formatNumber(winData.balance));
                                const winText = winDescriptions.length === 1 
                                    ? winDescriptions[0].split(' (')[1].replace(')', '')
                                    : `${winDescriptions.length} winning row(s)`;
                                $('#result').html(`<div class="alert alert-success">🎉 You won $${winAmount.toFixed(2)}! (${winText})</div>`);
                                // Update stats after win
                                updateWinRateStats('slots');
                            }
                        }, 'json');
                    } else {
                        // Loss - bet already recorded above
                        $('#balance').text(formatNumber(betData.balance));
                        const lostText = betRows === 3 ? `$${actualBetAmount.toFixed(2)} ($${betAmount.toFixed(2)} × 3 rows)` : `$${actualBetAmount.toFixed(2)}`;
                        $('#result').html(`<div class="alert alert-error">Better luck next time! Lost ${lostText}</div>`);
                        // Update stats after loss
                        updateWinRateStats('slots');
                    }
                } else {
                    $('#result').html(`<div class="alert alert-error">${betData.message}</div>`);
                }
            }, 'json');
            
            isSpinning = false;
            $('#spinBtn').prop('disabled', false);
        }, reel3StopTime + 100); // Wait for all reels to stop
        }, 'json');
    });
    
    // Update total bet when bet amount changes (multiple event types for compatibility)
    $('#betAmount').on('input change keyup paste', function() {
        updateTotalBetDisplay();
    });
    
    // Also listen for clicks on bet adjustment buttons (delegated event for dynamically added buttons)
    $(document).on('click', '.bet-adjust-btn', function() {
        // Small delay to ensure the input value has been updated by common.js
        setTimeout(function() {
            updateTotalBetDisplay();
        }, 50);
    });
    
    // Update total bet when row selection changes
    $('#betRows').on('change', function() {
        updateTotalBetDisplay();
    });
    
    // Initialize total bet display - call multiple times to catch all scenarios
    // First call immediately
    updateTotalBetDisplay();
    
    // Second call after DOM is fully ready and buttons might be added
    setTimeout(function() {
        updateTotalBetDisplay();
    }, 300);
    
    // Third call after settings are loaded (handled in settings callback above)
    
    // Update balance periodically
    setInterval(function() {
        $.get('../api/api.php?action=getBalance', function(data) {
            if (data.success) {
                $('#balance').text(formatNumber(data.balance));
            }
        }, 'json');
    }, 5000);
});
