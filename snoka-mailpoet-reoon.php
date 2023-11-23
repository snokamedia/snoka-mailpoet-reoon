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
    // Check if MailPoet is active
    if (!class_exists(\MailPoet\API\API::class)) {
        return 'MailPoet must be activated.';
    }

    // Get MailPoet API instance
    $mailpoet_api = \MailPoet\API\API::MP('v1');
    $lists = $mailpoet_api->getLists();
    $subscriber_form_fields = $mailpoet_api->getSubscriberFields();

    $recaptcha_site_key = get_option('mailpoet_reoon_recaptcha_site_key');

    // Start form
    $output = '<form id="mailpoet_reoon_form" action="' . esc_url($_SERVER['REQUEST_URI']) . '" method="post">';

    // Add form fields
    foreach ($subscriber_form_fields as $field) {
        if ($field['type'] === 'text' or $field['type'] === 'email') {
            $output .= '<label for="' . esc_attr($field['id']) . '">' . esc_html($field['name']) . '</label>';
            $output .= '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($field['id']) . '" id="' . esc_attr($field['id']) . '" value="' . (isset($_POST[$field['id']]) ? esc_attr($_POST[$field['id']]) : '') . '"><br>';
        }
    }

    // Add list selection
    $output .= '<label for="mailpoet_list">Subscribe to:</label>';
    $output .= '<select name="list_ids[]" id="mailpoet_list" multiple>';
    foreach ($lists as $list) {
        $output .= '<option value="' . esc_attr($list['id']) . '">' . esc_html($list['name']) . '</option>';
    }
    $output .= '</select><br>';

    // Add nonce field for security
    $output .= wp_nonce_field('mailpoet_reoon_form_action', 'mailpoet_reoon_form_nonce');

    // Add submit button
    $output .= '<input type="button" id="mailpoet_reoon_submit" value="Subscribe">';

    // End form
    $output .= '</form>';

    // Add Google reCAPTCHA script and widget
    $output .= '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    $output .= '<div class="g-recaptcha" data-sitekey="' . esc_attr($recaptcha_site_key) . '"></div>';

    // Add a div for displaying messages
    $output .= '<div id="mailpoet_reoon_message"></div>';

    return $output;
}
add_shortcode('mailpoet_reoon_form', 'render_mailpoet_reoon_subscription_form');



function process_mailpoet_reoon_form_submission() {
    check_ajax_referer('mailpoet_reoon_ajax_nonce');

    if (isset($_POST['mailpoet_reoon_submit'])) {

        // Verify nonce
        if (!isset($_POST['mailpoet_reoon_form_nonce']) || !wp_verify_nonce($_POST['mailpoet_reoon_form_nonce'], 'mailpoet_reoon_form_action')) {
            return 'Nonce verification failed.';
        }

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

        if (!$recaptcha_result['success']) {
            wp_send_json_error(array('message' => 'reCAPTCHA verification failed.'));
            return;
        }

        // Ensure email field is set and not empty
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (empty($email)) {
            return 'Email field is required.';
        }        

        // Fetch the saved Reoon API key from settings
        $reoon_api_key = get_option('mailpoet_reoon_api_key');

        // Validate email with Reoon API
        $reoon_response = wp_remote_get("https://emailverifier.reoon.com/api/v1/verify?email=$email&key=$reoon_api_key&mode=quick");

        if (is_wp_error($reoon_response)) {
            return 'Error in Reoon API call.';
        }

        $body = wp_remote_retrieve_body($reoon_response);
        $data = json_decode($body, true);

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
                } else {
                    // Update existing subscriber
                    $mailpoet_api->subscribeToLists($email, $list_ids);
                }
            } catch (\Exception $e) {
                $error_message = $e->getMessage();
                return $error_message; // Optionally handle this message in your form
            }
        } else {
            wp_send_json_error(array('message' => 'Email is invalid.'));
        }
        // If everything is successful
        wp_send_json_success(array('message' => 'Subscription successful.'));
    }
}
add_action('init', 'process_mailpoet_reoon_form_submission');


function mailpoet_reoon_enqueue_scripts() {
    wp_enqueue_script('mailpoet_reoon_ajax_script', plugins_url('/js/mailpoet_reoon_ajax.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('mailpoet_reoon_ajax_script', 'mailpoet_reoon_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mailpoet_reoon_ajax_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'mailpoet_reoon_enqueue_scripts');


add_action('wp_ajax_process_mailpoet_reoon_form', 'process_mailpoet_reoon_form_submission');
add_action('wp_ajax_nopriv_process_mailpoet_reoon_form', 'process_mailpoet_reoon_form_submission');

