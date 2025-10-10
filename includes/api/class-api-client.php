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
    private $api_base_url;
    private $api_key;
    private $timeout = 30; // ✅ AUMENTADO de 15 a 30 segundos
    
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
        // ✅ Obtener URL base (sin /api/v1 al final)
        $this->api_base_url = get_option('spamguard_api_url', SPAMGUARD_API_URL);
        $this->api_key = get_option('spamguard_api_key', '');
        
        // ✅ Limpiar URL si tiene /api/v1 al final
        $this->api_base_url = rtrim($this->api_base_url, '/');
        if (substr($this->api_base_url, -7) === '/api/v1') {
            $this->api_base_url = substr($this->api_base_url, 0, -7);
            update_option('spamguard_api_url', $this->api_base_url);
        }
    }
    
    /**
     * ✅ MEJORADO: Hacer request a la API con mejor manejo de errores
     */
    private function make_request($endpoint, $method = 'GET', $data = null, $use_api_key = true) {
        
        // Construir URL completa
        $url = rtrim($this->api_base_url, '/') . $endpoint;
        
        // Headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'SpamGuard-WordPress/' . SPAMGUARD_VERSION
        );
        
        // ✅ CRÍTICO: Usar X-API-Key (NO Authorization: Bearer)
        if ($use_api_key) {
            if (empty($this->api_key)) {
                return new WP_Error('no_api_key', __('API Key not configured', 'spamguard'));
            }
            $headers['X-API-Key'] = $this->api_key;
        }
        
        // Argumentos de wp_remote_request
        $args = array(
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $headers,
            'sslverify' => true,
            'httpversion' => '1.1', // ✅ NUEVO: Forzar HTTP/1.1
            'blocking' => true // ✅ NUEVO: Asegurar que espere respuesta
        );
        
        // Body para POST
        if ($data && $method === 'POST') {
            $args['body'] = json_encode($data);
        }
        
        // Log de debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SpamGuard API Request: {$method} {$url}");
            if ($data) {
                error_log("SpamGuard API Data: " . json_encode($data));
            }
        }
        
        // ✅ NUEVO: Reintentar en caso de fallo
        $max_retries = 2;
        $retry_count = 0;
        $last_error = null;
        
        while ($retry_count <= $max_retries) {
            $response = wp_remote_request($url, $args);
            
            // Si no es error de conexión, salir del loop
            if (!is_wp_error($response)) {
                break;
            }
            
            $last_error = $response;
            $retry_count++;
            
            if ($retry_count <= $max_retries) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("SpamGuard API: Retrying ({$retry_count}/{$max_retries})...");
                }
                sleep(1); // Esperar 1 segundo antes de reintentar
            }
        }
        
        // Si todos los reintentos fallaron
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("SpamGuard API Error: {$error_msg}");
            }
            
            return new WP_Error(
                'connection_error',
                sprintf(__('Connection error: %s', 'spamguard'), $error_msg),
                array('url' => $url)
            );
        }
        
        // Obtener código de respuesta
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $parsed = json_decode($body, true);
        
        // Log de debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SpamGuard API Response: {$status_code}");
            error_log("SpamGuard API Body: " . substr($body, 0, 500));
        }
        
        // ✅ NUEVO: Manejo específico de código 502
        if ($status_code === 502) {
            return new WP_Error(
                'api_unavailable',
                __('API temporarily unavailable (502). Please try again in a moment.', 'spamguard'),
                array('status_code' => 502)
            );
        }
        
        // ✅ NUEVO: Manejo de código 504 (Gateway Timeout)
        if ($status_code === 504) {
            return new WP_Error(
                'api_timeout',
                __('API request timed out (504). The server is taking too long to respond.', 'spamguard'),
                array('status_code' => 504)
            );
        }
        
        // Manejar respuestas
        if ($status_code >= 200 && $status_code < 300) {
            return $parsed ? $parsed : array('success' => true);
        } else {
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
     * ✅ Health check
     */
    public function health_check() {
        return $this->make_request('/api/v1/health', 'GET', null, false);
    }
    
    /**
     * ✅ Registrar sitio y generar API key
     */
    public function register_and_generate_key($email) {
        $data = array(
            'site_url' => get_site_url(),
            'admin_email' => $email
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SpamGuard: Registrando sitio...');
            error_log('SpamGuard: URL base: ' . $this->api_base_url);
            error_log('SpamGuard: Endpoint: /api/v1/register-site');
        }
        
        $result = $this->make_request('/api/v1/register-site', 'POST', $data, false);
        
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SpamGuard: ❌ Error en registro: ' . $result->get_error_message());
            }
            
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        if (isset($result['api_key'])) {
            // Guardar API key automáticamente
            update_option('spamguard_api_key', $result['api_key']);
            $this->api_key = $result['api_key'];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SpamGuard: ✅ Sitio registrado exitosamente');
                error_log('SpamGuard: API Key: ' . substr($result['api_key'], 0, 20) . '...');
            }
            
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
     * ✅ Analizar comentario (Anti-Spam)
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
        
        // Si hay error, usar fallback local
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SpamGuard: API error, using local fallback - ' . $result->get_error_message());
            }
            
            // Usar análisis local como fallback
            if (class_exists('SpamGuard_Local_Fallback')) {
                return SpamGuard_Local_Fallback::get_instance()->analyze($comment);
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
     * ✅ Enviar feedback
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
     * ✅ Obtener estadísticas
     */
    public function get_stats($period_days = 30) {
        return $this->make_request('/api/v1/stats?period=' . intval($period_days), 'GET');
    }
    
    /**
     * ✅ Obtener información de cuenta
     */
    public function get_account_info() {
        // Retornar datos dummy por ahora
        return array(
            'plan' => 'free',
            'status' => 'active',
            'error' => false
        );
    }
    
    public function get_usage() {
        // Retornar datos dummy por ahora
        return array(
            'requests_this_month' => 0,
            'requests_limit' => 1000,
            'percentage' => 0,
            'error' => false
        );
    }
    
    /**
     * ============================================
     * HELPERS
     * ============================================
     */
    
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
