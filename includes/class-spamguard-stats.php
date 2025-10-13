<?php
/**
 * SpamGuard Stats Class
 * Maneja estadísticas y logs del plugin
 *
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Stats {

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
     * Constructor privado (singleton)
     */
    private function __construct() {
        // Constructor vacío
    }

    /**
     * Obtener logs recientes
     *
     * @param int $limit Número de logs a obtener
     * @return array Lista de logs
     */
    public static function get_recent_logs($limit = 100) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
            $limit
        ));

        return $logs ? $logs : array();
    }

    /**
     * Obtener estadísticas generales
     *
     * @return array Estadísticas
     */
    public static function get_general_stats() {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'spamguard_logs';

        $total_analyzed = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
        $total_spam = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE is_spam = 1");
        $total_ham = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE is_spam = 0");

        // Stats por mes actual
        $this_month_analyzed = $wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table
             WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
             AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        );

        $this_month_spam = $wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table
             WHERE is_spam = 1
             AND MONTH(created_at) = MONTH(CURRENT_DATE())
             AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        );

        return array(
            'total_analyzed' => intval($total_analyzed),
            'total_spam' => intval($total_spam),
            'total_ham' => intval($total_ham),
            'this_month_analyzed' => intval($this_month_analyzed),
            'this_month_spam' => intval($this_month_spam),
            'spam_percentage' => $total_analyzed > 0 ? round(($total_spam / $total_analyzed) * 100, 1) : 0
        );
    }

    /**
     * Obtener logs filtrados
     *
     * @param array $filters Filtros a aplicar
     * @return array Lista de logs filtrados
     */
    public static function get_filtered_logs($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $where = array();

        if (isset($filters['is_spam'])) {
            $where[] = $wpdb->prepare('is_spam = %d', $filters['is_spam'] ? 1 : 0);
        }

        if (isset($filters['comment_author'])) {
            $where[] = $wpdb->prepare('comment_author LIKE %s', '%' . $wpdb->esc_like($filters['comment_author']) . '%');
        }

        if (isset($filters['date_from'])) {
            $where[] = $wpdb->prepare('created_at >= %s', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $where[] = $wpdb->prepare('created_at <= %s', $filters['date_to']);
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 100;

        $query = "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT $limit";

        $logs = $wpdb->get_results($query);

        return $logs ? $logs : array();
    }

    /**
     * Obtener confianza promedio
     *
     * @return float Confianza promedio
     */
    public static function get_average_confidence() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $avg = $wpdb->get_var("SELECT AVG(confidence) FROM $table");

        return $avg ? floatval($avg) : 0;
    }

    /**
     * Obtener tendencia de spam (últimos 30 días)
     *
     * @return array Datos de tendencia por día
     */
    public static function get_spam_trend() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $results = $wpdb->get_results(
            "SELECT
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) as spam_count,
                SUM(CASE WHEN is_spam = 0 THEN 1 ELSE 0 END) as ham_count
             FROM $table
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );

        $trend = array();
        foreach ($results as $row) {
            $trend[$row->date] = array(
                'total' => intval($row->total),
                'spam' => intval($row->spam_count),
                'ham' => intval($row->ham_count)
            );
        }

        return $trend;
    }

    /**
     * Limpiar logs antiguos (más de 90 días)
     *
     * @return int Número de logs eliminados
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $deleted = $wpdb->query(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        return intval($deleted);
    }

    /**
     * Obtener log por ID
     *
     * @param int $log_id ID del log
     * @return object|null Log encontrado o null
     */
    public static function get_log_by_id($log_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $log_id
        ));
    }

    /**
     * Obtener logs por comment_id
     *
     * @param int $comment_id ID del comentario
     * @return array Lista de logs
     */
    public static function get_logs_by_comment($comment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE comment_id = %d ORDER BY created_at DESC",
            $comment_id
        ));

        return $logs ? $logs : array();
    }
}
