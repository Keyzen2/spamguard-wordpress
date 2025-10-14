<?php
/**
 * SpamGuard Advanced API Cache
 * Sistema inteligente de caché para reducir llamadas API duplicadas
 *
 * @package SpamGuard
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Cache_Advanced {

    private static $instance = null;

    /**
     * Tabla de caché
     */
    private $cache_table;

    /**
     * Tiempo de expiración por defecto (1 hora)
     */
    private $default_expiration = 3600;

    /**
     * Opciones de caché
     */
    private $cache_enabled = true;
    private $cache_duration = 3600;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->cache_table = $wpdb->prefix . 'spamguard_api_cache';

        // Cargar opciones
        $this->cache_enabled = get_option('spamguard_cache_enabled', true);
        $this->cache_duration = get_option('spamguard_cache_duration', 3600);

        // Limpiar caché expirado cada 6 horas
        add_action('spamguard_cleanup_cache_cron', array($this, 'cleanup_expired'));

        if (!wp_next_scheduled('spamguard_cleanup_cache_cron')) {
            wp_schedule_event(time(), 'twicedaily', 'spamguard_cleanup_cache_cron');
        }
    }

    /**
     * Crear tabla de caché en la base de datos
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'spamguard_api_cache';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cache_key varchar(64) NOT NULL,
            cache_type varchar(50) NOT NULL,
            content_hash varchar(64) NOT NULL,
            request_params text NOT NULL,
            response_data longtext NOT NULL,
            hits int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key (cache_key),
            KEY cache_type (cache_type),
            KEY content_hash (content_hash),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Generar cache key único basado en contenido
     *
     * @param string $type Tipo de request (spam_check, vulnerability_check, etc.)
     * @param array $params Parámetros de la request
     * @return string Hash único
     */
    private function generate_cache_key($type, $params) {
        // Normalizar parámetros (ordenar para consistency)
        ksort($params);

        // Generar hash único
        $content = json_encode(array('type' => $type, 'params' => $params));
        $hash = hash('sha256', $content);

        return $hash;
    }

    /**
     * Generar hash del contenido para detección de duplicados
     *
     * @param string $content Contenido a analizar
     * @return string Hash del contenido
     */
    private function generate_content_hash($content) {
        // Normalizar contenido (lowercase, trim, remover espacios extras)
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $content)));

        return hash('sha256', $normalized);
    }

    /**
     * Obtener respuesta cacheada si existe
     *
     * @param string $type Tipo de request
     * @param array $params Parámetros de la request
     * @return array|false Respuesta cacheada o false si no existe
     */
    public function get($type, $params) {
        if (!$this->cache_enabled) {
            return false;
        }

        global $wpdb;

        $cache_key = $this->generate_cache_key($type, $params);

        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->cache_table}
             WHERE cache_key = %s
             AND expires_at > NOW()",
            $cache_key
        ));

        if ($cached) {
            // Incrementar contador de hits
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->cache_table} SET hits = hits + 1 WHERE id = %d",
                $cached->id
            ));

            // Log en debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[SpamGuard Cache] HIT - Type: {$type}, Key: {$cache_key}, Hits: " . ($cached->hits + 1));
            }

            // Parsear respuesta
            $response = json_decode($cached->response_data, true);

            // Marcar como cacheado
            if (is_array($response)) {
                $response['cached'] = true;
                $response['cache_hits'] = intval($cached->hits) + 1;
            }

            return $response;
        }

        // Log MISS en debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SpamGuard Cache] MISS - Type: {$type}, Key: {$cache_key}");
        }

        return false;
    }

    /**
     * Buscar en caché por hash de contenido (similar spam/malware)
     *
     * @param string $content Contenido a buscar
     * @param string $type Tipo de búsqueda
     * @return array|false Respuesta cacheada o false
     */
    public function get_by_content($content, $type = 'spam_check') {
        if (!$this->cache_enabled) {
            return false;
        }

        global $wpdb;

        $content_hash = $this->generate_content_hash($content);

        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->cache_table}
             WHERE content_hash = %s
             AND cache_type = %s
             AND expires_at > NOW()
             ORDER BY created_at DESC
             LIMIT 1",
            $content_hash,
            $type
        ));

        if ($cached) {
            // Incrementar hits
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->cache_table} SET hits = hits + 1 WHERE id = %d",
                $cached->id
            ));

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[SpamGuard Cache] CONTENT HIT - Type: {$type}, Hash: {$content_hash}");
            }

            $response = json_decode($cached->response_data, true);

            if (is_array($response)) {
                $response['cached'] = true;
                $response['cache_hits'] = intval($cached->hits) + 1;
                $response['cache_reason'] = 'content_similarity';
            }

            return $response;
        }

        return false;
    }

    /**
     * Guardar respuesta en caché
     *
     * @param string $type Tipo de request
     * @param array $params Parámetros de la request
     * @param array $response Respuesta de la API
     * @param int $duration Duración del caché en segundos (opcional)
     * @return bool Success
     */
    public function set($type, $params, $response, $duration = null) {
        if (!$this->cache_enabled) {
            return false;
        }

        global $wpdb;

        if ($duration === null) {
            $duration = $this->cache_duration;
        }

        $cache_key = $this->generate_cache_key($type, $params);

        // Generar content hash si hay contenido
        $content_hash = '';
        if (isset($params['content'])) {
            $content_hash = $this->generate_content_hash($params['content']);
        }

        // Eliminar marcadores de caché de respuestas previas
        if (isset($response['cached'])) {
            unset($response['cached']);
        }
        if (isset($response['cache_hits'])) {
            unset($response['cache_hits']);
        }
        if (isset($response['cache_reason'])) {
            unset($response['cache_reason']);
        }

        $data = array(
            'cache_key' => $cache_key,
            'cache_type' => $type,
            'content_hash' => $content_hash,
            'request_params' => json_encode($params),
            'response_data' => json_encode($response),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $duration),
            'hits' => 0
        );

        // Insert or update
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->cache_table} WHERE cache_key = %s",
            $cache_key
        ));

        if ($existing) {
            $result = $wpdb->update(
                $this->cache_table,
                $data,
                array('cache_key' => $cache_key),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d'),
                array('%s')
            );
        } else {
            $result = $wpdb->insert(
                $this->cache_table,
                $data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SpamGuard Cache] SET - Type: {$type}, Key: {$cache_key}, Duration: {$duration}s");
        }

        return $result !== false;
    }

    /**
     * Invalidar caché específico
     *
     * @param string $type Tipo de request
     * @param array $params Parámetros de la request
     * @return bool Success
     */
    public function invalidate($type, $params) {
        global $wpdb;

        $cache_key = $this->generate_cache_key($type, $params);

        $result = $wpdb->delete(
            $this->cache_table,
            array('cache_key' => $cache_key),
            array('%s')
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SpamGuard Cache] INVALIDATE - Type: {$type}, Key: {$cache_key}");
        }

        return $result !== false;
    }

    /**
     * Limpiar todo el caché de un tipo específico
     *
     * @param string $type Tipo de caché a limpiar (opcional, si no se provee limpia todo)
     * @return int Número de entradas eliminadas
     */
    public function flush($type = null) {
        global $wpdb;

        if ($type) {
            $deleted = $wpdb->delete(
                $this->cache_table,
                array('cache_type' => $type),
                array('%s')
            );
        } else {
            $deleted = $wpdb->query("TRUNCATE TABLE {$this->cache_table}");
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $type_str = $type ? "Type: {$type}" : "ALL";
            error_log("[SpamGuard Cache] FLUSH - {$type_str}, Deleted: {$deleted}");
        }

        return intval($deleted);
    }

    /**
     * Limpiar caché expirado
     *
     * @return int Número de entradas eliminadas
     */
    public function cleanup_expired() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$this->cache_table} WHERE expires_at < NOW()"
        );

        if (defined('WP_DEBUG') && WP_DEBUG && $deleted > 0) {
            error_log("[SpamGuard Cache] CLEANUP - Deleted {$deleted} expired entries");
        }

        return intval($deleted);
    }

    /**
     * Obtener estadísticas de caché
     *
     * @return array Estadísticas
     */
    public function get_stats() {
        global $wpdb;

        $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$this->cache_table}");

        $expired_entries = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->cache_table} WHERE expires_at < NOW()"
        );

        $total_hits = $wpdb->get_var("SELECT SUM(hits) FROM {$this->cache_table}");

        $by_type = $wpdb->get_results(
            "SELECT cache_type, COUNT(*) as count, SUM(hits) as hits
             FROM {$this->cache_table}
             GROUP BY cache_type",
            ARRAY_A
        );

        $top_cached = $wpdb->get_results(
            "SELECT cache_type, hits, created_at, expires_at
             FROM {$this->cache_table}
             ORDER BY hits DESC
             LIMIT 10",
            ARRAY_A
        );

        return array(
            'enabled' => $this->cache_enabled,
            'total_entries' => intval($total_entries),
            'expired_entries' => intval($expired_entries),
            'total_hits' => intval($total_hits),
            'by_type' => $by_type,
            'top_cached' => $top_cached,
            'cache_duration' => $this->cache_duration,
            'hit_rate' => $total_entries > 0 ? round(($total_hits / $total_entries) * 100, 2) : 0
        );
    }

    /**
     * Habilitar/Deshabilitar caché
     *
     * @param bool $enabled Estado
     */
    public function set_enabled($enabled) {
        $this->cache_enabled = (bool) $enabled;
        update_option('spamguard_cache_enabled', $this->cache_enabled);
    }

    /**
     * Establecer duración del caché
     *
     * @param int $duration Duración en segundos
     */
    public function set_duration($duration) {
        $this->cache_duration = max(300, intval($duration)); // Mínimo 5 minutos
        update_option('spamguard_cache_duration', $this->cache_duration);
    }
}
