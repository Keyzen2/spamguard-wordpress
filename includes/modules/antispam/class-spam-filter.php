<?php
/**
 * SpamGuard Spam Filter
 * 
 * Filtro de spam para comentarios usando API v3.0
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Filter {
    
    private static $instance = null;
    private $api_client = null;
    
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
        $this->api_client = SpamGuard_API_Client::get_instance();
        
        // Hook principal de comentarios
        add_filter('preprocess_comment', array($this, 'check_comment'), 10);
        
        // Añadir honeypot y time check al formulario
        add_action('comment_form_after_fields', array($this, 'add_honeypot'));
        add_action('comment_form_logged_in_after', array($this, 'add_honeypot'));
        
        // Añadir campos custom a comment meta
        add_action('comment_post', array($this, 'save_custom_fields'), 10, 2);
    }
    
    /**
     * Verificar comentario
     * 
     * @param array $commentdata Datos del comentario
     * @return array Datos del comentario (modificados o no)
     */
    public function check_comment($commentdata) {
        // Skip si es admin
        if (current_user_can('moderate_comments')) {
            return $commentdata;
        }
        
        // Skip usuarios registrados (si está habilitado)
        if (get_option('spamguard_skip_registered', true) && is_user_logged_in()) {
            return $commentdata;
        }
        
        // Añadir honeypot y time check a los datos
        $commentdata['honeypot_field'] = isset($_POST['spamguard_honeypot']) ? $_POST['spamguard_honeypot'] : '';
        $commentdata['submit_time'] = isset($_POST['spamguard_time']) ? $_POST['spamguard_time'] : '';
        
        // Analizar con API v3.0
        $result = $this->api_client->analyze_comment($commentdata);
        
        // Guardar resultado en meta (para feedback posterior)
        $commentdata['spamguard_result'] = $result;
        
        // Si es spam, actuar según configuración
        if ($result['is_spam']) {
            $this->handle_spam($commentdata, $result);
        }
        
        return $commentdata;
    }
    
    /**
     * Manejar comentario spam
     * 
     * @param array $commentdata Datos del comentario
     * @param array $result Resultado del análisis
     */
    private function handle_spam($commentdata, $result) {
        $auto_delete = get_option('spamguard_auto_delete', true);
        
        if ($auto_delete) {
            // Auto-eliminar (bloquear)
            wp_die(
                '<h1>' . __('Comment Blocked', 'spamguard') . '</h1>' .
                '<p>' . __('Your comment has been identified as spam and was blocked.', 'spamguard') . '</p>' .
                '<p>' . sprintf(
                    __('Confidence: %s | Risk Level: %s', 'spamguard'),
                    '<strong>' . round($result['confidence'] * 100) . '%</strong>',
                    '<strong>' . esc_html($result['risk_level']) . '</strong>'
                ) . '</p>' .
                '<p><a href="javascript:history.back()">&larr; ' . __('Go Back', 'spamguard') . '</a></p>',
                __('Spam Detected', 'spamguard'),
                array('response' => 403, 'back_link' => true)
            );
        } else {
            // Marcar como spam (moderar)
            add_filter('pre_comment_approved', function() {
                return 'spam';
            });
        }
    }
    
    /**
     * Añadir honeypot y time check al formulario
     */
    public function add_honeypot() {
        if (!get_option('spamguard_use_honeypot', true)) {
            return;
        }
        
        ?>
        <!-- SpamGuard Honeypot -->
        <p style="display: none !important;">
            <label for="spamguard_honeypot"><?php _e('Leave this field empty', 'spamguard'); ?></label>
            <input type="text" name="spamguard_honeypot" id="spamguard_honeypot" value="" tabindex="-1" autocomplete="off" />
        </p>
        
        <!-- SpamGuard Time Check -->
        <input type="hidden" name="spamguard_time" value="<?php echo time(); ?>" />
        <?php
    }
    
    /**
     * Guardar campos custom en comment meta
     * 
     * @param int $comment_id ID del comentario
     * @param int|string $comment_approved Estado de aprobación
     */
    public function save_custom_fields($comment_id, $comment_approved) {
        // Guardar resultado de SpamGuard
        if (isset($_POST['spamguard_result'])) {
            add_comment_meta($comment_id, 'spamguard_result', $_POST['spamguard_result'], true);
        }
    }
    
    /**
     * Obtener resultado de análisis desde comment meta
     * 
     * @param int $comment_id ID del comentario
     * @return array|null Resultado o null
     */
    public static function get_comment_result($comment_id) {
        return get_comment_meta($comment_id, 'spamguard_result', true);
    }
}