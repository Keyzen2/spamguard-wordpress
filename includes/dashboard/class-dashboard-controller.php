<?php
/**
 * SpamGuard Dashboard Controller v3.0 - OPTIMIZADO
 * SIN llamadas síncronas a API durante la carga
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Dashboard_Controller {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // ✅ AJAX handlers para cargar datos de forma asíncrona
        add_action('wp_ajax_spamguard_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
    }
    
    /**
     * ✅ Renderizar dashboard (SIN cargar datos pesados)
     */
    public function render_dashboard() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // ✅ NO obtener estadísticas aquí - se cargarán con AJAX
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
                
                <!-- ✅ Loading Placeholder -->
                <div id="dashboard-loading" style="text-align: center; padding: 60px; background: #fff; border-radius: 8px; margin: 20px 0;">
                    <div class="spinner is-active" style="float: none; margin: 0 auto 20px;"></div>
                    <p style="color: #666; font-size: 16px;"><?php _e('Loading dashboard...', 'spamguard'); ?></p>
                </div>
                
                <!-- ✅ Dashboard Content (se llenará con AJAX) -->
                <div id="dashboard-content" style="display: none;">
                    
                    <!-- Quick Stats Overview -->
                    <div class="spamguard-stats-overview" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
                        
                        <!-- Protection Score -->
                        <div class="stat-card stat-primary" style="background: #fff; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div class="stat-number" id="protection-score" style="font-size: 32px; font-weight: bold; color: #2271b1;">--</div>
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
                        <div class="stat-card stat-danger" style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div class="stat-number" id="spam-blocked" style="font-size: 32px; font-weight: bold; color: #d63638;">--</div>
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
                        <div class="stat-card stat-warning" style="background: #fff; padding: 20px; border-left: 4px solid #dba617; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div class="stat-number" id="active-threats" style="font-size: 32px; font-weight: bold; color: #dba617;">--</div>
                                    <div class="stat-label" style="color: #666; margin-top: 5px;">
                                        <?php _e('Active Threats', 'spamguard'); ?>
                                    </div>
                                </div>
                                <div style="font-size: 40px; color: #dba617; opacity: 0.2;">
                                    <span class="dashicons dashicons-warning"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- API Usage -->
                        <div class="stat-card stat-success" style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div class="stat-number" id="api-usage" style="font-size: 32px; font-weight: bold; color: #00a32a;">--</div>
                                    <div class="stat-label" style="color: #666; margin-top: 5px;">
                                        <?php _e('API Usage', 'spamguard'); ?>
                                    </div>
                                </div>
                                <div style="font-size: 40px; color: #00a32a; opacity: 0.2;">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Quick Actions -->
                    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
                        <h2 style="margin-top: 0;"><?php _e('Quick Actions', 'spamguard'); ?></h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <a href="<?php echo admin_url('admin.php?page=spamguard-antivirus'); ?>" class="button button-large" style="text-align: center;">
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('Scan for Threats', 'spamguard'); ?>
                            </a>
                            <a href="<?php echo admin_url('edit-comments.php?comment_status=spam'); ?>" class="button button-large" style="text-align: center;">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('View Spam', 'spamguard'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-large" style="text-align: center;">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php _e('Settings', 'spamguard'); ?>
                            </a>
                        </div>
                    </div>
                    
                </div>
                
            <?php endif; ?>
            
        </div>
        
        <?php if ($is_configured): ?>
        <script>
        jQuery(document).ready(function($) {
            // ✅ Cargar estadísticas con AJAX (no bloquea la página)
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_get_dashboard_stats',
                    nonce: '<?php echo wp_create_nonce('spamguard_ajax'); ?>'
                },
                timeout: 10000, // 10 segundos máximo
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;
                        
                        // Actualizar stats
                        $('#protection-score').text(data.protection_score + '%');
                        $('#spam-blocked').text(data.spam_blocked.toLocaleString());
                        $('#active-threats').text(data.active_threats);
                        $('#api-usage').text(data.api_usage + '%');
                        
                        // Mostrar contenido
                        $('#dashboard-loading').fadeOut(300, function() {
                            $('#dashboard-content').fadeIn(300);
                        });
                    } else {
                        $('#dashboard-loading').html(
                            '<p style="color: #d63638;">⚠️ Error loading dashboard data</p>' +
                            '<p><a href="javascript:location.reload()" class="button">Retry</a></p>'
                        );
                    }
                },
                error: function() {
                    $('#dashboard-loading').html(
                        '<p style="color: #d63638;">⚠️ Error loading dashboard data</p>' +
                        '<p><a href="javascript:location.reload()" class="button">Retry</a></p>'
                    );
                }
            });
        });
        </script>
        <?php endif; ?>
        
        <style>
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            box-shadow: 0 3px 8px rgba(0,0,0,0.15) !important;
            transform: translateY(-2px);
        }
        </style>
        <?php
    }
    
    /**
     * ✅ AJAX: Obtener estadísticas del dashboard
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('spamguard_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        try {
            // ✅ Obtener stats de forma rápida (solo BD local)
            $stats = $this->get_dashboard_stats_fast();
            
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * ✅ Obtener stats rápido (solo BD local, sin API)
     */
    private function get_dashboard_stats_fast() {
        global $wpdb;
        
        // Spam stats (BD local)
        $usage_table = $wpdb->prefix . 'spamguard_usage';
        
        $spam_blocked = $wpdb->get_var(
            "SELECT COUNT(*) FROM $usage_table 
            WHERE category = 'spam' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Antivirus stats (BD local)
        $active_threats = 0;
        if (class_exists('SpamGuard_Antivirus_Results')) {
            $av_stats = SpamGuard_Antivirus_Results::get_antivirus_stats();
            $active_threats = $av_stats['active_threats'];
        }
        
        // API usage (BD local)
        $requests_this_month = $wpdb->get_var(
            "SELECT COUNT(*) FROM $usage_table 
             WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
             AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        );
        
        $limit = 1000;
        $usage_percentage = $requests_this_month > 0 ? min(100, ($requests_this_month / $limit) * 100) : 0;
        
        // Protection score
        $protection_score = 100;
        if ($active_threats > 0) {
            $protection_score = max(0, 100 - ($active_threats * 10));
        }
        
        return array(
            'protection_score' => intval($protection_score),
            'spam_blocked' => intval($spam_blocked),
            'active_threats' => intval($active_threats),
            'api_usage' => round($usage_percentage, 1)
        );
    }
}
