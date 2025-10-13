<?php
if (!defined('ABSPATH')) {
    exit;
}

// ✅ DEBUG: Verificar que se carga
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[SpamGuard] class-antivirus-dashboard.php loaded');
}

class SpamGuard_Antivirus_Dashboard {
    
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
     * Renderizar dashboard
     */
    public function render() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Obtener estadísticas
        $stats = SpamGuard_Antivirus_Results::get_antivirus_stats();
        $last_scan = SpamGuard_Antivirus_Results::get_last_scan();
        $active_threats = SpamGuard_Antivirus_Results::get_active_threats(10);
        
        ?>
        <div class="wrap spamguard-antivirus-dashboard">
            <h1>
                <span class="dashicons dashicons-shield" style="color: #2271b1;"></span>
                <?php _e('SpamGuard Antivirus', 'spamguard'); ?>
            </h1>
            
            <p class="description">
                <?php _e('Scan your WordPress site for malware, backdoors, and security threats.', 'spamguard'); ?>
            </p>
            
            <!-- Stats Cards -->
            <div class="spamguard-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
                
                <!-- Total Scans -->
                <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 28px; font-weight: bold; color: #2271b1;">
                                <?php echo number_format($stats['total_scans']); ?>
                            </div>
                            <div style="color: #666; margin-top: 5px;">
                                <?php _e('Total Scans', 'spamguard'); ?>
                            </div>
                        </div>
                        <div style="font-size: 40px; color: #2271b1; opacity: 0.2;">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Active Threats -->
                <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid <?php echo $stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 28px; font-weight: bold; color: <?php echo $stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>;">
                                <?php echo number_format($stats['active_threats']); ?>
                            </div>
                            <div style="color: #666; margin-top: 5px;">
                                <?php _e('Active Threats', 'spamguard'); ?>
                            </div>
                        </div>
                        <div style="font-size: 40px; color: <?php echo $stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>; opacity: 0.2;">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Critical Threats -->
                <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 28px; font-weight: bold; color: #d63638;">
                                <?php echo number_format($stats['threats_by_severity']['critical']); ?>
                            </div>
                            <div style="color: #666; margin-top: 5px;">
                                <?php _e('Critical', 'spamguard'); ?>
                            </div>
                        </div>
                        <div style="font-size: 40px; color: #d63638; opacity: 0.2;">
                            🔴
                        </div>
                    </div>
                </div>
                
                <!-- High Threats -->
                <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #f56e28; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 28px; font-weight: bold; color: #f56e28;">
                                <?php echo number_format($stats['threats_by_severity']['high']); ?>
                            </div>
                            <div style="color: #666; margin-top: 5px;">
                                <?php _e('High', 'spamguard'); ?>
                            </div>
                        </div>
                        <div style="font-size: 40px; color: #f56e28; opacity: 0.2;">
                            🟠
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Scan Controls -->
            <div class="spamguard-scan-controls" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;"><?php _e('Start New Scan', 'spamguard'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                    
                    <!-- Quick Scan -->
                    <div class="scan-option" style="border: 2px solid #ddd; padding: 20px; border-radius: 5px;">
                        <h3 style="margin-top: 0;">
                            <span class="dashicons dashicons-controls-play" style="color: #2271b1;"></span>
                            <?php _e('Quick Scan', 'spamguard'); ?>
                        </h3>
                        <p style="color: #666; font-size: 14px;">
                            <?php _e('Scan active plugins and themes. Fast and recommended for regular checks.', 'spamguard'); ?>
                        </p>
                        <p style="color: #999; font-size: 12px;">
                            <strong><?php _e('Duration:', 'spamguard'); ?></strong> ~2-5 min
                        </p>
                        <button type="button" class="button button-primary spamguard-start-scan" data-scan-type="quick">
                            <?php _e('Start Quick Scan', 'spamguard'); ?>
                        </button>
                    </div>
                    
                    <!-- Full Scan -->
                    <div class="scan-option" style="border: 2px solid #ddd; padding: 20px; border-radius: 5px;">
                        <h3 style="margin-top: 0;">
                            <span class="dashicons dashicons-database" style="color: #dba617;"></span>
                            <?php _e('Full Scan', 'spamguard'); ?>
                        </h3>
                        <p style="color: #666; font-size: 14px;">
                            <?php _e('Deep scan of entire WordPress installation including core files.', 'spamguard'); ?>
                        </p>
                        <p style="color: #999; font-size: 12px;">
                            <strong><?php _e('Duration:', 'spamguard'); ?></strong> ~10-30 min
                        </p>
                        <button type="button" class="button button-secondary spamguard-start-scan" data-scan-type="full">
                            <?php _e('Start Full Scan', 'spamguard'); ?>
                        </button>
                    </div>
                    
                    <!-- Plugins Only -->
                    <div class="scan-option" style="border: 2px solid #ddd; padding: 20px; border-radius: 5px;">
                        <h3 style="margin-top: 0;">
                            <span class="dashicons dashicons-admin-plugins" style="color: #50575e;"></span>
                            <?php _e('Plugins Only', 'spamguard'); ?>
                        </h3>
                        <p style="color: #666; font-size: 14px;">
                            <?php _e('Scan only installed plugins. Good for checking after plugin updates.', 'spamguard'); ?>
                        </p>
                        <p style="color: #999; font-size: 12px;">
                            <strong><?php _e('Duration:', 'spamguard'); ?></strong> ~3-8 min
                        </p>
                        <button type="button" class="button button-secondary spamguard-start-scan" data-scan-type="plugins">
                            <?php _e('Scan Plugins', 'spamguard'); ?>
                        </button>
                    </div>
                    
                </div>
            </div>
            
            <!-- Scan Progress (hidden by default) -->
            <div id="spamguard-scan-progress" style="display: none; background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2><?php _e('Scan in Progress...', 'spamguard'); ?></h2>
                
                <div style="margin: 20px 0;">
                    <div id="scan-progress-bar" style="background: #f0f0f1; height: 30px; border-radius: 15px; overflow: hidden; position: relative;">
                        <div id="scan-progress-fill" style="background: linear-gradient(90deg, #2271b1, #72aee6); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">
                            0%
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center; margin: 20px 0;">
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #2271b1;" id="scan-files-scanned">0</div>
                        <div style="color: #666; font-size: 12px;"><?php _e('Files Scanned', 'spamguard'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #d63638;" id="scan-threats-found">0</div>
                        <div style="color: #666; font-size: 12px;"><?php _e('Threats Found', 'spamguard'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #50575e;" id="scan-status">Running</div>
                        <div style="color: #666; font-size: 12px;"><?php _e('Status', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <p style="text-align: center; color: #666;">
                    <span class="spinner is-active" style="float: none; margin: 0;"></span>
                    <?php _e('Please wait while we scan your files...', 'spamguard'); ?>
                </p>
            </div>
            
            <!-- Last Scan Info -->
            <?php if ($last_scan): ?>
            <div class="spamguard-last-scan" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2><?php _e('Last Scan', 'spamguard'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <th style="width: 200px;"><?php _e('Date', 'spamguard'); ?></th>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_scan->started_at)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Type', 'spamguard'); ?></th>
                            <td>
                                <span style="text-transform: capitalize;"><?php echo esc_html($last_scan->scan_type); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Status', 'spamguard'); ?></th>
                            <td>
                                <?php if ($last_scan->status === 'completed'): ?>
                                    <span style="color: #00a32a; font-weight: bold;">✓ <?php _e('Completed', 'spamguard'); ?></span>
                                <?php elseif ($last_scan->status === 'running'): ?>
                                    <span style="color: #dba617; font-weight: bold;">⟳ <?php _e('Running', 'spamguard'); ?></span>
                                <?php else: ?>
                                    <span style="color: #d63638; font-weight: bold;">✗ <?php _e('Failed', 'spamguard'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Files Scanned', 'spamguard'); ?></th>
                            <td><?php echo number_format($last_scan->files_scanned); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Threats Found', 'spamguard'); ?></th>
                            <td>
                                <?php if ($last_scan->threats_found > 0): ?>
                                    <strong style="color: #d63638;"><?php echo number_format($last_scan->threats_found); ?></strong>
                                <?php else: ?>
                                    <span style="color: #00a32a;">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Duration', 'spamguard'); ?></th>
                            <td>
                                <?php 
                                if ($last_scan->completed_at) {
                                    $start = strtotime($last_scan->started_at);
                                    $end = strtotime($last_scan->completed_at);
                                    $duration = $end - $start;
                                    
                                    if ($duration < 60) {
                                        echo $duration . ' ' . __('seconds', 'spamguard');
                                    } else {
                                        echo round($duration / 60, 1) . ' ' . __('minutes', 'spamguard');
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if ($last_scan->threats_found > 0): ?>
                <p style="margin-top: 15px;">
                    <a href="#threats-list" class="button button-primary">
                        <?php _e('View Threats', 'spamguard'); ?> →
                    </a>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Active Threats -->
            <?php if (!empty($active_threats)): ?>
            <div id="threats-list" class="spamguard-threats-list" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="color: #d63638;">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('Active Threats', 'spamguard'); ?>
                    <span style="background: #d63638; color: white; padding: 3px 10px; border-radius: 10px; font-size: 14px; margin-left: 10px;">
                        <?php echo count($active_threats); ?>
                    </span>
                </h2>
                
                <div class="notice notice-error inline">
                    <p>
                        <strong><?php _e('Warning:', 'spamguard'); ?></strong>
                        <?php _e('We detected security threats on your site. Review and take action immediately.', 'spamguard'); ?>
                    </p>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 60px;"><?php _e('Severity', 'spamguard'); ?></th>
                            <th><?php _e('File', 'spamguard'); ?></th>
                            <th><?php _e('Threat Type', 'spamguard'); ?></th>
                            <th><?php _e('Description', 'spamguard'); ?></th>
                            <th style="width: 150px;"><?php _e('Detected', 'spamguard'); ?></th>
                            <th style="width: 150px;"><?php _e('Actions', 'spamguard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_threats as $threat): 
                            $severity_info = SpamGuard_Antivirus_Results::format_severity($threat->severity);
                        ?>
                        <tr>
                            <td>
                                <span style="display: inline-block; padding: 5px 10px; border-radius: 3px; color: white; font-weight: bold; font-size: 11px; background: <?php echo $severity_info['color']; ?>;">
                                    <?php echo $severity_info['icon']; ?> <?php echo strtoupper($threat->severity); ?>
                                </span>
                            </td>
                            <td>
                                <code style="font-size: 12px; background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">
                                    <?php echo esc_html($threat->file_path); ?>
                                </code>
                            </td>
                            <td>
                                <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $threat->threat_type))); ?></strong>
                            </td>
                            <td style="font-size: 13px;">
                                <?php echo esc_html($threat->signature_matched); ?>
                                
                                <?php if (!empty($threat->code_snippet)): ?>
                                <details style="margin-top: 5px;">
                                    <summary style="cursor: pointer; color: #2271b1;"><?php _e('View snippet', 'spamguard'); ?></summary>
                                    <pre style="background: #f0f0f1; padding: 10px; margin-top: 5px; font-size: 11px; overflow-x: auto;"><?php echo esc_html($threat->code_snippet); ?></pre>
                                </details>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo SpamGuard_API_Helper::time_ago($threat->detected_at); ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small spamguard-quarantine-threat" data-threat-id="<?php echo esc_attr($threat->id); ?>">
                                    <span class="dashicons dashicons-lock" style="vertical-align: middle;"></span>
                                    <?php _e('Quarantine', 'spamguard'); ?>
                                </button>
                                
                                <button type="button" class="button button-link-delete button-small spamguard-ignore-threat" data-threat-id="<?php echo esc_attr($threat->id); ?>" style="margin-left: 5px;">
                                    <?php _e('Ignore', 'spamguard'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="spamguard-no-threats" style="background: #fff; padding: 40px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 60px; color: #00a32a; margin-bottom: 20px;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h2 style="color: #00a32a; margin: 0 0 10px 0;">
                    <?php _e('No Threats Detected', 'spamguard'); ?>
                </h2>
                <p style="color: #666; font-size: 16px;">
                    <?php _e('Your WordPress site is clean and secure!', 'spamguard'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Scan History -->
            <div class="spamguard-scan-history" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2><?php _e('Recent Scans', 'spamguard'); ?></h2>
                
                <?php 
                $recent_scans = SpamGuard_Antivirus_Results::get_recent_scans(10);
                
                if ($recent_scans): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'spamguard'); ?></th>
                                <th><?php _e('Type', 'spamguard'); ?></th>
                                <th><?php _e('Files', 'spamguard'); ?></th>
                                <th><?php _e('Threats', 'spamguard'); ?></th>
                                <th><?php _e('Status', 'spamguard'); ?></th>
                                <th><?php _e('Duration', 'spamguard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_scans as $scan): ?>
                            <tr>
                                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($scan->started_at)); ?></td>
                                <td style="text-transform: capitalize;"><?php echo esc_html($scan->scan_type); ?></td>
                                <td><?php echo number_format($scan->files_scanned); ?></td>
                                <td>
                                    <?php if ($scan->threats_found > 0): ?>
                                        <strong style="color: #d63638;"><?php echo number_format($scan->threats_found); ?></strong>
                                    <?php else: ?>
                                        <span style="color: #00a32a;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($scan->status === 'completed'): ?>
                                        <span style="color: #00a32a;">✓ <?php _e('Completed', 'spamguard'); ?></span>
                                    <?php elseif ($scan->status === 'running'): ?>
                                        <span style="color: #dba617;">⟳ <?php _e('Running', 'spamguard'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #d63638;">✗ <?php _e('Failed', 'spamguard'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($scan->completed_at) {
                                        $duration = strtotime($scan->completed_at) - strtotime($scan->started_at);
                                        if ($duration < 60) {
                                            echo $duration . 's';
                                        } else {
                                            echo round($duration / 60, 1) . 'm';
                                        }
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No scans yet. Start your first scan above!', 'spamguard'); ?></p>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- JavaScript -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currentScanId = null;
            var progressInterval = null;
            
            // Start scan
            $('.spamguard-start-scan').on('click', function() {
                var scanType = $(this).data('scan-type');
                var $button = $(this);
                
                if (!confirm('<?php _e('Start scan? This may take several minutes.', 'spamguard'); ?>')) {
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e('Starting...', 'spamguard'); ?>');
                
                $.ajax({
                    url: spamguardData.ajaxurl || ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_start_scan',
                        nonce: spamguardData.nonce || '<?php echo wp_create_nonce('spamguard_ajax'); ?>',
                        scan_type: scanType
                    },
                    success: function(response) {
                        if (response.success) {
                            currentScanId = response.data.scan_id;
                            
                            // Show progress
                            $('#spamguard-scan-progress').slideDown();
                            $('html, body').animate({
                                scrollTop: $('#spamguard-scan-progress').offset().top - 50
                            }, 500);
                            
                            // Start polling
                            pollScanProgress();
                        } else {
                            alert(response.data.message || '<?php _e('Error starting scan', 'spamguard'); ?>');
                            $button.prop('disabled', false).text($button.data('original-text') || '<?php _e('Start Scan', 'spamguard'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Connection error. Please try again.', 'spamguard'); ?>');
                        $button.prop('disabled', false).text($button.data('original-text') || '<?php _e('Start Scan', 'spamguard'); ?>');
                    }
                });
            });
            
            // Poll scan progress
            function pollScanProgress() {
                if (!currentScanId) return;
                
                progressInterval = setInterval(function() {
                    $.ajax({
                        url: spamguardData.ajaxurl || ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'spamguard_scan_progress',
                            nonce: spamguardData.nonce || '<?php echo wp_create_nonce('spamguard_ajax'); ?>',
                            scan_id: currentScanId
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                
                                // Update progress bar
                                $('#scan-progress-fill').css('width', data.progress + '%').text(data.progress + '%');
                                
                                // Update stats
                                $('#scan-files-scanned').text(data.files_scanned.toLocaleString());
                                $('#scan-threats-found').text(data.threats_found.toLocaleString());
                                $('#scan-status').text(data.status.charAt(0).toUpperCase() + data.status.slice(1));
                                
                                // Check if completed
                                if (data.status === 'completed' || data.status === 'failed') {
                                    clearInterval(progressInterval);
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                }
                            }
                        }
                    });
                }, 2000); // Poll every 2 seconds
            }
            
            // Quarantine threat
            $(document).on('click', '.spamguard-quarantine-threat', function() {
                var threatId = $(this).data('threat-id');
                var $button = $(this);
                var $row = $button.closest('tr');
                
                if (!confirm('<?php _e('Move this file to quarantine? The file will be moved to a safe location.', 'spamguard'); ?>')) {
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e('Processing...', 'spamguard'); ?>');
                
                $.ajax({
                    url: spamguardData.ajaxurl || ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_quarantine_threat',
                        nonce: spamguardData.nonce || '<?php echo wp_create_nonce('spamguard_ajax'); ?>',
                        threat_id: threatId
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if no more threats
                                if ($('.spamguard-threats-list tbody tr').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            alert(response.data.message || '<?php _e('Error quarantining threat', 'spamguard'); ?>');
                            $button.prop('disabled', false).text('<?php _e('Quarantine', 'spamguard'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Connection error. Please try again.', 'spamguard'); ?>');
                        $button.prop('disabled', false).text('<?php _e('Quarantine', 'spamguard'); ?>');
                    }
                });
            });
            
            // Ignore threat
            $(document).on('click', '.spamguard-ignore-threat', function() {
                var threatId = $(this).data('threat-id');
                var $row = $(this).closest('tr');
                
                if (!confirm('<?php _e('Mark this threat as false positive? You can always scan again later.', 'spamguard'); ?>')) {
                    return;
                }
                
                // Just mark as resolved in DB
                $.ajax({
                    url: spamguardData.ajaxurl || ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_ignore_threat',
                        nonce: spamguardData.nonce || '<?php echo wp_create_nonce('spamguard_ajax'); ?>',
                        threat_id: threatId
                    },
                    success: function(response) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            if ($('.spamguard-threats-list tbody tr').length === 0) {
                                location.reload();
                            }
                        });
                    }
                });
            });
        });
        </script>
        
        <style>
        .spamguard-antivirus-dashboard .dashicons {
            vertical-align: middle;
        }
        
        .scan-option:hover {
            border-color: #2271b1;
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.2);
        }
        
        details summary:hover {
            text-decoration: underline;
        }
        </style>
        <?php
    }

}
