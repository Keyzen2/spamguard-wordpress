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

    /**
     * Obtener amenaza por ID
     */
    public static function get_threat_by_id($threat_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_threats';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %s",
            $threat_id
        ));
    }

    /**
     * Obtener amenazas por escaneo
     */
    public static function get_threats_by_scan($scan_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_threats';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE scan_id = %s ORDER BY severity DESC, detected_at DESC",
            $scan_id
        ));
    }

    /**
     * Obtener estadísticas por tipo de amenaza
     */
    public static function get_threat_types_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_threats';

        $results = $wpdb->get_results(
            "SELECT threat_type, COUNT(*) as count
             FROM $table
             WHERE status = 'active'
             GROUP BY threat_type
             ORDER BY count DESC"
        );

        $stats = array();
        foreach ($results as $row) {
            $stats[$row->threat_type] = intval($row->count);
        }

        return $stats;
    }

    /**
     * Verificar si hay escaneo en progreso
     */
    public static function has_scan_in_progress() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_scans';

        $running_scan = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status IN ('pending', 'running')"
        );

        return intval($running_scan) > 0;
    }

    /**
     * Obtener escaneo en progreso
     */
    public static function get_scan_in_progress() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_scans';

        return $wpdb->get_row(
            "SELECT * FROM $table WHERE status IN ('pending', 'running') ORDER BY started_at DESC LIMIT 1"
        );
    }

    /**
     * Obtener archivos en cuarentena
     */
    public static function get_quarantined_threats($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_threats';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'quarantined' ORDER BY detected_at DESC LIMIT %d",
            $limit
        ));
    }

    /**
     * Limpiar escaneos antiguos (más de 30 días)
     */
    public static function cleanup_old_scans() {
        global $wpdb;
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        $threats_table = $wpdb->prefix . 'spamguard_threats';

        // Eliminar escaneos antiguos completados
        $deleted_scans = $wpdb->query(
            "DELETE FROM $scans_table WHERE status IN ('completed', 'failed') AND started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Eliminar amenazas ignoradas antiguas
        $deleted_threats = $wpdb->query(
            "DELETE FROM $threats_table WHERE status = 'ignored' AND detected_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        return intval($deleted_scans) + intval($deleted_threats);
    }

    /**
     * Contar archivos escaneados totales
     */
    public static function get_total_files_scanned() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_scans';

        return intval($wpdb->get_var(
            "SELECT SUM(files_scanned) FROM $table WHERE status = 'completed'"
        ));
    }

    /**
     * Obtener tendencia de amenazas (últimos 30 días)
     */
    public static function get_threats_trend() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_threats';

        $results = $wpdb->get_results(
            "SELECT DATE(detected_at) as date, COUNT(*) as count
             FROM $table
             WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(detected_at)
             ORDER BY date ASC"
        );

        $trend = array();
        foreach ($results as $row) {
            $trend[$row->date] = intval($row->count);
        }

        return $trend;
    }

    /**
     * Obtener promedio de tiempo de escaneo
     */
    public static function get_average_scan_time() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_scans';

        $avg_seconds = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at))
             FROM $table
             WHERE status = 'completed' AND completed_at IS NOT NULL"
        );

        return $avg_seconds ? round($avg_seconds / 60, 1) : 0;
    }

    /**
     * Verificar salud del sistema antivirus
     */
    public static function get_system_health() {
        $stats = self::get_antivirus_stats();
        $last_scan = self::get_last_scan();

        $health = array(
            'status' => 'good',
            'score' => 100,
            'issues' => array()
        );

        // Verificar amenazas críticas
        if ($stats['threats_by_severity']['critical'] > 0) {
            $health['status'] = 'critical';
            $health['score'] -= 50;
            $health['issues'][] = sprintf(
                __('%d critical threats detected', 'spamguard'),
                $stats['threats_by_severity']['critical']
            );
        }

        // Verificar amenazas altas
        if ($stats['threats_by_severity']['high'] > 0) {
            if ($health['status'] === 'good') {
                $health['status'] = 'warning';
            }
            $health['score'] -= 20;
            $health['issues'][] = sprintf(
                __('%d high-risk threats detected', 'spamguard'),
                $stats['threats_by_severity']['high']
            );
        }

        // Verificar último escaneo
        if ($last_scan) {
            $days_since_scan = (time() - strtotime($last_scan->started_at)) / DAY_IN_SECONDS;

            if ($days_since_scan > 7) {
                if ($health['status'] === 'good') {
                    $health['status'] = 'warning';
                }
                $health['score'] -= 10;
                $health['issues'][] = __('Last scan was more than 7 days ago', 'spamguard');
            }
        } else {
            $health['status'] = 'warning';
            $health['score'] -= 30;
            $health['issues'][] = __('No scans performed yet', 'spamguard');
        }

        $health['score'] = max(0, $health['score']);

        return $health;
    }
}