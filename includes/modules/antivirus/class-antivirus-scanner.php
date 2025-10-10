<?php
/**
 * SpamGuard Antivirus Scanner v3.0 - CORREGIDO
 * 
 * ✅ Escaneo síncrono con actualizaciones en tiempo real
 * ✅ Sin dependencia de WordPress Cron
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
        // Constructor vacío - Los AJAX se registran en SpamGuard_Core
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
                $max_files = 1000;
                break;
            
            case 'full':
                $paths = array(
                    WP_CONTENT_DIR,
                    ABSPATH . 'wp-includes',
                    ABSPATH . 'wp-admin'
                );
                $max_files = 5000;
                break;
            
            case 'plugins':
                $paths = array(WP_CONTENT_DIR . '/plugins');
                $max_files = 2000;
                break;
            
            case 'themes':
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
     * ✅ CORREGIDO: Iniciar escaneo SÍNCRONO
     */
    public function start_scan($scan_type = 'quick') {
        global $wpdb;
        
        // Generar ID único
        $scan_id = wp_generate_uuid4();
        
        // Obtener archivos
        $files = $this->get_files_to_scan($scan_type);
        
        if (empty($files)) {
            return false;
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
        
        // ✅ CRÍTICO: Procesar INMEDIATAMENTE de forma síncrona
        // Esto permite que AJAX vea el progreso en tiempo real
        $this->process_scan_sync($scan_id, $files);
        
        return $scan_id;
    }
    
    /**
     * ✅ NUEVO: Procesar escaneo de forma síncrona
     * Se actualiza la BD cada pocos archivos para que AJAX vea el progreso
     */
    private function process_scan_sync($scan_id, $files) {
        global $wpdb;
        
        // Permitir que continúe aunque el usuario cierre la página
        @ignore_user_abort(true);
        @set_time_limit(300); // 5 minutos
        
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        
        $total_files = count($files);
        $scanned = 0;
        $threats_found = 0;
        
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
            
            // ✅ Actualizar progreso cada 5 archivos (crítico para que AJAX vea cambios)
            if ($scanned % 5 === 0 || $scanned === $total_files) {
                $progress = round(($scanned / $total_files) * 100);
                
                $wpdb->update($scans_table, array(
                    'files_scanned' => $scanned,
                    'threats_found' => $threats_found,
                    'progress' => $progress,
                    'status' => 'running'
                ), array('id' => $scan_id));
                
                // ✅ CRÍTICO: Flush para que los cambios sean visibles inmediatamente
                $wpdb->flush();
            }
        }
        
        // Marcar como completado
        $wpdb->update($scans_table, array(
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'files_scanned' => $scanned,
            'threats_found' => $threats_found,
            'progress' => 100
        ), array('id' => $scan_id));
        
        // Enviar notificación si hay amenazas
        if ($threats_found > 0 && get_option('spamguard_email_notifications', true)) {
            $this->send_threat_notification($scan_id, $threats_found);
        }
        
        return true;
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
