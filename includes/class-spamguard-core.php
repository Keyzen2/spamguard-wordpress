<?php
/**
 * SpamGuard Core
 * Funcionalidad principal del plugin
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
        // AJAX handlers para registro de sitio
        add_action('wp_ajax_spamguard_register_site', array($this, 'ajax_register_site'));
        add_action('wp_ajax_spamguard_test_connection', array($this, 'ajax_test_connection'));
        
        // ✅ AJAX handlers para antivirus
        add_action('wp_ajax_spamguard_start_scan', array($this, 'ajax_start_scan'));
        add_action('wp_ajax_spamguard_scan_progress', array($this, 'ajax_scan_progress'));
        add_action('wp_ajax_spamguard_quarantine_threat', array($this, 'ajax_quarantine_threat'));
        add_action('wp_ajax_spamguard_ignore_threat', array($this, 'ajax_ignore_threat'));
        
        // Cleanup diario
        add_action('spamguard_daily_cleanup', array($this, 'daily_cleanup'));
        
        // Widget del dashboard
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * AJAX: Registrar sitio y generar API key
     */
    public function ajax_register_site() {
        check_ajax_referer('spamguard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'spamguard')
            ));
        }
        
        // Obtener API client
        if (!class_exists('SpamGuard_API_Client')) {
            wp_send_json_error(array(
                'message' => __('API Client not available', 'spamguard')
            ));
        }
        
        $api_client = SpamGuard_API_Client::get_instance();
        
        // Email del admin
        $admin_email = get_option('admin_email');
        
        // Intentar registrar
        $result = $api_client->register_and_generate_key($admin_email);
        
        if (isset($result['success']) && $result['success']) {
            // Guardar API key
            update_option('spamguard_api_key', $result['api_key']);
            
            wp_send_json_success(array(
                'message' => __('API Key generated successfully!', 'spamguard'),
                'api_key' => $result['api_key']
            ));
        } else {
            wp_send_json_error(array(
                'message' => isset($result['message']) ? $result['message'] : __('Error generating API Key', 'spamguard')
            ));
        }
    }
    
    /**
     * AJAX: Test de conexión con la API
     */
    public function ajax_test_connection() {
        check_ajax_referer('spamguard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'spamguard')
            ));
        }
        
        if (!class_exists('SpamGuard_API_Client')) {
            wp_send_json_error(array(
                'message' => __('API Client not available', 'spamguard')
            ));
        }
        
        $api_client = SpamGuard_API_Client::get_instance();
        $result = $api_client->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }
    
    /**
     * ✅ AJAX: Iniciar escaneo de antivirus
     */
    public function ajax_start_scan() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'spamguard')
            ));
        }
        
        $scan_type = sanitize_text_field($_POST['scan_type'] ?? 'quick');
        
        // Validar tipo de escaneo
        $valid_types = array('quick', 'full', 'plugins', 'themes');
        if (!in_array($scan_type, $valid_types)) {
            wp_send_json_error(array(
                'message' => __('Invalid scan type', 'spamguard')
            ));
        }
        
        if (!class_exists('SpamGuard_Antivirus_Scanner')) {
            wp_send_json_error(array(
                'message' => __('Antivirus scanner not available', 'spamguard')
            ));
        }
        
        try {
            $scanner = SpamGuard_Antivirus_Scanner::get_instance();
            $scan_id = $scanner->start_scan($scan_type);
            
            if (!$scan_id) {
                wp_send_json_error(array(
                    'message' => __('Failed to start scan', 'spamguard')
                ));
            }
            
            wp_send_json_success(array(
                'scan_id' => $scan_id,
                'message' => __('Scan started successfully', 'spamguard'),
                'scan_type' => $scan_type
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * ✅ AJAX: Obtener progreso del escaneo
     */
    public function ajax_scan_progress() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'spamguard')
            ));
        }
        
        $scan_id = sanitize_text_field($_POST['scan_id'] ?? '');
        
        if (empty($scan_id)) {
            wp_send_json_error(array(
                'message' => __('Scan ID required', 'spamguard')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_scans';
        
        $scan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %s",
            $scan_id
        ));
        
        if (!$scan) {
            wp_send_json_error(array(
                'message' => __('Scan not found', 'spamguard')
            ));
        }
        
        wp_send_json_success(array(
            'status' => $scan->status,
            'progress' => intval($scan->progress),
            'files_scanned' => intval($scan->files_scanned),
            'threats_found' => intval($scan->threats_found),
            'scan_type' => $scan->scan_type
        ));
    }
    
    /**
     * ✅ AJAX: Poner amenaza en cuarentena
     */
    public function ajax_quarantine_threat() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'spamguard')
            ));
        }
        
        $threat_id = sanitize_text_field($_POST['threat_id'] ?? '');
        
        if (empty($threat_id)) {
            wp_send_json_error(array(
                'message' => __('Threat ID required', 'spamguard')
            ));
        }
        
        global $wpdb;
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $quarantine_table = $wpdb->prefix . 'spamguard_quarantine';
        
        // Obtener información de la amenaza
        $threat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $threats_table WHERE id = %s",
            $threat_id
        ));
        
        if (!$threat) {
            wp_send_json_error(array(
                'message' => __('Threat not found', 'spamguard')
            ));
        }
        
        // Verificar que el archivo existe
        $file_path = ABSPATH . ltrim($threat->file_path, '/');
        
        if (!file_exists($file_path)) {
            wp_send_json_error(array(
                'message' => __('File not found', 'spamguard')
            ));
        }
        
        try {
            // Leer contenido del archivo
            $file_content = file_get_contents($file_path);
            
            if ($file_content === false) {
                throw new Exception(__('Could not read file', 'spamguard'));
            }
            
            // Crear directorio de cuarentena si no existe
            $quarantine_dir = WP_CONTENT_DIR . '/spamguard-quarantine';
            if (!file_exists($quarantine_dir)) {
                wp_mkdir_p($quarantine_dir);
                
                // Proteger directorio
                file_put_contents($quarantine_dir . '/.htaccess', 'Deny from all');
                file_put_contents($quarantine_dir . '/index.php', '<?php // Silence is golden');
            }
            
            // Generar nombre único para backup
            $backup_filename = date('Ymd_His') . '_' . basename($threat->file_path);
            $backup_path = $quarantine_dir . '/' . $backup_filename;
            
            // Mover archivo a cuarentena
            if (!rename($file_path, $backup_path)) {
                throw new Exception(__('Could not move file to quarantine', 'spamguard'));
            }
            
            // Registrar en BD
            $quarantine_id = wp_generate_uuid4();
            
            $wpdb->insert(
                $quarantine_table,
                array(
                    'id' => $quarantine_id,
                    'threat_id' => $threat_id,
                    'site_id' => get_option('spamguard_site_id', ''),
                    'file_path' => $threat->file_path,
                    'original_content' => $file_content,
                    'backup_location' => str_replace(ABSPATH, '', $backup_path),
                    'quarantined_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            // Actualizar estado de amenaza
            $wpdb->update(
                $threats_table,
                array(
                    'status' => 'quarantined',
                    'resolved_at' => current_time('mysql')
                ),
                array('id' => $threat_id),
                array('%s', '%s'),
                array('%s')
            );
            
            wp_send_json_success(array(
                'message' => __('File moved to quarantine successfully', 'spamguard'),
                'quarantine_id' => $quarantine_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Quarantine failed: %s', 'spamguard'),
                    $e->getMessage()
                )
            ));
        }
    }
    
    /**
     * ✅ AJAX: Ignorar amenaza (marcar como falso positivo)
     */
    public function ajax_ignore_threat() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'spamguard')
            ));
        }
        
        $threat_id = sanitize_text_field($_POST['threat_id'] ?? '');
        
        if (empty($threat_id)) {
            wp_send_json_error(array(
                'message' => __('Threat ID required', 'spamguard')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_threats';
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'ignored',
                'resolved_at' => current_time('mysql')
            ),
            array('id' => $threat_id),
            array('%s', '%s'),
            array('%s')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Failed to update threat status', 'spamguard')
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Threat marked as false positive', 'spamguard')
        ));
    }
    
    /**
     * Limpieza diaria
     */
    public function daily_cleanup() {
        global $wpdb;
        
        // Limpiar logs antiguos (más de 90 días)
        $logs_table = $wpdb->prefix . 'spamguard_logs';
        $wpdb->query(
            "DELETE FROM $logs_table 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        // Limpiar estadísticas antiguas (más de 6 meses)
        $usage_table = $wpdb->prefix . 'spamguard_usage';
        $wpdb->query(
            "DELETE FROM $usage_table 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
        );
        
        // Limpiar escaneos completados antiguos (más de 30 días)
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        $wpdb->query(
            "DELETE FROM $scans_table 
             WHERE status = 'completed' 
             AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Limpiar amenazas resueltas antiguas (más de 60 días)
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $wpdb->query(
            "DELETE FROM $threats_table 
             WHERE status IN ('quarantined', 'ignored') 
             AND resolved_at < DATE_SUB(NOW(), INTERVAL 60 DAY)"
        );
        
        // Limpiar caché si existe
        if (class_exists('SpamGuard_API_Cache')) {
            SpamGuard_API_Cache::get_instance()->cleanup_old_cache(7); // 7 días
        }
        
        // Log de limpieza
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SpamGuard: Daily cleanup completed');
        }
    }
    
    /**
     * Widget del dashboard de WordPress
     */
    public function add_dashboard_widget() {
        // Solo para administradores
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'spamguard_dashboard_widget',
            __('SpamGuard Security', 'spamguard'),
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Renderizar widget del dashboard
     */
    public function render_dashboard_widget() {
        // Verificar si está configurado
        if (!SpamGuard::get_instance()->is_configured()) {
            ?>
            <div style="text-align: center; padding: 20px;">
                <p style="font-size: 16px; color: #d63638;">
                    <span class="dashicons dashicons-warning" style="font-size: 24px;"></span><br>
                    <strong><?php _e('Setup Required', 'spamguard'); ?></strong>
                </p>
                <p><?php _e('SpamGuard needs to be configured.', 'spamguard'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-primary">
                    <?php _e('Generate API Key', 'spamguard'); ?>
                </a>
            </div>
            <?php
            return;
        }
        
        // Obtener estadísticas
        global $wpdb;
        
        // Stats de spam (últimos 7 días)
        $usage_table = $wpdb->prefix . 'spamguard_usage';
        $spam_blocked = $wpdb->get_var(
            "SELECT COUNT(*) FROM $usage_table 
             WHERE category = 'spam' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Amenazas activas
        if (class_exists('SpamGuard_Antivirus_Results')) {
            $threats = SpamGuard_Antivirus_Results::get_antivirus_stats();
            $active_threats = $threats['active_threats'];
        } else {
            $active_threats = 0;
        }
        
        ?>
        <div class="spamguard-widget">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                <div style="text-align: center; padding: 15px; background: #f0f6fc; border-radius: 4px;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;">
                        <?php echo number_format($spam_blocked); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <?php _e('Spam Blocked (7d)', 'spamguard'); ?>
                    </div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: <?php echo $active_threats > 0 ? '#fef0f0' : '#f0fdf4'; ?>; border-radius: 4px;">
                    <div style="font-size: 32px; font-weight: bold; color: <?php echo $active_threats > 0 ? '#d63638' : '#00a32a'; ?>;">
                        <?php echo number_format($active_threats); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <?php _e('Active Threats', 'spamguard'); ?>
                    </div>
                </div>
            </div>
            
            <?php if ($active_threats > 0): ?>
            <div style="background: #fef7f7; border-left: 4px solid #d63638; padding: 12px; margin-bottom: 15px;">
                <strong style="color: #d63638;">⚠️ <?php _e('Security Alert', 'spamguard'); ?></strong><br>
                <span style="font-size: 13px;">
                    <?php printf(_n('%d threat detected', '%d threats detected', $active_threats, 'spamguard'), $active_threats); ?>
                </span>
            </div>
            <?php endif; ?>
            
            <p style="text-align: center; margin: 15px 0 0 0;">
                <a href="<?php echo admin_url('admin.php?page=spamguard'); ?>" class="button">
                    <?php _e('View Dashboard', 'spamguard'); ?> →
                </a>
            </p>
        </div>
        
        <style>
        .spamguard-widget .dashicons {
            vertical-align: middle;
        }
        </style>
        <?php
    }
    
    /**
     * Helper: Verificar si está configurado
     */
    public static function is_configured() {
        return SpamGuard::get_instance()->is_configured();
    }
    
    /**
     * Helper: Obtener API key
     */
    public static function get_api_key() {
        return get_option('spamguard_api_key', '');
    }
    
    /**
     * Helper: Obtener API URL
     */
    public static function get_api_url() {
        return get_option('spamguard_api_url', SPAMGUARD_API_URL);
    }
    
    /**
     * Helper: Guardar estadística de uso
     */
    public static function log_usage($category, $confidence, $risk_level, $processing_time = 0, $cached = false) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spamguard_usage';
        
        $wpdb->insert(
            $table,
            array(
                'category' => $category,
                'confidence' => $confidence,
                'risk_level' => $risk_level,
                'processing_time_ms' => intval($processing_time),
                'cached' => $cached ? 1 : 0,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%f', '%s', '%d', '%d', '%s')
        );
    }
    
    /**
     * Helper: Guardar log de comentario analizado
     */
    public static function log_comment_analysis($comment_data, $analysis_result) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spamguard_logs';
        
        $is_spam = ($analysis_result['category'] === 'spam');
        
        $wpdb->insert(
            $table,
            array(
                'comment_id' => isset($comment_data['comment_ID']) ? intval($comment_data['comment_ID']) : null,
                'comment_author' => isset($comment_data['comment_author']) ? sanitize_text_field($comment_data['comment_author']) : '',
                'comment_author_email' => isset($comment_data['comment_author_email']) ? sanitize_email($comment_data['comment_author_email']) : '',
                'comment_content' => isset($comment_data['comment_content']) ? wp_kses_post($comment_data['comment_content']) : '',
                'is_spam' => $is_spam ? 1 : 0,
                'category' => $analysis_result['category'],
                'confidence' => isset($analysis_result['confidence']) ? floatval($analysis_result['confidence']) : null,
                'risk_level' => isset($analysis_result['risk_level']) ? $analysis_result['risk_level'] : 'low',
                'flags' => isset($analysis_result['flags']) ? json_encode($analysis_result['flags']) : null,
                'request_id' => isset($analysis_result['request_id']) ? $analysis_result['request_id'] : null,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s')
        );
    }
}
