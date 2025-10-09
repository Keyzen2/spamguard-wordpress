<?php
/**
 * SpamGuard API Client v3.0
 * Maneja todas las comunicaciones con la API
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Client {
    
    private static $instance = null;
    
    /**
     * URL base de la API
     */
    private $api_base_url;
    
    /**
     * API Key
     */
    private $api_key;
    
    /**
     * Timeout (segundos)
     */
    private $timeout = 15;
    
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
        $this->api_base_url = get_option('spamguard_api_url', SPAMGUARD_API_URL);
        $this->api_key = get_option('spamguard_api_key', '');
    }
    
    /**
     * Hacer request a la API
     */
    private function make_request($endpoint, $method = 'GET', $data = null, $use_api_key = true) {
        
        // Construir URL completa
        $url = rtrim($this->api_base_url, '/') . $endpoint;
        
        // Headers
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'SpamGuard-WordPress/' . SPAMGUARD_VERSION
        );
        
        // Agregar API key si se requiere
        if ($use_api_key) {
            if (empty($this->api_key)) {
                return new WP_Error('no_api_key', __('API Key not configured', 'spamguard'));
            }
            // ⚠️ IMPORTANTE: La API v3.0 usa formato Authorization: Bearer
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }
        
        // Argumentos de wp_remote_request
        $args = array(
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $headers,
            'sslverify' => true
        );
        
        // Body para POST
        if ($data && $method === 'POST') {
            $args['body'] = json_encode($data);
        }
        
        // Log de debug (solo si WP_DEBUG está activo)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SpamGuard API Request: {$method} {$url}");
        }
        
        // Hacer request
        $response = wp_remote_request($url, $args);
        
        // Verificar errores de conexión
        if (is_wp_error($response)) {
            return new WP_Error(
                'connection_error',
                sprintf(__('Connection error: %s', 'spamguard'), $response->get_error_message()),
                array('url' => $url)
            );
        }
        
        // Obtener código de respuesta
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $parsed = json_decode($body, true);
        
        // Log de debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SpamGuard API Response: {$status_code} - " . substr($body, 0, 200));
        }
        
        // Manejar respuestas
        if ($status_code >= 200 && $status_code < 300) {
            return $parsed ? $parsed : array('success' => true);
        } else {
            // Error de API
            $error_message = isset($parsed['detail']) ? $parsed['detail'] : 
                           (isset($parsed['message']) ? $parsed['message'] : 'Unknown API error');
            
            return new WP_Error(
                'api_error',
                $error_message,
                array(
                    'status_code' => $status_code,
                    'response' => $parsed
                )
            );
        }
    }
    
    /**
     * ============================================
     * ENDPOINTS PÚBLICOS
     * ============================================
     */
    
    /**
     * Health check
     */
    public function health_check() {
        return $this->make_request('/health', 'GET', null, false);
    }
    
    /**
     * Registrar sitio y generar API key
     */
    public function register_and_generate_key($email) {
        $data = array(
            'email' => $email,
            'site_url' => get_site_url(),
            'name' => get_bloginfo('name')
        );
        
        $result = $this->make_request('/api/v1/register', 'POST', $data, false);
        
        if (!is_wp_error($result) && isset($result['api_key'])) {
            // Guardar API key automáticamente
            update_option('spamguard_api_key', $result['api_key']);
            $this->api_key = $result['api_key'];
            
            return array(
                'success' => true,
                'api_key' => $result['api_key'],
                'user_id' => $result['user_id'],
                'message' => $result['message']
            );
        }
        
        return $result;
    }
    
    /**
     * Analizar comentario (Anti-Spam)
     */
    public function analyze_comment($comment) {
        $data = array(
            'text' => $comment['comment_content'],
            'context' => array(
                'email' => isset($comment['comment_author_email']) ? $comment['comment_author_email'] : '',
                'ip' => isset($comment['comment_author_IP']) ? $comment['comment_author_IP'] : ''
            )
        );
        
        $result = $this->make_request('/api/v1/analyze', 'POST', $data);
        
        // Si hay error, usar fallback local
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SpamGuard: API error, using local fallback - ' . $result->get_error_message());
            }
            
            // Usar análisis local como fallback
            if (class_exists('SpamGuard_Local_Fallback')) {
                return SpamGuard_Local_Fallback::analyze($comment);
            }
            
            // Si no hay fallback, retornar como no-spam (safe fallback)
            return array(
                'is_spam' => false,
                'category' => 'ham',
                'confidence' => 0.5,
                'risk_level' => 'low',
                'scores' => array('ham' => 1, 'spam' => 0, 'phishing' => 0),
                'flags' => array('api_error'),
                'cached' => false
            );
        }
        
        return $result;
    }
    
    /**
     * Enviar feedback
     */
    public function send_feedback($comment_id, $predicted_category, $correct_category, $notes = '') {
        $comment = get_comment($comment_id);
        
        if (!$comment) {
            return false;
        }
        
        $data = array(
            'text' => $comment->comment_content,
            'predicted_category' => $predicted_category,
            'correct_category' => $correct_category,
            'notes' => $notes
        );
        
        $result = $this->make_request('/api/v1/feedback', 'POST', $data);
        
        return !is_wp_error($result);
    }
    
    /**
     * Obtener estadísticas
     */
    public function get_stats($period_days = 30) {
        return $this->make_request('/api/v1/stats?period=' . intval($period_days), 'GET');
    }
    
    /**
     * Obtener información de cuenta
     */
    public function get_account_info() {
        return $this->make_request('/api/v1/account', 'GET');
    }
    
    /**
     * Obtener uso actual
     */
    public function get_usage() {
        return $this->make_request('/api/v1/account/usage', 'GET');
    }
    
    /**
     * ============================================
     * ANTIVIRUS ENDPOINTS
     * ============================================
     */
    
    /**
     * Iniciar escaneo de malware
     */
    public function start_malware_scan($scan_type = 'quick', $max_size_mb = 10) {
        $data = array(
            'scan_type' => $scan_type,
            'max_size_mb' => $max_size_mb
        );
        
        return $this->make_request('/api/v1/antivirus/scan/start', 'POST', $data);
    }
    
    /**
     * Obtener progreso de escaneo
     */
    public function get_scan_progress($scan_id) {
        return $this->make_request('/api/v1/antivirus/scan/' . $scan_id . '/progress', 'GET');
    }
    
    /**
     * Obtener resultados de escaneo
     */
    public function get_scan_results($scan_id) {
        return $this->make_request('/api/v1/antivirus/scan/' . $scan_id . '/results', 'GET');
    }
    
    /**
     * Obtener escaneos recientes
     */
    public function get_recent_scans($limit = 10) {
        return $this->make_request('/api/v1/antivirus/scans/recent?limit=' . intval($limit), 'GET');
    }
    
    /**
     * Obtener estadísticas de antivirus
     */
    public function get_antivirus_stats() {
        return $this->make_request('/api/v1/antivirus/stats', 'GET');
    }
    
    /**
     * Poner amenaza en cuarentena
     */
    public function quarantine_threat($threat_id) {
        return $this->make_request('/api/v1/antivirus/threat/' . $threat_id . '/quarantine', 'POST');
    }
    
    /**
     * Ignorar amenaza (falso positivo)
     */
    public function ignore_threat($threat_id) {
        return $this->make_request('/api/v1/antivirus/threat/' . $threat_id . '/ignore', 'DELETE');
    }
    
    /**
     * ============================================
     * HELPERS
     * ============================================
     */
    
    /**
     * Test de conexión
     */
    public function test_connection() {
        $health = $this->health_check();
        
        if (is_wp_error($health)) {
            return array(
                'success' => false,
                'message' => $health->get_error_message()
            );
        }
        
        if (isset($health['status']) && $health['status'] === 'healthy') {
            return array(
                'success' => true,
                'message' => __('Connection successful!', 'spamguard'),
                'version' => isset($health['version']) ? $health['version'] : 'unknown',
                'environment' => isset($health['environment']) ? $health['environment'] : 'unknown'
            );
        }
        
        return array(
            'success' => false,
            'message' => __('API not responding correctly', 'spamguard')
        );
    }
}
