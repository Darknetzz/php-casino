$(document).ready(function() {
    let deck = [];
    let playerHand = [];
    let dealerHand = [];
    let gameActive = false;
    let betAmount = 0;
    let maxBet = 100;
    let maxBetEnabled = true;
    let regularMultiplier = 2.0;
    let blackjackMultiplier = 2.5;
    let dealerStandThreshold = 17;
    
    // Load max bet, default bet, and blackjack settings from settings
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
            if (data.settings.blackjack_regular_multiplier !== undefined) {
                regularMultiplier = data.settings.blackjack_regular_multiplier;
            }
            if (data.settings.blackjack_blackjack_multiplier !== undefined) {
                blackjackMultiplier = data.settings.blackjack_blackjack_multiplier;
            }
            if (data.settings.blackjack_dealer_stand !== undefined) {
                dealerStandThreshold = data.settings.blackjack_dealer_stand;
            }
        }
    }, 'json');
    
    const suits = ['♠', '♥', '♦', '♣'];
    const ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    
    function createDeck() {
        deck = [];
        for (let suit of suits) {
            for (let rank of ranks) {
                deck.push({suit, rank, value: getCardValue(rank)});
            }
        }
        shuffleDeck();
    }
    
    function shuffleDeck() {
        for (let i = deck.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [deck[i], deck[j]] = [deck[j], deck[i]];
        }
    }
    
    function getCardValue(rank) {
        if (rank === 'A') return 11;
        if (['J', 'Q', 'K'].includes(rank)) return 10;
        return parseInt(rank);
    }
    
    function calculateHand(hand) {
        let total = 0;
        let aces = 0;
        
        for (let card of hand) {
            if (card.rank === 'A') {
                aces++;
                total += 11;
            } else {
                total += card.value;
            }
        }
        
        while (total > 21 && aces > 0) {
            total -= 10;
            aces--;
        }
        
        return total;
    }
    
    function displayCard(card, isHidden = false) {
        if (isHidden) {
            return '<div class="card card-hidden">?</div>';
        }
        const color = (card.suit === '♥' || card.suit === '♦') ? 'red' : 'black';
        return `<div class="card card-${color}">${card.rank}${card.suit}</div>`;
    }
    
    function displayHand(hand, elementId, hideFirst = false) {
        let html = '';
        for (let i = 0; i < hand.length; i++) {
            html += displayCard(hand[i], hideFirst && i === 0);
        }
        $(elementId).html(html);
    }
    
    function updateScores() {
        const playerScore = calculateHand(playerHand);
        const dealerScore = calculateHand(dealerHand);
        $('#playerScore').text(`Score: ${playerScore}`);
        $('#dealerScore').text(`Score: ${hideFirst ? '?' : dealerScore}`);
    }
    
    let hideFirst = true;
    
    function dealCard(hand) {
        if (deck.length === 0) {
            createDeck();
        }
        return hand.push(deck.pop());
    }
    
    function startGame() {
        const bet = parseFloat($('#betAmount').val());
        if (bet < 1 || (maxBetEnabled && bet > maxBet)) {
            const maxBetText = maxBetEnabled ? '$' + maxBet : 'unlimited';
            $('#result').html('<div class="alert alert-error">Bet must be at least $1' + (maxBetEnabled ? ' and not exceed $' + maxBet : '') + '</div>');
            return;
        }
        
        // Check if user has enough balance
        $.get('../api/api.php?action=getBalance', function(data) {
            if (!data.success || parseFloat(data.balance) < bet) {
                $('#result').html('<div class="alert alert-error">Insufficient funds. Your balance is $' + (data.success ? formatNumber(data.balance) : '0.00') + '</div>');
                return;
            }
            
            // Deduct bet
            $.post('../api/api.php?action=updateBalance', {
                amount: -bet,
                type: 'bet',
                description: 'Blackjack bet',
                game: 'blackjack'
            }, function(data) {
                if (data.success) {
                    // Add beforeunload warning to prevent navigation during game
                    $(window).on('beforeunload', function() {
                        if (gameActive) {
                            return 'A blackjack game is in progress. If you leave now, your bet may be lost. Are you sure you want to leave?';
                        }
                    });
                    
                    $('#balance').text(formatNumber(data.balance));
                    betAmount = bet;
                    gameActive = true;
                    hideFirst = true;
                    
                    createDeck();
                    playerHand = [];
                    dealerHand = [];
                    
                    dealCard(playerHand);
                    dealCard(playerHand);
                    dealCard(dealerHand);
                    dealCard(dealerHand);
                    
                    displayHand(playerHand, '#playerHand');
                    displayHand(dealerHand, '#dealerHand', true);
                    updateScores();
                    
                    $('#gameControls').show();
                    $('#result').html('');
                    $('#newGameBtn').prop('disabled', true).text('Game in Progress...').addClass('game-disabled');
                    // Disable buttons but keep Hit, Stand, and info buttons active
                    $('.game-container button, .game-container .btn').not('[onclick*="openModal"], #hitBtn, #standBtn').addClass('game-disabled');
                    
                    // Check for blackjack
                    if (calculateHand(playerHand) === 21) {
                        endGame(true, true);
                    }
                }
            }, 'json');
        }, 'json');
    }
    
    function endGame(playerWon, isBlackjack = false) {
        gameActive = false;
        hideFirst = false;
        
        // Remove beforeunload warning
        $(window).off('beforeunload');
        
        const playerScore = calculateHand(playerHand);
        
        // Dealer draws until stand threshold or higher, but stops if already beating player
        while (true) {
            const currentDealerScore = calculateHand(dealerHand);
            
            // Dealer must hit until stand threshold, but if already beating player, stand
            if (currentDealerScore >= dealerStandThreshold) {
                break; // Dealer stands at threshold or higher
            }
            
            // If dealer is already beating player (and both are valid), stand
            if (currentDealerScore > playerScore && playerScore <= 21 && currentDealerScore <= 21) {
                break;
            }
            
            dealCard(dealerHand);
        }
        
        displayHand(dealerHand, '#dealerHand', false);
        updateScores();
        
        const dealerScore = calculateHand(dealerHand);
        
        let won = false;
        let message = '';
        
        if (playerScore > 21) {
            message = `Bust! You lost $${betAmount.toFixed(2)}`;
        } else if (dealerScore > 21) {
            won = true;
            const winAmount = isBlackjack ? betAmount * blackjackMultiplier : betAmount * regularMultiplier;
            message = `Dealer busts! You won $${winAmount.toFixed(2)}!`;
            $.post('../api/api.php?action=updateBalance', {
                amount: winAmount,
                type: 'win',
                description: isBlackjack ? 'Blackjack win!' : 'Blackjack win',
                game: 'blackjack'
            }, function(data) {
                if (data.success) {
                    $('#balance').text(formatNumber(data.balance));
                    // Update stats after win
                    updateWinRateStats('blackjack');
                }
            }, 'json');
        } else if (isBlackjack) {
            won = true;
            const winAmount = betAmount * blackjackMultiplier;
            message = `Blackjack! You won $${winAmount.toFixed(2)}!`;
            $.post('../api/api.php?action=updateBalance', {
                amount: winAmount,
                type: 'win',
                description: 'Blackjack win!',
                game: 'blackjack'
            }, function(data) {
                if (data.success) {
                    $('#balance').text(formatNumber(data.balance));
                    // Update stats after win
                    updateWinRateStats('blackjack');
                }
            }, 'json');
        } else if (playerScore > dealerScore) {
            won = true;
            const winAmount = betAmount * regularMultiplier;
            message = `You win! You won $${winAmount.toFixed(2)}!`;
            $.post('../api/api.php?action=updateBalance', {
                amount: winAmount,
                type: 'win',
                description: 'Blackjack win',
                game: 'blackjack'
            }, function(data) {
                if (data.success) {
                    $('#balance').text(formatNumber(data.balance));
                    // Update stats after win
                    updateWinRateStats('blackjack');
                }
            }, 'json');
        } else if (playerScore < dealerScore) {
            message = `Dealer wins! You lost $${betAmount.toFixed(2)}`;
            // Update stats after loss
            updateWinRateStats('blackjack');
        } else {
            message = `Push! Your bet is returned.`;
            $.post('../api/api.php?action=updateBalance', {
                amount: betAmount,
                type: 'win',
                description: 'Blackjack push',
                game: 'blackjack'
            }, function(data) {
                if (data.success) {
                    $('#balance').text(formatNumber(data.balance));
                    // Update stats after push (counts as a game played)
                    updateWinRateStats('blackjack');
                }
            }, 'json');
        }
        
        $('#result').html(`<div class="alert ${won ? 'alert-success' : 'alert-error'}">${message} <button class="btn btn-primary" id="newGameFromResult" style="margin-left: 10px; margin-top: 5px;">New Game</button></div>`);
        $('#gameControls').hide();
        $('#newGameBtn').prop('disabled', false).text('New Game').removeClass('game-disabled');
        // Remove disabled class from buttons (Hit and Stand were never disabled)
        $('.game-container button, .game-container .btn').not('[onclick*="openModal"], #hitBtn, #standBtn').removeClass('game-disabled');
        
        // Add click handler for new game button in result
        $('#newGameFromResult').click(function() {
            startGame();
        });
    }
    
    $('#newGameBtn').click(startGame);
    
    // Ensure new game button is enabled initially
    $('#newGameBtn').prop('disabled', false);
    
    $('#hitBtn').click(function() {
        if (!gameActive) return;
        
        dealCard(playerHand);
        displayHand(playerHand, '#playerHand');
        updateScores();
        
        if (calculateHand(playerHand) > 21) {
            endGame(false);
        }
    });
    
    $('#standBtn').click(function() {
        if (!gameActive) return;
        endGame(false);
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
