<?php
/**
 * SpamGuard Antivirus Scanner v3.0
 * 
 * Scanner 100% local - NO requiere API
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Antivirus_Scanner {
    
    private static $instance = null;
    
    /**
     * Patrones de malware conocidos
     */
    private $malware_patterns = array(
        // Eval patterns
        'eval_base64' => array(
            'pattern' => '/eval\s*\(\s*base64_decode/i',
            'severity' => 'critical',
            'description' => 'Código ofuscado con base64_decode + eval'
        ),
        'eval_gzinflate' => array(
            'pattern' => '/eval\s*\(\s*(gzinflate|gzuncompress|str_rot13)/i',
            'severity' => 'critical',
            'description' => 'Código ofuscado con compresión'
        ),
        
        // Backdoors
        'preg_replace_e' => array(
            'pattern' => '/preg_replace\s*\(.*["\']\/.*\/e/i',
            'severity' => 'critical',
            'description' => 'Backdoor con preg_replace /e modifier'
        ),
        'create_function' => array(
            'pattern' => '/create_function\s*\(.*\$_(GET|POST|REQUEST|COOKIE)/i',
            'severity' => 'high',
            'description' => 'Backdoor con create_function'
        ),
        'assert_backdoor' => array(
            'pattern' => '/assert\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i',
            'severity' => 'high',
            'description' => 'Backdoor con assert()'
        ),
        
        // Shells
        'shell_exec' => array(
            'pattern' => '/(shell_exec|exec|passthru|system|proc_open|popen)\s*\(\s*\$_(GET|POST|REQUEST)/i',
            'severity' => 'critical',
            'description' => 'Ejecución de comandos del sistema'
        ),
        'file_upload' => array(
            'pattern' => '/move_uploaded_file\s*\(.*\$_(GET|POST|REQUEST|FILES)/i',
            'severity' => 'high',
            'description' => 'Upload de archivos sospechoso'
        ),
        
        // Obfuscation
        'globals_array' => array(
            'pattern' => '/\$GLOBALS\s*\[\s*[\'"]___[\'"]\s*\]/i',
            'severity' => 'high',
            'description' => 'Uso sospechoso de $GLOBALS'
        ),
        'variable_variables' => array(
            'pattern' => '/\$\$[a-zA-Z0-9_]+\s*=.*\$_(GET|POST|REQUEST)/i',
            'severity' => 'medium',
            'description' => 'Variables variables sospechosas'
        ),
        
        // WordPress specific
        'add_admin_user' => array(
            'pattern' => '/wp_insert_user.*administrator/i',
            'severity' => 'high',
            'description' => 'Creación de usuario administrador'
        ),
        'file_get_contents_url' => array(
            'pattern' => '/file_get_contents\s*\(\s*[\'"]https?:\/\//i',
            'severity' => 'medium',
            'description' => 'Descarga de contenido remoto'
        ),
        
        // Crypto miners
        'crypto_miner' => array(
            'pattern' => '/(coinhive|cryptonight|monero)/i',
            'severity' => 'high',
            'description' => 'Posible crypto miner'
        )
    );
    
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
        // AJAX Handlers
        add_action('wp_ajax_spamguard_start_scan', array($this, 'ajax_start_scan'));
        add_action('wp_ajax_spamguard_scan_progress', array($this, 'ajax_scan_progress'));
        add_action('wp_ajax_spamguard_scan_results', array($this, 'ajax_scan_results'));
        add_action('wp_ajax_spamguard_quarantine_threat', array($this, 'ajax_quarantine_threat'));
        add_action('wp_ajax_spamguard_restore_threat', array($this, 'ajax_restore_threat'));
        
        // Cron para escaneo automático
        add_action('spamguard_auto_scan', array($this, 'run_scheduled_scan'));
    }
    
    /**
     * Obtener archivos a escanear
     */
    private function get_files_to_scan($scan_type = 'quick') {
        $files = array();
        $base_dir = ABSPATH;
        
        // Determinar qué escanear
        switch ($scan_type) {
            case 'quick':
                // Solo plugins y themes activos
                $paths = array(
                    WP_CONTENT_DIR . '/plugins',
                    WP_CONTENT_DIR . '/themes/' . get_template()
                );
                $max_files = 1000;
                break;
            
            case 'full':
                // Todo WordPress
                $paths = array(
                    WP_CONTENT_DIR,
                    ABSPATH . 'wp-includes',
                    ABSPATH . 'wp-admin'
                );
                $max_files = 5000;
                break;
            
            case 'plugins':
                // Solo plugins
                $paths = array(WP_CONTENT_DIR . '/plugins');
                $max_files = 2000;
                break;
            
            case 'themes':
                // Solo themes
                $paths = array(WP_CONTENT_DIR . '/themes');
                $max_files = 500;
                break;
            
            default:
                $paths = array(WP_CONTENT_DIR . '/plugins');
                $max_files = 1000;
        }
        
        // Recolectar archivos PHP
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $file) {
                    if (count($files) >= $max_files) {
                        break 2;
                    }
                    
                    if (!$file->isFile()) {
                        continue;
                    }
                    
                    // Solo archivos PHP
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }
                    
                    // Skip archivos muy grandes (> 5MB)
                    if ($file->getSize() > 5 * 1024 * 1024) {
                        continue;
                    }
                    
                    $files[] = array(
                        'path' => $file->getPathname(),
                        'relative_path' => str_replace($base_dir, '', $file->getPathname()),
                        'size' => $file->getSize(),
                        'modified' => $file->getMTime()
                    );
                }
            } catch (Exception $e) {
                error_log('[SpamGuard Antivirus] Error scanning path: ' . $path . ' - ' . $e->getMessage());
            }
        }
        
        return $files;
    }
    
    /**
     * Escanear un archivo
     */
    private function scan_file($file_path) {
        $threats = array();
        
        try {
            $content = file_get_contents($file_path);
            
            if ($content === false) {
                return array(
                    'error' => 'Could not read file'
                );
            }
            
            // Buscar patrones de malware
            foreach ($this->malware_patterns as $name => $pattern_info) {
                if (preg_match($pattern_info['pattern'], $content, $matches)) {
                    $threats[] = array(
                        'type' => $name,
                        'severity' => $pattern_info['severity'],
                        'description' => $pattern_info['description'],
                        'matched_text' => isset($matches[0]) ? substr($matches[0], 0, 100) : ''
                    );
                }
            }
            
            return array(
                'file_path' => $file_path,
                'is_malicious' => count($threats) > 0,
                'threats' => $threats,
                'file_hash' => md5($content),
                'file_size' => strlen($content)
            );
            
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Iniciar escaneo
     */
    public function start_scan($scan_type = 'quick') {
        global $wpdb;
        
        // Generar ID único
        $scan_id = wp_generate_uuid4();
        
        // Obtener archivos
        $files = $this->get_files_to_scan($scan_type);
        
        if (empty($files)) {
            return new WP_Error('no_files', __('No files found to scan', 'spamguard'));
        }
        
        // Guardar escaneo en BD
        $table = $wpdb->prefix . 'spamguard_scans';
        $wpdb->insert($table, array(
            'id' => $scan_id,
            'scan_type' => $scan_type,
            'status' => 'running',
            'started_at' => current_time('mysql'),
            'files_scanned' => 0,
            'threats_found' => 0,
            'progress' => 0,
            'results' => json_encode(array('total_files' => count($files)))
        ));
        
        // Guardar archivos a escanear en transient
        set_transient('spamguard_scan_' . $scan_id . '_files', $files, HOUR_IN_SECONDS);
        
        // Iniciar escaneo en background
        wp_schedule_single_event(time() + 5, 'spamguard_process_scan', array($scan_id));
        
        return array(
            'scan_id' => $scan_id,
            'total_files' => count($files),
            'status' => 'running'
        );
    }
    
    /**
     * Procesar escaneo (background)
     */
    public function process_scan($scan_id) {
        global $wpdb;
        
        $files = get_transient('spamguard_scan_' . $scan_id . '_files');
        
        if (!$files) {
            return;
        }
        
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        
        $total_files = count($files);
        $scanned = 0;
        $threats_found = 0;
        
        // Procesar en batches para evitar timeout
        $batch_size = 50;
        $start_time = time();
        $max_execution_time = 20; // 20 segundos
        
        foreach ($files as $file) {
            // Check timeout
            if (time() - $start_time > $max_execution_time) {
                // Guardar progreso y reprogramar
                $remaining_files = array_slice($files, $scanned);
                set_transient('spamguard_scan_' . $scan_id . '_files', $remaining_files, HOUR_IN_SECONDS);
                wp_schedule_single_event(time() + 5, 'spamguard_process_scan', array($scan_id));
                return;
            }
            
            $result = $this->scan_file($file['path']);
            $scanned++;
            
            // Si hay amenazas, guardar
            if (!empty($result['is_malicious']) && !empty($result['threats'])) {
                foreach ($result['threats'] as $threat) {
                    $threat_id = wp_generate_uuid4();
                    
                    $wpdb->insert($threats_table, array(
                        'id' => $threat_id,
                        'scan_id' => $scan_id,
                        'site_id' => get_site_url(),
                        'file_path' => $file['relative_path'],
                        'threat_type' => $threat['type'],
                        'severity' => $threat['severity'],
                        'signature_matched' => $threat['description'],
                        'code_snippet' => $threat['matched_text'],
                        'status' => 'active',
                        'detected_at' => current_time('mysql')
                    ));
                    
                    $threats_found++;
                }
            }
            
            // Actualizar progreso cada 10 archivos
            if ($scanned % 10 === 0) {
                $progress = round(($scanned / $total_files) * 100);
                
                $wpdb->update($scans_table, array(
                    'files_scanned' => $scanned,
                    'threats_found' => $threats_found,
                    'progress' => $progress
                ), array('id' => $scan_id));
            }
        }
        
        // Completado
        $wpdb->update($scans_table, array(
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'files_scanned' => $scanned,
            'threats_found' => $threats_found,
            'progress' => 100
        ), array('id' => $scan_id));
        
        // Limpiar transient
        delete_transient('spamguard_scan_' . $scan_id . '_files');
        
        // Enviar notificación si hay amenazas
        if ($threats_found > 0 && get_option('spamguard_email_notifications', true)) {
            $this->send_threat_notification($scan_id, $threats_found);
        }
    }
    
    /**
     * Enviar notificación de amenazas
     */
    private function send_threat_notification($scan_id, $threats_count) {
        $to = get_option('spamguard_notification_email', get_option('admin_email'));
        $subject = sprintf(__('[SpamGuard] %d threats detected on your site', 'spamguard'), $threats_count);
        
        $message = sprintf(
            __('SpamGuard detected %d security threats on your WordPress site.', 'spamguard'),
            $threats_count
        ) . "\n\n";
        
        $message .= __('Please review the threats in your WordPress admin:', 'spamguard') . "\n";
        $message .= admin_url('admin.php?page=spamguard-antivirus') . "\n\n";
        
        $message .= __('Scan ID:', 'spamguard') . ' ' . $scan_id . "\n";
        $message .= __('Site:', 'spamguard') . ' ' . get_site_url() . "\n";
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * AJAX: Iniciar escaneo
     */
    public function ajax_start_scan() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $scan_type = isset($_POST['scan_type']) ? sanitize_text_field($_POST['scan_type']) : 'quick';
        
        $result = $this->start_scan($scan_type);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Progreso del escaneo
     */
    public function ajax_scan_progress() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $scan_id = isset($_POST['scan_id']) ? sanitize_text_field($_POST['scan_id']) : '';
        
        if (empty($scan_id)) {
            wp_send_json_error(array('message' => __('Scan ID required', 'spamguard')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_scans';
        
        $scan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %s",
            $scan_id
        ));
        
        if (!$scan) {
            wp_send_json_error(array('message' => __('Scan not found', 'spamguard')));
        }
        
        wp_send_json_success(array(
            'scan_id' => $scan->id,
            'status' => $scan->status,
            'progress' => intval($scan->progress),
            'files_scanned' => intval($scan->files_scanned),
            'threats_found' => intval($scan->threats_found)
        ));
    }
    
    /**
     * AJAX: Resultados del escaneo
     */
    public function ajax_scan_results() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $scan_id = isset($_POST['scan_id']) ? sanitize_text_field($_POST['scan_id']) : '';
        
        if (empty($scan_id)) {
            wp_send_json_error(array('message' => __('Scan ID required', 'spamguard')));
        }
        
        global $wpdb;
        
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        $scan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $scans_table WHERE id = %s",
            $scan_id
        ));
        
        if (!$scan) {
            wp_send_json_error(array('message' => __('Scan not found', 'spamguard')));
        }
        
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $threats = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $threats_table WHERE scan_id = %s ORDER BY severity DESC, detected_at DESC",
            $scan_id
        ));
        
        wp_send_json_success(array(
            'scan' => $scan,
            'threats' => $threats
        ));
    }
    
    /**
     * AJAX: Cuarentena
     */
    public function ajax_quarantine_threat() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $threat_id = isset($_POST['threat_id']) ? sanitize_text_field($_POST['threat_id']) : '';
        
        if (empty($threat_id)) {
            wp_send_json_error(array('message' => __('Threat ID required', 'spamguard')));
        }
        
        global $wpdb;
        
        // Obtener amenaza
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $threat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $threats_table WHERE id = %s",
            $threat_id
        ));
        
        if (!$threat) {
            wp_send_json_error(array('message' => __('Threat not found', 'spamguard')));
        }
        
        // Mover archivo a cuarentena
        $file_path = ABSPATH . $threat->file_path;
        $quarantine_dir = WP_CONTENT_DIR . '/spamguard-quarantine';
        
        if (!file_exists($quarantine_dir)) {
            wp_mkdir_p($quarantine_dir);
            file_put_contents($quarantine_dir . '/index.php', '<?php // Silence is golden');
        }
        
        $backup_path = $quarantine_dir . '/' . basename($threat->file_path) . '.' . time() . '.bak';
        
        if (file_exists($file_path)) {
            if (rename($file_path, $backup_path)) {
                // Actualizar BD
                $wpdb->update($threats_table, array(
                    'status' => 'quarantined',
                    'resolved_at' => current_time('mysql')
                ), array('id' => $threat_id));
                
                // Guardar en tabla quarantine
                $quarantine_table = $wpdb->prefix . 'spamguard_quarantine';
                $wpdb->insert($quarantine_table, array(
                    'id' => wp_generate_uuid4(),
                    'threat_id' => $threat_id,
                    'site_id' => get_site_url(),
                    'file_path' => $threat->file_path,
                    'original_content' => file_get_contents($backup_path),
                    'backup_location' => $backup_path,
                    'quarantined_at' => current_time('mysql')
                ));
                
                wp_send_json_success(array('message' => __('Threat quarantined successfully', 'spamguard')));
            } else {
                wp_send_json_error(array('message' => __('Could not move file to quarantine', 'spamguard')));
            }
        } else {
            wp_send_json_error(array('message' => __('File not found', 'spamguard')));
        }
    }
    
    /**
     * AJAX: Restaurar desde cuarentena
     */
    public function ajax_restore_threat() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }
        
        $threat_id = isset($_POST['threat_id']) ? sanitize_text_field($_POST['threat_id']) : '';
        
        if (empty($threat_id)) {
            wp_send_json_error(array('message' => __('Threat ID required', 'spamguard')));
        }
        
        global $wpdb;
        
        // Obtener de cuarentena
        $quarantine_table = $wpdb->prefix . 'spamguard_quarantine';
        $quarantine = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $quarantine_table WHERE threat_id = %s",
            $threat_id
        ));
        
        if (!$quarantine || !file_exists($quarantine->backup_location)) {
            wp_send_json_error(array('message' => __('Quarantine backup not found', 'spamguard')));
        }
        
        $original_path = ABSPATH . $quarantine->file_path;
        
        if (rename($quarantine->backup_location, $original_path)) {
            // Actualizar BD
            $threats_table = $wpdb->prefix . 'spamguard_threats';
            $wpdb->update($threats_table, array(
                'status' => 'restored'
            ), array('id' => $threat_id));
            
            $wpdb->update($quarantine_table, array(
                'restored_at' => current_time('mysql')
            ), array('threat_id' => $threat_id));
            
            wp_send_json_success(array('message' => __('Threat restored successfully', 'spamguard')));
        } else {
            wp_send_json_error(array('message' => __('Could not restore file', 'spamguard')));
        }
    }
    
    /**
     * Escaneo programado
     */
    public function run_scheduled_scan() {
        if (!get_option('spamguard_antivirus_enabled', true)) {
            return;
        }
        
        $auto_scan = get_option('spamguard_auto_scan', 'weekly');
        
        if ($auto_scan !== 'disabled') {
            $this->start_scan('quick');
        }
    }
}

// Registrar acción para procesar scans
add_action('spamguard_process_scan', array(SpamGuard_Antivirus_Scanner::get_instance(), 'process_scan'));