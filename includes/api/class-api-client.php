<?php
/**
 * SpamGuard API Client v3.0 - CORREGIDO
 * Maneja todas las comunicaciones con la API
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Client {
    
    private static $instance = null;
    private $api_base_url;
    private $api_key;
    private $timeout = 60; // ✅ AUMENTADO a 60 segundos
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // ✅ URL base SIN /api/v1
        $this->api_base_url = get_option('spamguard_api_url', SPAMGUARD_API_URL);
        $this->api_key = get_option('spamguard_api_key', '');
        
        // ✅ Limpiar URL
        $this->api_base_url = rtrim($this->api_base_url, '/');
    }
    
    /**
     * ✅ MEJORADO: Hacer request con mejor manejo de errores
     */
    private function make_request($endpoint, $method = 'GET', $data = null, $use_api_key = true) {
        
        // ✅ Construir URL completa
        $url = rtrim($this->api_base_url, '/') . $endpoint;
        
        // Headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'SpamGuard-WordPress/' . SPAMGUARD_VERSION
        );
        
        // ✅ API Key como header
        if ($use_api_key) {
            if (empty($this->api_key)) {
                return new WP_Error('no_api_key', __('API Key not configured', 'spamguard'));
            }
            $headers['X-API-Key'] = $this->api_key;
        }
        
        // Args
        $args = array(
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $headers,
            'sslverify' => true,
            'httpversion' => '1.1',
            'blocking' => true
        );
        
        if ($data && $method === 'POST') {
            $args['body'] = json_encode($data);
        }
        
        // ✅ Log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SpamGuard API Request: {$method} {$url}");
        }
        
        // ✅ Reintentos
        $max_retries = 2;
        $retry_count = 0;
        
        while ($retry_count <= $max_retries) {
            $response = wp_remote_request($url, $args);
            
            if (!is_wp_error($response)) {
                break;
            }
            
            $retry_count++;
            
            if ($retry_count <= $max_retries) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("SpamGuard API: Retry {$retry_count}/{$max_retries}");
                }
                sleep(1);
            }
        }
        
        // ✅ Error de conexión
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("SpamGuard API Error: {$error_msg}");
            }
            
            return new WP_Error(
                'connection_error',
                sprintf(__('API connection error: %s', 'spamguard'), $error_msg)
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // ✅ Log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SpamGuard API Response: {$status_code}");
        }
        
        // ✅ Manejo de código 502/504
        if ($status_code === 502 || $status_code === 504) {
            return new WP_Error(
                'api_unavailable',
                __('API temporarily unavailable. Please try again later.', 'spamguard'),
                array('status_code' => $status_code)
            );
        }
        
        // Parse JSON
        $parsed = json_decode($body, true);
        
        // Success
        if ($status_code >= 200 && $status_code < 300) {
            return $parsed ? $parsed : array('success' => true);
        }
        
        // Error de API
        $error_message = 'Unknown API error';
        
        if ($parsed) {
            if (isset($parsed['detail'])) {
                $error_message = $parsed['detail'];
            } elseif (isset($parsed['message'])) {
                $error_message = $parsed['message'];
            }
        }
        
        return new WP_Error(
            'api_error',
            $error_message,
            array('status_code' => $status_code)
        );
    }
    
    /**
     * ✅ Health check
     */
    public function health_check() {
        return $this->make_request('/api/v1/health', 'GET', null, false);
    }
    
    /**
     * ✅ Registrar sitio
     */
    public function register_and_generate_key($email) {
        $data = array(
            'site_url' => get_site_url(),
            'admin_email' => $email
        );
        
        $result = $this->make_request('/api/v1/register-site', 'POST', $data, false);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        if (isset($result['api_key'])) {
            update_option('spamguard_api_key', $result['api_key']);
            $this->api_key = $result['api_key'];
            
            return array(
                'success' => true,
                'api_key' => $result['api_key'],
                'site_id' => isset($result['site_id']) ? $result['site_id'] : '',
                'message' => isset($result['message']) ? $result['message'] : 'Site registered successfully'
            );
        }
        
        return array(
            'success' => false,
            'message' => __('API did not return an API key', 'spamguard')
        );
    }
    
    /**
     * ✅ Analizar comentario
     */
    public function analyze_comment($comment) {
        $data = array(
            'content' => $comment['comment_content'],
            'author' => $comment['comment_author'],
            'author_email' => isset($comment['comment_author_email']) ? $comment['comment_author_email'] : '',
            'author_url' => isset($comment['comment_author_url']) ? $comment['comment_author_url'] : '',
            'author_ip' => isset($comment['comment_author_IP']) ? $comment['comment_author_IP'] : $_SERVER['REMOTE_ADDR'],
            'post_id' => isset($comment['comment_post_ID']) ? intval($comment['comment_post_ID']) : 0
        );
        
        $result = $this->make_request('/api/v1/analyze', 'POST', $data);
        
        // ✅ Si hay error, usar fallback local
        if (is_wp_error($result)) {
            if (class_exists('SpamGuard_Local_Fallback')) {
                return SpamGuard_Local_Fallback::get_instance()->analyze($comment);
            }
            
            // Safe fallback
            return array(
                'is_spam' => false,
                'category' => 'ham',
                'confidence' => 0.5,
                'risk_level' => 'low',
                'scores' => array('ham' => 1, 'spam' => 0),
                'flags' => array('api_error'),
                'cached' => false
            );
        }
        
        return $result;
    }
    
    /**
     * ✅ Test de conexión
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
                'version' => isset($health['version']) ? $health['version'] : 'unknown'
            );
        }
        
        return array(
            'success' => false,
            'message' => __('API not responding correctly', 'spamguard')
        );
    }
    
    /**
     * ✅ DUMMY: Datos de cuenta (por ahora locales)
     */
    public function get_account_info() {
        return array(
            'plan' => 'free',
            'status' => 'active',
            'error' => false
        );
    }
    
    /**
     * ✅ DUMMY: Uso de API (calculado localmente)
     */
    public function get_usage() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_usage';
        
        $requests_this_month = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table 
             WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
             AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        );
        
        $limit = 1000;
        $percentage = $requests_this_month > 0 ? ($requests_this_month / $limit) * 100 : 0;
        
        return array(
            'current_month' => array(
                'requests' => intval($requests_this_month)
            ),
            'limit' => $limit,
            'percentage_used' => min(100, $percentage),
            'error' => false
        );
    }
}
