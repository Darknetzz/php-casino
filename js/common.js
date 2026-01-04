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
});
