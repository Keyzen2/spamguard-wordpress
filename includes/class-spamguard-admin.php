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
        // ✅ Menú principal → Dashboard Unificado
        add_menu_page(
            __('SpamGuard', 'spamguard'),
            __('SpamGuard', 'spamguard'),
            'manage_options',
            'spamguard', // ← Dashboard unificado
            array($this, 'render_unified_dashboard'),
            'dashicons-shield',
            30
        );
        
        // Submenú: Dashboard Unificado
        add_submenu_page(
            'spamguard',
            __('Dashboard', 'spamguard'),
            __('Dashboard', 'spamguard'),
            'manage_options',
            'spamguard',
            array($this, 'render_unified_dashboard')
        );
        
        // Submenú: Anti-Spam
        add_submenu_page(
            'spamguard',
            __('Anti-Spam', 'spamguard'),
            __('Anti-Spam', 'spamguard'),
            'manage_options',
            'spamguard-antispam',
            array($this, 'render_antispam_page')
        );

        // Submenú: Antivirus (dashboard propio)
        add_submenu_page(
            'spamguard',
            __('Antivirus', 'spamguard'),
            __('Antivirus', 'spamguard'),
            'manage_options',
            'spamguard-antivirus',
            array($this, 'render_antivirus_page')
        );

        // ✅ Submenú: Vulnerabilities (dashboard propio)
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
     * ✅ NUEVO: Renderizar Dashboard Unificado
     */
    public function render_unified_dashboard() {
        // Lazy load del controlador
        if (!class_exists('SpamGuard_Unified_Dashboard')) {
            require_once SPAMGUARD_PLUGIN_DIR . 'includes/dashboard/class-unified-dashboard.php';
        }
        
        if (class_exists('SpamGuard_Unified_Dashboard')) {
            SpamGuard_Unified_Dashboard::get_instance()->render();
        }
    }
    
    /**
     * ✅ Renderizar Vulnerabilities
     */
    public function render_vulnerabilities_page() {
        // Cargar template
        $template = SPAMGUARD_PLUGIN_DIR . 'templates/vulnerabilities/dashboard.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="wrap"><h1>Vulnerabilities</h1><p>Template not found</p></div>';
        }
    }

    /**
     * ✅ Renderizar página de Anti-Spam
     */
    public function render_antispam_page() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spamguard'));
        }

        // Cargar nuevo dashboard completo
        $template = SPAMGUARD_PLUGIN_DIR . 'templates/antispam-dashboard.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Anti-Spam Dashboard', 'spamguard') . '</h1>';
            echo '<p>' . __('Template not found.', 'spamguard') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * ✅ Renderizar dashboard principal (lazy load del controlador)
     */
    public function render_main_dashboard() {
        // ✅ Cargar el controlador SOLO cuando se necesita
        if (!class_exists('SpamGuard_Dashboard_Controller')) {
            require_once SPAMGUARD_PLUGIN_DIR . 'includes/dashboard/class-dashboard-controller.php';
        }
        
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
     * ✅ Renderizar página de antivirus (lazy load)
     */
    public function render_antivirus_page() {
        // ✅ Cargar SOLO cuando se necesita
        if (!class_exists('SpamGuard_Antivirus_Dashboard')) {
            require_once SPAMGUARD_PLUGIN_DIR . 'includes/dashboard/class-antivirus-dashboard.php';
        }
        
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
     * Renderizar página de settings
     */
    public function render_settings_page() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spamguard'));
        }
        
        // Procesar generación de API key
        if (isset($_POST['spamguard_generate_api_key']) && check_admin_referer('spamguard_generate_api_key')) {
            $this->handle_generate_api_key();
        }
        
        // Procesar guardado de settings
        if (isset($_POST['spamguard_save_settings']) && check_admin_referer('spamguard_save_settings')) {
            $this->save_settings();
        }
        
        // Obtener valores actuales
        $api_url = get_option('spamguard_api_url', SPAMGUARD_API_URL);
        $api_key = get_option('spamguard_api_key', '');
        
        $sensitivity = get_option('spamguard_sensitivity', 50);
        $auto_delete = get_option('spamguard_auto_delete', true);
        $active_learning = get_option('spamguard_active_learning', true);
        $skip_registered = get_option('spamguard_skip_registered', true);
        $use_honeypot = get_option('spamguard_use_honeypot', true);
        $time_check = get_option('spamguard_time_check', 3);
        
        $antivirus_enabled = get_option('spamguard_antivirus_enabled', true);
        $auto_scan = get_option('spamguard_auto_scan', 'weekly');
        $email_notifications = get_option('spamguard_email_notifications', true);
        $notification_email = get_option('spamguard_notification_email', get_option('admin_email'));
        
        // Obtener info de la cuenta si hay API key
        $account_info = null;
        $usage_info = null;
        
        if (!empty($api_key) && class_exists('SpamGuard_API_Client')) {
            $api_client = SpamGuard_API_Client::get_instance();
            $account_info = $api_client->get_account_info();
            $usage_info = $api_client->get_usage();
        }
        
        // Renderizar el formulario de settings
        ?>
        <div class="wrap spamguard-settings">
            <h1>
                <span class="dashicons dashicons-admin-settings" style="color: #2271b1;"></span>
                <?php _e('SpamGuard Settings', 'spamguard'); ?>
            </h1>
            
            <?php settings_errors('spamguard_messages'); ?>
            
            <?php if (!empty($api_key) && $usage_info): ?>
            <!-- Card de información de cuenta -->
            <div class="spamguard-account-card" style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;"><?php _e('Your Account', 'spamguard'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #2271b1;">
                            <?php echo number_format($usage_info['current_month']['requests']); ?>
                        </div>
                        <div style="color: #666;">
                            <?php _e('Requests This Month', 'spamguard'); ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #50575e;">
                            <?php echo number_format($usage_info['limit']); ?>
                        </div>
                        <div style="color: #666;">
                            <?php _e('Monthly Limit', 'spamguard'); ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: <?php echo $usage_info['percentage_used'] > 80 ? '#d63638' : '#00a32a'; ?>;">
                            <?php echo number_format($usage_info['percentage_used'], 1); ?>%
                        </div>
                        <div style="color: #666;">
                            <?php _e('Used', 'spamguard'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Progress bar -->
                <div style="background: #f0f0f1; height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: <?php echo $usage_info['percentage_used'] > 80 ? '#d63638' : '#2271b1'; ?>; height: 100%; width: <?php echo min($usage_info['percentage_used'], 100); ?>%;"></div>
                </div>
                
                <?php if ($usage_info['percentage_used'] > 80): ?>
                <p style="color: #d63638; margin-top: 10px;">
                    <strong><?php _e('Warning:', 'spamguard'); ?></strong>
                    <?php _e('You are approaching your monthly limit.', 'spamguard'); ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('spamguard_save_settings'); ?>
                
                <table class="form-table">
                    
                    <!-- ======================================== -->
                    <!-- API CONFIGURATION -->
                    <!-- ======================================== -->
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('API Configuration', 'spamguard'); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="spamguard_api_url"><?php _e('API URL', 'spamguard'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   name="spamguard_api_url" 
                                   id="spamguard_api_url" 
                                   value="<?php echo esc_attr($api_url); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Default:', 'spamguard'); ?> 
                                <code><?php echo SPAMGUARD_API_URL; ?></code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="spamguard_api_key"><?php _e('API Key', 'spamguard'); ?></label>
                        </th>
                        <td>
                            <?php if (empty($api_key)): ?>
                                <!-- NO HAY API KEY - Mostrar formulario de generación -->
                                <div style="background: #f0f6fc; border: 1px solid #0c5c99; padding: 20px; border-radius: 4px;">
                                    <h3 style="margin-top: 0;">
                                        <?php _e('Generate Your Free API Key', 'spamguard'); ?> 🎉
                                    </h3>
                                    <p><?php _e('Get started with 1,000 free requests per month. No credit card required!', 'spamguard'); ?></p>
                                    
                                    <?php wp_nonce_field('spamguard_generate_api_key'); ?>
                                    
                                    <p>
                                        <label for="admin_email"><?php _e('Email:', 'spamguard'); ?></label><br>
                                        <input type="email" 
                                               name="admin_email" 
                                               id="admin_email" 
                                               value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                                               class="regular-text" 
                                               required />
                                    </p>
                                    
                                    <p>
                                        <button type="submit" 
                                                name="spamguard_generate_api_key" 
                                                class="button button-primary button-hero">
                                            <?php _e('Generate API Key', 'spamguard'); ?> →
                                        </button>
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- YA HAY API KEY - Mostrar campo -->
                                <input type="text" 
                                       name="spamguard_api_key" 
                                       id="spamguard_api_key" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text" 
                                       readonly />
                                <p class="description">
                                    ✅ <?php _e('Your API key is configured and working.', 'spamguard'); ?>
                                    <br>
                                    <a href="#" onclick="if(confirm('<?php _e('Are you sure you want to clear the API key?', 'spamguard'); ?>')) { jQuery('#spamguard_api_key').val('').prop('readonly', false); } return false;">
                                        <?php _e('Clear API Key', 'spamguard'); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <?php if (!empty($api_key)): ?>
                    
                    <!-- ======================================== -->
                    <!-- ANTI-SPAM SETTINGS -->
                    <!-- ======================================== -->
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('Anti-Spam Settings', 'spamguard'); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="spamguard_sensitivity"><?php _e('Sensitivity', 'spamguard'); ?></label>
                        </th>
                        <td>
                            <input type="range" 
                                   name="spamguard_sensitivity" 
                                   id="spamguard_sensitivity" 
                                   value="<?php echo esc_attr($sensitivity); ?>" 
                                   min="0" 
                                   max="100" 
                                   step="5" />
                            <span id="sensitivity_value"><?php echo $sensitivity; ?>%</span>
                            <p class="description">
                                <?php _e('Higher values = more aggressive spam detection', 'spamguard'); ?>
                            </p>
                            <script>
                            jQuery(document).ready(function($) {
                                $('#spamguard_sensitivity').on('input', function() {
                                    $('#sensitivity_value').text($(this).val() + '%');
                                });
                            });
                            </script>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto-delete Spam', 'spamguard'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="spamguard_auto_delete" 
                                       value="1" 
                                       <?php checked($auto_delete); ?> />
                                <?php _e('Automatically block spam comments (they will not appear in spam folder)', 'spamguard'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Active Learning', 'spamguard'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="spamguard_active_learning" 
                                       value="1" 
                                       <?php checked($active_learning); ?> />
                                <?php _e('Send feedback to improve the model (recommended)', 'spamguard'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Skip Registered Users', 'spamguard'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="spamguard_skip_registered" 
                                       value="1" 
                                       <?php checked($skip_registered); ?> />
                                <?php _e('Don\'t check comments from logged-in users', 'spamguard'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Use Honeypot', 'spamguard'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="spamguard_use_honeypot" 
                                       value="1" 
                                       <?php checked($use_honeypot); ?> />
                                <?php _e('Add hidden field to catch bots', 'spamguard'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="spamguard_time_check"><?php _e('Time Check', 'spamguard'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="spamguard_time_check" 
                                   id="spamguard_time_check" 
                                   value="<?php echo esc_attr($time_check); ?>" 
                                   min="0" 
                                   max="60" 
                                   step="1" 
                                   class="small-text" />
                            <?php _e('seconds', 'spamguard'); ?>
                            <p class="description">
                                <?php _e('Minimum time before allowing comment submission (0 to disable)', 'spamguard'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- ======================================== -->
                    <!-- ANTIVIRUS SETTINGS -->
                    <!-- ======================================== -->
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('Antivirus Settings', 'spamguard'); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Enable Antivirus', 'spamguard'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="spamguard_antivirus_enabled" 
                                       value="1" 
                                       <?php checked($antivirus_enabled); ?> />
                                <?php _e('Enable malware scanning and threat detection', 'spamguard'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="spamguard_auto_scan"><?php _e('Automatic Scanning', 'spamguard'); ?></label>
                        </th>
                        <td>
                            <select name="spamguard_auto_scan" id="spamguard_auto_scan">
                                <option value="disabled" <?php selected($auto_scan, 'disabled'); ?>>
                                    <?php _e('Disabled', 'spamguard'); ?>
                                </option>
                                <option value="daily" <?php selected($auto_scan, 'daily'); ?>>
                                    <?php _e('Daily', 'spamguard'); ?>
                                </option>
                                <option value="weekly" <?php selected($auto_scan, 'weekly'); ?>>
                                    <?php _e('Weekly (Recommended)', 'spamguard'); ?>
                                </option>
                                <option value="monthly" <?php selected($auto_scan, 'monthly'); ?>>
                                    <?php _e('Monthly', 'spamguard'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Schedule automatic security scans', 'spamguard'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Email Notifications', 'spamguard'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="spamguard_email_notifications" 
                                       value="1" 
                                       <?php checked($email_notifications); ?> />
                                <?php _e('Send email alerts when threats are detected', 'spamguard'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="spamguard_notification_email"><?php _e('Notification Email', 'spamguard'); ?></label>
                        </th>
                        <td>
                            <input type="email" 
                                   name="spamguard_notification_email" 
                                   id="spamguard_notification_email" 
                                   value="<?php echo esc_attr($notification_email); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Email address to receive security alerts', 'spamguard'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <?php endif; // Fin de if (!empty($api_key)) ?>
                    
                </table>
                
                <?php if (!empty($api_key)): ?>
                <p class="submit">
                    <button type="submit" name="spamguard_save_settings" class="button button-primary">
                        <?php _e('Save Changes', 'spamguard'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=spamguard'); ?>" class="button">
                        <?php _e('Back to Dashboard', 'spamguard'); ?>
                    </a>
                </p>
                <?php endif; ?>
                
            </form>
        </div>
        <?php
    }
    
    /**
     * Manejar generación de API key
     */
    private function handle_generate_api_key() {
        $email = isset($_POST['admin_email']) ? sanitize_email($_POST['admin_email']) : '';
        
        if (empty($email)) {
            add_settings_error(
                'spamguard_messages',
                'email_required',
                __('Email is required', 'spamguard'),
                'error'
            );
            return;
        }
        
        if (!class_exists('SpamGuard_API_Client')) {
            add_settings_error(
                'spamguard_messages',
                'api_client_missing',
                __('API Client not available', 'spamguard'),
                'error'
            );
            return;
        }
        
        $api_client = SpamGuard_API_Client::get_instance();
        $result = $api_client->register_and_generate_key($email);
        
        if (is_wp_error($result)) {
            add_settings_error(
                'spamguard_messages',
                'generation_failed',
                $result->get_error_message(),
                'error'
            );
        } elseif (isset($result['success']) && $result['success']) {
            // Guardar API key
            update_option('spamguard_api_key', $result['api_key']);
            
            add_settings_error(
                'spamguard_messages',
                'generation_success',
                __('API Key generated successfully! You can now use SpamGuard.', 'spamguard') . 
                '<br><br><strong>' . __('Your API Key:', 'spamguard') . '</strong> ' . 
                '<code>' . esc_html($result['api_key']) . '</code>',
                'success'
            );
            
            // Redirect para evitar resubmit del formulario
            wp_redirect(admin_url('admin.php?page=spamguard-settings&key_generated=1'));
            exit;
        } else {
            add_settings_error(
                'spamguard_messages',
                'generation_failed',
                __('Failed to generate API key. Please try again.', 'spamguard'),
                'error'
            );
        }
    }
    
    /**
     * Guardar configuración
     */
    private function save_settings() {
        // API Configuration
        if (isset($_POST['spamguard_api_url'])) {
            update_option('spamguard_api_url', esc_url_raw($_POST['spamguard_api_url']));
        }
        
        if (isset($_POST['spamguard_api_key']) && class_exists('SpamGuard_API_Helper')) {
            $api_key = SpamGuard_API_Helper::sanitize_api_key($_POST['spamguard_api_key']);
            update_option('spamguard_api_key', $api_key);
        }
        
        // Anti-Spam Settings
        update_option('spamguard_sensitivity', intval($_POST['spamguard_sensitivity']));
        update_option('spamguard_auto_delete', isset($_POST['spamguard_auto_delete']));
        update_option('spamguard_active_learning', isset($_POST['spamguard_active_learning']));
        update_option('spamguard_skip_registered', isset($_POST['spamguard_skip_registered']));
        update_option('spamguard_use_honeypot', isset($_POST['spamguard_use_honeypot']));
        update_option('spamguard_time_check', intval($_POST['spamguard_time_check']));
        
        // Antivirus Settings
        update_option('spamguard_antivirus_enabled', isset($_POST['spamguard_antivirus_enabled']));
        update_option('spamguard_auto_scan', sanitize_text_field($_POST['spamguard_auto_scan']));
        update_option('spamguard_email_notifications', isset($_POST['spamguard_email_notifications']));
        update_option('spamguard_notification_email', sanitize_email($_POST['spamguard_notification_email']));
        
        // Mensaje de éxito
        add_settings_error(
            'spamguard_messages',
            'settings_updated',
            __('Settings saved successfully', 'spamguard'),
            'success'
        );
    }
}



