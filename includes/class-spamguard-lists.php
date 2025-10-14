<?php
/**
 * SpamGuard Lists Manager
 * Manages Whitelist and Blacklist for IPs, Emails, Domains, Keywords
 *
 * @package SpamGuard
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Lists {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // AJAX actions
        add_action('wp_ajax_spamguard_add_list_entry', array($this, 'ajax_add_entry'));
        add_action('wp_ajax_spamguard_remove_list_entry', array($this, 'ajax_remove_entry'));
        add_action('wp_ajax_spamguard_get_list_entries', array($this, 'ajax_get_entries'));
    }

    /**
     * Check if value is whitelisted
     */
    public function is_whitelisted($value, $type = 'auto') {
        if ($type === 'auto') {
            $type = $this->detect_type($value);
        }

        return $this->check_list('whitelist', $type, $value);
    }

    /**
     * Check if value is blacklisted
     */
    public function is_blacklisted($value, $type = 'auto') {
        if ($type === 'auto') {
            $type = $this->detect_type($value);
        }

        return $this->check_list('blacklist', $type, $value);
    }

    /**
     * Check value against list
     */
    private function check_list($list_type, $entry_type, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_lists';

        // Direct match
        $direct_match = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE list_type = %s AND entry_type = %s AND value = %s AND is_active = 1",
            $list_type,
            $entry_type,
            $value
        ));

        if ($direct_match) {
            return true;
        }

        // Pattern matching for wildcards
        if ($entry_type === 'ip') {
            // Check IP ranges (e.g., 192.168.1.*)
            $ip_patterns = $wpdb->get_results($wpdb->prepare(
                "SELECT value FROM $table WHERE list_type = %s AND entry_type = %s AND value LIKE '%%*%%' AND is_active = 1",
                $list_type,
                $entry_type
            ));

            foreach ($ip_patterns as $pattern) {
                if ($this->match_ip_pattern($value, $pattern->value)) {
                    return true;
                }
            }
        }

        if ($entry_type === 'domain' || $entry_type === 'email') {
            // Check domain patterns
            $domain_patterns = $wpdb->get_results($wpdb->prepare(
                "SELECT value FROM $table WHERE list_type = %s AND entry_type = %s AND value LIKE '%%*%%' AND is_active = 1",
                $list_type,
                $entry_type
            ));

            foreach ($domain_patterns as $pattern) {
                if ($this->match_domain_pattern($value, $pattern->value)) {
                    return true;
                }
            }
        }

        if ($entry_type === 'keyword') {
            // Check if content contains blacklisted keyword
            $keywords = $wpdb->get_results($wpdb->prepare(
                "SELECT value FROM $table WHERE list_type = %s AND entry_type = 'keyword' AND is_active = 1",
                $list_type
            ));

            foreach ($keywords as $keyword) {
                if (stripos($value, $keyword->value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add entry to list
     */
    public function add_entry($list_type, $entry_type, $value, $reason = '', $created_by = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_lists';

        // Validate list type
        if (!in_array($list_type, array('whitelist', 'blacklist'))) {
            return new WP_Error('invalid_list_type', __('Invalid list type', 'spamguard'));
        }

        // Validate entry type
        if (!in_array($entry_type, array('ip', 'email', 'domain', 'keyword', 'user'))) {
            return new WP_Error('invalid_entry_type', __('Invalid entry type', 'spamguard'));
        }

        // Sanitize value
        $value = $this->sanitize_value($value, $entry_type);

        if (empty($value)) {
            return new WP_Error('invalid_value', __('Invalid value', 'spamguard'));
        }

        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE list_type = %s AND entry_type = %s AND value = %s",
            $list_type,
            $entry_type,
            $value
        ));

        if ($exists) {
            return new WP_Error('already_exists', __('Entry already exists', 'spamguard'));
        }

        // Insert
        $result = $wpdb->insert(
            $table,
            array(
                'list_type' => $list_type,
                'entry_type' => $entry_type,
                'value' => $value,
                'reason' => sanitize_text_field($reason),
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'created_by' => $created_by > 0 ? $created_by : get_current_user_id()
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%d')
        );

        if ($result === false) {
            return new WP_Error('insert_failed', __('Failed to add entry', 'spamguard'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Remove entry from list
     */
    public function remove_entry($entry_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_lists';

        $result = $wpdb->delete(
            $table,
            array('id' => $entry_id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('delete_failed', __('Failed to remove entry', 'spamguard'));
        }

        return true;
    }

    /**
     * Get all entries from a list
     */
    public function get_entries($list_type, $entry_type = null, $active_only = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_lists';

        $where = array("list_type = '$list_type'");

        if ($entry_type) {
            $where[] = $wpdb->prepare("entry_type = %s", $entry_type);
        }

        if ($active_only) {
            $where[] = "is_active = 1";
        }

        $query = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";

        return $wpdb->get_results($query);
    }

    /**
     * Toggle entry active status
     */
    public function toggle_entry($entry_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_lists';

        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $entry_id
        ));

        if (!$entry) {
            return new WP_Error('not_found', __('Entry not found', 'spamguard'));
        }

        $new_status = $entry->is_active ? 0 : 1;

        $wpdb->update(
            $table,
            array('is_active' => $new_status),
            array('id' => $entry_id),
            array('%d'),
            array('%d')
        );

        return $new_status;
    }

    /**
     * Detect entry type from value
     */
    private function detect_type($value) {
        // IP address
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return 'ip';
        }

        // Email
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        // Domain (starts with @)
        if (strpos($value, '@') === 0) {
            return 'domain';
        }

        // Default to keyword
        return 'keyword';
    }

    /**
     * Sanitize value based on type
     */
    private function sanitize_value($value, $type) {
        switch ($type) {
            case 'ip':
                // Allow wildcards like 192.168.1.*
                $value = trim($value);
                if (strpos($value, '*') !== false) {
                    return $value; // Keep wildcard
                }
                return filter_var($value, FILTER_VALIDATE_IP) ? $value : '';

            case 'email':
                return sanitize_email($value);

            case 'domain':
                // Remove @ if present, add it back
                $value = ltrim($value, '@');
                $value = strtolower(trim($value));
                return '@' . $value;

            case 'keyword':
                return sanitize_text_field($value);

            case 'user':
                return sanitize_text_field($value);

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Match IP against pattern with wildcards
     */
    private function match_ip_pattern($ip, $pattern) {
        // Convert pattern to regex
        $pattern = str_replace('.', '\.', $pattern);
        $pattern = str_replace('*', '\d{1,3}', $pattern);
        $pattern = '/^' . $pattern . '$/';

        return preg_match($pattern, $ip) === 1;
    }

    /**
     * Match domain against pattern
     */
    private function match_domain_pattern($value, $pattern) {
        // Remove @ from pattern if present
        $pattern = ltrim($pattern, '@');
        $value_domain = ltrim($value, '@');

        // Extract domain from email if needed
        if (strpos($value_domain, '@') !== false) {
            $parts = explode('@', $value_domain);
            $value_domain = end($parts);
        }

        // Wildcard matching
        $pattern = str_replace('*', '.*', $pattern);
        $pattern = '/^' . $pattern . '$/i';

        return preg_match($pattern, $value_domain) === 1;
    }

    /**
     * AJAX: Add entry
     */
    public function ajax_add_entry() {
        check_ajax_referer('spamguard_lists', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $list_type = isset($_POST['list_type']) ? sanitize_text_field($_POST['list_type']) : '';
        $entry_type = isset($_POST['entry_type']) ? sanitize_text_field($_POST['entry_type']) : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

        $result = $this->add_entry($list_type, $entry_type, $value, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Entry added successfully', 'spamguard'),
            'entry_id' => $result
        ));
    }

    /**
     * AJAX: Remove entry
     */
    public function ajax_remove_entry() {
        check_ajax_referer('spamguard_lists', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;

        $result = $this->remove_entry($entry_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Entry removed successfully', 'spamguard')));
    }

    /**
     * AJAX: Get entries
     */
    public function ajax_get_entries() {
        check_ajax_referer('spamguard_lists', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $list_type = isset($_POST['list_type']) ? sanitize_text_field($_POST['list_type']) : 'whitelist';
        $entry_type = isset($_POST['entry_type']) ? sanitize_text_field($_POST['entry_type']) : null;

        $entries = $this->get_entries($list_type, $entry_type);

        wp_send_json_success(array('entries' => $entries));
    }

    /**
     * Get statistics
     */
    public function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_lists';

        $stats = array(
            'whitelist' => array(
                'total' => 0,
                'by_type' => array()
            ),
            'blacklist' => array(
                'total' => 0,
                'by_type' => array()
            )
        );

        // Whitelist stats
        $stats['whitelist']['total'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE list_type = 'whitelist' AND is_active = 1"
        ));

        $whitelist_types = $wpdb->get_results(
            "SELECT entry_type, COUNT(*) as count FROM $table WHERE list_type = 'whitelist' AND is_active = 1 GROUP BY entry_type"
        );

        foreach ($whitelist_types as $type) {
            $stats['whitelist']['by_type'][$type->entry_type] = intval($type->count);
        }

        // Blacklist stats
        $stats['blacklist']['total'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE list_type = 'blacklist' AND is_active = 1"
        ));

        $blacklist_types = $wpdb->get_results(
            "SELECT entry_type, COUNT(*) as count FROM $table WHERE list_type = 'blacklist' AND is_active = 1 GROUP BY entry_type"
        );

        foreach ($blacklist_types as $type) {
            $stats['blacklist']['by_type'][$type->entry_type] = intval($type->count);
        }

        return $stats;
    }
}
