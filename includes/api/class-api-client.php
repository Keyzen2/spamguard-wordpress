<?php
/**
 * SpamGuard API Client v3.0
 * 
 * Cliente para conectar con SpamGuard API v3.0 Hybrid
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Client {
    
    private static $instance = null;
    
    private $api_url;
    private $api_key;
    private $cache;
    
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
        $this->api_url = get_option('spamguard_api_url', SPAMGUARD_API_URL);
        $this->api_key = get_option('spamguard_api_key');
        $this->cache = SpamGuard_API_Cache::get_instance();
    }
    
    /**
     * Analizar comentario con API v3.0
     * 
     * @param array $comment_data Datos del comentario
     * @return array Resultado del análisis
     */
    public function analyze_comment($comment_data) {
        // 1. Verificar API key
        if (empty($this->api_key)) {
            return $this->local_fallback($comment_data, 'no_api_key');
        }
        
        // 2. Generar cache key
        $cache_key = $this->generate_cache_key($comment_data);
        
        // 3. Intentar desde caché (5 minutos)
        $cached = $this->cache->get($cache_key);
        if ($cached !== false) {
            $cached['cached'] = true;
            $cached['source'] = 'cache';
            return $cached;
        }
        
        // 4. Preparar datos para API
        $request_data = array(
            'text' => $comment_data['comment_content'],
            'context' => array(
                'email' => $comment_data['comment_author_email'],
                'ip' => $comment_data['comment_author_IP'],
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'referer' => wp_get_referer()
            ),
            'metadata' => array(
                'post_id' => isset($comment_data['comment_post_ID']) ? $comment_data['comment_post_ID'] : 0,
                'user_id' => isset($comment_data['user_id']) ? $comment_data['user_id'] : 0,
                'platform' => 'wordpress',
                'plugin_version' => SPAMGUARD_VERSION,
                'site_url' => get_site_url()
            )
        );
        
        // 5. Llamar a API
        $response = $this->make_request('POST', '/analyze', $request_data, 5);
        
        // 6. Si falla, usar fallback
        if (is_wp_error($response)) {
            $this->log_error('API request failed: ' . $response->get_error_message());
            return $this->local_fallback($comment_data, 'api_error');
        }
        
        // 7. Parsear respuesta
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200 || !$body || !isset($body['is_spam'])) {
            $this->log_error('Invalid API response: ' . wp_remote_retrieve_body($response));
            return $this->local_fallback($comment_data, 'invalid_response');
        }
        
        // 8. Añadir metadata
        $body['source'] = 'api';
        $body['api_version'] = '3.0';
        
        // 9. Cachear resultado (5 minutos)
        $this->cache->set($cache_key, $body, 300);
        
        // 10. Log estadísticas
        $this->log_usage($body);
        
        // 11. Log en tabla local
        $this->log_detection($comment_data, $body);
        
        return $body;
    }
    
    /**
     * Enviar feedback a API
     * 
     * @param int $comment_id ID del comentario
     * @param string $predicted_category Categoría predicha
     * @param string $correct_category Categoría correcta
     * @param string $notes Notas opcionales
     * @return bool Success
     */
    public function send_feedback($comment_id, $predicted_category, $correct_category, $notes = '') {
        $comment = get_comment($comment_id);
        
        if (!$comment) {
            return false;
        }
        
        $request_data = array(
            'text' => $comment->comment_content,
            'predicted_category' => $predicted_category,
            'correct_category' => $correct_category,
            'notes' => $notes,
            'metadata' => array(
                'comment_id' => $comment_id,
                'platform' => 'wordpress',
                'site_url' => get_site_url()
            )
        );
        
        // Enviar de forma asíncrona (no bloqueante)
        $response = $this->make_request('POST', '/feedback', $request_data, 3, false);
        
        return !is_wp_error($response);
    }
    
    /**
     * Obtener estadísticas desde API
     * 
     * @param int $period_days Días a analizar
     * @return array|null Estadísticas
     */
    public function get_stats($period_days = 30) {
        $endpoint = '/stats?period=' . intval($period_days);
        
        $response = $this->make_request('GET', $endpoint);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body;
    }
    
    /**
     * Obtener información de la cuenta
     * 
     * @return array|null Account info
     */
    public function get_account_info() {
        // Cache por 1 hora
        $cache_key = 'spamguard_account_info';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $response = $this->make_request('GET', '/account');
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $account = json_decode(wp_remote_retrieve_body($response), true);
        
        // Cachear por 1 hora
        set_transient($cache_key, $account, HOUR_IN_SECONDS);
        
        return $account;
    }
    
    /**
     * Obtener uso actual
     * 
     * @return array|null Usage info
     */
    public function get_usage() {
        $response = $this->make_request('GET', '/account/usage');
        
        if (is_wp_error($response)) {
            return null;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Verificar estado de API Key
     * 
     * @return bool API Key válida
     */
    public function verify_api_key() {
        if (empty($this->api_key)) {
            return false;
        }
        
        $response = $this->make_request('GET', '/account', array(), 5);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        return $status_code === 200;
    }
    
    /**
     * Generar nueva API Key
     * 
     * @param string $email Email del usuario
     * @param string $site_url URL del sitio
     * @return array|WP_Error Array con api_key o error
     */
    public function register_and_generate_key($email, $site_url = null) {
        if (empty($site_url)) {
            $site_url = get_site_url();
        }
        
        // Este endpoint NO requiere autenticación (es para registro)
        $response = wp_remote_post($this->api_url . '/register', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'SpamGuard-WordPress/' . SPAMGUARD_VERSION
            ),
            'body' => json_encode(array(
                'email' => $email,
                'site_url' => $site_url,
                'name' => get_bloginfo('name')
            )),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code === 201 && isset($body['api_key'])) {
            // Éxito - guardar API key
            update_option('spamguard_api_key', $body['api_key']);
            $this->api_key = $body['api_key'];
            
            return array(
                'success' => true,
                'api_key' => $body['api_key'],
                'message' => isset($body['message']) ? $body['message'] : __('API Key generated successfully', 'spamguard')
            );
        }
        
        // Error
        $error_message = isset($body['detail']) ? $body['detail'] : __('Failed to generate API key', 'spamguard');
        
        // Si es array (desde FastAPI)
        if (is_array($error_message)) {
            $error_message = isset($error_message['message']) ? $error_message['message'] : $error_message['error'];
        }
        
        return new WP_Error(
            'registration_failed',
            $error_message
        );
    }
    
    /**
     * Hacer request a la API
     * 
     * @param string $method Método HTTP
     * @param string $endpoint Endpoint
     * @param array $data Datos (para POST/PUT)
     * @param int $timeout Timeout en segundos
     * @param bool $blocking Bloqueante o no
     * @return array|WP_Error Response
     */
    private function make_request($method, $endpoint, $data = array(), $timeout = 10, $blocking = true) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API Key not configured', 'spamguard'));
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'SpamGuard-WordPress/' . SPAMGUARD_VERSION
            ),
            'timeout' => $timeout,
            'blocking' => $blocking,
            'sslverify' => true
        );
        
        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        // Si no es bloqueante, retornar inmediatamente
        if (!$blocking) {
            return array('success' => true);
        }
        
        // Verificar errores
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Manejar errores HTTP
        if ($status_code >= 400) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($body['detail']) ? $body['detail'] : 'API Error';
            
            // Si es array
            if (is_array($error_message)) {
                $error_message = isset($error_message['message']) ? $error_message['message'] : 'API Error';
            }
            
            return new WP_Error(
                'api_error',
                $error_message,
                array('status' => $status_code)
            );
        }
        
        return $response;
    }
    
    /**
     * Fallback local si API no disponible
     * 
     * @param array $comment_data Datos del comentario
     * @param string $reason Razón del fallback
     * @return array Resultado del análisis local
     */
    private function local_fallback($comment_data, $reason = 'unknown') {
        // Cargar fallback helper
        if (!class_exists('SpamGuard_Local_Fallback')) {
            require_once SPAMGUARD_PLUGIN_DIR . 'includes/modules/antispam/class-local-fallback.php';
        }
        
        $fallback = SpamGuard_Local_Fallback::get_instance();
        $result = $fallback->analyze($comment_data);
        
        // Añadir metadata
        $result['source'] = 'local_fallback';
        $result['fallback_reason'] = $reason;
        $result['cached'] = false;
        
        // Log
        $this->log_detection($comment_data, $result);
        
        return $result;
    }
    
    /**
     * Generar cache key único
     * 
     * @param array $comment_data Datos del comentario
     * @return string Cache key
     */
    private function generate_cache_key($comment_data) {
        $data = array(
            'content' => isset($comment_data['comment_content']) ? substr($comment_data['comment_content'], 0, 500) : '',
            'email' => isset($comment_data['comment_author_email']) ? $comment_data['comment_author_email'] : '',
            'ip' => isset($comment_data['comment_author_IP']) ? $comment_data['comment_author_IP'] : ''
        );
        
        return 'spamguard_analyze_' . md5(json_encode($data));
    }
    
    /**
     * Log de uso (para estadísticas locales)
     * 
     * @param array $result Resultado del análisis
     */
    private function log_usage($result) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spamguard_usage';
        
        $wpdb->insert($table, array(
            'category' => isset($result['category']) ? $result['category'] : 'unknown',
            'confidence' => isset($result['confidence']) ? $result['confidence'] : 0,
            'risk_level' => isset($result['risk_level']) ? $result['risk_level'] : 'low',
            'processing_time_ms' => isset($result['processing_time_ms']) ? $result['processing_time_ms'] : 0,
            'cached' => isset($result['cached']) ? intval($result['cached']) : 0,
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Log detección en tabla local
     * 
     * @param array $comment_data Datos del comentario
     * @param array $result Resultado
     */
    private function log_detection($comment_data, $result) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spamguard_logs';
        
        $wpdb->insert($table, array(
            'comment_id' => isset($comment_data['comment_ID']) ? $comment_data['comment_ID'] : null,
            'comment_author' => isset($comment_data['comment_author']) ? $comment_data['comment_author'] : '',
            'comment_author_email' => isset($comment_data['comment_author_email']) ? $comment_data['comment_author_email'] : '',
            'comment_content' => isset($comment_data['comment_content']) ? substr($comment_data['comment_content'], 0, 1000) : '',
            'is_spam' => isset($result['is_spam']) ? intval($result['is_spam']) : 0,
            'category' => isset($result['category']) ? $result['category'] : 'ham',
            'confidence' => isset($result['confidence']) ? $result['confidence'] : null,
            'risk_level' => isset($result['risk_level']) ? $result['risk_level'] : 'low',
            'flags' => isset($result['flags']) ? json_encode($result['flags']) : null,
            'request_id' => isset($result['request_id']) ? $result['request_id'] : null,
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Log de errores
     * 
     * @param string $message Mensaje de error
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SpamGuard API v3.0] ' . $message);
        }
    }
}