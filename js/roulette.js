$(document).ready(function() {
    let selectedBet = null;
    let isSpinning = false;
    
    // Roulette numbers with colors (0 is green)
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
    
    function getNumberColor(num) {
        const entry = rouletteNumbers.find(n => n.num === num);
        return entry ? entry.color : 'black';
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
        if (betAmount < 1 || betAmount > 100) {
            $('#result').html('<div class="alert alert-error">Bet must be between $1 and $100</div>');
            return;
        }
        
        isSpinning = true;
        $('#spinBtn').prop('disabled', true);
        $('#result').html('');
        
        // Spin animation
        let spinCount = 0;
        const maxSpins = 30;
        const spinInterval = setInterval(function() {
            const randomNum = Math.floor(Math.random() * 37);
            const color = getNumberColor(randomNum);
            $('#rouletteResult').html(`<span class="roulette-number roulette-${color}">${randomNum}</span>`);
            spinCount++;
            
            if (spinCount >= maxSpins) {
                clearInterval(spinInterval);
                
                const resultNum = Math.floor(Math.random() * 37);
                const resultColor = getNumberColor(resultNum);
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
