<?php
/**
 * SpamGuard Quarantine Manager
 * Manages quarantined files and threats
 *
 * @package SpamGuard
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Quarantine_Manager {

    private static $instance = null;
    private $quarantine_dir;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Set quarantine directory
        $upload_dir = wp_upload_dir();
        $this->quarantine_dir = $upload_dir['basedir'] . '/spamguard-quarantine';

        // Create quarantine directory if it doesn't exist
        $this->ensure_quarantine_directory();

        // AJAX actions
        add_action('wp_ajax_spamguard_quarantine_file', array($this, 'ajax_quarantine_file'));
        add_action('wp_ajax_spamguard_restore_file', array($this, 'ajax_restore_file'));
        add_action('wp_ajax_spamguard_delete_quarantine', array($this, 'ajax_delete_quarantine'));
        add_action('wp_ajax_spamguard_download_quarantine', array($this, 'ajax_download_quarantine'));
        add_action('wp_ajax_spamguard_get_quarantine_list', array($this, 'ajax_get_quarantine_list'));
        add_action('wp_ajax_spamguard_quarantine_bulk_action', array($this, 'ajax_bulk_action'));
    }

    /**
     * Ensure quarantine directory exists and is secure
     */
    private function ensure_quarantine_directory() {
        if (!file_exists($this->quarantine_dir)) {
            wp_mkdir_p($this->quarantine_dir);
        }

        // Add .htaccess to prevent direct access
        $htaccess_file = $this->quarantine_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "deny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Add index.php to prevent directory listing
        $index_file = $this->quarantine_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }

    /**
     * Quarantine a file
     *
     * @param string $file_path Path to the infected file
     * @param string $threat_id Associated threat ID
     * @param array $threat_details Details about the threat
     * @return array|WP_Error Result or error
     */
    public function quarantine_file($file_path, $threat_id = null, $threat_details = array()) {
        global $wpdb;

        // Validate file exists
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('File not found', 'spamguard'));
        }

        // Generate unique quarantine ID
        $quarantine_id = $this->generate_uuid();

        // Read file content
        $file_content = file_get_contents($file_path);
        if ($file_content === false) {
            return new WP_Error('read_failed', __('Failed to read file', 'spamguard'));
        }

        // Generate safe filename
        $safe_filename = $quarantine_id . '.quarantine';
        $quarantine_path = $this->quarantine_dir . '/' . $safe_filename;

        // Save file to quarantine (encrypted)
        $encrypted_content = $this->encrypt_content($file_content);
        if (file_put_contents($quarantine_path, $encrypted_content) === false) {
            return new WP_Error('save_failed', __('Failed to save to quarantine', 'spamguard'));
        }

        // Save metadata to database
        $table = $wpdb->prefix . 'spamguard_quarantine';
        $result = $wpdb->insert(
            $table,
            array(
                'id' => $quarantine_id,
                'threat_id' => $threat_id,
                'site_id' => get_site_url(),
                'file_path' => $file_path,
                'original_content' => base64_encode($file_content),
                'backup_location' => $quarantine_path,
                'quarantined_at' => current_time('mysql'),
                'restored_at' => null
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            // Clean up quarantine file if database insert failed
            unlink($quarantine_path);
            return new WP_Error('db_insert_failed', __('Failed to save quarantine record', 'spamguard'));
        }

        // Delete or neutralize original file
        $this->neutralize_file($file_path);

        // Log action
        $this->log_action('quarantine', $file_path, $threat_details);

        return array(
            'success' => true,
            'quarantine_id' => $quarantine_id,
            'message' => __('File quarantined successfully', 'spamguard')
        );
    }

    /**
     * Restore a quarantined file
     *
     * @param string $quarantine_id Quarantine ID
     * @return array|WP_Error Result or error
     */
    public function restore_file($quarantine_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_quarantine';

        // Get quarantine record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %s",
            $quarantine_id
        ));

        if (!$record) {
            return new WP_Error('not_found', __('Quarantine record not found', 'spamguard'));
        }

        if ($record->restored_at) {
            return new WP_Error('already_restored', __('File already restored', 'spamguard'));
        }

        // Decode original content
        $original_content = base64_decode($record->original_content);

        // Ensure destination directory exists
        $dir = dirname($record->file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Restore file
        if (file_put_contents($record->file_path, $original_content) === false) {
            return new WP_Error('restore_failed', __('Failed to restore file', 'spamguard'));
        }

        // Update database
        $wpdb->update(
            $table,
            array('restored_at' => current_time('mysql')),
            array('id' => $quarantine_id),
            array('%s'),
            array('%s')
        );

        // Log action
        $this->log_action('restore', $record->file_path, array(
            'quarantine_id' => $quarantine_id
        ));

        return array(
            'success' => true,
            'message' => __('File restored successfully', 'spamguard')
        );
    }

    /**
     * Permanently delete a quarantined file
     *
     * @param string $quarantine_id Quarantine ID
     * @return array|WP_Error Result or error
     */
    public function delete_quarantine($quarantine_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_quarantine';

        // Get quarantine record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %s",
            $quarantine_id
        ));

        if (!$record) {
            return new WP_Error('not_found', __('Quarantine record not found', 'spamguard'));
        }

        // Delete quarantine file
        if (file_exists($record->backup_location)) {
            unlink($record->backup_location);
        }

        // Delete database record
        $wpdb->delete(
            $table,
            array('id' => $quarantine_id),
            array('%s')
        );

        // Log action
        $this->log_action('delete', $record->file_path, array(
            'quarantine_id' => $quarantine_id
        ));

        return array(
            'success' => true,
            'message' => __('Quarantine deleted permanently', 'spamguard')
        );
    }

    /**
     * Get list of quarantined files
     *
     * @param array $args Query arguments
     * @return array List of quarantined files
     */
    public function get_quarantine_list($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_quarantine';

        $defaults = array(
            'status' => 'all', // all, active, restored
            'orderby' => 'quarantined_at',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        // Build query
        $where = array('1=1');

        if ($args['status'] === 'active') {
            $where[] = 'restored_at IS NULL';
        } elseif ($args['status'] === 'restored') {
            $where[] = 'restored_at IS NOT NULL';
        }

        $where_sql = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);

        $query = "SELECT * FROM $table WHERE $where_sql ORDER BY $orderby LIMIT $limit OFFSET $offset";
        $results = $wpdb->get_results($query);

        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = $wpdb->get_var($count_query);

        return array(
            'items' => $results,
            'total' => intval($total),
            'quarantine_dir_size' => $this->get_quarantine_directory_size()
        );
    }

    /**
     * Download a quarantined file (for analysis)
     *
     * @param string $quarantine_id Quarantine ID
     */
    public function download_quarantine($quarantine_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_quarantine';

        // Get quarantine record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %s",
            $quarantine_id
        ));

        if (!$record) {
            wp_die(__('Quarantine record not found', 'spamguard'));
        }

        // Decode content
        $content = base64_decode($record->original_content);

        // Prepare filename
        $filename = basename($record->file_path) . '.quarantined.txt';

        // Send headers
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $content;
        exit;
    }

    /**
     * Bulk actions on quarantine items
     *
     * @param string $action Action to perform (restore, delete)
     * @param array $quarantine_ids Array of quarantine IDs
     * @return array Result
     */
    public function bulk_action($action, $quarantine_ids) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );

        foreach ($quarantine_ids as $id) {
            if ($action === 'restore') {
                $result = $this->restore_file($id);
            } elseif ($action === 'delete') {
                $result = $this->delete_quarantine($id);
            } else {
                continue;
            }

            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = $result->get_error_message();
            } else {
                $results['success']++;
            }
        }

        return $results;
    }

    /**
     * Get quarantine directory size
     *
     * @return string Human-readable size
     */
    private function get_quarantine_directory_size() {
        $size = 0;

        if (is_dir($this->quarantine_dir)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->quarantine_dir)) as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }

        return $this->format_bytes($size);
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes Bytes
     * @return string Formatted size
     */
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Neutralize original file (replace with harmless content)
     *
     * @param string $file_path File path
     */
    private function neutralize_file($file_path) {
        $warning_content = "<?php\n";
        $warning_content .= "/**\n";
        $warning_content .= " * THIS FILE HAS BEEN QUARANTINED BY SPAMGUARD\n";
        $warning_content .= " * Date: " . current_time('mysql') . "\n";
        $warning_content .= " * Reason: Potential security threat detected\n";
        $warning_content .= " * \n";
        $warning_content .= " * To restore this file, go to SpamGuard > Antivirus > Quarantine\n";
        $warning_content .= " */\n";
        $warning_content .= "exit('This file has been quarantined by SpamGuard');\n";

        file_put_contents($file_path, $warning_content);
        chmod($file_path, 0444); // Read-only
    }

    /**
     * Simple encryption for quarantined content
     *
     * @param string $content Content to encrypt
     * @return string Encrypted content
     */
    private function encrypt_content($content) {
        // Simple base64 + reverse for obfuscation
        // In production, use proper encryption
        $encrypted = base64_encode(strrev($content));
        return $encrypted;
    }

    /**
     * Decrypt quarantined content
     *
     * @param string $encrypted Encrypted content
     * @return string Decrypted content
     */
    private function decrypt_content($encrypted) {
        return strrev(base64_decode($encrypted));
    }

    /**
     * Generate UUID v4
     *
     * @return string UUID
     */
    private function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Log quarantine action
     *
     * @param string $action Action performed
     * @param string $file_path File path
     * @param array $details Additional details
     */
    private function log_action($action, $file_path, $details = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $wpdb->insert(
            $table,
            array(
                'module' => 'quarantine',
                'action' => $action,
                'status' => 'success',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'system',
                'details' => serialize(array_merge($details, array(
                    'file_path' => $file_path,
                    'user_id' => get_current_user_id()
                ))),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * AJAX: Quarantine a file
     */
    public function ajax_quarantine_file() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $file_path = isset($_POST['file_path']) ? wp_unslash($_POST['file_path']) : '';
        $threat_id = isset($_POST['threat_id']) ? sanitize_text_field($_POST['threat_id']) : null;

        if (empty($file_path)) {
            wp_send_json_error(array('message' => __('File path is required', 'spamguard')));
        }

        $result = $this->quarantine_file($file_path, $threat_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Restore a file
     */
    public function ajax_restore_file() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $quarantine_id = isset($_POST['quarantine_id']) ? sanitize_text_field($_POST['quarantine_id']) : '';

        if (empty($quarantine_id)) {
            wp_send_json_error(array('message' => __('Quarantine ID is required', 'spamguard')));
        }

        $result = $this->restore_file($quarantine_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Delete quarantine
     */
    public function ajax_delete_quarantine() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $quarantine_id = isset($_POST['quarantine_id']) ? sanitize_text_field($_POST['quarantine_id']) : '';

        if (empty($quarantine_id)) {
            wp_send_json_error(array('message' => __('Quarantine ID is required', 'spamguard')));
        }

        $result = $this->delete_quarantine($quarantine_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Download quarantine
     */
    public function ajax_download_quarantine() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'spamguard'));
        }

        $quarantine_id = isset($_GET['quarantine_id']) ? sanitize_text_field($_GET['quarantine_id']) : '';

        if (empty($quarantine_id)) {
            wp_die(__('Quarantine ID is required', 'spamguard'));
        }

        $this->download_quarantine($quarantine_id);
    }

    /**
     * AJAX: Get quarantine list
     */
    public function ajax_get_quarantine_list() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;

        $list = $this->get_quarantine_list(array(
            'status' => $status,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        ));

        wp_send_json_success($list);
    }

    /**
     * AJAX: Bulk action
     */
    public function ajax_bulk_action() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $ids = isset($_POST['quarantine_ids']) ? array_map('sanitize_text_field', $_POST['quarantine_ids']) : array();

        if (empty($action) || empty($ids)) {
            wp_send_json_error(array('message' => __('Action and IDs are required', 'spamguard')));
        }

        $result = $this->bulk_action($action, $ids);
        wp_send_json_success($result);
    }
}
