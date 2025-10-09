<?php
/**
 * SpamGuard Dashboard Controller v3.0
 * 
 * Controlador del dashboard principal unificado
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Dashboard_Controller {
    
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
     * Renderizar dashboard principal
     */
    public function render_dashboard() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Obtener estadísticas
        $spam_stats = $this->get_spam_stats();
        $antivirus_stats = $this->get_antivirus_stats();
        
        // Obtener info de cuenta y uso
        $account_info = null;
        $usage_info = null;
        
        if (class_exists('SpamGuard_API_Client')) {
            $api_client = SpamGuard_API_Client::get_instance();
            
            if (get_option('spamguard_api_key')) {
                $account_info = $api_client->get_account_info();
                $usage_info = $api_client->get_usage();
            }
        }
        
        // Verificar si está configurado
        $is_configured = !empty(get_option('spamguard_api_key'));
        
        ?>
        <div class="wrap spamguard-dashboard">
            <h1>
                <span class="dashicons dashicons-shield" style="color: #2271b1;"></span>
                <?php _e('SpamGuard Dashboard', 'spamguard'); ?>
                <span style="font-size: 14px; color: #666; font-weight: normal; margin-left: 10px;">
                    v<?php echo SPAMGUARD_VERSION; ?>
                </span>
            </h1>
            
            <p class="description" style="margin-bottom: 20px;">
                <?php _e('Comprehensive security and spam protection for your WordPress site.', 'spamguard'); ?>
            </p>
            
            <?php if (!$is_configured): ?>
                <!-- Setup Notice -->
                <div class="notice notice-warning" style="border-left: 4px solid #dba617; padding: 20px;">
                    <h2 style="margin-top: 0;"><?php _e('Welcome to SpamGuard!', 'spamguard'); ?> 👋</h2>
                    <p style="font-size: 15px;">
                        <?php _e('Get started by generating your FREE API key. No credit card required!', 'spamguard'); ?>
                    </p>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-primary button-hero">
                            <?php _e('Generate API Key', 'spamguard'); ?> →
                        </a>
                    </p>
                    <p style="color: #666; font-size: 13px; margin-top: 15px;">
                        <strong><?php _e('What you get:', 'spamguard'); ?></strong>
                        1,000 free requests/month • AI-powered spam detection • Malware scanning • Real-time monitoring
                    </p>
                </div>
            <?php else: ?>
                
                <!-- Quick Stats Overview -->
                <div class="spamguard-stats-overview" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
                    
                    <!-- Total Protection Score -->
                    <div class="stat-card stat-primary">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #2271b1;">
                                    <?php echo $this->calculate_protection_score($spam_stats, $antivirus_stats); ?>%
                                </div>
                                <div class="stat-label" style="color: #666; margin-top: 5px;">
                                    <?php _e('Protection Score', 'spamguard'); ?>
                                </div>
                            </div>
                            <div style="font-size: 40px; color: #2271b1; opacity: 0.2;">
                                <span class="dashicons dashicons-shield-alt"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Spam Blocked -->
                    <div class="stat-card stat-danger">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #d63638;">
                                    <?php echo number_format($spam_stats['spam_blocked']); ?>
                                </div>
                                <div class="stat-label" style="color: #666; margin-top: 5px;">
                                    <?php _e('Spam Blocked', 'spamguard'); ?>
                                </div>
                            </div>
                            <div style="font-size: 40px; color: #d63638; opacity: 0.2;">
                                <span class="dashicons dashicons-dismiss"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Threats Detected -->
                    <div class="stat-card stat-warning">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div class="stat-number" style="font-size: 32px; font-weight: bold; color: <?php echo $antivirus_stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>;">
                                    <?php echo number_format($antivirus_stats['active_threats']); ?>
                                </div>
                                <div class="stat-label" style="color: #666; margin-top: 5px;">
                                    <?php _e('Active Threats', 'spamguard'); ?>
                                </div>
                            </div>
                            <div style="font-size: 40px; color: <?php echo $antivirus_stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>; opacity: 0.2;">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- API Usage -->
                    <?php if ($usage_info): ?>
                    <div class="stat-card <?php echo $usage_info['percentage_used'] > 80 ? 'stat-warning' : 'stat-success'; ?>">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div class="stat-number" style="font-size: 32px; font-weight: bold; color: <?php echo $usage_info['percentage_used'] > 80 ? '#dba617' : '#00a32a'; ?>;">
                                    <?php echo number_format($usage_info['percentage_used'], 0); ?>%
                                </div>
                                <div class="stat-label" style="color: #666; margin-top: 5px;">
                                    <?php _e('API Usage', 'spamguard'); ?>
                                </div>
                            </div>
                            <div style="font-size: 40px; color: <?php echo $usage_info['percentage_used'] > 80 ? '#dba617' : '#00a32a'; ?>; opacity: 0.2;">
                                <span class="dashicons dashicons-chart-bar"></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                
                <!-- Security Status Card -->
                <?php if ($antivirus_stats['active_threats'] > 0): ?>
                <div class="notice notice-error" style="border-left: 4px solid #d63638; padding: 20px; margin: 20px 0;">
                    <h2 style="margin-top: 0; color: #d63638;">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Security Alert', 'spamguard'); ?>
                    </h2>
                    <p style="font-size: 15px;">
                        <?php printf(
                            _n(
                                'We detected %d security threat on your site.',
                                'We detected %d security threats on your site.',
                                $antivirus_stats['active_threats'],
                                'spamguard'
                            ),
                            $antivirus_stats['active_threats']
                        ); ?>
                        <strong><?php _e('Immediate action recommended.', 'spamguard'); ?></strong>
                    </p>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=spamguard-antivirus'); ?>" class="button button-primary">
                            <?php _e('Review Threats', 'spamguard'); ?> →
                        </a>
                    </p>
                </div>
                <?php else: ?>
                <div class="notice notice-success inline" style="border-left: 4px solid #00a32a; padding: 20px; margin: 20px 0;">
                    <p style="margin: 0; font-size: 15px;">
                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                        <strong><?php _e('Your site is secure!', 'spamguard'); ?></strong>
                        <?php _e('No active threats detected.', 'spamguard'); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Two Column Layout -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin: 20px 0;">
                    
                    <!-- Left Column: Activity & Stats -->
                    <div>
                        
                        <!-- Recent Activity -->
                        <div class="spamguard-card" style="background: #fff; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h2 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                                <span class="dashicons dashicons-list-view"></span>
                                <?php _e('Recent Activity', 'spamguard'); ?>
                            </h2>
                            
                            <?php
                            $recent_logs = $this->get_recent_logs(10);
                            
                            if ($recent_logs): ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Time', 'spamguard'); ?></th>
                                            <th><?php _e('Type', 'spamguard'); ?></th>
                                            <th><?php _e('Content', 'spamguard'); ?></th>
                                            <th><?php _e('Result', 'spamguard'); ?></th>
                                            <th><?php _e('Confidence', 'spamguard'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                if (class_exists('SpamGuard_API_Helper')) {
                                                    echo SpamGuard_API_Helper::time_ago($log->created_at);
                                                } else {
                                                    echo human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ' . __('ago', 'spamguard');
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="dashicons dashicons-admin-comments" style="color: #2271b1;"></span>
                                                <?php _e('Comment', 'spamguard'); ?>
                                            </td>
                                            <td>
                                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?php echo esc_html(substr($log->comment_content, 0, 100)); ?>
                                                </div>
                                                <?php if (!empty($log->comment_author)): ?>
                                                <small style="color: #666;">
                                                    <?php echo esc_html($log->comment_author); ?>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="spamguard-badge spamguard-badge-<?php echo esc_attr($log->category); ?>">
                                                    <?php echo esc_html(strtoupper($log->category)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $log->confidence ? number_format($log->confidence * 100, 1) . '%' : '—'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 40px 20px;">
                                    <?php _e('No activity yet. SpamGuard will start logging once comments are submitted.', 'spamguard'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Spam Statistics Chart -->
                        <div class="spamguard-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h2 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                                <span class="dashicons dashicons-chart-pie"></span>
                                <?php _e('Detection Statistics', 'spamguard'); ?>
                            </h2>
                            
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center; margin: 20px 0;">
                                <div style="padding: 15px; background: #f0f6fc; border-radius: 5px;">
                                    <div style="font-size: 28px; font-weight: bold; color: #00a32a;">
                                        <?php echo number_format($spam_stats['ham_approved']); ?>
                                    </div>
                                    <div style="color: #666; font-size: 13px; margin-top: 5px;">
                                        <?php _e('Ham (Legitimate)', 'spamguard'); ?>
                                    </div>
                                </div>
                                
                                <div style="padding: 15px; background: #fef0f0; border-radius: 5px;">
                                    <div style="font-size: 28px; font-weight: bold; color: #d63638;">
                                        <?php echo number_format($spam_stats['spam_blocked']); ?>
                                    </div>
                                    <div style="color: #666; font-size: 13px; margin-top: 5px;">
                                        <?php _e('Spam', 'spamguard'); ?>
                                    </div>
                                </div>
                                
                                <div style="padding: 15px; background: #fff8e5; border-radius: 5px;">
                                    <div style="font-size: 28px; font-weight: bold; color: #dba617;">
                                        <?php echo number_format($spam_stats['total_analyzed']); ?>
                                    </div>
                                    <div style="color: #666; font-size: 13px; margin-top: 5px;">
                                        <?php _e('Total Analyzed', 'spamguard'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($spam_stats['total_analyzed'] > 0): ?>
                            <div style="margin-top: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="font-size: 13px; color: #666;">
                                        <?php _e('Detection Accuracy', 'spamguard'); ?>
                                    </span>
                                    <span style="font-size: 14px; font-weight: bold; color: #2271b1;">
                                        <?php echo number_format($spam_stats['accuracy'], 1); ?>%
                                    </span>
                                </div>
                                <div style="background: #f0f0f1; height: 12px; border-radius: 6px; overflow: hidden;">
                                    <div style="background: linear-gradient(90deg, #00a32a, #5fb830); height: 100%; width: <?php echo min($spam_stats['accuracy'], 100); ?>%;"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                    
                    <!-- Right Column: Quick Actions & Info -->
                    <div>
                        
                        <!-- API Usage Card -->
                        <?php if ($usage_info): ?>
                        <div class="spamguard-card" style="background: #fff; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h2 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                                <span class="dashicons dashicons-cloud"></span>
                                <?php _e('API Usage', 'spamguard'); ?>
                            </h2>
                            
                            <div style="margin: 20px 0;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <span style="font-weight: bold; color: #2271b1;">
                                        <?php echo number_format($usage_info['current_month']['requests']); ?>
                                    </span>
                                    <span style="color: #666;">
                                        / <?php echo number_format($usage_info['limit']); ?>
                                    </span>
                                </div>
                                
                                <div style="background: #f0f0f1; height: 20px; border-radius: 10px; overflow: hidden;">
                                    <div style="background: <?php echo $usage_info['percentage_used'] > 80 ? 'linear-gradient(90deg, #d63638, #f86368)' : 'linear-gradient(90deg, #2271b1, #72aee6)'; ?>; height: 100%; width: <?php echo min($usage_info['percentage_used'], 100); ?>%; transition: width 0.3s;"></div>
                                </div>
                                
                                <p style="text-align: center; margin-top: 10px; color: #666; font-size: 13px;">
                                    <?php echo number_format($usage_info['percentage_used'], 1); ?>% <?php _e('used this month', 'spamguard'); ?>
                                </p>
                            </div>
                            
                            <?php if ($usage_info['percentage_used'] > 80): ?>
                            <div style="background: #fff8e5; border-left: 3px solid #dba617; padding: 10px; margin-top: 15px;">
                                <p style="margin: 0; font-size: 12px; color: #666;">
                                    <strong><?php _e('Note:', 'spamguard'); ?></strong>
                                    <?php _e('Local fallback will activate when limit is reached.', 'spamguard'); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quick Actions -->
                        <div class="spamguard-card" style="background: #fff; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h2 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php _e('Quick Actions', 'spamguard'); ?>
                            </h2>
                            
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                                <a href="<?php echo admin_url('admin.php?page=spamguard-antivirus'); ?>" class="button button-secondary" style="text-align: left;">
                                    <span class="dashicons dashicons-shield-alt"></span>
                                    <?php _e('Run Security Scan', 'spamguard'); ?>
                                </a>
                                
                                <a href="<?php echo admin_url('edit-comments.php'); ?>" class="button button-secondary" style="text-align: left;">
                                    <span class="dashicons dashicons-admin-comments"></span>
                                    <?php _e('View Comments', 'spamguard'); ?>
                                </a>
                                
                                <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-secondary" style="text-align: left;">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                    <?php _e('Settings', 'spamguard'); ?>
                                </a>
                                
                                <a href="https://spamguard.ai/docs" target="_blank" class="button button-secondary" style="text-align: left;">
                                    <span class="dashicons dashicons-book"></span>
                                    <?php _e('Documentation', 'spamguard'); ?>
                                </a>
                            </div>
                        </div>
                        
                        <!-- System Info -->
                        <div class="spamguard-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h2 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                                <span class="dashicons dashicons-info"></span>
                                <?php _e('System Info', 'spamguard'); ?>
                            </h2>
                            
                            <table style="width: 100%; margin-top: 15px;">
                                <tr>
                                    <td style="padding: 5px 0; color: #666; font-size: 13px;">
                                        <?php _e('Plugin Version:', 'spamguard'); ?>
                                    </td>
                                    <td style="padding: 5px 0; text-align: right; font-weight: bold;">
                                        <?php echo SPAMGUARD_VERSION; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; color: #666; font-size: 13px;">
                                        <?php _e('WordPress:', 'spamguard'); ?>
                                    </td>
                                    <td style="padding: 5px 0; text-align: right; font-weight: bold;">
                                        <?php echo get_bloginfo('version'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; color: #666; font-size: 13px;">
                                        <?php _e('PHP:', 'spamguard'); ?>
                                    </td>
                                    <td style="padding: 5px 0; text-align: right; font-weight: bold;">
                                        <?php echo PHP_VERSION; ?>
                                    </td>
                                </tr>
                                <?php if ($account_info): ?>
                                <tr>
                                    <td style="padding: 5px 0; color: #666; font-size: 13px;">
                                        <?php _e('Plan:', 'spamguard'); ?>
                                    </td>
                                    <td style="padding: 5px 0; text-align: right;">
                                        <span style="background: #00a32a; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                                            <?php echo esc_html($account_info['plan']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        
                    </div>
                    
                </div>
                
            <?php endif; ?>
            
        </div>
        
        <style>
        .stat-card {
            background: #fff;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            border-left: 4px solid #2271b1;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .stat-card.stat-primary {
            border-left-color: #2271b1;
        }
        
        .stat-card.stat-danger {
            border-left-color: #d63638;
        }
        
        .stat-card.stat-warning {
            border-left-color: #dba617;
        }
        
        .stat-card.stat-success {
            border-left-color: #00a32a;
        }
        
        .spamguard-dashboard .button .dashicons {
            margin-right: 5px;
            vertical-align: middle;
        }
        </style>
        <?php
    }
    
    /**
     * Obtener estadísticas de spam
     */
    private function get_spam_stats() {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'spamguard_usage';
        
        // Total analyzed (últimos 30 días)
        $total_analyzed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $usage_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ));
        
        // Spam blocked
        $spam_blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $usage_table 
            WHERE category = 'spam' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ));
        
        // Ham approved
        $ham_approved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $usage_table 
            WHERE category = 'ham' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ));
        
        // Calcular accuracy estimado
        $accuracy = $total_analyzed > 0 ? (($ham_approved + $spam_blocked) / $total_analyzed) * 95 : 95;
        
        return array(
            'total_analyzed' => intval($total_analyzed),
            'spam_blocked' => intval($spam_blocked),
            'ham_approved' => intval($ham_approved),
            'accuracy' => floatval($accuracy)
        );
    }
    
    /**
     * Obtener estadísticas de antivirus
     */
    private function get_antivirus_stats() {
        if (!class_exists('SpamGuard_Antivirus_Results')) {
            return array(
                'total_scans' => 0,
                'active_threats' => 0,
                'threats_by_severity' => array(
                    'critical' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0
                )
            );
        }
        
        return SpamGuard_Antivirus_Results::get_antivirus_stats();
    }
    
    /**
     * Obtener logs recientes
     */
    private function get_recent_logs($limit = 10) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'spamguard_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Calcular protection score
     */
    private function calculate_protection_score($spam_stats, $antivirus_stats) {
        $score = 100;
        
        // Restar por amenazas activas
        if ($antivirus_stats['active_threats'] > 0) {
            $score -= min($antivirus_stats['active_threats'] * 5, 30);
        }
        
        // Restar si accuracy es bajo
        if ($spam_stats['accuracy'] < 90) {
            $score -= (90 - $spam_stats['accuracy']);
        }
        
        return max(0, min(100, round($score)));
    }
}