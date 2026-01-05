// Common JavaScript functions used across the casino application

// Format number with commas (like PHP's number_format)
function formatNumber(num, decimals = 2) {
    const numStr = parseFloat(num).toFixed(decimals);
    const parts = numStr.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
}

// Update balance display
function updateBalance() {
    $.get(getApiPath('getBalance'), function(data) {
        if (data.success) {
            $('#balance').text(formatNumber(data.balance));
            $('.balance-large').text('$' + formatNumber(data.balance));
        }
    }, 'json');
}

// Get API path based on current page location
function getApiPath(action) {
    let basePath = '';
    if (window.location.pathname.includes('/games/') || window.location.pathname.includes('/pages/')) {
        basePath = '../';
    }
    return basePath + 'api/api.php?action=' + action;
}

// Update balance periodically (every 5 seconds)
function startBalanceUpdates() {
    setInterval(function() {
        updateBalance();
    }, 5000);
}

// Update win rate stats for a specific game
function updateWinRateStats(gameName) {
    // Only update if the stats elements exist on the page
    if ($('#winRate').length === 0) {
        return;
    }
    
    $.get(getApiPath('getWinRates') + '&game=' + gameName, function(data) {
        if (data.success && data.winRate) {
            $('#winRate').text(data.winRate.rate || 0);
            $('#gamesPlayed').text(data.winRate.total || 0);
            $('#wins').text(data.winRate.wins || 0);
            if ($('#netWinLoss').length > 0) {
                const netWinLoss = data.winRate.netWinLoss || 0;
                const netWinLossText = netWinLoss >= 0 ? '$' + netWinLoss.toFixed(2) : '-$' + Math.abs(netWinLoss).toFixed(2);
                $('#netWinLoss').text(netWinLossText).css('color', netWinLoss >= 0 ? '#28a745' : '#dc3545');
            }
        } else {
            // On error, keep current values or set to 0 if not set
            if ($('#winRate').text() === '-') {
                $('#winRate').text('0');
                $('#gamesPlayed').text('0');
                $('#wins').text('0');
                if ($('#netWinLoss').length > 0) {
                    $('#netWinLoss').text('$0.00').css('color', '#666');
                }
            }
        }
    }, 'json').fail(function() {
        // On failure, keep current values
        if ($('#winRate').text() === '-') {
            $('#winRate').text('0');
            $('#gamesPlayed').text('0');
            $('#wins').text('0');
            if ($('#netWinLoss').length > 0) {
                $('#netWinLoss').text('$0.00').css('color', '#666');
            }
        }
    });
}

// Dark mode functions
function applyDarkMode(isDark) {
    if (isDark) {
        $('body').addClass('dark-mode');
        $('#darkModeIcon').text('☀️');
    } else {
        $('body').removeClass('dark-mode');
        $('#darkModeIcon').text('🌙');
    }
}

function toggleDarkMode() {
    const isCurrentlyDark = $('body').hasClass('dark-mode');
    const newDarkMode = !isCurrentlyDark;
    
    applyDarkMode(newDarkMode);
    
    // Save preference
    $.post(getApiPath('updateDarkMode'), {
        darkMode: newDarkMode ? 1 : 0
    }, function(data) {
        if (!data.success) {
            // Revert on error
            applyDarkMode(isCurrentlyDark);
        }
    }, 'json');
}

function loadDarkMode() {
    $.get(getApiPath('getDarkMode'), function(data) {
        if (data.success) {
            applyDarkMode(data.darkMode);
        }
    }, 'json');
}

// Add bet adjustment buttons to bet inputs
function addBetAdjustButtons(inputSelector) {
    const $input = $(inputSelector);
    if ($input.length === 0 || $input.closest('.bet-input-inline-wrapper').length > 0) {
        return; // Already has buttons or input doesn't exist
    }
    
    // Wrap input in inline wrapper with minus/plus buttons
    const $inlineWrapper = $('<div class="bet-input-inline-wrapper"></div>');
    $input.wrap($inlineWrapper);
    
    // Create minus button (left side)
    const $minusBtn = $('<button type="button" class="bet-adjust-btn-inline bet-adjust-negative" title="Decrease bet"></button>');
    $minusBtn.html('<span>−</span>');
    $minusBtn.on('click', function(e) {
        e.preventDefault();
        const current = parseFloat($input.val()) || 0;
        const min = parseFloat($input.attr('min')) || 0;
        const maxAttr = $input.attr('max');
        const max = maxAttr ? parseFloat(maxAttr) : Infinity;
        const newValue = Math.max(min, Math.min(max, current - 1));
        $input.val(newValue).trigger('change');
    });
    
    // Create plus button (right side)
    const $plusBtn = $('<button type="button" class="bet-adjust-btn-inline bet-adjust-positive" title="Increase bet"></button>');
    $plusBtn.html('<span>+</span>');
    $plusBtn.on('click', function(e) {
        e.preventDefault();
        const current = parseFloat($input.val()) || 0;
        const min = parseFloat($input.attr('min')) || 0;
        const maxAttr = $input.attr('max');
        const max = maxAttr ? parseFloat(maxAttr) : Infinity;
        const newValue = Math.max(min, Math.min(max, current + 1));
        $input.val(newValue).trigger('change');
    });
    
    // Add buttons to wrapper (minus before input, plus after)
    $input.before($minusBtn);
    $input.after($plusBtn);
    
    // Add class to input for styling
    $input.addClass('bet-input-inline');
}

// Initialize bet adjustment buttons for all bet inputs
function initBetAdjustButtons() {
    // Use a small delay to ensure all DOM is ready
    setTimeout(function() {
        $('.bet-input-with-adjust').each(function() {
            const $input = $(this);
            // Only add if not already wrapped
            if ($input.closest('.bet-input-inline-wrapper').length === 0) {
                addBetAdjustButtons($input);
            }
        });
    }, 100);
}

// Initialize quick bet buttons for all games
function initQuickBetButtons() {
    $('.quick-bet-btn').on('click', function(e) {
        e.preventDefault();
        const amount = parseFloat($(this).data('amount'));
        if (amount) {
            // Find the bet input in the same bet-controls container
            const $betControls = $(this).closest('.bet-controls');
            const $input = $betControls.find('input.bet-input-with-adjust, input[id="betAmount"]');
            if ($input.length > 0) {
                const maxAttr = $input.attr('max');
                const max = maxAttr ? parseFloat(maxAttr) : Infinity;
                const finalAmount = Math.min(amount, max);
                $input.val(finalAmount).trigger('change');
            }
        }
    });
}

// Initialize common functionality when document is ready
$(document).ready(function() {
    // Start balance updates if balance element exists
    if ($('#balance').length > 0) {
        startBalanceUpdates();
    }
    
    // Load dark mode preference
    loadDarkMode();
    
    // Set up dark mode toggle
    $('#darkModeToggle').on('click', function(e) {
        e.stopPropagation();
        toggleDarkMode();
    });
    
    // Initialize bet adjustment buttons
    initBetAdjustButtons();
    
    // Initialize quick bet buttons
    initQuickBetButtons();
    
    // Also try again after a longer delay in case some inputs are added dynamically
    setTimeout(function() {
        initBetAdjustButtons();
        initQuickBetButtons();
    }, 500);
});

// Close modal by ID
function closeModal(modalId) {
    $('#' + modalId).hide();
}

// Open modal by ID
function openModal(modalId) {
    $('#' + modalId).show();
}

// Close modal when clicking outside of it
$(document).ready(function() {
    $(document).on('click', '.modal', function(e) {
        // If clicking directly on the modal (background), close it
        if ($(e.target).hasClass('modal')) {
            $(this).hide();
        }
    });
    
    // Prevent modal content clicks from closing the modal
    $(document).on('click', '.modal-content', function(e) {
        e.stopPropagation();
    });
});
