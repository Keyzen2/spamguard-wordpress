<?php
/**
 * Plugin Name: SpamGuard Security Suite
 * Plugin URI: https://spamguard.ai
 * Description: ML-powered spam detection, antivirus, and security monitoring (v3.0 Hybrid - FREE)
 * Version: 3.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: SpamGuard Team
 * Author URI: https://spamguard.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: spamguard
 * Domain Path: /languages
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SPAMGUARD_VERSION', '3.0.0');
define('SPAMGUARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPAMGUARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPAMGUARD_PLUGIN_BASENAME', plugin_basename(__FILE__));

// API por defecto (puedes cambiarlo en settings)
if (!defined('SPAMGUARD_API_URL')) {
    define('SPAMGUARD_API_URL', 'https://api.spamguard.ai/api/v1');
}

/**
 * Autoloader simple para clases
 */
spl_autoload_register(function($class) {
    // Solo autoload clases de SpamGuard
    if (strpos($class, 'SpamGuard_') !== 0) {
        return;
    }
    
    // Convertir nombre de clase a nombre de archivo
    $class_file = str_replace('_', '-', strtolower($class));
    $class_file = 'class-' . $class_file . '.php';
    
    // Directorios donde buscar
    $directories = array(
        SPAMGUARD_PLUGIN_DIR . 'includes/',
        SPAMGUARD_PLUGIN_DIR . 'includes/api/',
        SPAMGUARD_PLUGIN_DIR . 'includes/modules/antispam/',
        SPAMGUARD_PLUGIN_DIR . 'includes/modules/antivirus/',
        SPAMGUARD_PLUGIN_DIR . 'includes/dashboard/',
    );
    
    foreach ($directories as $directory) {
        $file = $directory . $class_file;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * Clase principal del plugin
 */
class SpamGuard {
    
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
        $this->init();
    }
    
    /**
     * Inicializar plugin
     */
    private function init() {
        // Load textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));
        
        // Activation & Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Cargar traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'spamguard',
            false,
            dirname(SPAMGUARD_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Inicializar componentes
     */
    public function init_components() {
        // Core components (siempre cargar)
        if (class_exists('SpamGuard_Core')) {
            SpamGuard_Core::get_instance();
        }
        
        if (class_exists('SpamGuard_Admin')) {
            SpamGuard_Admin::get_instance();
        }
        
        // API Client (siempre disponible)
        if (class_exists('SpamGuard_API_Client')) {
            SpamGuard_API_Client::get_instance();
        }
        
        // Dashboard controllers
        if (class_exists('SpamGuard_Dashboard_Controller')) {
            SpamGuard_Dashboard_Controller::get_instance();
        }
        
        if (class_exists('SpamGuard_Antivirus_Dashboard')) {
            SpamGuard_Antivirus_Dashboard::get_instance();
        }
        
        // Solo cargar módulos activos si está configurado
        if ($this->is_configured()) {
            // Anti-spam filter
            if (class_exists('SpamGuard_Filter')) {
                SpamGuard_Filter::get_instance();
            }
            
            // Antivirus (si está habilitado)
            if (get_option('spamguard_antivirus_enabled', true)) {
                if (class_exists('SpamGuard_Antivirus_Scanner')) {
                    SpamGuard_Antivirus_Scanner::get_instance();
                }
                
                if (class_exists('SpamGuard_Antivirus_Results')) {
                    SpamGuard_Antivirus_Results::get_instance();
                }
            }
        }
    }
    
    /**
     * Verificar si el plugin está configurado
     */
    public function is_configured() {
        $api_key = get_option('spamguard_api_key');
        return !empty($api_key);
    }
    
    /**
     * Cargar assets del admin
     */
    public function enqueue_admin_assets($hook) {
        // Solo en páginas de SpamGuard
        if (strpos($hook, 'spamguard') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'spamguard-admin',
            SPAMGUARD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SPAMGUARD_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'spamguard-admin',
            SPAMGUARD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SPAMGUARD_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('spamguard-admin', 'spamguardData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spamguard_ajax'),
            'i18n' => array(
                'confirmDelete' => __('Are you sure?', 'spamguard'),
                'error' => __('An error occurred', 'spamguard'),
                'success' => __('Success', 'spamguard'),
                'loading' => __('Loading...', 'spamguard')
            )
        ));
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Crear tablas
        $this->create_tables();
        
        // Configuración por defecto
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag para mostrar notice
        set_transient('spamguard_activated', true, 60);
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Clear caches
        if (class_exists('SpamGuard_API_Cache')) {
            SpamGuard_API_Cache::get_instance()->flush();
        }
        
        // Clear scheduled events
        wp_clear_scheduled_hook('spamguard_daily_cleanup');
        wp_clear_scheduled_hook('spamguard_auto_scan');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Crear tablas de base de datos
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = array();
        
        // Tabla de logs de uso (v3.0)
        $table_usage = $wpdb->prefix . 'spamguard_usage';
        $sql[] = "CREATE TABLE IF NOT EXISTS $table_usage (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            category varchar(50) NOT NULL,
            confidence decimal(5,4) NOT NULL,
            risk_level varchar(20) NOT NULL,
            processing_time_ms int(11) DEFAULT 0,
            cached tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY category (category),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabla de logs de spam detectado
        $table_logs = $wpdb->prefix . 'spamguard_logs';
        $sql[] = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) DEFAULT NULL,
            comment_author varchar(255) DEFAULT NULL,
            comment_author_email varchar(255) DEFAULT NULL,
            comment_content text,
            is_spam tinyint(1) DEFAULT 0,
            category varchar(50) DEFAULT 'ham',
            confidence decimal(5,4) DEFAULT NULL,
            risk_level varchar(20) DEFAULT 'low',
            flags text DEFAULT NULL,
            request_id varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY comment_id (comment_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabla de escaneos (antivirus)
        $table_scans = $wpdb->prefix . 'spamguard_scans';
        $sql[] = "CREATE TABLE IF NOT EXISTS $table_scans (
            id varchar(36) NOT NULL,
            site_id varchar(255) DEFAULT NULL,
            scan_type varchar(50) NOT NULL,
            status varchar(50) NOT NULL,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            files_scanned int(11) DEFAULT 0,
            threats_found int(11) DEFAULT 0,
            progress int(11) DEFAULT 0,
            results longtext,
            PRIMARY KEY (id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";
        
        // Tabla de amenazas
        $table_threats = $wpdb->prefix . 'spamguard_threats';
        $sql[] = "CREATE TABLE IF NOT EXISTS $table_threats (
            id varchar(36) NOT NULL,
            scan_id varchar(36) DEFAULT NULL,
            site_id varchar(255) DEFAULT NULL,
            file_path text NOT NULL,
            threat_type varchar(100) NOT NULL,
            severity varchar(20) NOT NULL,
            signature_matched text,
            code_snippet text,
            status varchar(50) NOT NULL,
            detected_at datetime NOT NULL,
            resolved_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY scan_id (scan_id),
            KEY status (status),
            KEY severity (severity)
        ) $charset_collate;";
        
        // Tabla de cuarentena
        $table_quarantine = $wpdb->prefix . 'spamguard_quarantine';
        $sql[] = "CREATE TABLE IF NOT EXISTS $table_quarantine (
            id varchar(36) NOT NULL,
            threat_id varchar(36) DEFAULT NULL,
            site_id varchar(255) DEFAULT NULL,
            file_path text NOT NULL,
            original_content longtext,
            backup_location text,
            quarantined_at datetime NOT NULL,
            restored_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY threat_id (threat_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }
    
    /**
     * Configuración por defecto
     */
    private function set_default_options() {
        // API Configuration
        add_option('spamguard_api_url', SPAMGUARD_API_URL);
        add_option('spamguard_api_key', '');
        
        // Anti-Spam Settings
        add_option('spamguard_sensitivity', 50);
        add_option('spamguard_auto_delete', true);
        add_option('spamguard_active_learning', true);
        add_option('spamguard_skip_registered', true);
        add_option('spamguard_use_honeypot', true);
        add_option('spamguard_time_check', 3);
        
        // Antivirus Settings
        add_option('spamguard_antivirus_enabled', true);
        add_option('spamguard_auto_scan', 'weekly');
        add_option('spamguard_email_notifications', true);
        add_option('spamguard_notification_email', get_option('admin_email'));
        
        // v3.0 specific
        add_option('spamguard_version', SPAMGUARD_VERSION);
        add_option('spamguard_first_install', current_time('mysql'));
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Notice de bienvenida después de activación
        if (get_transient('spamguard_activated')) {
            delete_transient('spamguard_activated');
            
            if (!$this->is_configured()) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <h3><?php _e('Welcome to SpamGuard v3.0!', 'spamguard'); ?> 🎉</h3>
                    <p>
                        <?php _e('Thank you for installing SpamGuard Security Suite.', 'spamguard'); ?>
                        <strong><?php _e('Get started by generating your FREE API key!', 'spamguard'); ?></strong>
                    </p>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-primary">
                            <?php _e('Generate API Key', 'spamguard'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=spamguard'); ?>" class="button">
                            <?php _e('View Dashboard', 'spamguard'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
        
        // Notice si no está configurado y estamos en páginas de SpamGuard
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'spamguard') !== false && !$this->is_configured()) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('SpamGuard Setup Required', 'spamguard'); ?></strong><br>
                    <?php _e('Please generate your API key to start using SpamGuard.', 'spamguard'); ?>
                    <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>">
                        <?php _e('Configure Now', 'spamguard'); ?> →
                    </a>
                </p>
            </div>
            <?php
        }
    }
}

/**
 * Inicializar plugin
 */
function spamguard_init() {
    return SpamGuard::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'spamguard_init', 10);

/**
 * Helper function para obtener instancia de API client
 */
function spamguard_api() {
    return SpamGuard_API_Client::get_instance();
}

/**
 * Helper function para verificar si está configurado
 */
function spamguard_is_configured() {
    return SpamGuard::get_instance()->is_configured();
}