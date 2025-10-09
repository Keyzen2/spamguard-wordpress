<?php
/**
 * Procesador de resultados del antivirus
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Antivirus_Results {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener escaneos recientes
     */
    public static function get_recent_scans($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_scans';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY started_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Obtener último escaneo
     */
    public static function get_last_scan() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_scans';
        
        return $wpdb->get_row(
            "SELECT * FROM $table ORDER BY started_at DESC LIMIT 1"
        );
    }
    
    /**
     * Obtener amenazas activas
     */
    public static function get_active_threats($limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_threats';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'active' ORDER BY severity DESC, detected_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Obtener estadísticas del antivirus
     */
    public static function get_antivirus_stats() {
        global $wpdb;
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        
        // Total de escaneos
        $total_scans = $wpdb->get_var("SELECT COUNT(*) FROM $scans_table");
        
        // Amenazas activas
        $active_threats = $wpdb->get_var("SELECT COUNT(*) FROM $threats_table WHERE status = 'active'");
        
        // Amenazas por severidad
        $threats_by_severity = $wpdb->get_results(
            "SELECT severity, COUNT(*) as count FROM $threats_table WHERE status = 'active' GROUP BY severity"
        );
        
        $severity_counts = array(
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        );
        
        foreach ($threats_by_severity as $row) {
            $severity_counts[$row->severity] = intval($row->count);
        }
        
        // Último escaneo
        $last_scan = self::get_last_scan();
        
        return array(
            'total_scans' => intval($total_scans),
            'active_threats' => intval($active_threats),
            'threats_by_severity' => $severity_counts,
            'last_scan' => $last_scan ? array(
                'date' => $last_scan->started_at,
                'threats_found' => intval($last_scan->threats_found),
                'status' => $last_scan->status
            ) : null
        );
    }
    
    /**
     * Formatear severidad para mostrar
     */
    public static function format_severity($severity) {
        $labels = array(
            'critical' => array(
                'label' => __('Crítico', 'spamguard'),
                'color' => '#d63638',
                'icon' => '🔴'
            ),
            'high' => array(
                'label' => __('Alto', 'spamguard'),
                'color' => '#f56e28',
                'icon' => '🟠'
            ),
            'medium' => array(
                'label' => __('Medio', 'spamguard'),
                'color' => '#dba617',
                'icon' => '🟡'
            ),
            'low' => array(
                'label' => __('Bajo', 'spamguard'),
                'color' => '#00a32a',
                'icon' => '🟢'
            )
        );
        
        return isset($labels[$severity]) ? $labels[$severity] : $labels['low'];
    }
}