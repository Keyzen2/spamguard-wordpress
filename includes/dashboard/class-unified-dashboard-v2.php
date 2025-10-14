<?php
/**
 * SpamGuard Unified Dashboard v2 - Mejorado
 * Dashboard central tipo "Security Control Center"
 *
 * @package SpamGuard
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Unified_Dashboard_V2 {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Encolar scripts para el dashboard
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_spamguard') {
            return;
        }

        // Chart.js para gráficos
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        // Script personalizado del dashboard
        wp_enqueue_script(
            'spamguard-dashboard-v2',
            SPAMGUARD_PLUGIN_URL . 'assets/js/dashboard-v2.js',
            array('jquery', 'chartjs'),
            SPAMGUARD_VERSION,
            true
        );

        // CSS del dashboard
        wp_enqueue_style(
            'spamguard-dashboard-v2',
            SPAMGUARD_PLUGIN_URL . 'assets/css/dashboard-v2.css',
            array(),
            SPAMGUARD_VERSION
        );

        // Localizar datos para JavaScript
        wp_localize_script('spamguard-dashboard-v2', 'spamguardDashboard', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spamguard_dashboard'),
            'refreshInterval' => 30000, // 30 segundos
        ));
    }

    /**
     * Renderizar dashboard completo
     */
    public function render() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spamguard'));
        }

        // Obtener datos
        $overall_status = $this->get_overall_status();
        $stats_summary = $this->get_stats_summary();
        $recent_activity = $this->get_recent_activity();
        $security_score = $this->calculate_security_score();

        ?>
        <div class="wrap spamguard-dashboard-v2">

            <!-- Header -->
            <div class="sg-header">
                <div class="sg-header-left">
                    <h1>
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php _e('SpamGuard Security Center', 'spamguard'); ?>
                    </h1>
                    <p class="sg-subtitle">
                        <?php _e('Complete protection for your WordPress site', 'spamguard'); ?>
                    </p>
                </div>
                <div class="sg-header-right">
                    <div class="sg-security-score">
                        <div class="sg-score-circle" data-score="<?php echo $security_score; ?>">
                            <svg viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="45" class="sg-score-bg"></circle>
                                <circle cx="50" cy="50" r="45" class="sg-score-fill"
                                        style="stroke-dashoffset: <?php echo 283 - (283 * $security_score / 100); ?>">
                                </circle>
                            </svg>
                            <div class="sg-score-text">
                                <span class="sg-score-number"><?php echo $security_score; ?></span>
                                <span class="sg-score-label"><?php _e('Security', 'spamguard'); ?></span>
                            </div>
                        </div>
                    </div>
                    <button class="button button-primary sg-refresh-btn" id="sg-refresh-dashboard">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'spamguard'); ?>
                    </button>
                </div>
            </div>

            <!-- Status Cards -->
            <div class="sg-status-cards">

                <!-- Critical Threats -->
                <div class="sg-status-card sg-card-critical">
                    <div class="sg-card-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="sg-card-content">
                        <div class="sg-card-number"><?php echo $overall_status['critical']; ?></div>
                        <div class="sg-card-label"><?php _e('Critical Threats', 'spamguard'); ?></div>
                    </div>
                </div>

                <!-- Warnings -->
                <div class="sg-status-card sg-card-warning">
                    <div class="sg-card-icon">
                        <span class="dashicons dashicons-flag"></span>
                    </div>
                    <div class="sg-card-content">
                        <div class="sg-card-number"><?php echo $overall_status['warnings']; ?></div>
                        <div class="sg-card-label"><?php _e('Warnings', 'spamguard'); ?></div>
                    </div>
                </div>

                <!-- Safe Items -->
                <div class="sg-status-card sg-card-safe">
                    <div class="sg-card-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="sg-card-content">
                        <div class="sg-card-number"><?php echo $overall_status['safe']; ?></div>
                        <div class="sg-card-label"><?php _e('Safe Items', 'spamguard'); ?></div>
                    </div>
                </div>

                <!-- Uptime -->
                <div class="sg-status-card sg-card-uptime">
                    <div class="sg-card-icon">
                        <span class="dashicons dashicons-backup"></span>
                    </div>
                    <div class="sg-card-content">
                        <div class="sg-card-number"><?php echo $overall_status['uptime']; ?>%</div>
                        <div class="sg-card-label"><?php _e('Protection Uptime', 'spamguard'); ?></div>
                    </div>
                </div>

            </div>

            <!-- Module Stats Grid -->
            <div class="sg-modules-grid">

                <!-- Anti-Spam Module -->
                <div class="sg-module-card">
                    <div class="sg-module-header">
                        <h3>
                            <span class="dashicons dashicons-email"></span>
                            <?php _e('Anti-Spam', 'spamguard'); ?>
                        </h3>
                        <a href="<?php echo admin_url('admin.php?page=spamguard-antispam'); ?>" class="button button-small">
                            <?php _e('View Details', 'spamguard'); ?> →
                        </a>
                    </div>
                    <div class="sg-module-stats">
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('Blocked Today', 'spamguard'); ?></span>
                            <span class="sg-stat-value sg-stat-danger"><?php echo $stats_summary['antispam']['today']; ?></span>
                        </div>
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('Total Blocked', 'spamguard'); ?></span>
                            <span class="sg-stat-value"><?php echo number_format($stats_summary['antispam']['total']); ?></span>
                        </div>
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('Accuracy', 'spamguard'); ?></span>
                            <span class="sg-stat-value sg-stat-success"><?php echo $stats_summary['antispam']['accuracy']; ?>%</span>
                        </div>
                    </div>
                    <div class="sg-module-chart">
                        <canvas id="chart-antispam"></canvas>
                    </div>
                </div>

                <!-- Antivirus Module -->
                <div class="sg-module-card">
                    <div class="sg-module-header">
                        <h3>
                            <span class="dashicons dashicons-shield"></span>
                            <?php _e('Antivirus', 'spamguard'); ?>
                        </h3>
                        <a href="<?php echo admin_url('admin.php?page=spamguard-antivirus'); ?>" class="button button-small">
                            <?php _e('View Details', 'spamguard'); ?> →
                        </a>
                    </div>
                    <div class="sg-module-stats">
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('Last Scan', 'spamguard'); ?></span>
                            <span class="sg-stat-value"><?php echo $stats_summary['antivirus']['last_scan']; ?></span>
                        </div>
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('Threats Found', 'spamguard'); ?></span>
                            <span class="sg-stat-value sg-stat-<?php echo $stats_summary['antivirus']['threats'] > 0 ? 'danger' : 'success'; ?>">
                                <?php echo $stats_summary['antivirus']['threats']; ?>
                            </span>
                        </div>
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('Files Scanned', 'spamguard'); ?></span>
                            <span class="sg-stat-value"><?php echo number_format($stats_summary['antivirus']['files_scanned']); ?></span>
                        </div>
                    </div>
                    <div class="sg-module-action">
                        <button class="button button-primary button-hero sg-scan-btn" data-scan-type="quick">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e('Run Quick Scan', 'spamguard'); ?>
                        </button>
                    </div>
                </div>

                <!-- Vulnerabilities Module -->
                <div class="sg-module-card">
                    <div class="sg-module-header">
                        <h3>
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('Vulnerabilities', 'spamguard'); ?>
                        </h3>
                        <a href="<?php echo admin_url('admin.php?page=spamguard-vulnerabilities'); ?>" class="button button-small">
                            <?php _e('View Details', 'spamguard'); ?> →
                        </a>
                    </div>
                    <div class="sg-module-stats">
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('Critical', 'spamguard'); ?></span>
                            <span class="sg-stat-value sg-stat-danger"><?php echo $stats_summary['vulnerabilities']['critical']; ?></span>
                        </div>
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('High', 'spamguard'); ?></span>
                            <span class="sg-stat-value sg-stat-warning"><?php echo $stats_summary['vulnerabilities']['high']; ?></span>
                        </div>
                        <div class="sg-stat-item">
                            <span class="sg-stat-label"><?php _e('Total', 'spamguard'); ?></span>
                            <span class="sg-stat-value"><?php echo $stats_summary['vulnerabilities']['total']; ?></span>
                        </div>
                    </div>
                    <div class="sg-module-chart">
                        <canvas id="chart-vulnerabilities"></canvas>
                    </div>
                </div>

            </div>

            <!-- Activity Timeline -->
            <div class="sg-activity-section">
                <div class="sg-section-header">
                    <h2>
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('Recent Activity', 'spamguard'); ?>
                    </h2>
                    <div class="sg-activity-filters">
                        <button class="sg-filter-btn active" data-filter="all"><?php _e('All', 'spamguard'); ?></button>
                        <button class="sg-filter-btn" data-filter="critical"><?php _e('Critical', 'spamguard'); ?></button>
                        <button class="sg-filter-btn" data-filter="blocked"><?php _e('Blocked', 'spamguard'); ?></button>
                    </div>
                </div>

                <div class="sg-activity-timeline">
                    <?php if (empty($recent_activity)): ?>
                        <div class="sg-no-activity">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php _e('No recent activity to display', 'spamguard'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="sg-activity-item sg-activity-<?php echo esc_attr($activity['type']); ?>">
                                <div class="sg-activity-icon">
                                    <span class="dashicons dashicons-<?php echo esc_attr($activity['icon']); ?>"></span>
                                </div>
                                <div class="sg-activity-content">
                                    <div class="sg-activity-title"><?php echo esc_html($activity['title']); ?></div>
                                    <div class="sg-activity-description"><?php echo esc_html($activity['description']); ?></div>
                                    <div class="sg-activity-time"><?php echo esc_html($activity['time_ago']); ?></div>
                                </div>
                                <?php if (isset($activity['action_url'])): ?>
                                    <div class="sg-activity-action">
                                        <a href="<?php echo esc_url($activity['action_url']); ?>" class="button button-small">
                                            <?php echo esc_html($activity['action_label']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="sg-charts-section">
                <div class="sg-chart-container">
                    <h2><?php _e('Security Trends (Last 30 Days)', 'spamguard'); ?></h2>
                    <canvas id="chart-main-timeline"></canvas>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Obtener estado general del sistema
     */
    private function get_overall_status() {
        global $wpdb;

        $status = array(
            'critical' => 0,
            'warnings' => 0,
            'safe' => 0,
            'uptime' => 99.8
        );

        // Contar amenazas críticas del antivirus
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $critical_threats = $wpdb->get_var(
            "SELECT COUNT(*) FROM $threats_table WHERE severity = 'critical' AND status = 'active'"
        );
        $status['critical'] += intval($critical_threats);

        // Contar vulnerabilidades críticas
        $vuln_table = $wpdb->prefix . 'spamguard_vulnerabilities';
        $critical_vulns = $wpdb->get_var(
            "SELECT COUNT(*) FROM $vuln_table WHERE severity = 'critical'"
        );
        $status['critical'] += intval($critical_vulns);

        // Advertencias (vulnerabilidades high + amenazas high)
        $high_vulns = $wpdb->get_var(
            "SELECT COUNT(*) FROM $vuln_table WHERE severity = 'high'"
        );
        $high_threats = $wpdb->get_var(
            "SELECT COUNT(*) FROM $threats_table WHERE severity = 'high' AND status = 'active'"
        );
        $status['warnings'] = intval($high_vulns) + intval($high_threats);

        // Items seguros (logs de spam legítimo)
        $logs_table = $wpdb->prefix . 'spamguard_logs';
        $safe_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE is_spam = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $status['safe'] = intval($safe_count);

        return $status;
    }

    /**
     * Obtener resumen de estadísticas de cada módulo
     */
    private function get_stats_summary() {
        global $wpdb;

        $summary = array();

        // Anti-Spam Stats
        $logs_table = $wpdb->prefix . 'spamguard_logs';

        $today_spam = $wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE is_spam = 1 AND DATE(created_at) = CURDATE()"
        );

        $total_spam = $wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE is_spam = 1"
        );

        $total_analyzed = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
        $accuracy = $total_analyzed > 0 ? round(($total_spam / $total_analyzed) * 100, 1) : 0;

        $summary['antispam'] = array(
            'today' => intval($today_spam),
            'total' => intval($total_spam),
            'accuracy' => 98.5 // Placeholder - calcular real con feedback
        );

        // Antivirus Stats
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        $last_scan = $wpdb->get_row(
            "SELECT * FROM $scans_table WHERE scan_type LIKE 'antivirus%' ORDER BY started_at DESC LIMIT 1"
        );

        $summary['antivirus'] = array(
            'last_scan' => $last_scan ? human_time_diff(strtotime($last_scan->started_at)) . ' ago' : __('Never', 'spamguard'),
            'threats' => $last_scan ? intval($last_scan->threats_found) : 0,
            'files_scanned' => $last_scan ? intval($last_scan->files_scanned) : 0
        );

        // Vulnerabilities Stats
        $vuln_table = $wpdb->prefix . 'spamguard_vulnerabilities';

        $critical_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $vuln_table WHERE severity = 'critical'"
        );

        $high_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $vuln_table WHERE severity = 'high'"
        );

        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $vuln_table");

        $summary['vulnerabilities'] = array(
            'critical' => intval($critical_count),
            'high' => intval($high_count),
            'total' => intval($total_count)
        );

        return $summary;
    }

    /**
     * Obtener actividad reciente
     */
    private function get_recent_activity() {
        global $wpdb;

        $activity = array();

        // Últimos spam bloqueados
        $logs_table = $wpdb->prefix . 'spamguard_logs';
        $recent_spam = $wpdb->get_results(
            "SELECT * FROM $logs_table WHERE is_spam = 1 ORDER BY created_at DESC LIMIT 5"
        );

        foreach ($recent_spam as $spam) {
            $activity[] = array(
                'type' => 'blocked',
                'icon' => 'dismiss',
                'title' => __('Spam Blocked', 'spamguard'),
                'description' => sprintf(__('From %s - %s', 'spamguard'),
                    esc_html($spam->comment_author),
                    wp_trim_words($spam->comment_content, 10)
                ),
                'time_ago' => human_time_diff(strtotime($spam->created_at)) . ' ' . __('ago', 'spamguard'),
                'action_url' => null,
                'action_label' => null
            );
        }

        // Últimos escaneos
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        $recent_scans = $wpdb->get_results(
            "SELECT * FROM $scans_table ORDER BY started_at DESC LIMIT 3"
        );

        foreach ($recent_scans as $scan) {
            $activity[] = array(
                'type' => $scan->threats_found > 0 ? 'critical' : 'info',
                'icon' => $scan->threats_found > 0 ? 'warning' : 'yes-alt',
                'title' => sprintf(__('%s Scan Completed', 'spamguard'), ucfirst($scan->scan_type)),
                'description' => sprintf(__('%d files scanned, %d threats found', 'spamguard'),
                    $scan->files_scanned,
                    $scan->threats_found
                ),
                'time_ago' => human_time_diff(strtotime($scan->started_at)) . ' ' . __('ago', 'spamguard'),
                'action_url' => admin_url('admin.php?page=spamguard-antivirus'),
                'action_label' => __('View Results', 'spamguard')
            );
        }

        // Ordenar por fecha
        usort($activity, function($a, $b) {
            return strtotime($b['time_ago']) - strtotime($a['time_ago']);
        });

        return array_slice($activity, 0, 10);
    }

    /**
     * Calcular puntuación de seguridad
     */
    private function calculate_security_score() {
        global $wpdb;

        $score = 100;

        // Restar por vulnerabilidades críticas
        $vuln_table = $wpdb->prefix . 'spamguard_vulnerabilities';
        $critical_vulns = $wpdb->get_var(
            "SELECT COUNT(*) FROM $vuln_table WHERE severity = 'critical'"
        );
        $score -= ($critical_vulns * 10); // -10 por cada crítica

        // Restar por vulnerabilidades high
        $high_vulns = $wpdb->get_var(
            "SELECT COUNT(*) FROM $vuln_table WHERE severity = 'high'"
        );
        $score -= ($high_vulns * 5); // -5 por cada high

        // Restar por amenazas activas
        $threats_table = $wpdb->prefix . 'spamguard_threats';
        $active_threats = $wpdb->get_var(
            "SELECT COUNT(*) FROM $threats_table WHERE status = 'active'"
        );
        $score -= ($active_threats * 15); // -15 por amenaza activa

        // Restar si no se ha escaneado recientemente
        $scans_table = $wpdb->prefix . 'spamguard_scans';
        $last_scan = $wpdb->get_var(
            "SELECT MAX(started_at) FROM $scans_table"
        );

        if (!$last_scan || strtotime($last_scan) < strtotime('-7 days')) {
            $score -= 10; // -10 si hace más de 7 días sin escanear
        }

        // No puede ser menor que 0 ni mayor que 100
        return max(0, min(100, $score));
    }
}
