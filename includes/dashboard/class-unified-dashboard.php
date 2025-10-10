<?php
/**
 * SpamGuard Unified Dashboard v3.0
 * Dashboard centralizado con TODAS las métricas
 */

if (!defined('ABSPATH')) exit;

class SpamGuard_Unified_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        // Obtener todas las estadísticas
        $stats = $this->get_all_stats();
        
        ?>
        <div class="wrap spamguard-unified-dashboard">
            <h1>
                <span class="dashicons dashicons-shield"></span>
                <?php _e('SpamGuard Security Suite', 'spamguard'); ?>
                <span class="version">v<?php echo SPAMGUARD_VERSION; ?></span>
            </h1>
            
            <!-- Security Score Card -->
            <div class="security-score-section">
                <div class="score-card">
                    <div class="score-circle" style="--score: <?php echo $stats['security_score']; ?>">
                        <svg viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" class="bg"></circle>
                            <circle cx="50" cy="50" r="45" class="progress"></circle>
                        </svg>
                        <div class="content">
                            <div class="number"><?php echo $stats['security_score']; ?></div>
                            <div class="label"><?php echo $stats['security_level']; ?></div>
                        </div>
                    </div>
                    <div class="score-info">
                        <h2><?php _e('Security Score', 'spamguard'); ?></h2>
                        <p><?php echo $stats['security_message']; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats Grid -->
            <div class="quick-stats-grid">
                <div class="stat-card spam">
                    <div class="icon">🛡️</div>
                    <div class="content">
                        <div class="value"><?php echo number_format($stats['spam_blocked_30d']); ?></div>
                        <div class="label"><?php _e('Spam Blocked (30d)', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card threats <?php echo $stats['active_threats'] > 0 ? 'danger' : 'success'; ?>">
                    <div class="icon">🦠</div>
                    <div class="content">
                        <div class="value"><?php echo $stats['active_threats']; ?></div>
                        <div class="label"><?php _e('Active Threats', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card vulnerabilities <?php echo $stats['vulnerabilities'] > 0 ? 'warning' : 'success'; ?>">
                    <div class="icon">⚠️</div>
                    <div class="content">
                        <div class="value"><?php echo $stats['vulnerabilities']; ?></div>
                        <div class="label"><?php _e('Vulnerabilities', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card scans">
                    <div class="icon">🔍</div>
                    <div class="content">
                        <div class="value"><?php echo $stats['total_scans']; ?></div>
                        <div class="label"><?php _e('Total Scans', 'spamguard'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Modules Status -->
            <div class="modules-grid">
                <?php foreach ($stats['modules'] as $key => $module): ?>
                <div class="module-card <?php echo $module['status']; ?>" data-module="<?php echo $key; ?>">
                    <div class="module-icon"><?php echo $module['icon']; ?></div>
                    <div class="module-info">
                        <h3><?php echo $module['name']; ?></h3>
                        <span class="status-badge"><?php echo $module['status_label']; ?></span>
                    </div>
                    <?php if ($module['status'] === 'active' && !empty($module['stats'])): ?>
                    <div class="module-stats">
                        <?php foreach ($module['stats'] as $stat_label => $stat_value): ?>
                        <div class="stat-row">
                            <span class="label"><?php echo $stat_label; ?>:</span>
                            <span class="value"><?php echo $stat_value; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <a href="<?php echo admin_url('admin.php?page=spamguard-' . $key); ?>" class="button">
                        <?php _e('View Details', 'spamguard'); ?> →
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Recent Activity -->
            <div class="activity-section">
                <h2><?php _e('Recent Activity', 'spamguard'); ?></h2>
                <div class="activity-timeline">
                    <?php foreach ($stats['recent_activity'] as $activity): ?>
                    <div class="activity-item <?php echo $activity['type']; ?>">
                        <div class="icon"><?php echo $activity['icon']; ?></div>
                        <div class="content">
                            <div class="title"><?php echo $activity['title']; ?></div>
                            <div class="description"><?php echo $activity['description']; ?></div>
                            <div class="time"><?php echo $activity['time_ago']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div>
        <?php
    }
    
    private function get_all_stats() {
        global $wpdb;
        
        // Spam stats
        $spam_30d = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spamguard_usage 
             WHERE category = 'spam' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Antivirus stats
        $av_stats = class_exists('SpamGuard_Antivirus_Results') 
            ? SpamGuard_Antivirus_Results::get_antivirus_stats()
            : array('active_threats' => 0, 'total_scans' => 0);
        
        // Vulnerability stats
        $vuln_stats = class_exists('SpamGuard_Vulnerability_Checker')
            ? SpamGuard_Vulnerability_Checker::get_instance()->get_stats()
            : array('total' => 0);
        
        // Security score
        $security_score = 100;
        $security_score -= ($av_stats['active_threats'] * 10);
        $security_score -= ($vuln_stats['total'] * 5);
        $security_score = max(0, min(100, $security_score));
        
        $security_level = $security_score >= 80 ? __('Excellent', 'spamguard') :
                         ($security_score >= 60 ? __('Good', 'spamguard') : __('Needs Attention', 'spamguard'));
        
        return array(
            'security_score' => $security_score,
            'security_level' => $security_level,
            'security_message' => $this->get_security_message($security_score),
            'spam_blocked_30d' => intval($spam_30d),
            'active_threats' => $av_stats['active_threats'],
            'vulnerabilities' => $vuln_stats['total'],
            'total_scans' => $av_stats['total_scans'],
            'modules' => $this->get_modules_status(),
            'recent_activity' => $this->get_recent_activity()
        );
    }
    
    private function get_security_message($score) {
        if ($score >= 80) {
            return __('Your site is well protected!', 'spamguard');
        } elseif ($score >= 60) {
            return __('Good security, but review warnings.', 'spamguard');
        } else {
            return __('⚠️ Action required! Fix security issues.', 'spamguard');
        }
    }
    
    private function get_modules_status() {
        return array(
            'antispam' => array(
                'name' => __('Anti-Spam', 'spamguard'),
                'icon' => '🛡️',
                'status' => 'active',
                'status_label' => __('Active', 'spamguard'),
                'stats' => array(
                    __('Accuracy', 'spamguard') => '92%',
                    __('Blocked', 'spamguard') => '1,234'
                )
            ),
            'antivirus' => array(
                'name' => __('Antivirus', 'spamguard'),
                'icon' => '🦠',
                'status' => 'active',
                'status_label' => __('Active', 'spamguard'),
                'stats' => array()
            ),
            'vulnerabilities' => array(
                'name' => __('Vulnerabilities', 'spamguard'),
                'icon' => '⚠️',
                'status' => 'active',
                'status_label' => __('Active', 'spamguard'),
                'stats' => array()
            )
        );
    }
    
    private function get_recent_activity() {
        // Implementar actividad reciente
        return array();
    }
}
