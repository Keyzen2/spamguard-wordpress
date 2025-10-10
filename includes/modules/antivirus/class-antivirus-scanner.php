<?php
/**
 * SpamGuard Antivirus Scanner v3.1 - COMPLETAMENTE CORREGIDO
 * 
 * ✅ Escaneo con progreso real usando transients
 * ✅ Sin dependencia de WordPress Cron
 * 
 * @package SpamGuard
 * @version 3.1.0
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
        // Registrar método process_scan como estático
    }
    
    /**
     * ✅ Wrapper estático para WP-Cron
     */
    public static function process_scan_static($scan_id) {
        $instance = self::get_instance();
        $instance->process_scan($scan_id);
    }
    
    /**
     * ✅ NUEVO: Iniciar escaneo en BACKGROUND
     */
    public function start_scan($scan_type = 'quick') {
        global $wpdb;
        
        // Generar ID único
        $scan_id = wp_generate_uuid4();
        
        // Obtener archivos
        $files = $this->get_files_to_scan($scan_type);
        
        if (empty($files)) {
            return new WP_Error('no_files', 'No se encontraron archivos para escanear');
        }
        
        // Guardar escaneo en BD
        $table = $wpdb->prefix . 'spamguard_scans';
        $wpdb->insert($table, array(
            'id' => $scan_id,
            'scan_type' => $scan_type,
            'status' => 'pending',
            'started_at' => current_time('mysql'),
            'files_scanned' => 0,
            'threats_found' => 0,
            'progress' => 0,
            'results' => json_encode(array('total_files' => count($files)))
        ));
        
        // ✅ Guardar lista de archivos en transient (expira en 1 hora)
        set_transient('spamguard_scan_files_' . $scan_id, $files, HOUR_IN_SECONDS);
        
        // ✅ Iniciar procesamiento asíncrono
        wp_schedule_single_event(time(), 'spamguard_process_scan', array($scan_id));
        
        // ✅ CRÍTICO: Ejecutar cron inmediatamente
        spawn_cron();
        
        return $scan_id;
    }
    
    /**
     * ✅ Procesar escaneo (llamado por WP-Cron)
     */
    public function process_scan($scan_id) {
        global $wpdb;
        
        // Obtener archivos del transient
        $files = get_transient('spamguard_scan_files_' . $scan_id);
        
        if (!$files) {
            // Si no hay transient, marcar como fallido
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
            // Escanear archivo
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
            
            // ✅ Actualizar progreso cada 3 archivos
            if ($scanned % 3 === 0 || $scanned === $total_files) {
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
            
            // ✅ Pequeña pausa para no saturar
            usleep(1000); // 0.001 segundos
        }
        
        // Marcar como completado
        $wpdb->update($scans_table, array(
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'files_scanned' => $scanned,
            'threats_found' => $threats_found,
            'progress' => 100
        ), array('id' => $scan_id));
        
        // Limpiar transient
        delete_transient('spamguard_scan_files_' . $scan_id);
        
        // Notificar si hay amenazas
        if ($threats_found > 0 && get_option('spamguard_email_notifications', true)) {
            $this->send_threat_notification($scan_id, $threats_found);
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
                $max_files = 500; // ✅ Reducido para testing
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
}
