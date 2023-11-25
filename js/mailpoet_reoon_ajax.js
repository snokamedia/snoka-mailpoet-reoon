document.addEventListener('DOMContentLoaded', function() {
    var submitButton = document.getElementById('mailpoet_reoon_submit');
    var messageDiv = document.getElementById('mailpoet_reoon_message');
    var form = document.getElementById('mailpoet_reoon_form'); // Moved for easier access
    var recaptchaWidgetId;
    var isRecaptchaValid = false;
    var isEmailValid = false;

    var emailInput = document.getElementById('snoka-email-verify-input'); // Email input field

    // Regular expression for simple email validation
    var emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    // Function to validate email and update button state
    function validateEmail() {
        isEmailValid = emailRegex.test(emailInput.value);
        if (isEmailValid) {
            emailInput.classList.remove('invalid');
            emailInput.classList.add('valid');
        } else {
            emailInput.classList.remove('valid');
            emailInput.classList.add('invalid');
        }
        updateSubmitButtonState();
    }

    // Event listener for email input
    emailInput.addEventListener('input', validateEmail);


    // Initially disable the submit button
    submitButton.disabled = true;

    // Function to update submit button state
    function updateSubmitButtonState() {
        submitButton.disabled = !(isEmailValid && isRecaptchaValid);
    }

    // Check if reCAPTCHA site key is available
    if (mailpoet_reoon_ajax_object.recaptcha_site_key) {
        // Load the reCAPTCHA library
        var script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoad&render=explicit';
        script.async = true;
        script.defer = true;
        document.body.appendChild(script);

        // Define the callback function for reCAPTCHA
        window.onRecaptchaLoad = function() {
            recaptchaWidgetId = grecaptcha.render('mailpoet_reoon_recaptcha', {
                'sitekey': mailpoet_reoon_ajax_object.recaptcha_site_key,
                'callback': onRecaptchaSuccess // Add this callback
            });
        };

        // Define what happens when reCAPTCHA is successfully completed
        function onRecaptchaSuccess() {
            isRecaptchaValid = true;
            updateSubmitButtonState();
        }
    }

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
        form.style.display = 'none'; // Hide the form

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
                if (typeof grecaptcha !== 'undefined' && recaptchaWidgetId !== undefined) {
                    grecaptcha.reset(recaptchaWidgetId);
                }
                form.style.display = 'block';
            }
        })
        .catch((error) => {
            messageDiv.classList.remove('processing-message');
            console.error('Error:', error);
            messageDiv.innerHTML = 'An error occurred: ' + error.message;
            messageDiv.classList.add('error-message');
            if (typeof grecaptcha !== 'undefined' && recaptchaWidgetId !== undefined) {
                grecaptcha.reset(recaptchaWidgetId);
            }
            form.style.display = 'block';
        });
    });
});
