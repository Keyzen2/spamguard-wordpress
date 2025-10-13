<?php
/**
 * SpamGuard Antivirus Scanner v3.2 - ARQUITECTURA HÍBRIDA MEJORADA
 *
 * ✅ Escaneo local de archivos (más eficiente)
 * ✅ Sincronización con API para estadísticas centralizadas
 * ✅ Progreso en tiempo real vía AJAX
 *
 * @package SpamGuard
 * @version 3.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Antivirus_Scanner {

    private static $instance = null;
    private $api_client;

    /**
     * Patrones de malware mejorados
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
            'pattern' => '/(coinhive|cryptonight|monero|stratum\+tcp)/i',
            'severity' => 'high',
            'description' => 'Posible crypto miner'
        ),

        // Common malware signatures
        'c99_shell' => array(
            'pattern' => '/(c99sh|c99shell|r57shell|WSO\s*shell)/i',
            'severity' => 'critical',
            'description' => 'Shell PHP conocido (C99/R57/WSO)'
        ),
        'encoded_eval' => array(
            'pattern' => '/\$[a-zA-Z0-9_]+\s*=\s*["\']([\w+\/=]{50,})["\'].*eval/i',
            'severity' => 'critical',
            'description' => 'Código codificado con eval'
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
        $this->api_client = SpamGuard_API_Client::get_instance();

        // AJAX handlers
        add_action('wp_ajax_spamguard_start_scan', array($this, 'ajax_start_scan'));
        add_action('wp_ajax_spamguard_scan_progress', array($this, 'ajax_get_scan_progress')); // Nombre usado en JS
        add_action('wp_ajax_spamguard_get_scan_progress', array($this, 'ajax_get_scan_progress')); // Compatibilidad
        add_action('wp_ajax_spamguard_get_scan_results', array($this, 'ajax_get_scan_results'));
        add_action('wp_ajax_spamguard_quarantine_threat', array($this, 'ajax_quarantine_threat'));
        add_action('wp_ajax_spamguard_ignore_threat', array($this, 'ajax_ignore_threat'));

        // Hook para procesar escaneo en background
        add_action('spamguard_process_scan', array($this, 'process_scan'));

        // Limpieza de escaneos antiguos
        add_action('spamguard_daily_cleanup', array($this, 'cleanup_old_scans'));
    }

    /**
     * ✅ Iniciar escaneo (HÍBRIDO: local + API)
     */
    public function start_scan($scan_type = 'quick') {
        global $wpdb;

        // 1. Generar ID único
        $scan_id = wp_generate_uuid4();

        // 2. Obtener archivos a escanear
        $files = $this->get_files_to_scan($scan_type);

        if (empty($files)) {
            return new WP_Error('no_files', __('No files found to scan', 'spamguard'));
        }

        // 3. Guardar escaneo en BD local
        $table = $wpdb->prefix . 'spamguard_scans';
        $wpdb->insert($table, array(
            'id' => $scan_id,
            'scan_type' => $scan_type,
            'status' => 'running',  // ✅ Cambiar a 'running' inmediatamente
            'started_at' => current_time('mysql'),
            'files_scanned' => 0,
            'threats_found' => 0,
            'progress' => 0,
            'results' => json_encode(array('total_files' => count($files)))
        ));

        // 4. Guardar lista de archivos en transient
        set_transient('spamguard_scan_files_' . $scan_id, $files, HOUR_IN_SECONDS);

        // ✅ 5. Ejecutar escaneo inmediatamente en lugar de usar cron
        // Esto garantiza que el progreso sea visible de inmediato
        $this->process_scan_async($scan_id);

        return $scan_id;
    }

    /**
     * ✅ Procesar escaneo de forma asíncrona (sin bloquear)
     */
    private function process_scan_async($scan_id) {
        // Intentar ejecutar en background
        if (function_exists('fastcgi_finish_request')) {
            // Para FastCGI - enviar respuesta al cliente primero
            fastcgi_finish_request();
            $this->process_scan($scan_id);
        } else {
            // Fallback: usar cron como backup
            wp_schedule_single_event(time(), 'spamguard_process_scan', array($scan_id));
            spawn_cron();
        }
    }

    /**
     * ✅ Procesar escaneo en background
     */
    public function process_scan($scan_id) {
        global $wpdb;

        // Obtener archivos del transient
        $files = get_transient('spamguard_scan_files_' . $scan_id);

        if (!$files) {
            $wpdb->update(
                $wpdb->prefix . 'spamguard_scans',
                array('status' => 'failed', 'completed_at' => current_time('mysql')),
                array('id' => $scan_id)
            );
            return;
        }

        // Actualizar a running
        $wpdb->update(
            $wpdb->prefix . 'spamguard_scans',
            array('status' => 'running'),
            array('id' => $scan_id)
        );

        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $scans_table = $wpdb->prefix . 'spamguard_scans';

        $total_files = count($files);
        $scanned = 0;
        $threats_found = 0;

        // Permitir ejecución larga
        @set_time_limit(300);
        @ignore_user_abort(true);

        foreach ($files as $file) {
            // Escanear archivo localmente
            $result = $this->scan_file($file['path']);
            $scanned++;

            // Si hay amenazas, guardar localmente
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

            // Actualizar progreso cada 5 archivos
            if ($scanned % 5 === 0 || $scanned === $total_files) {
                $progress = round(($scanned / $total_files) * 100);

                $wpdb->update($scans_table, array(
                    'files_scanned' => $scanned,
                    'threats_found' => $threats_found,
                    'progress' => $progress,
                    'status' => 'running',
                    'results' => json_encode(array(
                        'total_files' => $total_files,
                        'current_file' => $file['relative_path']
                    ))
                ), array('id' => $scan_id));
            }

            usleep(500); // 0.0005 segundos de pausa
        }

        // Marcar como completado localmente
        $wpdb->update($scans_table, array(
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'files_scanned' => $scanned,
            'threats_found' => $threats_found,
            'progress' => 100
        ), array('id' => $scan_id));

        // Limpiar transient
        delete_transient('spamguard_scan_files_' . $scan_id);

        // ✅ Sincronizar con API en background (no bloquea)
        $this->sync_scan_to_api($scan_id);

        // Notificar si hay amenazas
        if ($threats_found > 0 && get_option('spamguard_email_notifications', true)) {
            $this->send_threat_notification($scan_id, $threats_found);
        }
    }

    /**
     * ✅ Sincronizar resultados del escaneo con la API
     */
    private function sync_scan_to_api($scan_id) {
        global $wpdb;

        try {
            // Obtener datos del escaneo
            $scan = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}spamguard_scans WHERE id = %s",
                $scan_id
            ), ARRAY_A);

            if (!$scan) {
                return;
            }

            // Obtener amenazas del escaneo
            $threats = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}spamguard_threats WHERE scan_id = %s",
                $scan_id
            ), ARRAY_A);

            // Preparar datos para la API
            $sync_data = array(
                'scan_type' => $scan['scan_type'],
                'files_scanned' => $scan['files_scanned'],
                'threats_found' => $scan['threats_found'],
                'started_at' => $scan['started_at'],
                'completed_at' => $scan['completed_at'],
                'threats' => $threats
            );

            // Intentar enviar a la API (no crítico si falla)
            // La API guardará esto para estadísticas centralizadas
            $this->api_client->make_request('/api/v1/antivirus/sync-scan', 'POST', $sync_data, true);

        } catch (Exception $e) {
            // Log error pero no bloquear
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SpamGuard] API sync error: ' . $e->getMessage());
            }
        }
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
                $paths = array(
                    WP_CONTENT_DIR . '/plugins',
                    WP_CONTENT_DIR . '/themes/' . get_template()
                );
                $max_files = 500;
                break;

            case 'full':
                $paths = array(
                    WP_CONTENT_DIR,
                    ABSPATH . 'wp-includes',
                    ABSPATH . 'wp-admin'
                );
                $max_files = 2000;
                break;

            case 'plugins':
                $paths = array(WP_CONTENT_DIR . '/plugins');
                $max_files = 1000;
                break;

            case 'themes':
                $paths = array(WP_CONTENT_DIR . '/themes');
                $max_files = 500;
                break;

            default:
                $paths = array(WP_CONTENT_DIR . '/plugins');
                $max_files = 500;
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

                    // Skip archivos muy grandes (> 2MB)
                    if ($file->getSize() > 2 * 1024 * 1024) {
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
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SpamGuard] Error scanning path: ' . $path . ' - ' . $e->getMessage());
                }
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
            $content = @file_get_contents($file_path);

            if ($content === false) {
                return array('error' => 'Could not read file');
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
            return array('error' => $e->getMessage());
        }
    }

    /**
     * Obtener progreso de escaneo
     */
    public function get_scan_progress($scan_id) {
        global $wpdb;

        $scan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spamguard_scans WHERE id = %s",
            $scan_id
        ), ARRAY_A);

        if (!$scan) {
            return new WP_Error('scan_not_found', __('Scan not found', 'spamguard'));
        }

        return array(
            'scan_id' => $scan_id,
            'status' => $scan['status'],
            'progress' => intval($scan['progress']),
            'files_scanned' => intval($scan['files_scanned']),
            'threats_found' => intval($scan['threats_found']),
            'current_file' => isset($scan['results']) ? json_decode($scan['results'], true)['current_file'] : null
        );
    }

    /**
     * Obtener resultados de escaneo
     */
    public function get_scan_results($scan_id) {
        global $wpdb;

        $scan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spamguard_scans WHERE id = %s",
            $scan_id
        ), ARRAY_A);

        if (!$scan) {
            return new WP_Error('scan_not_found', __('Scan not found', 'spamguard'));
        }

        $threats = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spamguard_threats WHERE scan_id = %s ORDER BY severity DESC",
            $scan_id
        ), ARRAY_A);

        return array(
            'scan' => $scan,
            'threats' => $threats
        );
    }

    /**
     * Obtener estadísticas
     */
    public function get_stats() {
        global $wpdb;

        $total_scans = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spamguard_scans");
        $active_threats = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spamguard_threats WHERE status = 'active'");

        $threats_by_severity = array();
        foreach (array('critical', 'high', 'medium', 'low') as $severity) {
            $threats_by_severity[$severity] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spamguard_threats WHERE severity = %s AND status = 'active'",
                $severity
            )));
        }

        $last_scan = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}spamguard_scans ORDER BY started_at DESC LIMIT 1",
            ARRAY_A
        );

        return array(
            'total_scans' => intval($total_scans),
            'active_threats' => intval($active_threats),
            'threats_by_severity' => $threats_by_severity,
            'last_scan' => $last_scan
        );
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
     * Limpiar escaneos antiguos (más de 30 días)
     */
    public function cleanup_old_scans() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}spamguard_scans
             WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}spamguard_threats
             WHERE detected_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND status != 'active'"
        );
    }

    // ============================================
    // AJAX HANDLERS
    // ============================================

    public function ajax_start_scan() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $scan_type = isset($_POST['scan_type']) ? sanitize_text_field($_POST['scan_type']) : 'quick';

        $scan_id = $this->start_scan($scan_type);

        if (is_wp_error($scan_id)) {
            wp_send_json_error(array('message' => $scan_id->get_error_message()));
        }

        wp_send_json_success(array(
            'scan_id' => $scan_id,
            'message' => __('Scan started successfully', 'spamguard')
        ));
    }

    public function ajax_get_scan_progress() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $scan_id = isset($_POST['scan_id']) ? sanitize_text_field($_POST['scan_id']) : '';

        if (empty($scan_id)) {
            wp_send_json_error(array('message' => __('Invalid scan ID', 'spamguard')));
        }

        $progress = $this->get_scan_progress($scan_id);

        if (is_wp_error($progress)) {
            wp_send_json_error(array('message' => $progress->get_error_message()));
        }

        wp_send_json_success($progress);
    }

    public function ajax_get_scan_results() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $scan_id = isset($_POST['scan_id']) ? sanitize_text_field($_POST['scan_id']) : '';

        if (empty($scan_id)) {
            wp_send_json_error(array('message' => __('Invalid scan ID', 'spamguard')));
        }

        $results = $this->get_scan_results($scan_id);

        if (is_wp_error($results)) {
            wp_send_json_error(array('message' => $results->get_error_message()));
        }

        wp_send_json_success($results);
    }

    public function ajax_quarantine_threat() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $threat_id = isset($_POST['threat_id']) ? sanitize_text_field($_POST['threat_id']) : '';

        if (empty($threat_id)) {
            wp_send_json_error(array('message' => __('Invalid threat ID', 'spamguard')));
        }

        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'spamguard_threats',
            array('status' => 'quarantined'),
            array('id' => $threat_id)
        );

        wp_send_json_success(array('message' => __('Threat quarantined successfully', 'spamguard')));
    }

    public function ajax_ignore_threat() {
        check_ajax_referer('spamguard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $threat_id = isset($_POST['threat_id']) ? sanitize_text_field($_POST['threat_id']) : '';

        if (empty($threat_id)) {
            wp_send_json_error(array('message' => __('Invalid threat ID', 'spamguard')));
        }

        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'spamguard_threats',
            array('status' => 'ignored'),
            array('id' => $threat_id)
        );

        wp_send_json_success(array('message' => __('Threat ignored successfully', 'spamguard')));
    }
}
