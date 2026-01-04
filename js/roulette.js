$(document).ready(function() {
    let numberBets = []; // Array of {number: int, amount: float}
    let colorBets = []; // Array of {type: string, amount: float, multiplier: int}
    let isSpinning = false;
    let currentRotation = 0;
    let maxBet = 100;
    let currentRound = null;
    let lastRoundResult = null; // Store the last round result
    let pollInterval = null;
    let bettingCountdownInterval = null;
    let spinningDuration = 4; // Default spinning duration in seconds
    
    // Load max bet, default bet, and spinning duration from settings
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
            if (data.settings.roulette_spinning_duration) {
                spinningDuration = parseInt(data.settings.roulette_spinning_duration) || 4;
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
        
        // Create center circle for displaying result number
        const centerCircle = $('<div id="rouletteCenterResult" class="roulette-center-result"></div>');
        centerCircle.css({
            position: 'absolute',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: '25%',
            height: '25%',
            borderRadius: '50%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 10,
            fontSize: '2.5em',
            fontWeight: 'bold',
            color: '#ffffff',
            textShadow: '2px 2px 4px rgba(0, 0, 0, 0.8)',
            boxShadow: '0 4px 8px rgba(0, 0, 0, 0.5), inset 0 2px 4px rgba(0, 0, 0, 0.3)',
            opacity: 0,
            transition: 'opacity 0.5s ease-in-out'
        });
        wheel.append(centerCircle);
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
        // Combine both number bets and color bets into a single list
        const allBetsList = $('#activeBets');
        const totalBets = numberBets.length + colorBets.length;
        
        if (totalBets === 0) {
            allBetsList.html('<p>No bets placed yet</p>');
        } else {
            let html = '<div class="bets-list">';
            
            // Display number bets first
            numberBets.forEach(function(bet, index) {
                const colors = getRouletteNumberColors(bet.number);
                html += `<div class="bet-item" data-index="${index}" data-bet-type="number">
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 24px; height: 24px; border-radius: 50%; background-color: ${colors.bg}; color: ${colors.text}; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">${bet.number}</div>
                        <span>Number ${bet.number}: $${bet.amount.toFixed(2)}</span>
                    </span>
                    <button class="btn-remove-bet" data-index="${index}" data-type="number">×</button>
                </div>`;
            });
            
            // Display color bets after number bets
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
                html += `<div class="bet-item ${colorClass}" data-index="${index}" data-bet-type="color">
                    <span>${betName}: $${bet.amount.toFixed(2)} (${bet.multiplier}x)</span>
                    <button class="btn-remove-bet" data-index="${index}" data-type="color">×</button>
                </div>`;
            });
            
            html += '</div>';
            allBetsList.html(html);
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
    
    function updateActiveBetsDisplayFromServer(userBets) {
        // Display server-side bets (read-only, no remove buttons) in a single combined list
        const allBetsList = $('#activeBets');
        
        if (!userBets || userBets.length === 0) {
            allBetsList.html('<p>No bets placed yet</p>');
            $('#totalBetAmount').hide();
            return;
        }
        
        let html = '<div class="bets-list">';
        let totalBet = 0;
        
        // Consolidate bets by bet_type and bet_value
        const consolidatedBets = {};
        userBets.forEach(function(bet) {
            const key = bet.bet_type + '_' + bet.bet_value;
            if (!consolidatedBets[key]) {
                consolidatedBets[key] = {
                    bet_type: bet.bet_type,
                    bet_value: bet.bet_value,
                    amount: 0,
                    count: 0,
                    multiplier: bet.multiplier || 2
                };
            }
            consolidatedBets[key].amount += parseFloat(bet.amount || 0);
            consolidatedBets[key].count += 1;
            totalBet += parseFloat(bet.amount || 0);
        });
        
        const numberBets = [];
        const colorBets = [];
        
        // Separate consolidated bets into number and color bets
        Object.keys(consolidatedBets).forEach(function(key) {
            const bet = consolidatedBets[key];
            if (bet.bet_type === 'number') {
                numberBets.push(bet);
            } else if (bet.bet_type === 'color' || bet.bet_type === 'range') {
                colorBets.push(bet);
            }
        });
        
        // Display number bets first
        numberBets.forEach(function(bet) {
            const number = parseInt(bet.bet_value);
            const colors = getRouletteNumberColors(number);
            const countText = bet.count > 1 ? ` (${bet.count}x)` : '';
            html += `<div class="bet-item">
                <span style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background-color: ${colors.bg}; color: ${colors.text}; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">${number}</div>
                    <span>Number ${bet.bet_value}: $${bet.amount.toFixed(2)}${countText}</span>
                </span>
            </div>`;
        });
        
        // Display color bets after number bets
        colorBets.forEach(function(bet) {
            const betValue = bet.bet_value || '';
            const betName = betValue.charAt(0).toUpperCase() + betValue.slice(1);
            let colorClass = '';
            if (betValue === 'red') {
                colorClass = 'bet-item-red';
            } else if (betValue === 'black') {
                colorClass = 'bet-item-black';
            } else if (betValue === 'green') {
                colorClass = 'bet-item-green';
            }
            const countText = bet.count > 1 ? ` (${bet.count}x)` : '';
            html += `<div class="bet-item ${colorClass}">
                <span>${betName}: $${bet.amount.toFixed(2)} (${parseInt(bet.multiplier)}x)${countText}</span>
            </div>`;
        });
        
        html += '</div>';
        allBetsList.html(html);
        
        if (totalBet > 0) {
            $('#totalBetValue').text(totalBet.toFixed(2));
            $('#totalBetAmount').show();
        } else {
            $('#totalBetAmount').hide();
        }
    }
    
    function updateAllPlayersBetsDisplay(allBets, predictedResult) {
        const allBetsContainer = $('#allPlayersBets');
        if (!allBetsContainer.length) return; // Section doesn't exist (local mode)
        
        if (!allBets || allBets.length === 0) {
            allBetsContainer.html('<p style="text-align: center; color: #999;">No bets placed yet</p>');
            return;
        }
        
        // Group bets by user
        const betsByUser = {};
        allBets.forEach(function(bet) {
            const userId = bet.user_id;
            if (!betsByUser[userId]) {
                betsByUser[userId] = {
                    username: bet.username || 'Unknown',
                    bets: []
                };
            }
            betsByUser[userId].bets.push(bet);
        });
        
        let html = '<div class="all-bets-list" style="display: flex; flex-direction: column; gap: 12px;">';
        
        // Display bets grouped by user
        Object.keys(betsByUser).forEach(function(userId) {
            const userData = betsByUser[userId];
            
            // Consolidate bets by bet_type and bet_value for this user
            const consolidatedBets = {};
            userData.bets.forEach(function(bet) {
                const key = bet.bet_type + '_' + bet.bet_value;
                if (!consolidatedBets[key]) {
                    consolidatedBets[key] = {
                        bet_type: bet.bet_type,
                        bet_value: bet.bet_value,
                        amount: 0,
                        count: 0,
                        multiplier: bet.multiplier || 2
                    };
                }
                consolidatedBets[key].amount += parseFloat(bet.amount || 0);
                consolidatedBets[key].count += 1;
            });
            
            html += '<div class="user-bets-group" style="border: 1px solid #ddd; border-radius: 6px; padding: 12px; background: #f9f9f9;">';
            html += '<div style="font-weight: bold; margin-bottom: 8px; color: #667eea;">' + escapeHtml(userData.username) + '</div>';
            html += '<div style="display: flex; flex-direction: column; gap: 6px;">';
            
            // Display consolidated bets
            Object.keys(consolidatedBets).forEach(function(key) {
                const bet = consolidatedBets[key];
                
                // Check if bet matches predicted result (only if predictedResult is provided)
                let matchesPrediction = false;
                if (predictedResult !== null && predictedResult !== undefined) {
                    if (bet.bet_type === 'number') {
                        matchesPrediction = parseInt(bet.bet_value) === parseInt(predictedResult);
                    } else if (bet.bet_type === 'color' || bet.bet_type === 'range') {
                        matchesPrediction = checkColorBetWin(bet.bet_value, parseInt(predictedResult));
                    }
                }
                
                const sparkleIcon = matchesPrediction ? ' 🔮' : '';
                const countText = bet.count > 1 ? ` (${bet.count}x)` : '';
                
                if (bet.bet_type === 'number') {
                    const number = parseInt(bet.bet_value);
                    const colors = getRouletteNumberColors(number);
                    html += '<div class="bet-item" style="display: flex; align-items: center; gap: 8px; padding: 6px; background: white; border-radius: 4px;">';
                    html += '<div style="width: 24px; height: 24px; border-radius: 50%; background-color: ' + colors.bg + '; color: ' + colors.text + '; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">' + number + '</div>';
                    html += '<span>Number ' + bet.bet_value + ': $' + bet.amount.toFixed(2) + countText + sparkleIcon + '</span>';
                    html += '</div>';
                } else if (bet.bet_type === 'color' || bet.bet_type === 'range') {
                    const betValue = bet.bet_value || '';
                    const betName = betValue.charAt(0).toUpperCase() + betValue.slice(1);
                    let colorClass = '';
                    if (betValue === 'red') {
                        colorClass = 'bet-item-red';
                    } else if (betValue === 'black') {
                        colorClass = 'bet-item-black';
                    } else if (betValue === 'green') {
                        colorClass = 'bet-item-green';
                    }
                    html += '<div class="bet-item ' + colorClass + '" style="display: flex; align-items: center; padding: 6px; background: white; border-radius: 4px;">';
                    html += '<span>' + betName + ': $' + bet.amount.toFixed(2) + ' (' + parseInt(bet.multiplier) + 'x)' + countText + sparkleIcon + '</span>';
                    html += '</div>';
                }
            });
            
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        allBetsContainer.html(html);
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Poll server for current round state
    function pollRoundState() {
        $.get('../api/api.php?action=getRouletteRound', function(data) {
            if (data && data.success) {
                const round = data.round;
                
                if (!round) {
                    // No active round - show last result if available, otherwise show waiting message
                    if (lastRoundResult !== null) {
                        // Keep showing the last result during interval
                        // Don't call showRoundResult again to avoid re-animating
                        const resultColor = getNumberColor(lastRoundResult);
                        // Always update to ensure it's displayed correctly
                        $('#rouletteResult').html(`<span class="roulette-number roulette-${resultColor}">${lastRoundResult}</span>`);
                        $('#spinBtn').hide();
                        $('#roundCountdown').show();
                        $('#countdownText').html('Next round starting soon...');
                    } else {
                        // No previous result - worker might not be running
                        $('#rouletteResult').html('Waiting for next round...<br><small style="color: #999;">Make sure the game rounds worker is running</small>');
                        $('#spinBtn').hide();
                        $('#roundCountdown').show();
                        $('#countdownText').html('Waiting for next round...');
                    }
                    currentRound = null;
                    // Update status display
                    updateRoundStatusDisplay(null);
                    // Clear all players' bets display
                    updateAllPlayersBetsDisplay([], null);
                    // Load history anyway
                    loadHistory();
                    // Continue polling to catch when a round starts
                    return;
                }
                
                // Check if round changed
                const roundChanged = !currentRound || currentRound.id !== round.id || currentRound.status !== round.status;
                
                // Store previous status before updating
                const previousStatus = currentRound ? currentRound.status : null;
                const isNewRound = !currentRound || currentRound.id !== round.id;
                
                // Clear bets when round status changes from betting to spinning/finished, or when a new round starts
                if (isNewRound || (previousStatus === 'betting' && round.status !== 'betting')) {
                    numberBets = [];
                    colorBets = [];
                    updateActiveBetsDisplay();
                }
                
                currentRound = round;
                
                // Update round status display
                updateRoundStatusDisplay(round);
                
                // Update round info display
                if (round.status === 'betting') {
                    // Clear last result when new round starts
                    lastRoundResult = null;
                    // Hide center circle result
                    $('#rouletteCenterResult').css('opacity', 0);
                    const bettingEndsIn = round.time_until_betting_ends || 0;
                    const resultIn = round.time_until_result || (bettingEndsIn + 4); // fallback to +4 if not provided
                    $('#rouletteResult').html(`Round #${round.round_number} - Betting ends in ${Math.ceil(bettingEndsIn)}s`);
                    // Hide spin button, show countdown in central mode
                    $('#spinBtn').hide();
                    $('#roundCountdown').show();
                    // For the main countdown, show time until result (betting ends + spinning duration)
                    updateBettingCountdown(bettingEndsIn, resultIn);
                    
                    // Display server-side bets if available
                    if (round.user_bets && round.user_bets.length > 0) {
                        updateActiveBetsDisplayFromServer(round.user_bets);
                    }
                    
                    // Display all players' bets
                    if (round.all_bets && round.all_bets.length > 0) {
                        updateAllPlayersBetsDisplay(round.all_bets, null); // No predicted result for regular users
                    } else {
                        updateAllPlayersBetsDisplay([], null);
                    }

                    // Disable betting if betting period has ended
                    if (bettingEndsIn <= 0) {
                        $('.bet-btn, #addNumberBetBtn').prop('disabled', true).addClass('disabled');
                        $('#betAmount').prop('disabled', true);
                    } else {
                        $('.bet-btn, #addNumberBetBtn').prop('disabled', false).removeClass('disabled');
                        $('#betAmount').prop('disabled', false);
                    }
                    
                    // Reset spinning state when entering betting
                    if (isSpinning) {
                        isSpinning = false;
                        if (spinAnimationInterval) {
                            clearInterval(spinAnimationInterval);
                            spinAnimationInterval = null;
                        }
                    }
                } else if (round.status === 'spinning') {
                    // Disable betting during spinning
                    $('.bet-btn, #addNumberBetBtn').prop('disabled', true).addClass('disabled');
                    $('#betAmount').prop('disabled', true);
                    $('#spinBtn').hide();
                    $('#roundCountdown').show();
                    const timeLeft = round.time_until_finish || 0;
                    if (timeLeft > 0) {
                        $('#countdownText').html(`Spinning... Result in: <span style="font-size: 1.5em; color: #ffc107;">${Math.ceil(timeLeft)}s</span>`);
                    } else {
                        $('#countdownText').html('Spinning...');
                    }
                    // Start spinning animation when entering spinning state
                    // The animation duration will match the spinning duration setting
                    if (roundChanged || !isSpinning) {
                        startSpinningAnimation(round, timeLeft);
                    }
                    // Check if result is available
                    if (round.result_number !== null && round.result_number !== undefined) {
                        // Round finished, show result
                        showRoundResult(round.result_number);
                    }
                    
                    // Display all players' bets during spinning
                    if (round.all_bets && round.all_bets.length > 0) {
                        updateAllPlayersBetsDisplay(round.all_bets, null); // No predicted result for regular users
                    } else {
                        updateAllPlayersBetsDisplay([], null);
                    }
                } else if (round.status === 'finished') {
                    if (round.result_number !== null) {
                        lastRoundResult = round.result_number; // Store the result
                        showRoundResult(round.result_number);
                        // Show countdown until next round
                        $('#roundCountdown').show();
                        $('#countdownText').html('Next round starting soon...');
                    } else {
                        // Reset if no result yet
                        isSpinning = false;
                    }
                    
                    // Display all players' bets for finished round
                    if (round.all_bets && round.all_bets.length > 0) {
                        updateAllPlayersBetsDisplay(round.all_bets, round.result_number);
                    } else {
                        updateAllPlayersBetsDisplay([], null);
                    }
                    
                    // Wait for next round - continue polling
                }
                
                // Load history
                loadHistory();
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Failed to poll round state:', status, error, xhr);
            $('#rouletteResult').html('Error connecting to server<br><small style="color: #999;">Status: ' + status + ' | Error: ' + error + '</small>');
        });
    }
    
    // Store end timestamps for smooth countdown
    let bettingEndsTimestamp = null;
    let resultEndsTimestamp = null;
    let countdownRoundId = null;
    
    function updateBettingCountdown(bettingEndsIn, resultIn) {
        if (bettingCountdownInterval) {
            clearInterval(bettingCountdownInterval);
        }
        
        // Only update timestamps if round changed or we're starting fresh
        const currentRoundId = currentRound ? currentRound.id : null;
        if (countdownRoundId !== currentRoundId || bettingEndsTimestamp === null) {
            // Calculate end timestamps from current time + remaining seconds
            const now = Date.now();
            const bettingEndsInMs = Math.max(0, (bettingEndsIn || 0) * 1000);
            const resultInMs = Math.max(0, (resultIn || (bettingEndsIn || 0) + 4) * 1000);
            
            bettingEndsTimestamp = now + bettingEndsInMs;
            resultEndsTimestamp = now + resultInMs;
            countdownRoundId = currentRoundId;
        }
        
        const updateCountdown = function() {
            if (currentRound && currentRound.status === 'betting' && currentRound.id === countdownRoundId) {
                const now = Date.now();
                const bettingEnds = Math.max(0, Math.ceil((bettingEndsTimestamp - now) / 1000));
                const resultTime = Math.max(0, Math.ceil((resultEndsTimestamp - now) / 1000));
                
                if (bettingEnds > 0 || resultTime > 0) {
                    $('#rouletteResult').html(`Round #${currentRound.round_number} - Betting ends in ${bettingEnds}s`);
                    $('#countdownText').html(`Next spin in: <span style="font-size: 1.5em; color: #667eea;">${resultTime}s</span>`);
                    
                    // Disable betting when betting period ends
                    if (bettingEnds <= 0) {
                        $('.bet-btn, #addNumberBetBtn').prop('disabled', true).addClass('disabled');
                        $('#betAmount').prop('disabled', true);
                    } else {
                        $('.bet-btn, #addNumberBetBtn').prop('disabled', false).removeClass('disabled');
                        $('#betAmount').prop('disabled', false);
                    }
                } else {
                    // Betting period has ended
                    $('.bet-btn, #addNumberBetBtn').prop('disabled', true).addClass('disabled');
                    $('#betAmount').prop('disabled', true);
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
                // Reset timestamps when round changes
                const currentRoundIdCheck = currentRound ? currentRound.id : null;
                if (currentRoundIdCheck !== countdownRoundId) {
                    bettingEndsTimestamp = null;
                    resultEndsTimestamp = null;
                    countdownRoundId = null;
                }
            }
        };
        
        updateCountdown();
        bettingCountdownInterval = setInterval(updateCountdown, 1000);
    }
    
    let spinAnimationInterval = null;
    
    function startSpinningAnimation(round, timeUntilFinish) {
        if (isSpinning && spinAnimationInterval) return;
        
        // Hide center circle during spinning
        $('#rouletteCenterResult').css('opacity', 0);
        
        isSpinning = true;
        $('#spinBtn').prop('disabled', true).text('SPINNING...');
        
        // Clear any existing animation
        if (spinAnimationInterval) {
            clearInterval(spinAnimationInterval);
        }
        
        // Use the actual time until finish (spinning duration) for the animation
        // This ensures the wheel animation matches the server timer
        const actualSpinningDuration = timeUntilFinish > 0 ? timeUntilFinish : spinningDuration;
        
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
        
        // Animate wheel - use actual time until finish to match the timer
        $('#rouletteWheel').css({
            transition: `transform ${actualSpinningDuration}s cubic-bezier(0.17, 0.67, 0.12, 0.99)`,
            transform: `rotate(${totalRotation}deg)`
        });
        
        // Show spinning numbers in result display - match the actual spinning duration
        let spinCount = 0;
        const maxSpins = Math.ceil(actualSpinningDuration * 10); // 10 updates per second
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
        
        // Display result in center circle
        const centerCircle = $('#rouletteCenterResult');
        const colors = getRouletteNumberColors(resultNum);
        centerCircle.css({
            backgroundColor: colors.bg,
            color: colors.text,
            opacity: 1
        });
        centerCircle.text(resultNum);
        
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
        
        // Use actual spinning duration from settings
        $('#rouletteWheel').css({
            transition: `transform ${spinningDuration}s cubic-bezier(0.17, 0.67, 0.12, 0.99)`,
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
    
    // Use centralized function from utils.js - getRouletteNumberColors() is available globally
    
    function loadHistory() {
        if (!$('#roundHistoryList').length) return; // Section doesn't exist (local mode)
        
        $.get('../api/api.php?action=getRouletteHistory&limit=10', function(data) {
            if (data.success && data.history) {
                if (data.history.length === 0) {
                    $('#roundHistoryList').html('<p class="loading-text" style="text-align: center;">No history yet</p>');
                    return;
                }
                
                // Display as colored circles in a single row (no wrap, max 10 rounds)
                let html = '<div style="display: flex; flex-wrap: nowrap; gap: 10px; justify-content: flex-start; align-items: center; overflow-x: auto; padding: 5px 0;">';
                
                data.history.forEach(function(round) {
                    const result = round.result_number !== null ? round.result_number : '-';
                    if (result === '-') return; // Skip rounds without results
                    
                    const colors = getRouletteNumberColors(result);
                    html += '<div style="';
                    html += 'width: 50px; height: 50px; ';
                    html += 'border-radius: 50%; ';
                    html += 'background-color: ' + colors.bg + '; ';
                    html += 'color: ' + colors.text + '; ';
                    html += 'display: flex; ';
                    html += 'align-items: center; ';
                    html += 'justify-content: center; ';
                    html += 'font-weight: bold; ';
                    html += 'font-size: 18px; ';
                    html += 'box-shadow: 0 2px 4px rgba(0,0,0,0.2); ';
                    html += 'cursor: pointer; ';
                    html += 'transition: transform 0.2s; ';
                    html += '" ';
                    html += 'title="Round #' + round.round_number + (round.finished_at ? ' - ' + new Date(round.finished_at).toLocaleTimeString() : '') + '" ';
                    html += 'onmouseover="this.style.transform=\'scale(1.1)\'" ';
                    html += 'onmouseout="this.style.transform=\'scale(1)\'" ';
                    html += '>';
                    html += result;
                    html += '</div>';
                });
                
                html += '</div>';
                $('#roundHistoryList').html(html);
            } else {
                $('#roundHistoryList').html('<p class="loading-text" style="text-align: center;">Failed to load history</p>');
            }
        }, 'json').fail(function() {
            $('#roundHistoryList').html('<p class="loading-text" style="text-align: center;">Error loading history</p>');
        });
    }
    
    // Handle color/range bet buttons
    $('.bet-btn').click(function() {
        if (!currentRound || currentRound.status !== 'betting') {
            $('#bettingResult').html('<div class="alert alert-error">Betting is not open</div>');
            $('#result').html('');
            return;
        }
        
        // Check if betting period has ended
        const bettingEndsIn = currentRound.time_until_betting_ends || 0;
        if (bettingEndsIn <= 0) {
            $('#bettingResult').html('<div class="alert alert-error">Betting period has ended</div>');
            $('#result').html('');
            return;
        }
        
        const betType = $(this).data('bet');
        const multiplier = parseInt($(this).data('multiplier'));
        const amount = parseFloat($('#betAmount').val());
        
        if (isNaN(amount) || amount < 1 || amount > maxBet) {
            $('#bettingResult').html('<div class="alert alert-error">Bet amount must be between $1 and $' + maxBet + '</div>');
            $('#result').html('');
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
        $('#bettingResult').html('');
        
        // In central mode, auto-place bets
        if (currentRound && currentRound.status === 'betting') {
            placeAllBets();
        }
    });
    
    $('#addNumberBetBtn').click(function() {
        // Check if betting is allowed
        if (!currentRound || currentRound.status !== 'betting') {
            $('#bettingResult').html('<div class="alert alert-error">Betting is not open</div>');
            $('#result').html('');
            return;
        }
        
        // Check if betting period has ended
        const bettingEndsIn = currentRound.time_until_betting_ends || 0;
        if (bettingEndsIn <= 0) {
            $('#bettingResult').html('<div class="alert alert-error">Betting period has ended</div>');
            $('#result').html('');
            return;
        }
        if (!currentRound || currentRound.status !== 'betting') {
            $('#bettingResult').html('<div class="alert alert-error">Betting is not open</div>');
            $('#result').html('');
            return;
        }
        
        const number = parseInt($('#numberBet').val());
        const amount = parseFloat($('#betAmount').val());
        
        if (isNaN(number) || number < 0 || number > 36) {
            $('#bettingResult').html('<div class="alert alert-error">Please enter a valid number (0-36)</div>');
            $('#result').html('');
            return;
        }
        
        if (isNaN(amount) || amount < 1 || amount > maxBet) {
            $('#bettingResult').html('<div class="alert alert-error">Bet amount must be between $1 and $' + maxBet + '</div>');
            $('#result').html('');
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
        $('#bettingResult').html('');
        
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
            $('#bettingResult').html('<div class="alert alert-error">Please add at least one bet</div>');
            $('#result').html('');
            return;
        }
        
        // In central mode, just place bets (spin happens automatically)
        placeAllBets();
    });
    
    // Function to place all bets (used in central mode)
    function placeAllBets() {
        // Check if betting is allowed
        if (!currentRound || currentRound.status !== 'betting') {
            return;
        }
        
        // Check if betting period has ended
        const bettingEndsIn = currentRound.time_until_betting_ends || 0;
        if (bettingEndsIn <= 0) {
            return;
        }
        if (!currentRound || currentRound.status !== 'betting') {
            return;
        }
        
        if (numberBets.length === 0 && colorBets.length === 0) {
            return;
        }
        
        const totalBetAmount = numberBets.reduce((sum, bet) => sum + bet.amount, 0) + 
                              colorBets.reduce((sum, bet) => sum + bet.amount, 0);
        
        if (totalBetAmount > maxBet) {
            $('#bettingResult').html('<div class="alert alert-error">Total bet amount ($' + totalBetAmount.toFixed(2) + ') exceeds maximum of $' + formatNumber(maxBet) + '</div>');
            $('#result').html('');
            return;
        }
        
        // Check balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < totalBetAmount) {
                $('#bettingResult').html('<div class="alert alert-error">Insufficient funds</div>');
                $('#result').html('');
                // Clear bets if insufficient funds
                numberBets = [];
                colorBets = [];
                updateActiveBetsDisplay();
                return;
            }
            
            // Place all bets
            let betsPlaced = 0;
            let betsToPlace = numberBets.length + colorBets.length;
            
            function placeNextBet() {
                if (betsPlaced >= betsToPlace) {
                    // All bets placed successfully - clear the arrays since they're now on the server
                    numberBets = [];
                    colorBets = [];
                    // Fetch and display the server-side bets
                    $.get('../api/api.php?action=getRouletteRound', function(data) {
                        if (data.success && data.round && data.round.user_bets) {
                            updateActiveBetsDisplayFromServer(data.round.user_bets);
                        } else {
                            updateActiveBetsDisplay();
                        }
                    }, 'json');
                    updateBalance();
                    $('#bettingResult').html('<div class="alert alert-success">All bets placed!</div>');
                    $('#result').html('');
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
                        $('#bettingResult').html('<div class="alert alert-error">' + (data.message || 'Failed to place bet') + '</div>');
                        $('#result').html('');
                        // Clear all bets if placement fails
                        numberBets = [];
                        colorBets = [];
                        updateActiveBetsDisplay();
                        // Stop trying to place more bets
                        betsPlaced = betsToPlace;
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error('Failed to place bet:', status, error);
                    $('#bettingResult').html('<div class="alert alert-error">Error placing bet. Please try again.</div>');
                    $('#result').html('');
                    // Clear all bets if placement fails
                    numberBets = [];
                    colorBets = [];
                    updateActiveBetsDisplay();
                    betsPlaced = betsToPlace;
                });
            }
            
            placeNextBet();
        }, 'json');
    }
    
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
    
    let statusCountdownInterval = null;
    let statusEndTimestamp = null;
    let statusCountdownRoundId = null;
    let statusCountdownType = null; // 'betting' or 'spinning'
    
    // Use centralized function from utils.js - getRouletteNumberColors() is available globally
    
    function updateRoundStatusDisplay(round) {
        if (!$('#roundStatusDisplay').length) return; // Section doesn't exist (local mode)
        
        // Clear existing countdown interval
        if (statusCountdownInterval) {
            clearInterval(statusCountdownInterval);
            statusCountdownInterval = null;
        }
        
        if (!round) {
            $('#currentRoundNumber').text('-');
            $('#roundStatusText').text('Waiting for next round...');
            $('#countdownValue').text('Waiting...');
            statusEndTimestamp = null;
            statusCountdownRoundId = null;
            statusCountdownType = null;
            return;
        }
        
        $('#currentRoundNumber').text('#' + round.round_number);
        
        let statusText = round.status.charAt(0).toUpperCase() + round.status.slice(1);
        
        if (round.status === 'betting') {
            statusText = 'Betting Phase';
            // Only update timestamp if round changed or we're starting fresh
            if (statusCountdownRoundId !== round.id || statusCountdownType !== 'betting' || statusEndTimestamp === null) {
                const now = Date.now();
                const resultIn = round.time_until_result || round.time_until_betting_ends || 0;
                statusEndTimestamp = now + (resultIn * 1000);
                statusCountdownRoundId = round.id;
                statusCountdownType = 'betting';
            }
            
            const updateCountdown = function() {
                if (currentRound && currentRound.status === 'betting' && currentRound.id === round.id) {
                    const now = Date.now();
                    const timeLeft = Math.max(0, Math.ceil((statusEndTimestamp - now) / 1000));
                    $('#countdownValue').text(`Next spin in: ${timeLeft}s`);
                    if (timeLeft <= 0) {
                        clearInterval(statusCountdownInterval);
                        statusCountdownInterval = null;
                    }
                } else {
                    clearInterval(statusCountdownInterval);
                    statusCountdownInterval = null;
                    if (statusCountdownRoundId !== round.id) {
                        statusEndTimestamp = null;
                        statusCountdownRoundId = null;
                        statusCountdownType = null;
                    }
                }
            };
            updateCountdown();
            statusCountdownInterval = setInterval(updateCountdown, 1000);
        } else if (round.status === 'spinning') {
            statusText = 'Spinning';
            // Only update timestamp if round changed or we're starting fresh
            if (statusCountdownRoundId !== round.id || statusCountdownType !== 'spinning' || statusEndTimestamp === null) {
                const now = Date.now();
                const finishIn = round.time_until_finish || 0;
                statusEndTimestamp = now + (finishIn * 1000);
                statusCountdownRoundId = round.id;
                statusCountdownType = 'spinning';
            }
            
            const updateCountdown = function() {
                if (currentRound && currentRound.status === 'spinning' && currentRound.id === round.id) {
                    const now = Date.now();
                    const timeLeft = Math.max(0, Math.ceil((statusEndTimestamp - now) / 1000));
                    if (timeLeft > 0) {
                        $('#countdownValue').text(`Result in: ${timeLeft}s`);
                    } else {
                        $('#countdownValue').text('Spinning...');
                        clearInterval(statusCountdownInterval);
                        statusCountdownInterval = null;
                    }
                } else {
                    clearInterval(statusCountdownInterval);
                    statusCountdownInterval = null;
                    if (statusCountdownRoundId !== round.id) {
                        statusEndTimestamp = null;
                        statusCountdownRoundId = null;
                        statusCountdownType = null;
                    }
                }
            };
            updateCountdown();
            statusCountdownInterval = setInterval(updateCountdown, 1000);
        } else if (round.status === 'finished') {
            statusText = 'Finished';
            statusEndTimestamp = null;
            statusCountdownRoundId = null;
            statusCountdownType = null;
            if (round.result_number !== null) {
                $('#countdownValue').text(`Result: ${round.result_number}`);
            } else {
                $('#countdownValue').text('Waiting for next round...');
            }
        }
        
        $('#roundStatusText').text(statusText);
    }
    
    function loadHistory() {
        if (!$('#roundHistoryList').length) return; // Section doesn't exist (local mode)
        
        $.get('../api/api.php?action=getRouletteHistory&limit=10', function(data) {
            if (data.success && data.history) {
                if (data.history.length === 0) {
                    $('#roundHistoryList').html('<p class="loading-text" style="text-align: center;">No history yet</p>');
                    return;
                }
                
                // Display as colored circles in a single row (no wrap, max 10 rounds)
                let html = '<div style="display: flex; flex-wrap: nowrap; gap: 10px; justify-content: flex-start; align-items: center; overflow-x: auto; padding: 5px 0;">';
                
                data.history.forEach(function(round) {
                    const result = round.result_number !== null ? round.result_number : '-';
                    if (result === '-') return; // Skip rounds without results
                    
                    const colors = getRouletteNumberColors(result);
                    html += '<div style="';
                    html += 'width: 50px; height: 50px; ';
                    html += 'border-radius: 50%; ';
                    html += 'background-color: ' + colors.bg + '; ';
                    html += 'color: ' + colors.text + '; ';
                    html += 'display: flex; ';
                    html += 'align-items: center; ';
                    html += 'justify-content: center; ';
                    html += 'font-weight: bold; ';
                    html += 'font-size: 18px; ';
                    html += 'box-shadow: 0 2px 4px rgba(0,0,0,0.2); ';
                    html += 'cursor: pointer; ';
                    html += 'transition: transform 0.2s; ';
                    html += '" ';
                    html += 'title="Round #' + round.round_number + (round.finished_at ? ' - ' + new Date(round.finished_at).toLocaleTimeString() : '') + '" ';
                    html += 'onmouseover="this.style.transform=\'scale(1.1)\'" ';
                    html += 'onmouseout="this.style.transform=\'scale(1)\'" ';
                    html += '>';
                    html += result;
                    html += '</div>';
                });
                
                html += '</div>';
                $('#roundHistoryList').html(html);
            } else {
                $('#roundHistoryList').html('<p class="loading-text" style="text-align: center;">Failed to load history</p>');
            }
        }, 'json').fail(function() {
            $('#roundHistoryList').html('<p class="loading-text" style="text-align: center;">Error loading history</p>');
        });
    }
    
    // Start polling
    pollRoundState();
    pollInterval = setInterval(pollRoundState, 2000); // Poll every 2 seconds
    
    // Load history immediately
    loadHistory();
    
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
        if (statusCountdownInterval) {
            clearInterval(statusCountdownInterval);
        }
    });
});
