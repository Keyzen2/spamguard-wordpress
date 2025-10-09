<?php
/**
 * SpamGuard Uninstall Script
 * 
 * Fired when the plugin is uninstalled
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * SpamGuard Uninstaller
 */
class SpamGuard_Uninstaller {
    
    /**
     * Run uninstall process
     */
    public static function uninstall() {
        global $wpdb;
        
        // Check if user really wants to delete all data
        $delete_data = get_option('spamguard_delete_data_on_uninstall', false);
        
        if (!$delete_data) {
            // Just deactivate, keep data
            self::deactivate_only();
            return;
        }
        
        // Delete all data
        self::delete_tables();
        self::delete_options();
        self::delete_transients();
        self::delete_cron_jobs();
        self::delete_user_meta();
        self::delete_comment_meta();
        self::delete_quarantine_files();
    }
    
    /**
     * Only deactivate (keep data)
     */
    private static function deactivate_only() {
        // Clear cron jobs
        wp_clear_scheduled_hook('spamguard_daily_cleanup');
        wp_clear_scheduled_hook('spamguard_auto_scan');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Delete plugin tables
     */
    private static function delete_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'spamguard_usage',
            $wpdb->prefix . 'spamguard_logs',
            $wpdb->prefix . 'spamguard_scans',
            $wpdb->prefix . 'spamguard_threats',
            $wpdb->prefix . 'spamguard_quarantine'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Delete plugin options
     */
    private static function delete_options() {
        $options = array(
            'spamguard_api_url',
            'spamguard_api_key',
            'spamguard_sensitivity',
            'spamguard_auto_delete',
            'spamguard_active_learning',
            'spamguard_skip_registered',
            'spamguard_use_honeypot',
            'spamguard_time_check',
            'spamguard_antivirus_enabled',
            'spamguard_auto_scan',
            'spamguard_email_notifications',
            'spamguard_notification_email',
            'spamguard_version',
            'spamguard_first_install',
            'spamguard_delete_data_on_uninstall'
        );
        
        foreach ($options as $option) {
            delete_option($option);
            
            // For multisite
            delete_site_option($option);
        }
    }
    
    /**
     * Delete transients
     */
    private static function delete_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_spamguard_%' 
            OR option_name LIKE '_transient_timeout_spamguard_%'"
        );
        
        // For multisite
        $wpdb->query(
            "DELETE FROM {$wpdb->sitemeta} 
            WHERE meta_key LIKE '_site_transient_spamguard_%' 
            OR meta_key LIKE '_site_transient_timeout_spamguard_%'"
        );
    }
    
    /**
     * Clear scheduled cron jobs
     */
    private static function delete_cron_jobs() {
        wp_clear_scheduled_hook('spamguard_daily_cleanup');
        wp_clear_scheduled_hook('spamguard_auto_scan');
        wp_clear_scheduled_hook('spamguard_process_scan');
    }
    
    /**
     * Delete user meta
     */
    private static function delete_user_meta() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
            WHERE meta_key LIKE 'spamguard_%'"
        );
    }
    
    /**
     * Delete comment meta
     */
    private static function delete_comment_meta() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->commentmeta} 
            WHERE meta_key LIKE 'spamguard_%'"
        );
    }
    
    /**
     * Delete quarantine files
     */
    private static function delete_quarantine_files() {
        $quarantine_dir = WP_CONTENT_DIR . '/spamguard-quarantine';
        
        if (is_dir($quarantine_dir)) {
            self::delete_directory($quarantine_dir);
        }
    }
    
    /**
     * Recursively delete directory
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                @unlink($path);
            }
        }
        
        @rmdir($dir);
    }
}

// Run uninstall
SpamGuard_Uninstaller::uninstall();