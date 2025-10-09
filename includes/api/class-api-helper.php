<?php
/**
 * SpamGuard API Helper
 * Funciones auxiliares para la API
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Helper {
    
    /**
     * Sanitizar API key
     */
    public static function sanitize_api_key($api_key) {
        // Remover espacios
        $api_key = trim($api_key);
        
        // Validar formato (sg_test_ o sg_live_)
        if (!preg_match('/^sg_(test|live)_[a-zA-Z0-9_-]+$/', $api_key)) {
            return '';
        }
        
        return $api_key;
    }
    
    /**
     * Validar email
     */
    public static function is_valid_email($email) {
        return is_email($email);
    }
    
    /**
     * ✅ NUEVO: Convertir timestamp a "hace X tiempo"
     * 
     * @param string|int $datetime Timestamp o fecha en formato MySQL
     * @return string Texto legible "hace X minutos"
     */
    public static function time_ago($datetime) {
        // Convertir a timestamp si es string
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        
        // Calcular diferencia con el tiempo actual
        $diff = current_time('timestamp') - $timestamp;
        
        // Casos negativos (fecha futura)
        if ($diff < 0) {
            return __('In the future', 'spamguard');
        }
        
        // Menos de 1 minuto
        if ($diff < 60) {
            return __('Just now', 'spamguard');
        }
        
        // Menos de 1 hora
        if ($diff < 3600) {
            $mins = round($diff / 60);
            return sprintf(
                _n('%d minute ago', '%d minutes ago', $mins, 'spamguard'),
                $mins
            );
        }
        
        // Menos de 1 día
        if ($diff < 86400) {
            $hours = round($diff / 3600);
            return sprintf(
                _n('%d hour ago', '%d hours ago', $hours, 'spamguard'),
                $hours
            );
        }
        
        // Menos de 1 mes (30 días)
        if ($diff < 2592000) {
            $days = round($diff / 86400);
            return sprintf(
                _n('%d day ago', '%d days ago', $days, 'spamguard'),
                $days
            );
        }
        
        // Menos de 1 año
        if ($diff < 31536000) {
            $months = round($diff / 2592000);
            return sprintf(
                _n('%d month ago', '%d months ago', $months, 'spamguard'),
                $months
            );
        }
        
        // Más de 1 año
        $years = round($diff / 31536000);
        return sprintf(
            _n('%d year ago', '%d years ago', $years, 'spamguard'),
            $years
        );
    }
    
    /**
     * ✅ NUEVO: Formatear bytes a tamaño legible
     * 
     * @param int $bytes Tamaño en bytes
     * @param int $precision Decimales
     * @return string Tamaño formateado (ej: "1.5 MB")
     */
    public static function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * ✅ NUEVO: Formatear número con separador de miles
     * 
     * @param int|float $number Número a formatear
     * @param int $decimals Número de decimales
     * @return string Número formateado
     */
    public static function format_number($number, $decimals = 0) {
        return number_format_i18n($number, $decimals);
    }
    
    /**
     * ✅ NUEVO: Obtener color según severidad
     * 
     * @param string $severity Severidad (critical, high, medium, low)
     * @return string Color hex
     */
    public static function get_severity_color($severity) {
        $colors = array(
            'critical' => '#d63638',
            'high'     => '#f56e28',
            'medium'   => '#dba617',
            'low'      => '#50575e',
        );
        
        return isset($colors[$severity]) ? $colors[$severity] : '#50575e';
    }
    
    /**
     * ✅ NUEVO: Obtener icono según tipo de amenaza
     * 
     * @param string $threat_type Tipo de amenaza
     * @return string Emoji o icono
     */
    public static function get_threat_icon($threat_type) {
        $icons = array(
            'malware'           => '🦠',
            'backdoor'          => '🚪',
            'suspicious_code'   => '⚠️',
            'obfuscated_code'   => '🔒',
            'eval_execution'    => '⚡',
            'file_injection'    => '💉',
            'sql_injection'     => '💾',
            'xss'               => '🔗',
            'shell_access'      => '💻',
            'unknown'           => '❓',
        );
        
        return isset($icons[$threat_type]) ? $icons[$threat_type] : '⚠️';
    }
    
    /**
     * ✅ NUEVO: Validar nonce de forma segura
     * 
     * @param string $nonce Nonce a validar
     * @param string $action Acción del nonce
     * @return bool True si es válido
     */
    public static function verify_nonce($nonce, $action = 'spamguard_ajax') {
        return wp_verify_nonce($nonce, $action) !== false;
    }
    
    /**
     * ✅ NUEVO: Crear response AJAX estandarizado
     * 
     * @param bool $success Si fue exitoso
     * @param mixed $data Datos a retornar
     * @param string $message Mensaje opcional
     * @return void Envía JSON y termina ejecución
     */
    public static function ajax_response($success, $data = null, $message = '') {
        $response = array(
            'success' => $success,
        );
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        wp_send_json($response);
    }
    
    /**
     * ✅ NUEVO: Sanitizar input de usuario
     * 
     * @param mixed $input Input a sanitizar
     * @param string $type Tipo de sanitización
     * @return mixed Input sanitizado
     */
    public static function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            
            case 'url':
                return esc_url_raw($input);
            
            case 'int':
                return intval($input);
            
            case 'float':
                return floatval($input);
            
            case 'bool':
                return (bool) $input;
            
            case 'html':
                return wp_kses_post($input);
            
            case 'textarea':
                return sanitize_textarea_field($input);
            
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * ✅ NUEVO: Log de debug (solo si WP_DEBUG está activo)
     * 
     * @param string $message Mensaje a loggear
     * @param string $level Nivel (info, warning, error)
     * @return void
     */
    public static function log($message, $level = 'info') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $prefix = strtoupper($level);
        $timestamp = date('Y-m-d H:i:s');
        
        error_log("[{$timestamp}] SPAMGUARD [{$prefix}]: {$message}");
    }
    
    /**
     * ✅ NUEVO: Obtener IP del usuario actual
     * 
     * @return string IP address
     */
    public static function get_user_ip() {
        $ip = '';
        
        // Check for shared internet/ISP IP
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IPs passing through proxies
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Can have multiple IPs, get the first one
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        // Regular remote address
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Sanitize
        $ip = filter_var(trim($ip), FILTER_VALIDATE_IP);
        
        return $ip ? $ip : '0.0.0.0';
    }
    
    /**
     * ✅ NUEVO: Verificar si es request AJAX
     * 
     * @return bool True si es AJAX
     */
    public static function is_ajax() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
    
    /**
     * ✅ NUEVO: Verificar si es admin
     * 
     * @return bool True si es admin
     */
    public static function is_admin_user() {
        return current_user_can('manage_options');
    }
}
