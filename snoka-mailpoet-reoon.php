<?php
/*
Plugin Name: Snoka MailPoet Validator
Plugin URI: https://snoka.ca
Description: A plugin to validate email addresses using Reoon's API in MailPoet subscription forms.
Version: 1.0.0
Author: Snoka Media
Author URI: https://snoka.ca
License: GPL2
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


function mailpoet_reoon_settings_page() {
    add_menu_page(
        'MailPoet Reoon Settings', // Page title
        'MailPoet Reoon', // Menu title
        'manage_options', // Capability
        'mailpoet-reoon-settings', // Menu slug
        'mailpoet_reoon_settings_page_html' // Callback function
    );
}
add_action('admin_menu', 'mailpoet_reoon_settings_page');

function mailpoet_reoon_settings_page_html() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Output security fields for the registered setting "mailpoet_reoon"
            settings_fields('mailpoet_reoon');
            // Output setting sections and their fields
            do_settings_sections('mailpoet-reoon-settings');
            // Output save settings button
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}


function mailpoet_reoon_settings_init() {
    // Registering the setting for Reoon API key
    register_setting('mailpoet_reoon', 'mailpoet_reoon_api_key');
    register_setting('mailpoet_reoon', 'mailpoet_reoon_recaptcha_site_key');
    register_setting('mailpoet_reoon', 'mailpoet_reoon_recaptcha_secret_key');

    // Registering the setting for rejected email domains
    register_setting('mailpoet_reoon', 'mailpoet_reoon_rejected_domains');

    // Adding a new field for rejected email domains
    add_settings_field(
        'mailpoet_reoon_rejected_domains',
        'Rejected Email Domains',
        'mailpoet_reoon_rejected_domains_field_cb',
        'mailpoet-reoon-settings',
        'mailpoet_reoon_api_key_section'
    );    

    // Adding sections and fields
    add_settings_section(
        'mailpoet_reoon_api_key_section',
        'API Key Settings',
        'mailpoet_reoon_api_key_section_cb',
        'mailpoet-reoon-settings'
    );

    add_settings_field(
        'mailpoet_reoon_api_key',
        'Reoon API Key',
        'mailpoet_reoon_api_key_field_cb',
        'mailpoet-reoon-settings',
        'mailpoet_reoon_api_key_section'
    );

    add_settings_field(
        'mailpoet_reoon_recaptcha_site_key',
        'reCAPTCHA Site Key',
        'mailpoet_reoon_recaptcha_site_key_field_cb',
        'mailpoet-reoon-settings',
        'mailpoet_reoon_api_key_section'
    );

    add_settings_field(
        'mailpoet_reoon_recaptcha_secret_key',
        'reCAPTCHA Secret Key',
        'mailpoet_reoon_recaptcha_secret_key_field_cb',
        'mailpoet-reoon-settings',
        'mailpoet_reoon_api_key_section'
    );
}
add_action('admin_init', 'mailpoet_reoon_settings_init');

function mailpoet_reoon_rejected_domains_field_cb() {
    $setting = get_option('mailpoet_reoon_rejected_domains');
    echo '<input type="text" name="mailpoet_reoon_rejected_domains" value="' . esc_attr($setting) . '">';
    echo '<p class="description">Enter a comma-separated list of email domains to reject (e.g., "example.com, spam.com").</p>';
}

function mailpoet_reoon_recaptcha_site_key_field_cb() {
    $setting = get_option('mailpoet_reoon_recaptcha_site_key');
    echo '<input type="text" name="mailpoet_reoon_recaptcha_site_key" value="' . esc_attr($setting) . '">';
}

function mailpoet_reoon_recaptcha_secret_key_field_cb() {
    $setting = get_option('mailpoet_reoon_recaptcha_secret_key');
    echo '<input type="text" name="mailpoet_reoon_recaptcha_secret_key" value="' . esc_attr($setting) . '">';
}

function mailpoet_reoon_api_key_section_cb() {
    echo '<p>Enter your Reoon API Key here.</p>';
}

function mailpoet_reoon_api_key_field_cb() {
    $setting = get_option('mailpoet_reoon_api_key');
    ?>
    <input type="text" name="mailpoet_reoon_api_key" value="<?= isset($setting) ? esc_attr($setting) : ''; ?>">
    <?php
}


function render_mailpoet_reoon_subscription_form() {
    ob_start();    

    // Check if MailPoet is active
    if (!class_exists(\MailPoet\API\API::class)) {
        echo 'MailPoet must be activated.';
    } else {
        // Get MailPoet API instance
        $mailpoet_api = \MailPoet\API\API::MP('v1');
        $lists = $mailpoet_api->getLists();
        $subscriber_form_fields = $mailpoet_api->getSubscriberFields();

        $recaptcha_site_key = get_option('mailpoet_reoon_recaptcha_site_key');

        // Start form
        echo '<form id="mailpoet_reoon_form" action="' . esc_url($_SERVER['REQUEST_URI']) . '" method="post">';

        // Add form fields
        foreach ($subscriber_form_fields as $field) {
            if ($field['type'] === 'text' or $field['type'] === 'email') {
                echo '<label for="' . esc_attr($field['id']) . '">' . esc_html($field['name']) . '</label>';
                echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($field['id']) . '" id="' . esc_attr($field['id']) . '" value="' . (isset($_POST[$field['id']]) ? esc_attr($_POST[$field['id']]) : '') . '"><br>';
            }
        }

        // Add list selection
        echo '<label for="mailpoet_list">Subscribe to:</label>';
        echo '<select name="list_ids[]" id="mailpoet_list" multiple>';
        foreach ($lists as $list) {
            echo '<option value="' . esc_attr($list['id']) . '">' . esc_html($list['name']) . '</option>';
        }
        echo '</select><br>';

        // Add nonce field for security
        wp_nonce_field('mailpoet_reoon_form_action', 'mailpoet_reoon_form_nonce', true, true);
        // Add Google reCAPTCHA script and widget
        echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
        echo '<div class="g-recaptcha" id="mailpoet_reoon_recaptcha" data-sitekey="' . esc_attr($recaptcha_site_key) . '"></div>';

        // Add submit button
        echo '<input type="button" id="mailpoet_reoon_submit" value="Subscribe">';

        // End form
        echo '</form>';


        // Add a div for displaying messages
        echo '<div id="mailpoet_reoon_message"></div>';
    }

    return ob_get_clean();
}
add_shortcode('mailpoet_reoon_form', 'render_mailpoet_reoon_subscription_form');




function process_mailpoet_reoon_form_submission() {
    // Additional check for the specific AJAX action
    if (!isset($_POST['action']) || $_POST['action'] !== 'process_mailpoet_reoon_form') {
        return;
    }

    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        error_log('Not an AJAX call');
        return;
    }

    // Log the entire $_POST array for debugging
    error_log('POST Data: ' . print_r($_POST, true));

    // Log the received nonce value
    if (isset($_POST['mailpoet_reoon_form_nonce'])) {
        error_log('Received nonce: ' . $_POST['mailpoet_reoon_form_nonce']);
    } else {
        error_log('Nonce not set in POST data');
    }

    // Verify nonce
    if (!isset($_POST['mailpoet_reoon_form_nonce']) || !wp_verify_nonce($_POST['mailpoet_reoon_form_nonce'], 'mailpoet_reoon_form_action')) {
        error_log('Nonce verification failed');
        wp_send_json_error(array('message' => 'Nonce verification failed.'));
        wp_die();
    }

    // Ensure email field is set and not empty
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    if (empty($email)) {
        error_log('Email field is empty');
        wp_send_json_error(array('message' => 'Email field is required.'));
        wp_die();
    } else {

        // reCAPTCHA verification
        $recaptcha_response = $_POST['g-recaptcha-response'];
        // reCAPTCHA verification
        $recaptcha_secret = get_option('mailpoet_reoon_recaptcha_secret_key');
        $recaptcha_verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $recaptcha_secret,
                'response' => $recaptcha_response
            ]
        ]);

        $recaptcha_verify_body = wp_remote_retrieve_body($recaptcha_verify);
        $recaptcha_result = json_decode($recaptcha_verify_body, true);

        // Inside process_mailpoet_reoon_form_submission()
        if (!$recaptcha_result['success']) {
            wp_send_json_error(array('message' => 'reCAPTCHA verification failed.'));
            wp_die(); // Terminate AJAX request
        }


        // Ensure email field is set and not empty
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (empty($email)) {
            error_log('Email field is empty');
            wp_send_json_error(array('message' => 'Email field is required.'));
            wp_die();
        }

        // Check if the email domain is in the list of rejected domains
        $rejected_domains = explode(',', get_option('mailpoet_reoon_rejected_domains'));
        $rejected_domains = array_map('trim', $rejected_domains); // Trim whitespace
        $email_domain = substr(strrchr($email, "@"), 1);
        if (in_array($email_domain, $rejected_domains)) {
            wp_send_json_error(array('message' => 'Email domain is not allowed.'));
            wp_die();
        }

        // Fetch the saved Reoon API key from settings
        $reoon_api_key = get_option('mailpoet_reoon_api_key');

        // Validate email with Reoon API
        $reoon_response = wp_remote_get("https://emailverifier.reoon.com/api/v1/verify?email=$email&key=$reoon_api_key&mode=quick");

        // Error handling for Reoon API call
        if (is_wp_error($reoon_response)) {
            error_log('Reoon API Call Error: ' . $reoon_response->get_error_message());
            wp_send_json_error(array('message' => 'Error in Reoon API call: ' . $reoon_response->get_error_message()));
            wp_die();
        }
        
        $body = wp_remote_retrieve_body($reoon_response);
        $data = json_decode($body, true);

        // Log the entire response
        error_log('Reoon API Full Response: ' . print_r($data, true));
        
        if (!isset($data['status']) || $data['status'] !== 'valid') {
            wp_send_json_error(array('message' => 'Invalid response from Reoon API.'));
            wp_die();
        }
        

        // Proceed only if email is valid
        if ($data['status'] === 'valid') {
            // Get MailPoet API instance
            $mailpoet_api = \MailPoet\API\API::MP('v1');

            // Prepare subscriber data
            $subscriber = ['email' => $email];
            $subscriber_form_fields = $mailpoet_api->getSubscriberFields();
            foreach ($subscriber_form_fields as $field) {
                if (isset($_POST[$field['id']]) && $field['id'] !== 'email') {
                    $subscriber[$field['id']] = $_POST[$field['id']];
                }
            }
            $list_ids = isset($_POST['list_ids']) ? $_POST['list_ids'] : [];

            // Add or update subscriber in MailPoet
            try {
                $get_subscriber = $mailpoet_api->getSubscriber($email);
            } catch (\Exception $e) {
                $get_subscriber = false;
            }

            try {
                if (!$get_subscriber) {
                    // Add new subscriber
                    $mailpoet_api->addSubscriber($subscriber, $list_ids);
                    wp_send_json_success(array('message' => 'Subscription successful.'));
                } else {
                    // Update existing subscriber
                    $mailpoet_api->subscribeToLists($email, $list_ids);
                    wp_send_json_success(array('message' => 'You are already subscribed.'));
                }
            } catch (\Exception $e) {
                $error_message = $e->getMessage();
                wp_send_json_error(array('message' => $error_message));
                wp_die(); // Terminate AJAX request
            }
        } else {
            wp_send_json_error(array('message' => 'Email is invalid.'));
        }
    }
}



function mailpoet_reoon_enqueue_scripts() {
    if (has_shortcode(get_post()->post_content, 'mailpoet_reoon_form')) {
        // Enqueue your script
        wp_enqueue_script('mailpoet_reoon_ajax_script', plugins_url('/js/mailpoet_reoon_ajax.js', __FILE__), array('jquery'), null, true);

        // Localize the script with new data
        wp_localize_script('mailpoet_reoon_ajax_script', 'mailpoet_reoon_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),

        ));
    }
}
add_action('wp_enqueue_scripts', 'mailpoet_reoon_enqueue_scripts');



add_action('wp_ajax_process_mailpoet_reoon_form', 'process_mailpoet_reoon_form_submission');
add_action('wp_ajax_nopriv_process_mailpoet_reoon_form', 'process_mailpoet_reoon_form_submission');

