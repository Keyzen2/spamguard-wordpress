<?php
/**
 * Clase para manejar comunicación con la API de SpamGuard
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API {
    
    /**
     * ⚠️ URL BASE DE LA API - ACTUALIZADA
     */
    private $api_base_url = 'https://spamguard.up.railway.app';
    
    /**
     * API Key del sitio
     */
    private $api_key;
    
    /**
     * Timeout para requests
     */
    private $timeout = 15;
    
    public function __construct() {
        $this->api_key = SpamGuard_Core::get_option('api_key');
    }
    
    /**
     * Registrar un nuevo sitio y obtener API key
     */
    public function register_site($site_url, $admin_email) {
        $endpoint = '/api/v1/register-site';
        
        $response = $this->make_request($endpoint, array(
            'site_url' => $site_url,
            'admin_email' => $admin_email
        ), 'POST', false); // false = no usar API key para este endpoint
        
        return $response;
    }
    
    /**
     * Verificar si un sitio ya está registrado
     */
    public function check_site($site_url) {
        $endpoint = '/api/v1/check-site';
        
        $response = $this->make_request($endpoint . '?site_url=' . urlencode($site_url), null, 'GET', false);
        
        return $response;
    }
    
    /**
     * Analizar un comentario
     */
    public function analyze_comment($comment_data) {
        $endpoint = '/api/v1/analyze';
        
        $data = array(
            'content' => $comment_data['content'],
            'author' => $comment_data['author'],
            'author_email' => $comment_data['author_email'],
            'author_url' => isset($comment_data['author_url']) ? $comment_data['author_url'] : '',
            'author_ip' => $comment_data['author_ip'],
            'post_id' => $comment_data['post_id'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
        );
        
        $response = $this->make_request($endpoint, $data, 'POST');
        
        return $response;
    }
    
    /**
     * Enviar feedback sobre una clasificación
     */
    public function send_feedback($comment_id, $is_spam) {
        $endpoint = '/api/v1/feedback';
        
        $data = array(
            'comment_id' => $comment_id,
            'is_spam' => $is_spam
        );
        
        $response = $this->make_request($endpoint, $data, 'POST');
        
        return $response;
    }
    
    /**
     * Obtener estadísticas del sitio
     */
    public function get_statistics() {
        $endpoint = '/api/v1/stats';
        
        $response = $this->make_request($endpoint, null, 'GET');
        
        return $response;
    }
    
    /**
     * Health check de la API
     */
    public function health_check() {
        $endpoint = '/health';
        
        $response = $this->make_request($endpoint, null, 'GET', false);
        
        return $response;
    }
    
    /**
     * Iniciar escaneo de malware
     */
    public function start_malware_scan($scan_type = 'quick', $max_size_mb = 10) {
        $endpoint = '/api/v1/antivirus/scan/start';
        
        $data = array(
            'scan_type' => $scan_type,
            'max_size_mb' => $max_size_mb
        );
        
        $response = $this->make_request($endpoint, $data, 'POST');
        
        return $response;
    }
    
    /**
     * Obtener progreso de escaneo
     */
    public function get_scan_progress($scan_id) {
        $endpoint = '/api/v1/antivirus/scan/' . $scan_id . '/progress';
        
        $response = $this->make_request($endpoint, null, 'GET');
        
        return $response;
    }
    
    /**
     * Obtener resultados de escaneo
     */
    public function get_scan_results($scan_id) {
        $endpoint = '/api/v1/antivirus/scan/' . $scan_id . '/results';
        
        $response = $this->make_request($endpoint, null, 'GET');
        
        return $response;
    }
    
    /**
     * Hacer request a la API
     * 
     * @param string $endpoint Endpoint (ej: /api/v1/analyze)
     * @param array|null $data Datos a enviar (para POST)
     * @param string $method Método HTTP (GET, POST, etc)
     * @param bool $use_api_key Si debe incluir API key en headers
     * @return array Respuesta parseada o array con error
     */
    private function make_request($endpoint, $data = null, $method = 'GET', $use_api_key = true) {
        
        // Construir URL completa
        $url = $this->api_base_url . $endpoint;
        
        // Preparar headers
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'SpamGuard-WordPress/' . SPAMGUARD_VERSION
        );
        
        // Agregar API key si se requiere
        if ($use_api_key) {
            if (!$this->api_key) {
                return array(
                    'error' => true,
                    'message' => 'API Key no configurada'
                );
            }
            $headers['X-API-Key'] = $this->api_key;
        }
        
        // Preparar argumentos de wp_remote_request
        $args = array(
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $headers,
            'sslverify' => true // ✅ Importante para producción
        );
        
        // Agregar body si es POST
        if ($data && $method === 'POST') {
            $args['body'] = json_encode($data);
        }
        
        // Hacer request
        $response = wp_remote_request($url, $args);
        
        // Manejar errores de conexión
        if (is_wp_error($response)) {
            return array(
                'error' => true,
                'message' => 'Error de conexión: ' . $response->get_error_message(),
                'url' => $url // Para debugging
            );
        }
        
        // Obtener código de respuesta
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Obtener body
        $body = wp_remote_retrieve_body($response);
        $parsed = json_decode($body, true);
        
        // Manejar respuesta
        if ($status_code >= 200 && $status_code < 300) {
            // Success
            return $parsed ? $parsed : array('success' => true);
        } else {
            // Error
            return array(
                'error' => true,
                'message' => isset($parsed['detail']) ? $parsed['detail'] : 'Error en la API',
                'status_code' => $status_code,
                'response' => $parsed
            );
        }
    }
    
    /**
     * Verificar conexión con la API
     */
    public function test_connection() {
        $health = $this->health_check();
        
        if (isset($health['error'])) {
            return array(
                'success' => false,
                'message' => $health['message']
            );
        }
        
        if (isset($health['status']) && $health['status'] === 'healthy') {
            return array(
                'success' => true,
                'message' => 'Conexión exitosa con la API',
                'version' => isset($health['version']) ? $health['version'] : 'unknown'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'API no disponible'
        );
    }
}
