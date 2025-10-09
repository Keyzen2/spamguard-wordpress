<?php
/**
 * SpamGuard Spam Filter
 * Filtro de comentarios integrado con API v3.0
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Filter {
    
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
        // Hook principal para interceptar comentarios
        add_filter('preprocess_comment', array($this, 'check_comment'), 10);
        
        // Añadir honeypot y time check al formulario
        add_action('comment_form_after_fields', array($this, 'add_honeypot'));
        add_action('comment_form_logged_in_after', array($this, 'add_honeypot'));
        
        // Guardar metadata del comentario
        add_action('comment_post', array($this, 'save_comment_meta'), 10, 2);
    }
    
    /**
     * ✅ Verificar comentario antes de guardarlo
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
        
        // Preparar datos para el análisis
        $comment_for_analysis = array(
            'comment_content' => $commentdata['comment_content'],
            'comment_author' => $commentdata['comment_author'],
            'comment_author_email' => $commentdata['comment_author_email'],
            'comment_author_url' => isset($commentdata['comment_author_url']) ? $commentdata['comment_author_url'] : '',
            'comment_author_IP' => $commentdata['comment_author_IP'],
            'comment_post_ID' => $commentdata['comment_post_ID'],
            
            // Honeypot y time check
            'honeypot_field' => isset($_POST['spamguard_honeypot']) ? $_POST['spamguard_honeypot'] : '',
            'submit_time' => isset($_POST['spamguard_time']) ? $_POST['spamguard_time'] : ''
        );
        
        // Analizar con API
        $api = SpamGuard_API::get_instance();
        $result = $api->analyze_comment($comment_for_analysis);
        
        // Guardar resultado en el comentario (para después)
        $commentdata['spamguard_result'] = $result;
        
        // Si es spam, manejar según configuración
        if (isset($result['is_spam']) && $result['is_spam']) {
            $this->handle_spam($commentdata, $result);
        }
        
        return $commentdata;
    }
    
    /**
     * ✅ Manejar comentario detectado como spam
     */
    private function handle_spam($commentdata, $result) {
        global $wpdb;
        
        // Guardar en logs
        $table = $wpdb->prefix . 'spamguard_logs';
        
        $wpdb->insert($table, array(
            'comment_author' => $commentdata['comment_author'],
            'comment_author_email' => $commentdata['comment_author_email'],
            'comment_content' => $commentdata['comment_content'],
            'is_spam' => 1,
            'confidence' => isset($result['confidence']) ? $result['confidence'] : 0,
            'created_at' => current_time('mysql')
        ));
        
        $auto_delete = get_option('spamguard_auto_delete', true);
        
        if ($auto_delete) {
            // Bloquear completamente (mostrar mensaje de error)
            wp_die(
                $this->get_spam_blocked_message($result),
                __('Comentario Bloqueado - Spam Detectado', 'spamguard'),
                array(
                    'response' => 403,
                    'back_link' => true
                )
            );
        } else {
            // Marcar como spam (irá a la carpeta de spam)
            add_filter('pre_comment_approved', function() {
                return 'spam';
            }, 999);
        }
    }
    
    /**
     * ✅ Mensaje de spam bloqueado
     */
    private function get_spam_blocked_message($result) {
        $confidence = isset($result['confidence']) ? round($result['confidence'] * 100) : 0;
        $risk_level = isset($result['risk_level']) ? $result['risk_level'] : 'medium';
        
        $message = '<div style="max-width: 600px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
        $message .= '<div style="text-align: center; margin-bottom: 20px;">';
        $message .= '<div style="font-size: 64px; margin-bottom: 10px;">🛡️</div>';
        $message .= '<h1 style="color: #d63638; margin: 0;">' . __('Comentario Bloqueado', 'spamguard') . '</h1>';
        $message .= '</div>';
        
        $message .= '<p style="font-size: 16px; line-height: 1.6; color: #50575e;">';
        $message .= __('Tu comentario ha sido identificado como spam y fue bloqueado por nuestro sistema de seguridad.', 'spamguard');
        $message .= '</p>';
        
        $message .= '<div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin: 20px 0;">';
        $message .= '<strong>' . __('Detalles de la detección:', 'spamguard') . '</strong><br>';
        $message .= __('Confianza:', 'spamguard') . ' <strong>' . $confidence . '%</strong><br>';
        $message .= __('Nivel de riesgo:', 'spamguard') . ' <strong>' . ucfirst($risk_level) . '</strong>';
        $message .= '</div>';
        
        if (isset($result['reasons']) && !empty($result['reasons'])) {
            $message .= '<div style="margin: 20px 0;">';
            $message .= '<strong>' . __('Razones:', 'spamguard') . '</strong>';
            $message .= '<ul style="margin: 10px 0; padding-left: 20px;">';
            foreach (array_slice($result['reasons'], 0, 3) as $reason) {
                $message .= '<li style="color: #646970;">' . esc_html($reason) . '</li>';
            }
            $message .= '</ul>';
            $message .= '</div>';
        }
        
        $message .= '<p style="color: #646970; font-size: 14px; margin-top: 20px;">';
        $message .= __('Si crees que esto es un error, por favor contacta al administrador del sitio.', 'spamguard');
        $message .= '</p>';
        
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="javascript:history.back()" style="display: inline-block; padding: 12px 24px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px;">';
        $message .= '← ' . __('Volver', 'spamguard');
        $message .= '</a>';
        $message .= '</div>';
        
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * ✅ Añadir honeypot y time check al formulario
     */
    public function add_honeypot() {
        if (!get_option('spamguard_use_honeypot', true)) {
            return;
        }
        
        ?>
        <!-- SpamGuard Security Fields -->
        <p style="position: absolute !important; left: -9999px !important; top: -9999px !important;">
            <label for="spamguard_honeypot"><?php _e('Leave this field empty', 'spamguard'); ?></label>
            <input type="text" 
                   name="spamguard_honeypot" 
                   id="spamguard_honeypot" 
                   value="" 
                   tabindex="-1" 
                   autocomplete="off" 
                   aria-hidden="true" />
        </p>
        
        <input type="hidden" name="spamguard_time" value="<?php echo time(); ?>" />
        <?php
    }
    
    /**
     * ✅ Guardar metadata del comentario
     */
    public function save_comment_meta($comment_id, $comment_approved) {
        global $wpdb;
        
        // Obtener el comentario
        $comment = get_comment($comment_id);
        
        if (!$comment) {
            return;
        }
        
        // Si hay resultado de SpamGuard guardado temporalmente
        if (isset($_POST['spamguard_result_temp'])) {
            $result = json_decode(stripslashes($_POST['spamguard_result_temp']), true);
            
            if ($result) {
                // Guardar como comment meta
                add_comment_meta($comment_id, 'spamguard_result', $result, true);
                add_comment_meta($comment_id, 'spamguard_is_spam', $result['is_spam'], true);
                add_comment_meta($comment_id, 'spamguard_confidence', $result['confidence'], true);
                
                // Guardar en tabla de logs
                $table = $wpdb->prefix . 'spamguard_logs';
                
                $wpdb->insert($table, array(
                    'comment_id' => $comment_id,
                    'comment_author' => $comment->comment_author,
                    'comment_author_email' => $comment->comment_author_email,
                    'comment_content' => $comment->comment_content,
                    'is_spam' => $result['is_spam'] ? 1 : 0,
                    'category' => isset($result['category']) ? $result['category'] : 'ham',
                    'confidence' => $result['confidence'],
                    'risk_level' => isset($result['risk_level']) ? $result['risk_level'] : 'low',
                    'flags' => isset($result['flags']) ? json_encode($result['flags']) : null,
                    'request_id' => isset($result['request_id']) ? $result['request_id'] : null,
                    'created_at' => current_time('mysql')
                ));
            }
        }
    }
    
    /**
     * ✅ Obtener resultado de análisis de un comentario
     */
    public static function get_comment_result($comment_id) {
        return get_comment_meta($comment_id, 'spamguard_result', true);
    }
}
