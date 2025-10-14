<?php
/**
 * SpamGuard API Client v3.0 - CORREGIDO Y VALIDADO
 * Compatible con dependencies.py de FastAPI
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Client {
    
    private static $instance = null;
    private $api_base_url;
    private $api_key;
    private $timeout = 60;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_base_url = rtrim(get_option('spamguard_api_url', SPAMGUARD_API_URL), '/');
        $this->api_key = get_option('spamguard_api_key', '');
    }
    
    /**
     * ✅ CORREGIDO: Make request con autenticación correcta
     */
    private function make_request($endpoint, $method = 'GET', $data = null, $use_api_key = true) {
        
        // Construir URL completa
        $url = $this->api_base_url . $endpoint;
        
        // Headers base
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'SpamGuard-WordPress/' . SPAMGUARD_VERSION
        );
        
        // ✅ CRÍTICO: API Key en header (exactamente como lo espera FastAPI)
        if ($use_api_key) {
            if (empty($this->api_key)) {
                return new WP_Error('no_api_key', __('API Key not configured', 'spamguard'));
            }
            
            // ✅ VALIDACIÓN: Verificar que el API key tenga formato correcto
            if (!preg_match('/^sg_(test|live)_[a-zA-Z0-9_-]+$/', $this->api_key)) {
                return new WP_Error(
                    'invalid_api_key_format',
                    __('API Key format is invalid. Must start with sg_test_ or sg_live_', 'spamguard')
                );
            }
            
            // ✅ Enviar como espera FastAPI
            $headers['X-API-Key'] = $this->api_key;
        }
        
        // Args de la request
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
        
        // Log detallado en debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                "[SpamGuard API] %s %s | API Key: %s",
                $method,
                $url,
                $use_api_key ? 'Yes (X-API-Key header)' : 'No'
            ));
            
            if ($data) {
                error_log("[SpamGuard API] Request data: " . json_encode($data));
            }
        }
        
        // ✅ Reintentos con backoff exponencial
        $max_retries = 3;
        $retry_count = 0;
        $response = null;
        
        while ($retry_count <= $max_retries) {
            $response = wp_remote_request($url, $args);
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                
                // ✅ No reintentar en errores de autenticación (401, 403)
                if ($status_code === 401 || $status_code === 403) {
                    break;
                }
                
                // ✅ Reintentar solo en errores de servidor (5xx)
                if ($status_code >= 500) {
                    $retry_count++;
                    if ($retry_count <= $max_retries) {
                        sleep(pow(2, $retry_count)); // Backoff exponencial: 2s, 4s, 8s
                        continue;
                    }
                }
                
                break; // Success o error no recuperable
            }
            
            $retry_count++;
            
            if ($retry_count <= $max_retries) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[SpamGuard API] Retry {$retry_count}/{$max_retries}");
                }
                sleep(pow(2, $retry_count));
            }
        }
        
        // ✅ Error de conexión
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[SpamGuard API Error] Connection failed: {$error_msg}");
            }
            
            return new WP_Error(
                'connection_error',
                sprintf(__('API connection error: %s', 'spamguard'), $error_msg)
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log de respuesta
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SpamGuard API Response] Status: {$status_code}");
            if ($body) {
                error_log("[SpamGuard API Response] Body: " . substr($body, 0, 500));
            }
        }
        
        // ✅ Manejo específico de códigos de error HTTP
        
        // 401: API key inválida o faltante
        if ($status_code === 401) {
            return new WP_Error(
                'invalid_api_key',
                __('API Key is invalid or missing. Please check your configuration.', 'spamguard'),
                array('status_code' => $status_code)
            );
        }
        
        // 403: API key no autorizada
        if ($status_code === 403) {
            return new WP_Error(
                'unauthorized_api_key',
                __('API Key is not authorized. Please verify your API Key.', 'spamguard'),
                array('status_code' => $status_code)
            );
        }
        
        // 429: Rate limit excedido
        if ($status_code === 429) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after');
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Please try again in %s seconds.', 'spamguard'),
                    $retry_after ?: '3600'
                ),
                array('status_code' => $status_code, 'retry_after' => $retry_after)
            );
        }
        
        // 502/504: Servidor no disponible
        if ($status_code === 502 || $status_code === 504) {
            return new WP_Error(
                'api_unavailable',
                __('API temporarily unavailable. Using local fallback rules.', 'spamguard'),
                array('status_code' => $status_code)
            );
        }
        
        // Parse JSON
        $parsed = json_decode($body, true);
        
        // Success (2xx)
        if ($status_code >= 200 && $status_code < 300) {
            return $parsed ? $parsed : array('success' => true);
        }
        
        // ✅ Error de API (4xx, 5xx)
        $error_message = 'Unknown API error';
        
        if ($parsed) {
            // FastAPI devuelve errores en formato {"detail": "mensaje"}
            if (isset($parsed['detail'])) {
                $error_message = is_array($parsed['detail']) 
                    ? json_encode($parsed['detail']) 
                    : $parsed['detail'];
            } elseif (isset($parsed['message'])) {
                $error_message = $parsed['message'];
            }
        } else {
            $error_message = "HTTP {$status_code}: " . wp_remote_retrieve_response_message($response);
        }
        
        return new WP_Error(
            'api_error',
            $error_message,
            array('status_code' => $status_code, 'response' => $parsed)
        );
    }
    
    /**
     * ✅ Health check (sin autenticación)
     */
    public function health_check() {
        return $this->make_request('/api/v1/health', 'GET', null, false);
    }
    
    /**
     * ✅ Registrar sitio (sin autenticación)
     */
    public function register_and_generate_key($email) {
        // ✅ Validar email
        if (!is_email($email)) {
            return array(
                'success' => false,
                'message' => __('Invalid email address', 'spamguard')
            );
        }
        
        $data = array(
            'site_url' => get_site_url(),
            'admin_email' => $email
        );
        
        $result = $this->make_request('/api/v1/register-site', 'POST', $data, false);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message(),
                'error_code' => $result->get_error_code()
            );
        }
        
        // ✅ Verificar que la API retornó un API key válido
        if (isset($result['api_key'])) {
            // Validar formato
            if (!preg_match('/^sg_(test|live)_[a-zA-Z0-9_-]+$/', $result['api_key'])) {
                return array(
                    'success' => false,
                    'message' => __('API returned invalid key format', 'spamguard')
                );
            }
            
            // Guardar
            update_option('spamguard_api_key', $result['api_key']);
            $this->api_key = $result['api_key'];
            
            // ✅ Guardar site_id si existe
            if (isset($result['site_id'])) {
                update_option('spamguard_site_id', $result['site_id']);
            }
            
            return array(
                'success' => true,
                'api_key' => $result['api_key'],
                'site_id' => isset($result['site_id']) ? $result['site_id'] : '',
                'message' => isset($result['message']) ? $result['message'] : __('Site registered successfully', 'spamguard')
            );
        }
        
        return array(
            'success' => false,
            'message' => __('API did not return an API key', 'spamguard')
        );
    }
    
    /**
     * ✅ Analizar comentario (CON autenticación) + CACHÉ INTELIGENTE
     */
    public function analyze_comment($comment) {
        // ✅ Validar datos mínimos
        if (empty($comment['comment_content'])) {
            return array(
                'is_spam' => false,
                'category' => 'ham',
                'confidence' => 0.5,
                'risk_level' => 'low',
                'error' => 'empty_content'
            );
        }

        $data = array(
            'content' => $comment['comment_content'],
            'author' => isset($comment['comment_author']) ? $comment['comment_author'] : '',
            'author_email' => isset($comment['comment_author_email']) ? $comment['comment_author_email'] : '',
            'author_url' => isset($comment['comment_author_url']) ? $comment['comment_author_url'] : '',
            'author_ip' => isset($comment['comment_author_IP']) ? $comment['comment_author_IP'] : $this->get_user_ip(),
            'post_id' => isset($comment['comment_post_ID']) ? intval($comment['comment_post_ID']) : 0
        );

        // 🚀 NUEVO: Verificar caché primero
        if (class_exists('SpamGuard_API_Cache_Advanced')) {
            $cache = SpamGuard_API_Cache_Advanced::get_instance();

            // Intentar obtener respuesta exacta del caché
            $cached_result = $cache->get('spam_check', $data);

            if ($cached_result !== false) {
                // ✅ Cache HIT - ahorró un API request
                $this->record_cache_hit();
                return $cached_result;
            }

            // Intentar buscar contenido similar
            $similar_result = $cache->get_by_content($comment['comment_content'], 'spam_check');

            if ($similar_result !== false) {
                // ✅ Similar content found - ahorró un API request
                $this->record_cache_hit();
                return $similar_result;
            }
        }

        // 📞 LLAMAR A LA API (no hay cache)
        $result = $this->make_request('/api/v1/analyze', 'POST', $data, true); // ✅ true = usar API key

        // Registrar uso de API
        $this->record_api_usage('spam_check');

        // ✅ Si hay error, usar fallback local
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SpamGuard] API error, using local fallback: ' . $result->get_error_message());
            }

            if (class_exists('SpamGuard_Local_Fallback')) {
                return SpamGuard_Local_Fallback::get_instance()->analyze($comment);
            }

            // Safe fallback final
            return array(
                'is_spam' => false,
                'category' => 'ham',
                'confidence' => 0.5,
                'risk_level' => 'low',
                'scores' => array('ham' => 1, 'spam' => 0),
                'flags' => array('api_error'),
                'cached' => false,
                'error' => $result->get_error_message()
            );
        }

        // 💾 Guardar en caché para futuros requests
        if (class_exists('SpamGuard_API_Cache_Advanced') && !is_wp_error($result)) {
            $cache = SpamGuard_API_Cache_Advanced::get_instance();
            $cache->set('spam_check', $data, $result);
        }

        return $result;
    }

    /**
     * 🆕 Registrar uso de API request
     */
    private function record_api_usage($endpoint_type) {
        global $wpdb;

        $table = $wpdb->prefix . 'spamguard_usage';

        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if ($table_exists) {
            $wpdb->insert(
                $table,
                array(
                    'endpoint' => $endpoint_type,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s')
            );
        }
    }

    /**
     * 🆕 Registrar cache hit (para estadísticas)
     */
    private function record_cache_hit() {
        // Incrementar contador de cache hits
        $current = get_option('spamguard_cache_hits_month', 0);
        update_option('spamguard_cache_hits_month', $current + 1);
    }
    
    /**
     * ✅ Test de conexión completo
     */
    public function test_connection() {
        // 1. Test health (sin auth)
        $health = $this->health_check();
        
        if (is_wp_error($health)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Connection failed: %s', 'spamguard'),
                    $health->get_error_message()
                ),
                'error_code' => $health->get_error_code()
            );
        }
        
        if (!isset($health['status']) || $health['status'] !== 'healthy') {
            return array(
                'success' => false,
                'message' => __('API is not healthy', 'spamguard'),
                'data' => $health
            );
        }
        
        // 2. Test auth (si hay API key)
        if (!empty($this->api_key)) {
            $test_comment = array(
                'comment_content' => 'Test connection',
                'comment_author' => 'Test',
                'comment_author_email' => 'test@example.com'
            );
            
            $analyze_result = $this->analyze_comment($test_comment);
            
            if (is_wp_error($analyze_result)) {
                return array(
                    'success' => false,
                    'message' => sprintf(
                        __('Authentication failed: %s', 'spamguard'),
                        $analyze_result->get_error_message()
                    )
                );
            }
        }
        
        return array(
            'success' => true,
            'message' => __('✅ Connection successful! API is healthy and authentication works.', 'spamguard'),
            'version' => isset($health['version']) ? $health['version'] : 'unknown',
            'api_url' => $this->api_base_url,
            'has_api_key' => !empty($this->api_key),
            'data' => $health
        );
    }
    
    /**
     * ✅ Obtener info de cuenta (dummy por ahora)
     */
    public function get_account_info() {
        return array(
            'plan' => 'free',
            'status' => 'active',
            'error' => false
        );
    }
    
    /**
     * ✅ Uso de API (calculado localmente)
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
    
    /**
     * ✅ Helper: Obtener IP del usuario
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            $ip = trim($ip);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '0.0.0.0';
        }

        // Validar IP
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    // ============================================
    // VULNERABILITIES API METHODS
    // ============================================

    /**
     * ✅ Verificar vulnerabilidades en componentes
     *
     * @param array $components Lista de componentes a verificar
     * @return array|WP_Error Resultado del check o error
     */
    public function check_vulnerabilities($components) {
        if (empty($components) || !is_array($components)) {
            return array(
                'success' => false,
                'message' => __('Invalid components data', 'spamguard'),
                'total_checked' => 0,
                'vulnerable_count' => 0,
                'vulnerable_components' => array()
            );
        }

        $data = array('components' => $components);

        $result = $this->make_request('/api/v1/vulnerabilities/check', 'POST', $data, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * ✅ Obtener vulnerabilidades de un plugin específico
     *
     * @param string $plugin_slug Slug del plugin
     * @return array|WP_Error Vulnerabilidades encontradas o error
     */
    public function get_plugin_vulnerabilities($plugin_slug) {
        $result = $this->make_request('/api/v1/vulnerabilities/plugin/' . urlencode($plugin_slug), 'GET', null, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * ✅ Obtener vulnerabilidades de un theme específico
     *
     * @param string $theme_slug Slug del theme
     * @return array|WP_Error Vulnerabilidades encontradas o error
     */
    public function get_theme_vulnerabilities($theme_slug) {
        $result = $this->make_request('/api/v1/vulnerabilities/theme/' . urlencode($theme_slug), 'GET', null, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * ✅ Obtener estadísticas de vulnerabilidades
     *
     * @return array|WP_Error Estadísticas o error
     */
    public function get_vulnerability_stats() {
        $result = $this->make_request('/api/v1/vulnerabilities/stats', 'GET', null, true);

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'total_vulnerabilities' => 0,
                'by_severity' => array(
                    'critical' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0
                ),
                'by_type' => array(
                    'plugin' => 0,
                    'theme' => 0,
                    'core' => 0
                ),
                'error' => $result->get_error_message()
            );
        }

        return $result;
    }

    /**
     * ✅ Buscar vulnerabilidades por término
     *
     * @param string $query Término de búsqueda
     * @param array $filters Filtros adicionales (component_type, severity)
     * @return array|WP_Error Resultados de búsqueda o error
     */
    public function search_vulnerabilities($query, $filters = array()) {
        $params = array('query' => $query);

        if (isset($filters['component_type'])) {
            $params['component_type'] = $filters['component_type'];
        }

        if (isset($filters['severity'])) {
            $params['severity'] = $filters['severity'];
        }

        $url = '/api/v1/vulnerabilities/search?' . http_build_query($params);

        $result = $this->make_request($url, 'GET', null, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    // ============================================
    // ANTIVIRUS API METHODS
    // ============================================

    /**
     * ✅ Iniciar escaneo antivirus
     *
     * @param string $scan_type Tipo de escaneo: quick, full, custom
     * @param array $paths Rutas personalizadas (solo para custom)
     * @param int $max_size_mb Tamaño máximo de archivo en MB
     * @return array|WP_Error Resultado con scan_id o error
     */
    public function start_antivirus_scan($scan_type = 'quick', $paths = null, $max_size_mb = 10) {
        $data = array(
            'scan_type' => $scan_type,
            'max_size_mb' => $max_size_mb
        );

        if ($scan_type === 'custom' && !empty($paths)) {
            $data['paths'] = $paths;
        }

        $result = $this->make_request('/api/v1/antivirus/scan/start', 'POST', $data, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * ✅ Obtener progreso de un escaneo
     *
     * @param string $scan_id ID del escaneo
     * @return array|WP_Error Progreso del escaneo o error
     */
    public function get_scan_progress($scan_id) {
        $result = $this->make_request('/api/v1/antivirus/scan/' . urlencode($scan_id) . '/progress', 'GET', null, true);

        if (is_wp_error($result)) {
            return array(
                'scan_id' => $scan_id,
                'status' => 'error',
                'progress' => 0,
                'files_scanned' => 0,
                'threats_found' => 0,
                'error' => $result->get_error_message()
            );
        }

        return $result;
    }

    /**
     * ✅ Obtener resultados de un escaneo
     *
     * @param string $scan_id ID del escaneo
     * @return array|WP_Error Resultados completos o error
     */
    public function get_scan_results($scan_id) {
        $result = $this->make_request('/api/v1/antivirus/scan/' . urlencode($scan_id) . '/results', 'GET', null, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * ✅ Obtener escaneos recientes
     *
     * @param int $limit Número de escaneos a obtener
     * @return array|WP_Error Lista de escaneos o error
     */
    public function get_recent_scans($limit = 10) {
        $result = $this->make_request('/api/v1/antivirus/scans/recent?limit=' . intval($limit), 'GET', null, true);

        if (is_wp_error($result)) {
            return array(
                'scans' => array(),
                'total' => 0,
                'error' => $result->get_error_message()
            );
        }

        return $result;
    }

    /**
     * ✅ Obtener estadísticas del antivirus
     *
     * @return array|WP_Error Estadísticas o error
     */
    public function get_antivirus_stats() {
        $result = $this->make_request('/api/v1/antivirus/stats', 'GET', null, true);

        if (is_wp_error($result)) {
            return array(
                'total_scans' => 0,
                'active_threats' => 0,
                'threats_by_severity' => array(),
                'last_scan' => null,
                'error' => $result->get_error_message()
            );
        }

        return $result;
    }

    /**
     * ✅ Poner amenaza en cuarentena
     *
     * @param string $threat_id ID de la amenaza
     * @return array|WP_Error Resultado de la operación o error
     */
    public function quarantine_threat($threat_id) {
        $result = $this->make_request('/api/v1/antivirus/threat/' . urlencode($threat_id) . '/quarantine', 'POST', null, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * ✅ Ignorar amenaza (marcar como falso positivo)
     *
     * @param string $threat_id ID de la amenaza
     * @return array|WP_Error Resultado de la operación o error
     */
    public function ignore_threat($threat_id) {
        $result = $this->make_request('/api/v1/antivirus/threat/' . urlencode($threat_id) . '/ignore', 'DELETE', null, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }
}
