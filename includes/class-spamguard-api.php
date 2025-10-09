<?php
/**
 * SpamGuard API Client
 * Cliente para comunicación con SpamGuard API v3.0
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API {
    
    /**
     * URL base de la API
     */
    private $api_base_url;
    
    /**
     * API Key
     */
    private $api_key;
    
    /**
     * Timeout para requests
     */
    private $timeout = 15;
    
    /**
     * Singleton instance
     */
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
        $this->api_base_url = get_option('spamguard_api_url', 'https://spamguard.up.railway.app');
        $this->api_key = get_option('spamguard_api_key', '');
    }
    
    /**
     * ✅ Test de conexión con la API
     */
    public function test_connection() {
        $response = $this->make_request('/api/v1/health', null, 'GET', false);
        
        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => $response['message']
            );
        }
        
        if (isset($response['status']) && $response['status'] === 'healthy') {
            return array(
                'success' => true,
                'message' => __('✅ Conexión exitosa con la API', 'spamguard'),
                'version' => isset($response['version']) ? $response['version'] : 'unknown',
                'data' => $response
            );
        }
        
        return array(
            'success' => false,
            'message' => __('❌ La API no está respondiendo correctamente', 'spamguard')
        );
    }
    
    /**
     * ✅ Registrar sitio y obtener API key
     */
    public function register_site() {
        $site_url = get_site_url();
        $admin_email = get_option('admin_email');
        
        $data = array(
            'site_url' => $site_url,
            'admin_email' => $admin_email
        );
        
        $response = $this->make_request('/api/v1/register-site', $data, 'POST', false);
        
        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => $response['message']
            );
        }
        
        if (isset($response['api_key'])) {
            // Guardar API key automáticamente
            update_option('spamguard_api_key', $response['api_key']);
            $this->api_key = $response['api_key'];
            
            return array(
                'success' => true,
                'api_key' => $response['api_key'],
                'site_id' => $response['site_id'],
                'message' => $response['message']
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Error inesperado al registrar el sitio', 'spamguard')
        );
    }
    
    /**
     * ✅ Analizar comentario
     */
    public function analyze_comment($comment_data) {
        // Preparar datos en el formato que espera la API
        $data = array(
            'content' => $comment_data['comment_content'],
            'author' => $comment_data['comment_author'],
            'author_email' => isset($comment_data['comment_author_email']) ? $comment_data['comment_author_email'] : '',
            'author_url' => isset($comment_data['comment_author_url']) ? $comment_data['comment_author_url'] : '',
            'author_ip' => isset($comment_data['comment_author_IP']) ? $comment_data['comment_author_IP'] : $_SERVER['REMOTE_ADDR'],
            'post_id' => isset($comment_data['comment_post_ID']) ? intval($comment_data['comment_post_ID']) : 0,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
        );
        
        $response = $this->make_request('/api/v1/analyze', $data, 'POST', true);
        
        // Si hay error, usar fallback local
        if (isset($response['error'])) {
            error_log('SpamGuard: API error - ' . $response['message']);
            
            // Usar análisis local como fallback
            if (class_exists('SpamGuard_Local_Fallback')) {
                return SpamGuard_Local_Fallback::get_instance()->analyze($comment_data);
            }
            
            // Fallback seguro: aprobar el comentario
            return array(
                'is_spam' => false,
                'confidence' => 0.5,
                'spam_score' => 0,
                'reasons' => array('API error - using safe fallback'),
                'comment_id' => '',
                'explanation' => array()
            );
        }
        
        return $response;
    }
    
    /**
     * ✅ Enviar feedback
     */
    public function send_feedback($comment_id, $is_spam) {
        $data = array(
            'comment_id' => $comment_id,
            'is_spam' => $is_spam
        );
        
        return $this->make_request('/api/v1/feedback', $data, 'POST', true);
    }
    
    /**
     * ✅ Obtener estadísticas
     */
    public function get_statistics() {
        return $this->make_request('/api/v1/stats', null, 'GET', true);
    }
    
    /**
     * 🔧 MÉTODO PRINCIPAL: Hacer request a la API
     */
    private function make_request($endpoint, $data = null, $method = 'GET', $use_api_key = true) {
        
        // Construir URL completa
        $url = rtrim($this->api_base_url, '/') . $endpoint;
        
        // Headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'SpamGuard-WordPress/' . SPAMGUARD_VERSION
        );
        
        // ✅ IMPORTANTE: Usar X-API-Key (como espera la API)
        if ($use_api_key) {
            if (empty($this->api_key)) {
                return array(
                    'error' => true,
                    'message' => __('API Key no configurada. Ve a Configuración para obtener una.', 'spamguard')
                );
            }
            $headers['X-API-Key'] = $this->api_key;
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
            error_log(sprintf(
                'SpamGuard API Request: %s %s | Has API Key: %s',
                $method,
                $url,
                $use_api_key ? 'Yes' : 'No'
            ));
        }
        
        // Hacer request
        $response = wp_remote_request($url, $args);
        
        // Verificar errores de conexión
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SpamGuard API Error: ' . $error_message);
            }
            
            return array(
                'error' => true,
                'message' => sprintf(
                    __('Error de conexión: %s', 'spamguard'),
                    $error_message
                ),
                'url' => $url
            );
        }
        
        // Obtener código de respuesta
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Obtener body
        $body = wp_remote_retrieve_body($response);
        $parsed = json_decode($body, true);
        
        // Log de debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'SpamGuard API Response: %s | Body: %s',
                $status_code,
                substr($body, 0, 200)
            ));
        }
        
        // Manejar respuesta según código de estado
        if ($status_code >= 200 && $status_code < 300) {
            // Success
            return $parsed ? $parsed : array('success' => true);
        } else {
            // Error
            $error_message = 'Error desconocido';
            
            if ($parsed) {
                if (isset($parsed['detail'])) {
                    $error_message = $parsed['detail'];
                } elseif (isset($parsed['message'])) {
                    $error_message = $parsed['message'];
                }
            }
            
            return array(
                'error' => true,
                'message' => sprintf(
                    __('Error de la API (HTTP %s): %s', 'spamguard'),
                    $status_code,
                    $error_message
                ),
                'status_code' => $status_code,
                'response' => $parsed
            );
        }
    }
}
