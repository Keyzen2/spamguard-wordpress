<?php
/**
 * SpamGuard Vulnerabilities Dashboard v3.0
 * Dashboard profesional y completo para vulnerabilidades
 */

if (!defined('ABSPATH')) exit;

$checker = SpamGuard_Vulnerability_Checker::get_instance();
$stats = $checker->get_stats();
$vulnerabilities = $checker->get_vulnerabilities();

// Manejar escaneo
if (isset($_POST['scan_now']) && check_admin_referer('spamguard_scan_vulnerabilities')) {
    $scan_result = $checker->scan_all();

    if ($scan_result['success']) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(
            __('✅ Scan completed. Found %d vulnerabilities out of %d components checked.', 'spamguard'),
            $scan_result['vulnerable_count'],
            $scan_result['total_checked']
        );
        echo '</p></div>';

        // Recargar stats
        $stats = $checker->get_stats();
        $vulnerabilities = $checker->get_vulnerabilities();
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo '❌ ' . esc_html($scan_result['message']);
        echo '</p></div>';
    }
}
?>

<div class="wrap spamguard-vulnerabilities-dashboard">

    <h1>
        <span class="dashicons dashicons-shield-alt" style="color: #2271b1;"></span>
        <?php _e('Vulnerability Scanner', 'spamguard'); ?>
    </h1>

    <p class="description">
        <?php _e('Scan your WordPress installation for known security vulnerabilities in plugins, themes, and core.', 'spamguard'); ?>
    </p>

    <!-- Stats Grid -->
    <div class="spamguard-stats-grid">

        <!-- Total Vulnerabilities -->
        <div class="spamguard-stat-card <?php echo $stats['total'] > 0 ? 'stat-danger' : 'stat-success'; ?>">
            <h3><?php _e('Total Vulnerabilities', 'spamguard'); ?></h3>
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">
                <?php if ($stats['total'] > 0): ?>
                    <?php _e('Issues Found', 'spamguard'); ?>
                <?php else: ?>
                    <?php _e('All Clear', 'spamguard'); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Critical -->
        <div class="spamguard-stat-card stat-danger">
            <h3><?php _e('Critical', 'spamguard'); ?></h3>
            <div class="stat-number"><?php echo number_format($stats['by_severity']['critical']); ?></div>
            <div class="stat-label"><?php _e('Severity', 'spamguard'); ?></div>
        </div>

        <!-- High -->
        <div class="spamguard-stat-card" style="border-left-color: #f56e28;">
            <h3><?php _e('High', 'spamguard'); ?></h3>
            <div class="stat-number" style="color: #f56e28;"><?php echo number_format($stats['by_severity']['high']); ?></div>
            <div class="stat-label"><?php _e('Severity', 'spamguard'); ?></div>
        </div>

        <!-- Medium -->
        <div class="spamguard-stat-card stat-warning">
            <h3><?php _e('Medium', 'spamguard'); ?></h3>
            <div class="stat-number"><?php echo number_format($stats['by_severity']['medium']); ?></div>
            <div class="stat-label"><?php _e('Severity', 'spamguard'); ?></div>
        </div>

        <!-- Low -->
        <div class="spamguard-stat-card stat-success">
            <h3><?php _e('Low', 'spamguard'); ?></h3>
            <div class="stat-number"><?php echo number_format($stats['by_severity']['low']); ?></div>
            <div class="stat-label"><?php _e('Severity', 'spamguard'); ?></div>
        </div>

    </div>

    <!-- Scan Control Card -->
    <div class="spamguard-card" style="border-left: 4px solid #2271b1;">
        <h2>
            <span class="dashicons dashicons-update"></span>
            <?php _e('Vulnerability Scan', 'spamguard'); ?>
        </h2>

        <p>
            <?php _e('Scan your WordPress core, plugins, and themes for known security vulnerabilities from the CVE database.', 'spamguard'); ?>
        </p>

        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('spamguard_scan_vulnerabilities'); ?>

            <button type="submit" name="scan_now" class="button button-primary button-hero">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Scan Now', 'spamguard'); ?>
            </button>

            <?php if ($stats['last_scan']): ?>
            <p style="margin-top: 15px; color: #666; font-size: 13px;">
                <span class="dashicons dashicons-clock" style="vertical-align: middle;"></span>
                <?php printf(
                    __('Last scan: %s ago', 'spamguard'),
                    human_time_diff(strtotime($stats['last_scan']), current_time('timestamp'))
                ); ?>
            </p>
            <?php endif; ?>
        </form>
    </div>

    <!-- Components Summary -->
    <div class="spamguard-card">
        <h2>
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php _e('Scanned Components', 'spamguard'); ?>
        </h2>

        <div class="spamguard-stats-grid" style="grid-template-columns: repeat(3, 1fr);">

            <!-- Plugins -->
            <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                <div style="font-size: 48px; margin-bottom: 10px;">🔌</div>
                <div style="font-size: 24px; font-weight: bold; color: #2271b1; margin-bottom: 5px;">
                    <?php echo $stats['by_type']['plugin']; ?>
                </div>
                <div style="color: #666; font-size: 14px;"><?php _e('Plugins', 'spamguard'); ?></div>
            </div>

            <!-- Themes -->
            <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                <div style="font-size: 48px; margin-bottom: 10px;">🎨</div>
                <div style="font-size: 24px; font-weight: bold; color: #2271b1; margin-bottom: 5px;">
                    <?php echo $stats['by_type']['theme']; ?>
                </div>
                <div style="color: #666; font-size: 14px;"><?php _e('Themes', 'spamguard'); ?></div>
            </div>

            <!-- Core -->
            <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                <div style="font-size: 48px; margin-bottom: 10px;">⚙️</div>
                <div style="font-size: 24px; font-weight: bold; color: #2271b1; margin-bottom: 5px;">
                    <?php echo $stats['by_type']['core']; ?>
                </div>
                <div style="color: #666; font-size: 14px;"><?php _e('WordPress Core', 'spamguard'); ?></div>
            </div>

        </div>
    </div>

    <!-- Vulnerabilities List -->
    <?php if (!empty($vulnerabilities)): ?>

        <div class="spamguard-card" style="border-left: 4px solid #d63638;">
            <h2 style="color: #d63638;">
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Detected Vulnerabilities', 'spamguard'); ?>
                <span style="background: #d63638; color: white; padding: 3px 10px; border-radius: 10px; font-size: 14px; margin-left: 10px;">
                    <?php echo count($vulnerabilities); ?>
                </span>
            </h2>

            <div class="notice notice-error inline">
                <p>
                    <strong><?php _e('Security Warning:', 'spamguard'); ?></strong>
                    <?php _e('Vulnerabilities detected in your WordPress installation. Review and update affected components immediately.', 'spamguard'); ?>
                </p>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 80px;"><?php _e('Severity', 'spamguard'); ?></th>
                        <th><?php _e('Component', 'spamguard'); ?></th>
                        <th><?php _e('Vulnerability', 'spamguard'); ?></th>
                        <th><?php _e('Type', 'spamguard'); ?></th>
                        <th style="width: 120px;"><?php _e('Fixed In', 'spamguard'); ?></th>
                        <th style="width: 100px;"><?php _e('Detected', 'spamguard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vulnerabilities as $vuln):
                        $severity_colors = array(
                            'critical' => '#d63638',
                            'high' => '#f56e28',
                            'medium' => '#dba617',
                            'low' => '#00a32a'
                        );
                        $color = isset($severity_colors[$vuln->severity]) ? $severity_colors[$vuln->severity] : '#666';
                    ?>
                    <tr>
                        <!-- Severity Badge -->
                        <td>
                            <span class="threat-severity-badge" style="background: <?php echo $color; ?>;">
                                <?php echo strtoupper($vuln->severity); ?>
                            </span>
                        </td>

                        <!-- Component -->
                        <td>
                            <strong style="font-size: 14px;">
                                <?php
                                $icons = array('plugin' => '🔌', 'theme' => '🎨', 'core' => '⚙️');
                                echo isset($icons[$vuln->component_type]) ? $icons[$vuln->component_type] . ' ' : '';
                                echo esc_html(ucfirst($vuln->component_type));
                                ?>
                            </strong>
                            <br>
                            <code class="threat-file-path"><?php echo esc_html($vuln->component_slug); ?></code>
                            <small style="color: #666; display: block; margin-top: 3px;">
                                v<?php echo esc_html($vuln->component_version); ?>
                            </small>
                        </td>

                        <!-- Vulnerability Title & Description -->
                        <td>
                            <strong><?php echo esc_html($vuln->title); ?></strong>

                            <?php if (!empty($vuln->cve_id)): ?>
                            <br>
                            <a href="https://nvd.nist.gov/vuln/detail/<?php echo esc_attr($vuln->cve_id); ?>" target="_blank" style="font-size: 12px; color: #2271b1;">
                                <?php echo esc_html($vuln->cve_id); ?> ↗
                            </a>
                            <?php endif; ?>

                            <?php if (!empty($vuln->description)): ?>
                            <details style="margin-top: 8px;">
                                <summary style="cursor: pointer; color: #2271b1; font-size: 12px;">
                                    <?php _e('View details', 'spamguard'); ?>
                                </summary>
                                <p style="margin: 8px 0 0 0; padding: 10px; background: #f0f0f1; border-radius: 4px; font-size: 12px; color: #333;">
                                    <?php echo esc_html($vuln->description); ?>
                                </p>
                            </details>
                            <?php endif; ?>
                        </td>

                        <!-- Type -->
                        <td>
                            <span style="background: #f0f0f1; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                                <?php echo esc_html(str_replace('_', ' ', $vuln->vuln_type)); ?>
                            </span>
                        </td>

                        <!-- Fixed In -->
                        <td>
                            <?php if (!empty($vuln->patched_in)): ?>
                                <strong style="color: #00a32a;">v<?php echo esc_html($vuln->patched_in); ?></strong>
                                <br>
                                <a href="<?php echo admin_url('update-core.php'); ?>" class="button button-small" style="margin-top: 5px;">
                                    <?php _e('Update Now', 'spamguard'); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #d63638;">❌ <?php _e('No patch', 'spamguard'); ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- Detected -->
                        <td style="font-size: 12px; color: #666;">
                            <?php echo human_time_diff(strtotime($vuln->detected_at), current_time('timestamp')); ?>
                            <?php _e('ago', 'spamguard'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <!-- No Vulnerabilities -->
        <div class="spamguard-no-threats">
            <div class="success-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <h2 style="color: #00a32a; margin: 0 0 10px 0;">
                <?php _e('No Vulnerabilities Detected', 'spamguard'); ?>
            </h2>
            <p style="color: #666; font-size: 16px;">
                <?php _e('Your WordPress installation is up to date and secure!', 'spamguard'); ?>
            </p>
            <p style="color: #999; font-size: 14px; margin-top: 15px;">
                ℹ️ <?php _e('Regular scanning is recommended to stay protected against new vulnerabilities.', 'spamguard'); ?>
            </p>
        </div>

    <?php endif; ?>

    <!-- Recommendations Card -->
    <div class="spamguard-card">
        <h2>
            <span class="dashicons dashicons-lightbulb"></span>
            <?php _e('Security Recommendations', 'spamguard'); ?>
        </h2>

        <ul style="list-style-type: disc; margin-left: 20px; line-height: 1.8;">
            <li><?php _e('Keep WordPress core, plugins, and themes always updated', 'spamguard'); ?></li>
            <li><?php _e('Remove unused plugins and themes from your site', 'spamguard'); ?></li>
            <li><?php _e('Only install plugins and themes from trusted sources', 'spamguard'); ?></li>
            <li><?php _e('Enable automatic updates for security patches', 'spamguard'); ?></li>
            <li><?php _e('Run vulnerability scans weekly to catch new issues', 'spamguard'); ?></li>
            <li><?php _e('Monitor security bulletins for your installed components', 'spamguard'); ?></li>
        </ul>

        <p style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
            <strong><?php _e('Pro Tip:', 'spamguard'); ?></strong>
            <?php _e('Set up automatic weekly scans to stay ahead of security threats. SpamGuard will notify you immediately if any vulnerabilities are detected.', 'spamguard'); ?>
        </p>
    </div>

</div>

<!-- JavaScript for Vulnerability Dashboard -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Auto-refresh stats if scan is in progress
    var scanInProgress = <?php echo (isset($_POST['scan_now']) ? 'true' : 'false'); ?>;

    if (scanInProgress) {
        // Show scanning indicator
        console.log('Vulnerability scan completed');
    }

    // Smooth scroll to vulnerabilities list
    if (window.location.hash === '#vulnerabilities' && $('.spamguard-card[style*="border-left: 4px solid #d63638"]').length) {
        $('html, body').animate({
            scrollTop: $('.spamguard-card[style*="border-left: 4px solid #d63638"]').offset().top - 50
        }, 500);
    }
});
</script>
