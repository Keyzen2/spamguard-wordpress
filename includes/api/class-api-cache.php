<?php
/**
 * SpamGuard API Cache
 * 
 * Sistema de caché local para API responses
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Cache {
    
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
        // Constructor privado
    }
    
    /**
     * Obtener valor desde caché
     * 
     * @param string $key Cache key
     * @return mixed|false Valor o false si no existe
     */
    public function get($key) {
        return get_transient($key);
    }
    
    /**
     * Guardar valor en caché
     * 
     * @param string $key Cache key
     * @param mixed $value Valor
     * @param int $expiration Expiración en segundos (default: 300 = 5 min)
     * @return bool Success
     */
    public function set($key, $value, $expiration = 300) {
        return set_transient($key, $value, $expiration);
    }
    
    /**
     * Eliminar valor del caché
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key) {
        return delete_transient($key);
    }
    
    /**
     * Limpiar todo el caché de SpamGuard
     * 
     * @return int Número de entradas eliminadas
     */
    public function flush() {
        global $wpdb;
        
        // Eliminar transients de SpamGuard
        $count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_spamguard_') . '%'
            )
        );
        
        // También eliminar timeout transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_timeout_spamguard_') . '%'
            )
        );
        
        return $count;
    }
    
    /**
     * Limpiar caché antiguo (más de 1 día)
     * 
     * @return int Número de entradas eliminadas
     */
    public function clean_old() {
        global $wpdb;
        
        // Buscar transients timeout expirados
        $time = time();
        
        $count = $wpdb->query(
            $wpdb->prepare(
                "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
                WHERE a.option_name LIKE %s
                AND a.option_name NOT LIKE %s
                AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
                AND b.option_value < %d",
                $wpdb->esc_like('_transient_spamguard_') . '%',
                $wpdb->esc_like('_transient_timeout_') . '%',
                $time
            )
        );
        
        return $count;
    }
    
    /**
     * Obtener estadísticas del caché
     * 
     * @return array Estadísticas
     */
    public function get_stats() {
        global $wpdb;
        
        // Contar transients de SpamGuard
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_spamguard_') . '%'
            )
        );
        
        // Calcular tamaño aproximado
        $size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_spamguard_') . '%'
            )
        );
        
        return array(
            'total_entries' => intval($total),
            'total_size_bytes' => intval($size),
            'total_size_kb' => round($size / 1024, 2)
        );
    }
}