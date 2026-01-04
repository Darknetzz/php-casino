// Common JavaScript functions used across the casino application

// Update balance display
function updateBalance() {
    $.get(getApiPath('getBalance'), function(data) {
        if (data.success) {
            $('#balance').text(parseFloat(data.balance).toFixed(2));
            $('.balance-large').text('$' + parseFloat(data.balance).toFixed(2));
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
    if ($input.length === 0 || $input.closest('.form-group').length > 0) {
        return; // Already has buttons or input doesn't exist
    }
    
    // Wrap input in form-group structure
    const $formGroup = $('<div class="form-group"></div>');
    $input.wrap($formGroup);
    
    // Create input wrapper
    const $inputWrapper = $('<div class="form-input-wrapper"></div>');
    $input.wrap($inputWrapper);
    
    // Create button groups - positive and negative
    const $buttonGroups = $('<div class="form-button-groups"></div>');
    
    const $positiveGroup = $('<div class="button-group button-group-positive"></div>');
    const $negativeGroup = $('<div class="button-group button-group-negative"></div>');
    
    const adjustments = [1000, 100, 10, -10, -100, -1000];
    
    adjustments.forEach(function(adjust) {
        const $btn = $('<button type="button" class="btn btn-sm bet-adjust-btn"></button>');
        $btn.text(adjust > 0 ? '+' + adjust : adjust);
        $btn.attr('data-adjust', adjust);
        
        // Add class for positive/negative buttons
        if (adjust > 0) {
            $btn.addClass('bet-adjust-positive');
            $positiveGroup.append($btn);
        } else {
            $btn.addClass('bet-adjust-negative');
            $negativeGroup.append($btn);
        }
        
        $btn.on('click', function(e) {
            e.preventDefault();
            const current = parseFloat($input.val()) || 0;
            const min = parseFloat($input.attr('min')) || 0;
            const max = parseFloat($input.attr('max')) || Infinity;
            const newValue = Math.max(min, Math.min(max, current + adjust));
            $input.val(newValue).trigger('change');
        });
    });
    
    $buttonGroups.append($positiveGroup);
    $buttonGroups.append($negativeGroup);
    $inputWrapper.after($buttonGroups);
}

// Initialize bet adjustment buttons for all bet inputs
function initBetAdjustButtons() {
    $('.bet-input-with-adjust').each(function() {
        addBetAdjustButtons($(this));
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
});
