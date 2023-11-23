document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('mailpoet_reoon_submit').addEventListener('click', function() {
        var form = document.getElementById('mailpoet_reoon_form');
        var formData = new FormData(form);
        // Append reCAPTCHA response
        formData.append('g-recaptcha-response', document.querySelector('.g-recaptcha-response').value);
        formData.append('action', 'process_mailpoet_reoon_form');
        formData.append('mailpoet_reoon_form_nonce', mailpoet_reoon_ajax_object.nonce);

        fetch(mailpoet_reoon_ajax_object.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            var messageDiv = document.getElementById('mailpoet_reoon_message');
            messageDiv.innerHTML = data.data.message;
            if(data.success) {
                messageDiv.classList.add('success-message');
                messageDiv.classList.remove('error-message');
            } else {
                messageDiv.classList.add('error-message');
                messageDiv.classList.remove('success-message');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
        });
    });
});
