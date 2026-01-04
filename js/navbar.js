// Navbar dropdown functionality
$(document).ready(function() {
    // Games menu dropdown toggle
    $('#gamesMenuBtn').on('click', function(e) {
        e.stopPropagation();
        $('.games-menu').toggleClass('active');
        // Close other dropdowns
        $('.user-menu').removeClass('active');
        $('#notificationDropdown').hide();
    });
    
    // User menu dropdown toggle
    $('#userMenuBtn').on('click', function(e) {
        e.stopPropagation();
        $('.user-menu').toggleClass('active');
        // Close other dropdowns
        $('.games-menu').removeClass('active');
        $('#notificationDropdown').hide();
    });
    
    // Close dropdowns when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.user-menu').length) {
            $('.user-menu').removeClass('active');
        }
        if (!$(e.target).closest('.games-menu').length) {
            $('.games-menu').removeClass('active');
        }
        if (!$(e.target).closest('.notification-menu').length) {
            $('#notificationDropdown').hide();
        }
    });
});
