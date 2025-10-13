<?php
/**
 * Dashboard Anti-Spam Completo v3.0
 * Dashboard profesional con estadísticas y logs
 */

if (!defined('ABSPATH')) exit;

// Obtener estadísticas
$stats = SpamGuard_Stats::get_general_stats();
$logs = SpamGuard_Stats::get_recent_logs(50);

// Filtros
$filter_spam = isset($_GET['filter']) && $_GET['filter'] === 'spam';
$filter_ham = isset($_GET['filter']) && $_GET['filter'] === 'ham';
?>

<div class="wrap spamguard-antispam-dashboard">

    <h1>
        <span class="dashicons dashicons-shield-alt" style="color: #2271b1;"></span>
        <?php _e('Anti-Spam AI', 'spamguard'); ?>
    </h1>

    <p class="description">
        <?php _e('Machine learning powered spam detection for your WordPress comments.', 'spamguard'); ?>
    </p>

    <!-- Stats Grid -->
    <div class="spamguard-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">

        <!-- Total Analyzed -->
        <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 28px; font-weight: bold; color: #2271b1;">
                        <?php echo number_format($stats['total_analyzed']); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px; font-size: 13px;">
                        <?php _e('Total Analyzed', 'spamguard'); ?>
                    </div>
                </div>
                <div style="font-size: 40px; color: #2271b1; opacity: 0.2;">
                    <span class="dashicons dashicons-search"></span>
                </div>
            </div>
        </div>

        <!-- Spam Blocked -->
        <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 28px; font-weight: bold; color: #d63638;">
                        <?php echo number_format($stats['total_spam']); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px; font-size: 13px;">
                        <?php _e('Spam Blocked', 'spamguard'); ?>
                    </div>
                </div>
                <div style="font-size: 40px; color: #d63638; opacity: 0.2;">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
            </div>
        </div>

        <!-- Legitimate -->
        <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 28px; font-weight: bold; color: #00a32a;">
                        <?php echo number_format($stats['total_ham']); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px; font-size: 13px;">
                        <?php _e('Legitimate', 'spamguard'); ?>
                    </div>
                </div>
                <div style="font-size: 40px; color: #00a32a; opacity: 0.2;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
            </div>
        </div>

        <!-- This Month -->
        <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #dba617; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 28px; font-weight: bold; color: #dba617;">
                        <?php echo number_format($stats['this_month_analyzed']); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px; font-size: 13px;">
                        <?php _e('This Month', 'spamguard'); ?>
                    </div>
                </div>
                <div style="font-size: 40px; color: #dba617; opacity: 0.2;">
                    <span class="dashicons dashicons-calendar"></span>
                </div>
            </div>
        </div>

        <!-- Spam Percentage -->
        <div class="spamguard-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #50575e; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 28px; font-weight: bold; color: #50575e;">
                        <?php echo $stats['spam_percentage']; ?>%
                    </div>
                    <div style="color: #666; margin-top: 5px; font-size: 13px;">
                        <?php _e('Spam Rate', 'spamguard'); ?>
                    </div>
                </div>
                <div style="font-size: 40px; color: #50575e; opacity: 0.2;">
                    <span class="dashicons dashicons-chart-pie"></span>
                </div>
            </div>
        </div>

    </div>

    <!-- Protection Status -->
    <div class="spamguard-card" style="background: #f0f6fc; padding: 20px; margin: 20px 0; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0;">
            <span class="dashicons dashicons-shield"></span>
            <?php _e('Protection Status', 'spamguard'); ?>
        </h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <strong><?php _e('Honeypot:', 'spamguard'); ?></strong>
                <?php if (get_option('spamguard_use_honeypot', true)): ?>
                    <span style="color: #00a32a;">✓ <?php _e('Enabled', 'spamguard'); ?></span>
                <?php else: ?>
                    <span style="color: #d63638;">✗ <?php _e('Disabled', 'spamguard'); ?></span>
                <?php endif; ?>
            </div>

            <div>
                <strong><?php _e('Time Check:', 'spamguard'); ?></strong>
                <?php $time_check = get_option('spamguard_time_check', 3); ?>
                <span style="color: <?php echo $time_check > 0 ? '#00a32a' : '#d63638'; ?>;">
                    <?php echo $time_check > 0 ? $time_check . 's' : __('Disabled', 'spamguard'); ?>
                </span>
            </div>

            <div>
                <strong><?php _e('Auto-Delete Spam:', 'spamguard'); ?></strong>
                <?php if (get_option('spamguard_auto_delete', true)): ?>
                    <span style="color: #00a32a;">✓ <?php _e('Yes', 'spamguard'); ?></span>
                <?php else: ?>
                    <span style="color: #dba617;"><?php _e('To Spam Folder', 'spamguard'); ?></span>
                <?php endif; ?>
            </div>

            <div>
                <strong><?php _e('Sensitivity:', 'spamguard'); ?></strong>
                <span><?php echo get_option('spamguard_sensitivity', 50); ?>%</span>
            </div>
        </div>

        <p style="margin-top: 15px; margin-bottom: 0;">
            <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button">
                <span class="dashicons dashicons-admin-settings" style="vertical-align: middle;"></span>
                <?php _e('Configure Settings', 'spamguard'); ?>
            </a>
        </p>
    </div>

    <!-- Recent Activity -->
    <div class="spamguard-card" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0;">
            <span class="dashicons dashicons-list-view"></span>
            <?php _e('Recent Activity', 'spamguard'); ?>
        </h2>

        <!-- Filters -->
        <div class="tablenav top" style="margin: 15px 0;">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=spamguard-antispam'); ?>"
                   class="button <?php echo !$filter_spam && !$filter_ham ? 'button-primary' : ''; ?>">
                    <?php _e('All', 'spamguard'); ?> (<?php echo count($logs); ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=spamguard-antispam&filter=spam'); ?>"
                   class="button <?php echo $filter_spam ? 'button-primary' : ''; ?>">
                    <span class="dashicons dashicons-dismiss" style="color: #d63638; vertical-align: middle;"></span>
                    <?php _e('Spam', 'spamguard'); ?> (<?php echo $stats['total_spam']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=spamguard-antispam&filter=ham'); ?>"
                   class="button <?php echo $filter_ham ? 'button-primary' : ''; ?>">
                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;"></span>
                    <?php _e('Legitimate', 'spamguard'); ?> (<?php echo $stats['total_ham']; ?>)
                </a>
            </div>
        </div>

        <?php if (empty($logs)): ?>
            <div style="text-align: center; padding: 60px 20px; background: #f9f9f9; border-radius: 8px;">
                <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;">
                    <span class="dashicons dashicons-admin-comments"></span>
                </div>
                <h3 style="color: #666; margin: 0 0 10px 0;"><?php _e('No Activity Yet', 'spamguard'); ?></h3>
                <p style="color: #999;">
                    <?php _e('Comments analyzed by SpamGuard will appear here.', 'spamguard'); ?>
                </p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;"><?php _e('Date', 'spamguard'); ?></th>
                        <th style="width: 120px;"><?php _e('Author', 'spamguard'); ?></th>
                        <th><?php _e('Comment', 'spamguard'); ?></th>
                        <th style="width: 100px;"><?php _e('Classification', 'spamguard'); ?></th>
                        <th style="width: 120px;"><?php _e('Confidence', 'spamguard'); ?></th>
                        <th style="width: 100px;"><?php _e('Risk Level', 'spamguard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        // Aplicar filtros
                        if ($filter_spam && !$log->is_spam) continue;
                        if ($filter_ham && $log->is_spam) continue;
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($log->comment_author); ?></strong>
                            <?php if ($log->comment_author_email): ?>
                            <br><small style="color: #666;"><?php echo esc_html($log->comment_author_email); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="max-width: 400px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo esc_html(wp_trim_words($log->comment_content, 20)); ?>
                            </div>
                            <?php if ($log->comment_id): ?>
                            <div class="row-actions">
                                <a href="<?php echo admin_url('comment.php?action=editcomment&c=' . $log->comment_id); ?>">
                                    <?php _e('View comment', 'spamguard'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log->is_spam): ?>
                                <span style="display: inline-block; padding: 5px 10px; background: #fef7f7; color: #d63638; border: 1px solid #d63638; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                    <span class="dashicons dashicons-dismiss" style="font-size: 14px; vertical-align: middle;"></span>
                                    SPAM
                                </span>
                            <?php else: ?>
                                <span style="display: inline-block; padding: 5px 10px; background: #f0f6f0; color: #00a32a; border: 1px solid #00a32a; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 14px; vertical-align: middle;"></span>
                                    HAM
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="background: #f0f0f1; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 5px;">
                                <div style="background: linear-gradient(90deg, #00a32a 0%, #d63638 100%); height: 100%; width: <?php echo ($log->confidence * 100); ?>%;"></div>
                            </div>
                            <strong><?php echo round($log->confidence * 100, 1); ?>%</strong>
                        </td>
                        <td>
                            <?php
                            $risk_colors = array(
                                'low' => '#00a32a',
                                'medium' => '#dba617',
                                'high' => '#d63638'
                            );
                            $color = isset($risk_colors[$log->risk_level]) ? $risk_colors[$log->risk_level] : '#666';
                            ?>
                            <span style="color: <?php echo $color; ?>; font-weight: bold; text-transform: uppercase; font-size: 11px;">
                                <?php echo esc_html($log->risk_level); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<style>
.spamguard-antispam-dashboard .dashicons {
    vertical-align: middle;
}

.spamguard-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    transition: all 0.3s ease;
}
</style>
