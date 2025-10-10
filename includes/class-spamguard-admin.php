<?php
/**
 * SpamGuard Admin Class
 * 
 * Maneja todas las páginas del panel de administración
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Admin {
    
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
        add_action('admin_menu', array($this, 'register_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Registrar páginas del menú en WordPress admin
     */
    public function register_menu_pages() {
        // Página principal: Dashboard
        add_menu_page(
            __('SpamGuard', 'spamguard'),
            __('SpamGuard', 'spamguard'),
            'manage_options',
            'spamguard',
            array($this, 'render_main_dashboard'),
            'dashicons-shield',
            30
        );
        
        // Submenú: Dashboard (mismo que arriba)
        add_submenu_page(
            'spamguard',
            __('Dashboard', 'spamguard'),
            __('Dashboard', 'spamguard'),
            'manage_options',
            'spamguard',
            array($this, 'render_main_dashboard')
        );
        
        // Submenú: Antivirus
        add_submenu_page(
            'spamguard',
            __('Antivirus', 'spamguard'),
            __('Antivirus', 'spamguard'),
            'manage_options',
            'spamguard-antivirus',
            array($this, 'render_antivirus_page')
        );
        
        // ✅ NUEVO: Submenú Vulnerabilities
        add_submenu_page(
            'spamguard',
            __('Vulnerabilities', 'spamguard'),
            __('Vulnerabilities', 'spamguard'),
            'manage_options',
            'spamguard-vulnerabilities',
            array($this, 'render_vulnerabilities_page')
        );
        
        // Submenú: Settings
        add_submenu_page(
            'spamguard',
            __('Settings', 'spamguard'),
            __('Settings', 'spamguard'),
            'manage_options',
            'spamguard-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Registrar settings de WordPress
     */
    public function register_settings() {
        // API Configuration
        register_setting('spamguard_settings', 'spamguard_api_url');
        register_setting('spamguard_settings', 'spamguard_api_key');
        
        // Anti-Spam Settings
        register_setting('spamguard_settings', 'spamguard_sensitivity');
        register_setting('spamguard_settings', 'spamguard_auto_delete');
        register_setting('spamguard_settings', 'spamguard_active_learning');
        register_setting('spamguard_settings', 'spamguard_skip_registered');
        register_setting('spamguard_settings', 'spamguard_use_honeypot');
        register_setting('spamguard_settings', 'spamguard_time_check');
        
        // Antivirus Settings
        register_setting('spamguard_settings', 'spamguard_antivirus_enabled');
        register_setting('spamguard_settings', 'spamguard_auto_scan');
        register_setting('spamguard_settings', 'spamguard_email_notifications');
        register_setting('spamguard_settings', 'spamguard_notification_email');
    }
    
    /**
     * Renderizar dashboard principal
     */
    public function render_main_dashboard() {
        if (class_exists('SpamGuard_Dashboard_Controller')) {
            $dashboard = SpamGuard_Dashboard_Controller::get_instance();
            $dashboard->render_dashboard();
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('SpamGuard Dashboard', 'spamguard') . '</h1>';
            echo '<p>' . __('Dashboard controller not found.', 'spamguard') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Renderizar página de antivirus
     */
    public function render_antivirus_page() {
        if (class_exists('SpamGuard_Antivirus_Dashboard')) {
            $dashboard = SpamGuard_Antivirus_Dashboard::get_instance();
            $dashboard->render();
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Antivirus', 'spamguard') . '</h1>';
            echo '<p>' . __('Antivirus module not found.', 'spamguard') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Renderizar página de vulnerabilidades
     */
    public function render_vulnerabilities_page() {
        // Intentar cargar el template
        $template_file = SPAMGUARD_PLUGIN_DIR . 'templates/vulnerabilities/dashboard.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Fallback si el template no existe
            echo '<div class="wrap">';
            echo '<h1>' . __('Vulnerabilities', 'spamguard') . '</h1>';
            echo '<div class="notice notice-warning">';
            echo '<p>' . __('Vulnerability scanner template not found. Please ensure all plugin files are properly installed.', 'spamguard') . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Renderizar página de settings
     */
    public function render_settings_page() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spamguard'));
        }
        
        // Cargar template de settings
        $template_file = SPAMGUARD_PLUGIN_DIR . 'templates/admin-settings.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Settings', 'spamguard') . '</h1>';
            echo '<p>' . __('Settings template not found.', 'spamguard') . '</p>';
            echo '</div>';
        }
    }
}
