<?php
/**
 * SpamGuard Core Class
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Core {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Añadir info a comment meta
        add_action('wp_insert_comment', array($this, 'save_comment_meta'), 10, 2);
        add_action('wp_ajax_spamguard_ignore_threat', array($this, 'ajax_ignore_threat'));
        
        // AJAX handlers
        add_action('wp_ajax_spamguard_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_spamguard_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_spamguard_send_feedback', array($this, 'ajax_send_feedback'));
        
        // Cron jobs
        add_action('spamguard_daily_cleanup', array($this, 'daily_cleanup'));
        
        // Schedule cron if not scheduled
        if (!wp_next_scheduled('spamguard_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'spamguard_daily_cleanup');
        }
    }
    
    /**
     * Guardar metadata del comentario
     */
    public function save_comment_meta($comment_id, $comment) {
        // Guardar resultado de SpamGuard si existe
        if (isset($comment->spamguard_result)) {
            add_comment_meta($comment_id, 'spamguard_result', $comment->spamguard_result, true);
            add_comment_meta($comment_id, 'spamguard_category', $comment->spamguard_result['category'], true);
            add_comment_meta($comment_id, 'spamguard_confidence', $comment->spamguard_result['confidence'], true);
            add_comment_meta($comment_id, 'spamguard_risk_level', $comment->spamguard_result['risk_level'], true);
        }
    }
    
    /**
     * AJAX: Limpiar caché
     */
    public function ajax_clear_cache() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $cache = SpamGuard_API_Cache::get_instance();
        $count = $cache->flush();
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cache cleared: %d entries removed', 'spamguard'), $count)
        ));
    }
    
    /**
     * AJAX: Probar API
     */
    public function ajax_test_api() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $api_client = SpamGuard_API_Client::get_instance();
        
        // Test con comentario de ejemplo
        $test_comment = array(
            'comment_content' => 'This is a test comment to verify API connectivity.',
            'comment_author_email' => get_option('admin_email'),
            'comment_author_IP' => $_SERVER['REMOTE_ADDR']
        );
        
        $result = $api_client->analyze_comment($test_comment);
        
        if (isset($result['is_spam'])) {
            wp_send_json_success(array(
                'message' => __('API connection successful!', 'spamguard'),
                'result' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('API connection failed', 'spamguard'),
                'result' => $result
            ));
        }
    }

    public function ajax_ignore_threat() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $threat_id = isset($_POST['threat_id']) ? sanitize_text_field($_POST['threat_id']) : '';
        
        if (empty($threat_id)) {
            wp_send_json_error(array('message' => __('Threat ID required', 'spamguard')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_threats';
        
        $updated = $wpdb->update($table, array(
            'status' => 'ignored',
            'resolved_at' => current_time('mysql')
        ), array('id' => $threat_id));
        
        if ($updated !== false) {
            wp_send_json_success(array('message' => __('Threat marked as false positive', 'spamguard')));
        } else {
            wp_send_json_error(array('message' => __('Error updating threat', 'spamguard')));
        }
    }

    /**
     * AJAX: Enviar feedback
     */
    public function ajax_send_feedback() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $correct_category = isset($_POST['correct_category']) ? sanitize_text_field($_POST['correct_category']) : '';
        
        if (!$comment_id || !$correct_category) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'spamguard')));
        }
        
        $result = get_comment_meta($comment_id, 'spamguard_result', true);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('No SpamGuard data found for this comment', 'spamguard')));
        }
        
        $api_client = SpamGuard_API_Client::get_instance();
        $success = $api_client->send_feedback(
            $comment_id,
            $result['category'],
            $correct_category,
            __('Feedback from WordPress admin', 'spamguard')
        );
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Thank you! Your feedback helps improve the model.', 'spamguard')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to send feedback. Please try again.', 'spamguard')
            ));
        }
    }
    
    /**
     * Limpieza diaria
     */
    public function daily_cleanup() {
        // Limpiar caché antiguo
        $cache = SpamGuard_API_Cache::get_instance();
        $cache->clean_old();
        
        // Limpiar logs antiguos (más de 90 días)
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'spamguard_logs';
        $wpdb->query(
            "DELETE FROM $logs_table WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        $usage_table = $wpdb->prefix . 'spamguard_usage';
        $wpdb->query(
            "DELETE FROM $usage_table WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }
}