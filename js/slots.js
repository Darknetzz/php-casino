$(document).ready(function() {
    let symbols = [];
    let isSpinning = false;
    let maxBet = 100;
    let settings = {};
    let multipliers = {};
    let nOfKindRules = [];
    let customCombinations = [];
    let numReels = 3;
    let slotsDuration = 2500; // Default spin duration in milliseconds
    
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
            if (data.settings.slots_duration) {
                slotsDuration = parseInt(data.settings.slots_duration) || 2500;
            }
            if (data.settings.slots_num_reels) {
                numReels = parseInt(data.settings.slots_num_reels) || 3;
            }
            if (data.settings.slots_multipliers && data.settings.slots_multipliers.symbols) {
                // Build symbols array and multipliers map from dynamic configuration
                symbols = [];
                multipliers = {};
                data.settings.slots_multipliers.symbols.forEach(function(symbol) {
                    symbols.push(symbol.emoji);
                    multipliers[symbol.emoji] = parseFloat(symbol.multiplier) || 0;
                });
                nOfKindRules = data.settings.slots_multipliers.n_of_kind_rules || [];
                customCombinations = data.settings.slots_multipliers.custom_combinations || [];
                
                // Generate reel containers if needed
                generateReelContainers();
                
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
    
    function generateReelContainers() {
        const $container = $('#slotsReelsContainer');
        $container.empty();
        for (let i = 1; i <= numReels; i++) {
            $container.append(
                $('<div class="reel-container">').append(
                    $('<div class="reel" id="reel' + i + '">')
                )
            );
        }
    }
    
    function initializeReels() {
        // Initialize each reel with 3 different symbols
        for (let i = 1; i <= numReels; i++) {
            const $reel = $('#reel' + i);
            $reel.empty();
            const threeSymbols = getThreeDifferentSymbols();
            for (let j = 0; j < 3; j++) {
                $reel.append($('<div class="symbol">' + threeSymbols[j] + '</div>'));
            }
        }
    }
    
    function updatePayoutsTable() {
        const tbody = $('.slots-payout-table tbody');
        tbody.empty();
        
        // Add custom combinations first (ordered array, empty = any symbol)
        customCombinations.forEach(function(combination) {
            if (combination.symbols && Array.isArray(combination.symbols)) {
                // Display emojis in order, show "?" for empty positions
                const comboText = combination.symbols.map(function(symbol) {
                    const trimmed = (symbol || '').trim();
                    return trimmed === '' ? '?' : trimmed;
                }).join('');
                const multiplier = parseFloat(combination.multiplier) || 0;
                tbody.append(
                    $('<tr>').append(
                        $('<td>').text(comboText)
                    ).append(
                        $('<td>').text(multiplier + 'x bet')
                    )
                );
            }
        });
        
        // Add all-of-a-kind payouts
        symbols.forEach(function(emoji) {
            const multiplier = multipliers[emoji] || 0;
            // Repeat emoji for number of reels
            let allOfKindText = '';
            for (let i = 0; i < numReels; i++) {
                allOfKindText += emoji;
            }
            tbody.append(
                $('<tr>').append(
                    $('<td>').text(allOfKindText)
                ).append(
                    $('<td>').text(multiplier + 'x bet')
                )
            );
        });
        
        // Add N-of-a-kind payouts
        nOfKindRules.forEach(function(rule) {
            const count = parseInt(rule.count) || 2;
            const symbol = (rule.symbol || '').trim();
            const multiplier = parseFloat(rule.multiplier) || 0;
            let displayText = count + ' of a kind';
            if (symbol && symbol.toLowerCase() !== 'any' && symbol !== '') {
                displayText += ' (' + symbol + ')';
            } else {
                displayText += ' (any symbol)';
            }
            tbody.append(
                $('<tr>').append(
                    $('<td>').text(displayText)
                ).append(
                    $('<td>').text(multiplier + 'x bet')
                )
            );
        });
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
    
    function calculateWin(resultSymbols) {
        // Accept array of symbols, trim whitespace
        if (!Array.isArray(resultSymbols)) {
            resultSymbols = Array.prototype.slice.call(arguments);
        }
        resultSymbols = resultSymbols.map(function(s) { return (s || '').trim(); });
        
        // Check custom combinations first (exact order matching, empty = any symbol)
        for (let i = 0; i < customCombinations.length; i++) {
            const combination = customCombinations[i];
            if (combination.symbols && Array.isArray(combination.symbols)) {
                // Check for exact order match (empty string matches any symbol)
                if (combination.symbols.length === resultSymbols.length) {
                    let matches = true;
                    for (let j = 0; j < combination.symbols.length; j++) {
                        const comboSymbol = (combination.symbols[j] || '').trim();
                        const resultSymbol = (resultSymbols[j] || '').trim();
                        // If combo symbol is empty, it matches any symbol; otherwise must match exactly
                        if (comboSymbol !== '' && comboSymbol !== resultSymbol) {
                            matches = false;
                            break;
                        }
                    }
                    if (matches) {
                        return parseFloat(combination.multiplier) || 0;
                    }
                }
            }
        }
        
        // Check for all-of-a-kind (all symbols the same)
        if (resultSymbols.length > 0) {
            const firstSymbol = resultSymbols[0];
            let allSame = true;
            for (let i = 1; i < resultSymbols.length; i++) {
                if (resultSymbols[i] !== firstSymbol || firstSymbol === '') {
                    allSame = false;
                    break;
                }
            }
            if (allSame && firstSymbol !== '') {
                return multipliers[firstSymbol] || 0;
            }
        }
        
        // Check N-of-a-kind rules (ordered by specificity - specific symbols first, then "any")
        // Sort rules: specific symbols first, then "any", then by count (higher first)
        const sortedRules = nOfKindRules.slice().sort(function(a, b) {
            const aSymbol = (a.symbol || '').trim().toLowerCase();
            const bSymbol = (b.symbol || '').trim().toLowerCase();
            const aIsAny = !aSymbol || aSymbol === 'any';
            const bIsAny = !bSymbol || bSymbol === 'any';
            
            // Specific symbols come before "any"
            if (aIsAny && !bIsAny) return 1;
            if (!aIsAny && bIsAny) return -1;
            
            // Within same type, higher count first
            return (b.count || 0) - (a.count || 0);
        });
        
        // Count occurrences of each symbol
        const symbolCounts = {};
        resultSymbols.forEach(function(symbol) {
            if (symbol) {
                symbolCounts[symbol] = (symbolCounts[symbol] || 0) + 1;
            }
        });
        
        // Check each rule
        for (let i = 0; i < sortedRules.length; i++) {
            const rule = sortedRules[i];
            const count = parseInt(rule.count) || 2;
            const ruleSymbol = (rule.symbol || '').trim();
            const isAnySymbol = !ruleSymbol || ruleSymbol.toLowerCase() === 'any';
            
            if (isAnySymbol) {
                // Check if any symbol appears exactly N times (and not all symbols)
                for (const symbol in symbolCounts) {
                    if (symbolCounts[symbol] === count && resultSymbols.length > count) {
                        return parseFloat(rule.multiplier) || 0;
                    }
                }
            } else {
                // Check if the specific symbol appears exactly N times
                const actualCount = symbolCounts[ruleSymbol] || 0;
                if (actualCount === count && resultSymbols.length > count) {
                    return parseFloat(rule.multiplier) || 0;
                }
            }
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
            $('#spinBtn').prop('disabled', true).text('SPINNING...').addClass('game-disabled');
            $('.game-container button, .game-container .btn').addClass('game-disabled');
            $('#result').html('');
            
            // Add beforeunload warning to prevent navigation during game
            $(window).on('beforeunload', function() {
                if (isSpinning) {
                    return 'A slots game is in progress. If you leave now, your bet may be lost. Are you sure you want to leave?';
                }
            });
        
        // Determine final symbols (one per reel)
        const finalSymbols = [];
        for (let i = 0; i < numReels; i++) {
            finalSymbols.push(getRandomSymbol());
        }
        
        // Spin animation with staggered timing for visual effect (left to right, slower stops)
        const spinDuration = slotsDuration;
        const reelDelays = [];
        const reelStopTimes = [];
        for (let i = 0; i < numReels; i++) {
            reelDelays.push(i * 200);
            reelStopTimes.push(spinDuration + (i * 300));
        }
        
        // Start spinning all reels
        for (let i = 0; i < numReels; i++) {
            setTimeout(() => spinReel('#reel' + (i + 1), reelStopTimes[i], finalSymbols[i]), reelDelays[i]);
        }
        
        // Wait for all reels to stop (use the last reel's stop time)
        const lastReelStopTime = reelStopTimes[reelStopTimes.length - 1];
        setTimeout(function() {
            // Get bet rows from user selection (1 = one row, 3 = all rows)
            const betRows = parseInt($('#betRows').val()) || 1;
            const actualBetAmount = betRows === 3 ? betAmount * 3 : betAmount; // Betting on all 3 rows costs 3x
            let totalWin = 0;
            let winDescriptions = [];
            
            if (betRows === 1) {
                // Check only middle row (row 1)
                const resultSymbols = [];
                for (let i = 1; i <= numReels; i++) {
                    resultSymbols.push($('#reel' + i + ' .symbol').eq(1).text().trim());
                }
                
                const multiplier = calculateWin(resultSymbols);
                
                if (multiplier > 0) {
                    totalWin = betAmount * multiplier; // Use original bet amount, not multiplied
                    let winType = 'custom';
                    // Check win type
                    const allSame = resultSymbols.length > 0 && resultSymbols.every(function(s) { return s === resultSymbols[0]; });
                    if (allSame) {
                        winType = 'all of a kind';
                    } else {
                        const symbolCounts = {};
                        resultSymbols.forEach(function(s) {
                            symbolCounts[s] = (symbolCounts[s] || 0) + 1;
                        });
                        let hasTwoOfKind = false;
                        for (const s in symbolCounts) {
                            if (symbolCounts[s] === 2) {
                                hasTwoOfKind = true;
                                break;
                            }
                        }
                        if (hasTwoOfKind) {
                            winType = '2 of a kind';
                        }
                    }
                    winDescriptions.push(`Middle row: ${resultSymbols.join('')} (${multiplier}x - ${winType})`);
                }
            } else if (betRows === 3) {
                // Check all 3 rows
                const rows = [0, 1, 2]; // top, middle, bottom
                rows.forEach(function(row) {
                    const resultSymbols = [];
                    for (let i = 1; i <= numReels; i++) {
                        resultSymbols.push($('#reel' + i + ' .symbol').eq(row).text().trim());
                    }
                    
                    const multiplier = calculateWin(resultSymbols);
                    
                    if (multiplier > 0) {
                        const rowWin = betAmount * multiplier; // Use original bet amount per row
                        totalWin += rowWin;
                        let winType = 'custom';
                        // Check win type
                        const allSame = resultSymbols.length > 0 && resultSymbols.every(function(s) { return s === resultSymbols[0]; });
                        if (allSame) {
                            winType = 'all of a kind';
                        } else {
                            const symbolCounts = {};
                            resultSymbols.forEach(function(s) {
                                symbolCounts[s] = (symbolCounts[s] || 0) + 1;
                            });
                            let hasTwoOfKind = false;
                            for (const s in symbolCounts) {
                                if (symbolCounts[s] === 2) {
                                    hasTwoOfKind = true;
                                    break;
                                }
                            }
                            if (hasTwoOfKind) {
                                winType = '2 of a kind';
                            }
                        }
                        const rowName = row === 0 ? 'Top' : (row === 1 ? 'Middle' : 'Bottom');
                        winDescriptions.push(`${rowName} row: ${resultSymbols.join('')} (${multiplier}x - ${winType})`);
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
            $('#spinBtn').prop('disabled', false).text('SPIN').removeClass('game-disabled');
            $('.game-container button, .game-container .btn').removeClass('game-disabled');
            
            // Remove beforeunload warning
            $(window).off('beforeunload');
        }, lastReelStopTime + 100); // Wait for all reels to stop
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
