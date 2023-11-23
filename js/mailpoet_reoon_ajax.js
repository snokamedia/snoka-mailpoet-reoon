document.addEventListener('DOMContentLoaded', function() {
    var submitButton = document.getElementById('mailpoet_reoon_submit');
    var messageDiv = document.getElementById('mailpoet_reoon_message');
    console.log("Nonce valuetest"); // Correct variable used in log     
    submitButton.addEventListener('click', function(event) {
        event.preventDefault();
        messageDiv.innerHTML = 'Loading...';
        messageDiv.classList.remove('success-message', 'error-message');

        var form = document.getElementById('mailpoet_reoon_form');
        var formData = new FormData(form);

        var nonceField = document.getElementById('mailpoet_reoon_form_nonce');
        if (nonceField) {
            var nonce = nonceField.value; // Correctly declared variable
            formData.append('mailpoet_reoon_form_nonce', nonce);
            console.log("Nonce value: ", nonce); // Correct variable used in log
        } else {
            console.error('Nonce field not found');
        }
        console.log("Nonce valuetest"); // Correct variable used in log     
        // Before the fetch call
        for (var pair of formData.entries()) {
            console.log(pair[0]+ ', ' + pair[1]);
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
                messageDiv.classList.add('success-message');
                messageDiv.innerHTML = data.data.message;
            } else {
                messageDiv.classList.add('error-message');
                messageDiv.innerHTML = data.data.message; // Display the error message from the server
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            messageDiv.innerHTML = 'An error occurred: ' + error.message;
            messageDiv.classList.add('error-message');
        });
    });
});
