$(document).ready(function() {
    // Form validation
    $('#signupForm, #loginForm').on('submit', function(e) {
        const form = $(this);
        let isValid = true;
        
        form.find('input[required]').each(function() {
            if (!$(this).val().trim()) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (form.attr('id') === 'signupForm') {
            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();
            
            if (password !== confirmPassword) {
                isValid = false;
                $('#confirm_password').addClass('error');
            }
            
            if (password.length < 6) {
                isValid = false;
                $('#password').addClass('error');
            }
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});
