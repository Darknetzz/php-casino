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
        } else {
            // On error, keep current values or set to 0 if not set
            if ($('#winRate').text() === '-') {
                $('#winRate').text('0');
                $('#gamesPlayed').text('0');
                $('#wins').text('0');
            }
        }
    }, 'json').fail(function() {
        // On failure, keep current values
        if ($('#winRate').text() === '-') {
            $('#winRate').text('0');
            $('#gamesPlayed').text('0');
            $('#wins').text('0');
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
    if ($input.length === 0 || $input.closest('.bet-input-wrapper').length > 0) {
        return; // Already has buttons or input doesn't exist
    }
    
    // Check if input is inside bet-controls (flex container)
    const $betControls = $input.closest('.bet-controls');
    const isInBetControls = $betControls.length > 0;
    
    if (isInBetControls) {
        // For inputs in bet-controls, wrap in a container that maintains flex behavior
        const $wrapper = $('<div class="bet-input-wrapper"></div>');
        $input.wrap($wrapper);
        const $wrapperContainer = $input.parent(); // Get the wrapper after wrapping
        
        // Create button groups container (3-column grid)
        const $buttonGroups = $('<div class="bet-adjust-buttons-inline"></div>');
        
        // Arrange buttons in grid order: +1000, +100, +10, -10, -100, -1000
        const adjustments = [1000, 100, 10, -10, -100, -1000];
        
        adjustments.forEach(function(adjust) {
            const $btn = $('<button type="button" class="btn btn-sm bet-adjust-btn"></button>');
            $btn.text(adjust > 0 ? '+' + adjust : adjust);
            $btn.attr('data-adjust', adjust);
            
            if (adjust > 0) {
                $btn.addClass('bet-adjust-positive');
            } else {
                $btn.addClass('bet-adjust-negative');
            }
            
            $btn.on('click', function(e) {
                e.preventDefault();
                const current = parseFloat($input.val()) || 0;
                const min = parseFloat($input.attr('min')) || 0;
                const max = parseFloat($input.attr('max')) || Infinity;
                const newValue = Math.max(min, Math.min(max, current + adjust));
                $input.val(newValue).trigger('change');
            });
            
            $buttonGroups.append($btn);
        });
        
        $wrapperContainer.append($buttonGroups);
    } else {
        // For inputs not in bet-controls, use form-group structure
        const $formGroup = $('<div class="form-group"></div>');
        $input.wrap($formGroup);
        
        const $inputWrapper = $('<div class="form-input-wrapper"></div>');
        $input.wrap($inputWrapper);
        const $inputWrapperContainer = $input.parent(); // Get the wrapper after wrapping
        
        // Create button groups container (3-column grid)
        const $buttonGroups = $('<div class="form-button-groups"></div>');
        
        // Arrange buttons in grid order: +1000, +100, +10, -10, -100, -1000
        const adjustments = [1000, 100, 10, -10, -100, -1000];
        
        adjustments.forEach(function(adjust) {
            const $btn = $('<button type="button" class="btn btn-sm bet-adjust-btn"></button>');
            $btn.text(adjust > 0 ? '+' + adjust : adjust);
            $btn.attr('data-adjust', adjust);
            
            if (adjust > 0) {
                $btn.addClass('bet-adjust-positive');
            } else {
                $btn.addClass('bet-adjust-negative');
            }
            
            $btn.on('click', function(e) {
                e.preventDefault();
                const current = parseFloat($input.val()) || 0;
                const min = parseFloat($input.attr('min')) || 0;
                const max = parseFloat($input.attr('max')) || Infinity;
                const newValue = Math.max(min, Math.min(max, current + adjust));
                $input.val(newValue).trigger('change');
            });
            
            $buttonGroups.append($btn);
        });
        
        $inputWrapperContainer.after($buttonGroups);
    }
}

// Initialize bet adjustment buttons for all bet inputs
function initBetAdjustButtons() {
    // Use a small delay to ensure all DOM is ready
    setTimeout(function() {
        $('.bet-input-with-adjust').each(function() {
            const $input = $(this);
            // Only add if not already wrapped
            if ($input.closest('.bet-input-wrapper').length === 0 && $input.closest('.form-input-wrapper').length === 0) {
                addBetAdjustButtons($input);
            }
        });
    }, 100);
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
    
    // Also try again after a longer delay in case some inputs are added dynamically
    setTimeout(function() {
        initBetAdjustButtons();
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
