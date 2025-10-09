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
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        
        // ⭐ MIGRACIÓN DE v1.0 a v3.0
        $this->maybe_migrate_from_v1();
    }
    
    private function init_hooks() {
        add_action('wp_insert_comment', array($this, 'save_comment_meta'), 10, 2);
        add_action('wp_ajax_spamguard_ignore_threat', array($this, 'ajax_ignore_threat'));
        add_action('wp_ajax_spamguard_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_spamguard_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_spamguard_send_feedback', array($this, 'ajax_send_feedback'));
        add_action('spamguard_daily_cleanup', array($this, 'daily_cleanup'));
        add_action('wp_ajax_spamguard_register_site', array($this, 'ajax_register_site'));
        add_action('wp_ajax_spamguard_test_connection', array($this, 'ajax_test_connection'));
        
        // Schedule cron if not scheduled
        if (!wp_next_scheduled('spamguard_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'spamguard_daily_cleanup');
        }
    }
    
    /**
     * ⭐ MIGRACIÓN AUTOMÁTICA DE v1.0 a v3.0
     */
    private function maybe_migrate_from_v1() {
        // Solo ejecutar una vez
        if (get_option('spamguard_migrated_to_v3', false)) {
            return;
        }
        
        $migrated = false;
        
        // 1. Migrar API Key (si existe formato antiguo)
        $old_api_key = get_option('spamguard_api_key');
        if (!empty($old_api_key)) {
            // Verificar si es formato nuevo (sg_live_ o sg_test_)
            if (!preg_match('/^sg_(live|test)_/', $old_api_key)) {
                // Es formato antiguo - podría necesitar conversión
                // Por ahora, mantenerlo como está y registrarlo
                error_log("SpamGuard: Detectada API key de v1.0: " . substr($old_api_key, 0, 10) . "...");
            }
            $migrated = true;
        }
        
        // 2. Migrar configuraciones antiguas a nuevas
        $old_sensitivity = get_option('spamguard_sensitivity_old');
        if ($old_sensitivity !== false) {
            // Convertir de 0-1 a 0-100
            $new_sensitivity = intval($old_sensitivity * 100);
            update_option('spamguard_sensitivity', $new_sensitivity);
            delete_option('spamguard_sensitivity_old');
            $migrated = true;
        }
        
        // 3. Migrar estadísticas antiguas
        $old_stats = get_option('spamguard_stats');
        if (!empty($old_stats) && is_array($old_stats)) {
            // Las stats v1.0 son compatibles con v3.0
            // No necesita migración especial
            $migrated = true;
        }
        
        // 4. Migrar tabla de logs (si existe tabla antigua)
        global $wpdb;
        $old_table = $wpdb->prefix . 'spamguard_logs_old';
        $new_table = $wpdb->prefix . 'spamguard_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'") === $old_table) {
            // Copiar datos de tabla antigua a nueva
            $wpdb->query("
                INSERT INTO $new_table 
                (comment_id, is_spam, confidence, created_at)
                SELECT comment_id, is_spam, confidence, created_at
                FROM $old_table
                WHERE NOT EXISTS (
                    SELECT 1 FROM $new_table 
                    WHERE $new_table.comment_id = $old_table.comment_id
                )
            ");
            
            // Renombrar tabla antigua (no eliminar por seguridad)
            $wpdb->query("RENAME TABLE $old_table TO {$old_table}_backup");
            
            $migrated = true;
        }
        
        // Marcar como migrado
        if ($migrated) {
            update_option('spamguard_migrated_to_v3', true);
            update_option('spamguard_migration_date', current_time('mysql'));
            
            // Log exitoso
            error_log("SpamGuard: Migración de v1.0 a v3.0 completada exitosamente");
        }
    }
    
    /**
     * Guardar metadata del comentario
     */
    public function save_comment_meta($comment_id, $comment) {
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
        
        if (class_exists('SpamGuard_API_Cache')) {
            $cache = SpamGuard_API_Cache::get_instance();
            $count = $cache->flush();
            
            wp_send_json_success(array(
                'message' => sprintf(__('Cache cleared: %d entries removed', 'spamguard'), $count)
            ));
        }
    }
    
    /**
     * AJAX: Probar API
     */
    public function ajax_test_api() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        if (!class_exists('SpamGuard_API_Client')) {
            wp_send_json_error(array('message' => __('API Client not available', 'spamguard')));
            return;
        }
        
        $api_client = SpamGuard_API_Client::get_instance();
        
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
        
        if (!class_exists('SpamGuard_API_Client')) {
            wp_send_json_error(array('message' => __('API Client not available', 'spamguard')));
            return;
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
     * AJAX: Registrar sitio automáticamente
     */
    public function ajax_register_site() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'spamguard')));
            return;
        }
        
        $api = SpamGuard_API::get_instance();
        $result = $api->register_site();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Test de conexión
     */
    public function ajax_test_connection() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'spamguard')));
            return;
        }
        
        $api = SpamGuard_API::get_instance();
        $result = $api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function daily_cleanup() {
        if (class_exists('SpamGuard_API_Cache')) {
            $cache = SpamGuard_API_Cache::get_instance();
            $cache->clean_old();
        }
        
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



