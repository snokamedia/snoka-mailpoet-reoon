document.addEventListener('DOMContentLoaded', function() {
    var submitButton = document.getElementById('mailpoet_reoon_submit');
    var messageDiv = document.getElementById('mailpoet_reoon_message');
    submitButton.addEventListener('click', function(event) {
        event.preventDefault();
        messageDiv.classList.add('processing-message');        
        messageDiv.innerHTML = 'Checking email';
        var form = document.getElementById('mailpoet_reoon_form');
        var formData = new FormData(form);
        var nonceField = document.getElementById('mailpoet_reoon_form_nonce');
        if (nonceField) {
            var nonce = nonceField.value;
            formData.append('mailpoet_reoon_form_nonce', nonce);
        }
        formData.append('g-recaptcha-response', document.getElementById('mailpoet_reoon_recaptcha').querySelector('.g-recaptcha-response').value);
        formData.append('action', 'process_mailpoet_reoon_form');
        fetch(mailpoet_reoon_ajax_object.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                messageDiv.classList.remove('processing-message');
                messageDiv.classList.add('success-message');
                messageDiv.innerHTML = data.data.message;
            } else {
                messageDiv.classList.remove('processing-message');
                messageDiv.classList.add('error-message');
                messageDiv.innerHTML = data.data.message;
            }
        })
        .catch((error) => {
            messageDiv.classList.remove('processing-message');
            console.error('Error:', error);
            messageDiv.innerHTML = 'An error occurred: ' + error.message;
            messageDiv.classList.add('error-message');
        });
    });
});
