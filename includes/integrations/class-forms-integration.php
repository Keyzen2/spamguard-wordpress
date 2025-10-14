<?php
/**
 * SpamGuard Forms Integration
 * Integrates with popular form plugins: Contact Form 7, Gravity Forms, WPForms, Ninja Forms
 *
 * @package SpamGuard
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Forms_Integration {

    private static $instance = null;
    private $active_integrations = array();
    private $api_client;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Get API client
        if (class_exists('SpamGuard_API_Client')) {
            $this->api_client = SpamGuard_API_Client::get_instance();
        }

        // Initialize integrations
        add_action('plugins_loaded', array($this, 'init_integrations'), 20);

        // AJAX for testing
        add_action('wp_ajax_spamguard_test_form_protection', array($this, 'ajax_test_form_protection'));
    }

    /**
     * Initialize form integrations
     */
    public function init_integrations() {
        // Contact Form 7
        if ($this->is_enabled('contact_form_7') && $this->is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
            $this->init_contact_form_7();
            $this->active_integrations['contact_form_7'] = 'Contact Form 7';
        }

        // Gravity Forms
        if ($this->is_enabled('gravity_forms') && class_exists('GFForms')) {
            $this->init_gravity_forms();
            $this->active_integrations['gravity_forms'] = 'Gravity Forms';
        }

        // WPForms
        if ($this->is_enabled('wpforms') && function_exists('wpforms')) {
            $this->init_wpforms();
            $this->active_integrations['wpforms'] = 'WPForms';
        }

        // Ninja Forms
        if ($this->is_enabled('ninja_forms') && class_exists('Ninja_Forms')) {
            $this->init_ninja_forms();
            $this->active_integrations['ninja_forms'] = 'Ninja Forms';
        }

        // Elementor Forms
        if ($this->is_enabled('elementor_forms') && defined('ELEMENTOR_PRO_VERSION')) {
            $this->init_elementor_forms();
            $this->active_integrations['elementor_forms'] = 'Elementor Forms';
        }
    }

    /**
     * Check if plugin is active
     */
    private function is_plugin_active($plugin) {
        return is_plugin_active($plugin) || (is_multisite() && is_plugin_active_for_network($plugin));
    }

    /**
     * Check if integration is enabled
     */
    private function is_enabled($integration) {
        return get_option('spamguard_forms_' . $integration . '_enabled', true);
    }

    /**
     * Get active integrations
     */
    public function get_active_integrations() {
        return $this->active_integrations;
    }

    /**
     * =================================
     * CONTACT FORM 7 INTEGRATION
     * =================================
     */
    private function init_contact_form_7() {
        add_filter('wpcf7_validate', array($this, 'validate_contact_form_7'), 10, 2);
        add_action('wpcf7_before_send_mail', array($this, 'before_cf7_send'), 10, 1);
    }

    public function validate_contact_form_7($result, $tags) {
        $submission = WPCF7_Submission::get_instance();

        if (!$submission) {
            return $result;
        }

        $posted_data = $submission->get_posted_data();

        // Extract relevant data
        $content = '';
        $author_name = '';
        $author_email = '';

        // Try to find name field
        $name_fields = array('your-name', 'name', 'nombre', 'your_name');
        foreach ($name_fields as $field) {
            if (isset($posted_data[$field])) {
                $author_name = $posted_data[$field];
                break;
            }
        }

        // Try to find email field
        $email_fields = array('your-email', 'email', 'correo', 'your_email');
        foreach ($email_fields as $field) {
            if (isset($posted_data[$field])) {
                $author_email = $posted_data[$field];
                break;
            }
        }

        // Try to find message field
        $message_fields = array('your-message', 'message', 'mensaje', 'your_message', 'comments');
        foreach ($message_fields as $field) {
            if (isset($posted_data[$field])) {
                $content = $posted_data[$field];
                break;
            }
        }

        // If no content, combine all text fields
        if (empty($content)) {
            $content = implode(' ', array_filter($posted_data, 'is_string'));
        }

        // Check if whitelisted
        if ($this->is_whitelisted($author_email, $author_name)) {
            return $result;
        }

        // Check if blacklisted
        if ($this->is_blacklisted($author_email, $author_name, $content)) {
            $result->invalidate('', __('Your submission has been blocked due to spam detection. If you believe this is an error, please contact the site administrator.', 'spamguard'));
            $this->log_form_submission('contact_form_7', $author_name, $author_email, $content, 'blocked', 'blacklist');
            return $result;
        }

        // Call SpamGuard API
        $spam_check = $this->check_spam($content, $author_name, $author_email, 'contact_form_7');

        if ($spam_check['is_spam']) {
            $result->invalidate('', __('Your submission appears to be spam. If you believe this is an error, please contact the site administrator.', 'spamguard'));
            $this->log_form_submission('contact_form_7', $author_name, $author_email, $content, 'blocked', 'api');
        } else {
            $this->log_form_submission('contact_form_7', $author_name, $author_email, $content, 'allowed', 'api');
        }

        return $result;
    }

    public function before_cf7_send($contact_form) {
        // Hook before email is sent (if needed for additional processing)
    }

    /**
     * =================================
     * GRAVITY FORMS INTEGRATION
     * =================================
     */
    private function init_gravity_forms() {
        add_filter('gform_validation', array($this, 'validate_gravity_forms'), 10, 1);
    }

    public function validate_gravity_forms($validation_result) {
        $form = $validation_result['form'];

        // Extract data from form fields
        $content = '';
        $author_name = '';
        $author_email = '';

        foreach ($form['fields'] as $field) {
            $field_value = RGFormsModel::get_field_value($field);

            // Identify field type
            if ($field->type === 'name') {
                $author_name = is_array($field_value) ? implode(' ', $field_value) : $field_value;
            } elseif ($field->type === 'email') {
                $author_email = $field_value;
            } elseif (in_array($field->type, array('textarea', 'text', 'post_content'))) {
                $content .= ' ' . $field_value;
            }
        }

        $content = trim($content);

        // Check whitelist
        if ($this->is_whitelisted($author_email, $author_name)) {
            return $validation_result;
        }

        // Check blacklist
        if ($this->is_blacklisted($author_email, $author_name, $content)) {
            $validation_result['is_valid'] = false;
            foreach ($form['fields'] as &$field) {
                if ($field->type === 'textarea' || $field->type === 'text') {
                    $field->failed_validation = true;
                    $field->validation_message = __('Your submission has been blocked due to spam detection.', 'spamguard');
                    break;
                }
            }
            $this->log_form_submission('gravity_forms', $author_name, $author_email, $content, 'blocked', 'blacklist');
            return $validation_result;
        }

        // API spam check
        $spam_check = $this->check_spam($content, $author_name, $author_email, 'gravity_forms');

        if ($spam_check['is_spam']) {
            $validation_result['is_valid'] = false;
            foreach ($form['fields'] as &$field) {
                if ($field->type === 'textarea' || $field->type === 'text') {
                    $field->failed_validation = true;
                    $field->validation_message = __('Your submission appears to be spam. Please try again.', 'spamguard');
                    break;
                }
            }
            $this->log_form_submission('gravity_forms', $author_name, $author_email, $content, 'blocked', 'api');
        } else {
            $this->log_form_submission('gravity_forms', $author_name, $author_email, $content, 'allowed', 'api');
        }

        return $validation_result;
    }

    /**
     * =================================
     * WPFORMS INTEGRATION
     * =================================
     */
    private function init_wpforms() {
        add_filter('wpforms_process_before', array($this, 'validate_wpforms'), 10, 2);
    }

    public function validate_wpforms($fields, $entry) {
        // Extract data
        $content = '';
        $author_name = '';
        $author_email = '';

        foreach ($fields as $field) {
            if ($field['type'] === 'name') {
                $author_name = is_array($field['value']) ? implode(' ', $field['value']) : $field['value'];
            } elseif ($field['type'] === 'email') {
                $author_email = $field['value'];
            } elseif (in_array($field['type'], array('textarea', 'text'))) {
                $content .= ' ' . $field['value'];
            }
        }

        $content = trim($content);

        // Whitelist check
        if ($this->is_whitelisted($author_email, $author_name)) {
            return $fields;
        }

        // Blacklist check
        if ($this->is_blacklisted($author_email, $author_name, $content)) {
            wpforms()->process->errors[$entry['id']] = __('Your submission has been blocked due to spam detection.', 'spamguard');
            $this->log_form_submission('wpforms', $author_name, $author_email, $content, 'blocked', 'blacklist');
            return $fields;
        }

        // API check
        $spam_check = $this->check_spam($content, $author_name, $author_email, 'wpforms');

        if ($spam_check['is_spam']) {
            wpforms()->process->errors[$entry['id']] = __('Your submission appears to be spam.', 'spamguard');
            $this->log_form_submission('wpforms', $author_name, $author_email, $content, 'blocked', 'api');
        } else {
            $this->log_form_submission('wpforms', $author_name, $author_email, $content, 'allowed', 'api');
        }

        return $fields;
    }

    /**
     * =================================
     * NINJA FORMS INTEGRATION
     * =================================
     */
    private function init_ninja_forms() {
        add_filter('ninja_forms_submit_data', array($this, 'validate_ninja_forms'));
    }

    public function validate_ninja_forms($form_data) {
        // Extract data
        $content = '';
        $author_name = '';
        $author_email = '';

        foreach ($form_data['fields'] as $field) {
            $field_type = $field['type'];
            $field_value = $field['value'];

            if ($field_type === 'firstname' || $field_type === 'lastname' || $field_type === 'name') {
                $author_name .= ' ' . $field_value;
            } elseif ($field_type === 'email') {
                $author_email = $field_value;
            } elseif (in_array($field_type, array('textarea', 'textbox', 'message'))) {
                $content .= ' ' . $field_value;
            }
        }

        $author_name = trim($author_name);
        $content = trim($content);

        // Whitelist/Blacklist/API checks
        if ($this->is_whitelisted($author_email, $author_name)) {
            return $form_data;
        }

        if ($this->is_blacklisted($author_email, $author_name, $content)) {
            $form_data['errors']['form'] = __('Your submission has been blocked.', 'spamguard');
            $this->log_form_submission('ninja_forms', $author_name, $author_email, $content, 'blocked', 'blacklist');
            return $form_data;
        }

        $spam_check = $this->check_spam($content, $author_name, $author_email, 'ninja_forms');

        if ($spam_check['is_spam']) {
            $form_data['errors']['form'] = __('Your submission appears to be spam.', 'spamguard');
            $this->log_form_submission('ninja_forms', $author_name, $author_email, $content, 'blocked', 'api');
        } else {
            $this->log_form_submission('ninja_forms', $author_name, $author_email, $content, 'allowed', 'api');
        }

        return $form_data;
    }

    /**
     * =================================
     * ELEMENTOR FORMS INTEGRATION
     * =================================
     */
    private function init_elementor_forms() {
        add_action('elementor_pro/forms/validation', array($this, 'validate_elementor_forms'), 10, 2);
    }

    public function validate_elementor_forms($record, $ajax_handler) {
        $fields = $record->get('fields');

        $content = '';
        $author_name = '';
        $author_email = '';

        foreach ($fields as $field_id => $field) {
            if ($field['type'] === 'email') {
                $author_email = $field['value'];
            } elseif ($field['type'] === 'text' && strpos($field_id, 'name') !== false) {
                $author_name = $field['value'];
            } elseif (in_array($field['type'], array('textarea', 'text'))) {
                $content .= ' ' . $field['value'];
            }
        }

        $content = trim($content);

        if ($this->is_whitelisted($author_email, $author_name)) {
            return;
        }

        if ($this->is_blacklisted($author_email, $author_name, $content)) {
            $ajax_handler->add_error_message(__('Your submission has been blocked.', 'spamguard'));
            $this->log_form_submission('elementor_forms', $author_name, $author_email, $content, 'blocked', 'blacklist');
            return;
        }

        $spam_check = $this->check_spam($content, $author_name, $author_email, 'elementor_forms');

        if ($spam_check['is_spam']) {
            $ajax_handler->add_error_message(__('Your submission appears to be spam.', 'spamguard'));
            $this->log_form_submission('elementor_forms', $author_name, $author_email, $content, 'blocked', 'api');
        } else {
            $this->log_form_submission('elementor_forms', $author_name, $author_email, $content, 'allowed', 'api');
        }
    }

    /**
     * =================================
     * HELPER METHODS
     * =================================
     */

    /**
     * Check if submission is whitelisted
     */
    private function is_whitelisted($email, $name) {
        if (!class_exists('SpamGuard_Lists')) {
            return false;
        }

        $lists = SpamGuard_Lists::get_instance();

        // Check email
        if (!empty($email) && $lists->is_whitelisted($email, 'email')) {
            return true;
        }

        // Check IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($ip) && $lists->is_whitelisted($ip, 'ip')) {
            return true;
        }

        return false;
    }

    /**
     * Check if submission is blacklisted
     */
    private function is_blacklisted($email, $name, $content) {
        if (!class_exists('SpamGuard_Lists')) {
            return false;
        }

        $lists = SpamGuard_Lists::get_instance();

        // Check email
        if (!empty($email) && $lists->is_blacklisted($email, 'email')) {
            return true;
        }

        // Check IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($ip) && $lists->is_blacklisted($ip, 'ip')) {
            return true;
        }

        // Check content for blacklisted keywords
        if (!empty($content) && $lists->is_blacklisted($content, 'keyword')) {
            return true;
        }

        return false;
    }

    /**
     * Check spam via API
     */
    private function check_spam($content, $author_name, $author_email, $form_type) {
        if (!$this->api_client) {
            return array('is_spam' => false);
        }

        $result = $this->api_client->check_spam(
            $content,
            $author_name,
            $author_email,
            $_SERVER['REMOTE_ADDR'] ?? '',
            array('form_type' => $form_type)
        );

        return $result;
    }

    /**
     * Log form submission
     */
    private function log_form_submission($form_type, $author_name, $author_email, $content, $status, $method) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $wpdb->insert(
            $table,
            array(
                'module' => 'forms',
                'action' => 'form_submission',
                'status' => $status,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'details' => serialize(array(
                    'form_type' => $form_type,
                    'author_name' => $author_name,
                    'author_email' => $author_email,
                    'content' => substr($content, 0, 500),
                    'detection_method' => $method
                )),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get form protection statistics
     */
    public function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $stats = array(
            'total_submissions' => 0,
            'blocked' => 0,
            'allowed' => 0,
            'block_rate' => 0,
            'by_form_type' => array()
        );

        // Total submissions
        $stats['total_submissions'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE module = 'forms'"
        ));

        // Blocked
        $stats['blocked'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE module = 'forms' AND status = 'blocked'"
        ));

        // Allowed
        $stats['allowed'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE module = 'forms' AND status = 'allowed'"
        ));

        // Block rate
        if ($stats['total_submissions'] > 0) {
            $stats['block_rate'] = round(($stats['blocked'] / $stats['total_submissions']) * 100, 2);
        }

        // By form type
        $by_type = $wpdb->get_results(
            "SELECT details, COUNT(*) as count
             FROM $table
             WHERE module = 'forms'
             GROUP BY details"
        );

        foreach ($by_type as $row) {
            $details = maybe_unserialize($row->details);
            if (isset($details['form_type'])) {
                $form_type = $details['form_type'];
                if (!isset($stats['by_form_type'][$form_type])) {
                    $stats['by_form_type'][$form_type] = 0;
                }
                $stats['by_form_type'][$form_type] += intval($row->count);
            }
        }

        return $stats;
    }

    /**
     * AJAX: Test form protection
     */
    public function ajax_test_form_protection() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $test_data = array(
            'content' => 'This is a test form submission to verify SpamGuard protection is working correctly.',
            'author_name' => 'Test User',
            'author_email' => 'test@example.com'
        );

        $result = $this->check_spam(
            $test_data['content'],
            $test_data['author_name'],
            $test_data['author_email'],
            'test'
        );

        wp_send_json_success(array(
            'result' => $result,
            'active_integrations' => $this->get_active_integrations(),
            'message' => __('Form protection test completed successfully', 'spamguard')
        ));
    }
}
