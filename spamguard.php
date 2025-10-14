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
 */

// ✅ PASO 1: Seguridad básica
if (!defined('ABSPATH')) {
    exit;
}

// ✅ PASO 2: Definir constantes
define('SPAMGUARD_VERSION', '3.0.0');
define('SPAMGUARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPAMGUARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPAMGUARD_PLUGIN_BASENAME', plugin_basename(__FILE__));

if (!defined('SPAMGUARD_API_URL')) {
    define('SPAMGUARD_API_URL', 'https://spamguard.up.railway.app');
}

// ✅ PASO 3: Verificar requisitos de PHP
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>SpamGuard Error:</strong> Requiere PHP 7.4 o superior. Tu versión: ' . PHP_VERSION;
        echo '</p></div>';
    });
    return;
}

/**
 * ✅ FUNCIÓN SEGURA: Cargar archivo solo si existe
 */
function spamguard_require_file($file) {
    $path = SPAMGUARD_PLUGIN_DIR . $file;
    
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[SpamGuard] Archivo no encontrado: {$file}");
    }
    
    return false;
}

/**
 * ✅ PASO 4: Cargar archivos CORE (obligatorios)
 */
$core_files = array(
    'includes/class-spamguard-core.php',
    'includes/class-spamguard-admin.php',
    'includes/api/class-api-client.php',
    'includes/api/class-api-helper.php',
);

$all_core_loaded = true;

foreach ($core_files as $file) {
    if (!spamguard_require_file($file)) {
        $all_core_loaded = false;
        
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>SpamGuard Error:</strong> Falta archivo core: ' . esc_html($file);
            echo '</p></div>';
        });
    }
}

if (!$all_core_loaded) {
    return;
}

/**
 * ✅ PASO 5: Cargar archivos OPCIONALES
 */
$optional_files = array(
    'includes/class-spamguard-stats.php',
    'includes/class-spamguard-lists.php',
    'includes/class-spamguard-exporter.php',
    'includes/api/class-api-cache.php',
    'includes/modules/antispam/class-spam-filter.php',
    'includes/modules/antispam/class-local-fallback.php',
    'includes/dashboard/class-dashboard-controller.php',
    'includes/dashboard/class-antivirus-dashboard.php',
    'includes/dashboard/class-unified-dashboard-v2.php',
    'includes/modules/antivirus/class-antivirus-scanner.php',
    'includes/modules/antivirus/class-antivirus-results.php',
    'includes/modules/antivirus/class-quarantine-manager.php',
    'includes/modules/vulnerabilities/class-vulnerability-checker.php',
);

foreach ($optional_files as $file) {
    spamguard_require_file($file);
}

/**
 * ✅ CLASE PRINCIPAL DEL PLUGIN
 */
class SpamGuard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hooks de activación/desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inicializar
        add_action('plugins_loaded', array($this, 'init'), 5);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * ✅ Inicializar componentes
     */
    public function init() {
        if (class_exists('SpamGuard_Admin')) {
            SpamGuard_Admin::get_instance();
        }

        if (class_exists('SpamGuard_Core')) {
            SpamGuard_Core::get_instance();
        }

        if (class_exists('SpamGuard_API_Client')) {
            SpamGuard_API_Client::get_instance();
        }

        if (class_exists('SpamGuard_Dashboard_Controller')) {
            SpamGuard_Dashboard_Controller::get_instance();
        }

        // Exporter (always available)
        if (class_exists('SpamGuard_Exporter')) {
            SpamGuard_Exporter::get_instance();
        }

        // Quarantine Manager (always available)
        if (class_exists('SpamGuard_Quarantine_Manager')) {
            SpamGuard_Quarantine_Manager::get_instance();
        }

        // Solo si está configurado
        if ($this->is_configured()) {
            $this->init_active_modules();
        }
    }
    
    /**
     * ✅ Inicializar módulos activos
     */
    private function init_active_modules() {
        // Anti-Spam filter
        if (class_exists('SpamGuard_Filter')) {
            SpamGuard_Filter::get_instance();
        }

        // Antivirus Scanner
        if (get_option('spamguard_antivirus_enabled', true)) {
            if (class_exists('SpamGuard_Antivirus_Scanner')) {
                SpamGuard_Antivirus_Scanner::get_instance();
            }
        }

        // Vulnerability Checker
        if (get_option('spamguard_vulnerabilities_enabled', true)) {
            if (class_exists('SpamGuard_Vulnerability_Checker')) {
                SpamGuard_Vulnerability_Checker::get_instance();
            }
        }
    }
    
    /**
     * ✅ Verificar si está configurado
     */
    public function is_configured() {
        $api_key = get_option('spamguard_api_key');
        return !empty($api_key);
    }
    
    /**
     * ✅ Cargar assets del admin
     */
    public function enqueue_admin_assets($hook) {
        // Solo cargar en páginas de SpamGuard
        if (strpos($hook, 'spamguard') === false) {
            return;
        }

        // CSS principal
        wp_enqueue_style(
            'spamguard-admin',
            SPAMGUARD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SPAMGUARD_VERSION
        );

        // CSS del dashboard unificado
        wp_enqueue_style(
            'spamguard-dashboard',
            SPAMGUARD_PLUGIN_URL . 'assets/css/dashboard-unified.css',
            array('spamguard-admin'),
            SPAMGUARD_VERSION
        );

        // JavaScript principal
        wp_enqueue_script(
            'spamguard-admin',
            SPAMGUARD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SPAMGUARD_VERSION,
            true
        );

        // Localización
        wp_localize_script('spamguard-admin', 'spamguardData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spamguard_ajax'),
            'pluginUrl' => SPAMGUARD_PLUGIN_URL,
            'i18n' => array(
                'confirmDelete' => __('Are you sure?', 'spamguard'),
                'error' => __('An error occurred', 'spamguard'),
                'success' => __('Success', 'spamguard'),
                'loading' => __('Loading...', 'spamguard'),
                'scanning' => __('Scanning...', 'spamguard'),
                'completed' => __('Completed', 'spamguard'),
                'failed' => __('Failed', 'spamguard')
            )
        ));

        // Enqueue dashicons si no está cargado
        wp_enqueue_style('dashicons');
    }
    
    /**
     * ✅ ACTIVACIÓN DEL PLUGIN (CORREGIDA - ÚNICA VERSIÓN)
     */
    public function activate() {
        // ✅ SOLO operaciones rápidas en activación
        
        // Crear tablas (rápido)
        $this->create_tables();
        
        // Opciones por defecto (rápido)
        $this->set_default_options();
        
        // Programar limpieza (rápido)
        if (!wp_next_scheduled('spamguard_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'spamguard_daily_cleanup');
        }
        
        // ✅ NO hacer health check aquí
        // ✅ NO cargar módulos pesados
        // Se harán después, cuando el usuario visite el dashboard
        
        // Marcar como recién activado
        set_transient('spamguard_activated', true, 60);
        
        flush_rewrite_rules();
    }
    
    /**
     * ✅ Desactivación
     */
    public function deactivate() {
        wp_clear_scheduled_hook('spamguard_daily_cleanup');
        wp_clear_scheduled_hook('spamguard_auto_scan');
        flush_rewrite_rules();
    }
    
    /**
     * ✅ Crear tablas de base de datos
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabla de uso
        $table_usage = $wpdb->prefix . 'spamguard_usage';
        $sql_usage = "CREATE TABLE IF NOT EXISTS $table_usage (
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
        dbDelta($sql_usage);
        
        // Tabla de logs
        $table_logs = $wpdb->prefix . 'spamguard_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
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
        dbDelta($sql_logs);
        
        // Tabla de escaneos
        $table_scans = $wpdb->prefix . 'spamguard_scans';
        $sql_scans = "CREATE TABLE IF NOT EXISTS $table_scans (
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
        dbDelta($sql_scans);
        
        // Tabla de amenazas
        $table_threats = $wpdb->prefix . 'spamguard_threats';
        $sql_threats = "CREATE TABLE IF NOT EXISTS $table_threats (
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
        dbDelta($sql_threats);
        
        // Tabla de cuarentena
        $table_quarantine = $wpdb->prefix . 'spamguard_quarantine';
        $sql_quarantine = "CREATE TABLE IF NOT EXISTS $table_quarantine (
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
        dbDelta($sql_quarantine);
        
        // Tabla de vulnerabilidades
        $table_vulnerabilities = $wpdb->prefix . 'spamguard_vulnerabilities';
        $sql_vulnerabilities = "CREATE TABLE IF NOT EXISTS $table_vulnerabilities (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            component_type varchar(20) NOT NULL,
            component_slug varchar(255) NOT NULL,
            component_version varchar(50) NOT NULL,
            cve_id varchar(50),
            severity varchar(20) NOT NULL,
            title text NOT NULL,
            description text,
            vuln_type varchar(50),
            patched_in varchar(50),
            reference_urls longtext,
            detected_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY component_type (component_type),
            KEY severity (severity),
            KEY detected_at (detected_at)
        ) $charset_collate;";
        dbDelta($sql_vulnerabilities);

        // Tabla de listas (whitelist/blacklist)
        $table_lists = $wpdb->prefix . 'spamguard_lists';
        $sql_lists = "CREATE TABLE IF NOT EXISTS $table_lists (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            list_type varchar(20) NOT NULL,
            entry_type varchar(20) NOT NULL,
            value text NOT NULL,
            reason varchar(255) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            created_by bigint(20) DEFAULT 0,
            PRIMARY KEY (id),
            KEY list_type (list_type),
            KEY entry_type (entry_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql_lists);
    }

    /**
     * ✅ Opciones por defecto
     */
    private function set_default_options() {
        add_option('spamguard_api_url', SPAMGUARD_API_URL);
        add_option('spamguard_api_key', '');
        add_option('spamguard_sensitivity', 50);
        add_option('spamguard_auto_delete', true);
        add_option('spamguard_active_learning', true);
        add_option('spamguard_skip_registered', true);
        add_option('spamguard_use_honeypot', true);
        add_option('spamguard_time_check', 3);
        add_option('spamguard_antivirus_enabled', true);
        add_option('spamguard_auto_scan', 'weekly');
        add_option('spamguard_email_notifications', true);
        add_option('spamguard_notification_email', get_option('admin_email'));
        add_option('spamguard_version', SPAMGUARD_VERSION);
        add_option('spamguard_first_install', current_time('mysql'));
    }
    
    /**
     * ✅ Avisos del admin
     */
    public function admin_notices() {
        // Error 502 detectado
        if (isset($_GET['spamguard_error']) && $_GET['spamguard_error'] === '502') {
            ?>
            <div class="notice notice-error is-dismissible">
                <h3><?php _e('SpamGuard: API Connection Error', 'spamguard'); ?></h3>
                <p>
                    <?php _e('The API is temporarily unavailable (502 Error). This is usually temporary.', 'spamguard'); ?>
                </p>
                <p>
                    <strong><?php _e('What to do:', 'spamguard'); ?></strong>
                </p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php _e('Wait a few minutes and try again', 'spamguard'); ?></li>
                    <li><?php _e('The plugin will work locally until the API is available', 'spamguard'); ?></li>
                    <li><?php _e('Your site is still protected with local fallback rules', 'spamguard'); ?></li>
                </ul>
            </div>
            <?php
            return;
        }
        
        // Notice de activación
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
                        <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-primary button-hero">
                            <?php _e('Generate API Key', 'spamguard'); ?> →
                        </a>
                    </p>
                    <p style="color: #666; font-size: 13px;">
                        ℹ️ <?php _e('Note: If you see a 502 error, wait a moment and refresh. The API may be starting up.', 'spamguard'); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * ✅ Cargar traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'spamguard',
            false,
            dirname(SPAMGUARD_PLUGIN_BASENAME) . '/languages'
        );
    }
}

/**
 * ✅ FUNCIONES HELPER GLOBALES
 */
function spamguard() {
    return SpamGuard::get_instance();
}

function spamguard_api() {
    if (class_exists('SpamGuard_API_Client')) {
        return SpamGuard_API_Client::get_instance();
    }
    return null;
}

function spamguard_is_configured() {
    return SpamGuard::get_instance()->is_configured();
}

// ✅ INICIAR PLUGIN
spamguard();


