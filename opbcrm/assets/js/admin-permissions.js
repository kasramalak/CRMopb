// opbcrm/assets/js/admin-permissions.js
document.addEventListener('DOMContentLoaded', function () {
    const permissionsForm = document.getElementById('opbcrm-permissions-form');

    if (!permissionsForm) {
        return;
    }

    permissionsForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const spinner = this.querySelector('.spinner');
        const submitButton = this.querySelector('button[type="submit"]');

        spinner.classList.add('is-active');
        submitButton.disabled = true;

        const formData = new FormData(this);
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            spinner.classList.remove('is-active');
            submitButton.disabled = false;
            
            if (data.success) {
                alert(data.data.message || 'Permissions saved!');
            } else {
                alert('Error: ' + (data.data.message || 'An unknown error occurred.'));
            }
        })
        .catch(error => {
            spinner.classList.remove('is-active');
            submitButton.disabled = false;
            alert('An unexpected network error occurred.');
            console.error('Error:', error);
        });
    });

    // --- Add New Role AJAX ---
    $('#opbcrm-add-role-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var msgBox = $('#opbcrm-add-role-msg');
        msgBox.hide().text('');
        var formData = form.serialize() + '&action=opbcrm_add_role';
        form.find('button[type="submit"]').prop('disabled', true);
        $.post(ajaxurl, formData, function(response) {
            form.find('button[type="submit"]').prop('disabled', false);
            if (response.success) {
                msgBox.css('color', 'green').text(response.data.message).show();
                setTimeout(function(){ location.reload(); }, 1200);
            } else {
                msgBox.css('color', '#b00').text(response.data.message).show();
            }
        }).fail(function() {
            form.find('button[type="submit"]').prop('disabled', false);
            msgBox.css('color', '#b00').text('An unexpected error occurred.').show();
        });
    });
}); 