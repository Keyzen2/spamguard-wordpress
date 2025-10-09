<?php
/**
 * SpamGuard Settings Page
 * Página de configuración del plugin
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener valores actuales
$api_url = get_option('spamguard_api_url', 'https://spamguard.up.railway.app');
$api_key = get_option('spamguard_api_key', '');
$sensitivity = get_option('spamguard_sensitivity', 50);
$auto_delete = get_option('spamguard_auto_delete', true);
$active_learning = get_option('spamguard_active_learning', true);
$skip_registered = get_option('spamguard_skip_registered', true);
$use_honeypot = get_option('spamguard_use_honeypot', true);
$time_check = get_option('spamguard_time_check', 3);

// Procesar guardado de configuración
if (isset($_POST['spamguard_save_settings']) && check_admin_referer('spamguard_settings')) {
    
    // Guardar API URL
    if (isset($_POST['spamguard_api_url'])) {
        update_option('spamguard_api_url', esc_url_raw($_POST['spamguard_api_url']));
    }
    
    // Guardar API Key (si se modificó)
    if (isset($_POST['spamguard_api_key']) && !empty($_POST['spamguard_api_key'])) {
        update_option('spamguard_api_key', sanitize_text_field($_POST['spamguard_api_key']));
    }
    
    // Guardar configuraciones
    update_option('spamguard_sensitivity', intval($_POST['spamguard_sensitivity']));
    update_option('spamguard_auto_delete', isset($_POST['spamguard_auto_delete']));
    update_option('spamguard_active_learning', isset($_POST['spamguard_active_learning']));
    update_option('spamguard_skip_registered', isset($_POST['spamguard_skip_registered']));
    update_option('spamguard_use_honeypot', isset($_POST['spamguard_use_honeypot']));
    update_option('spamguard_time_check', intval($_POST['spamguard_time_check']));
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Configuración guardada correctamente.</strong></p></div>';
    
    // Recargar valores
    $api_url = get_option('spamguard_api_url');
    $api_key = get_option('spamguard_api_key');
    $sensitivity = get_option('spamguard_sensitivity');
    $auto_delete = get_option('spamguard_auto_delete');
    $active_learning = get_option('spamguard_active_learning');
    $skip_registered = get_option('spamguard_skip_registered');
    $use_honeypot = get_option('spamguard_use_honeypot');
    $time_check = get_option('spamguard_time_check');
}

// Test de conexión (si se solicita)
$connection_test = null;
if (isset($_GET['test_connection']) && $_GET['test_connection'] === '1') {
    $api = SpamGuard_API::get_instance();
    $connection_test = $api->test_connection();
}
?>

<div class="wrap spamguard-settings">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('Configuración de SpamGuard', 'spamguard'); ?>
    </h1>
    
    <p class="description">
        <?php _e('Configura SpamGuard para proteger tu sitio contra spam y amenazas.', 'spamguard'); ?>
    </p>
    
    <!-- Test de conexión (si se ejecutó) -->
    <?php if ($connection_test !== null): ?>
        <div class="notice notice-<?php echo $connection_test['success'] ? 'success' : 'error'; ?> is-dismissible">
            <p>
                <strong><?php echo esc_html($connection_test['message']); ?></strong>
            </p>
            <?php if ($connection_test['success'] && isset($connection_test['data'])): ?>
                <details>
                    <summary>Ver detalles de la API</summary>
                    <pre style="background: #f0f0f1; padding: 10px; border-radius: 4px; font-size: 12px; overflow: auto;">
<?php echo esc_html(json_encode($connection_test['data'], JSON_PRETTY_PRINT)); ?>
                    </pre>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="spamguard-settings-grid" style="display: grid; grid-template-columns: 1fr 350px; gap: 20px; margin-top: 20px;">
        
        <!-- Formulario principal -->
        <div class="spamguard-settings-main">
            
            <form method="post" action="">
                <?php wp_nonce_field('spamguard_settings'); ?>
                
                <!-- 🔐 Configuración de la API -->
                <div class="spamguard-card" style="background: #fff; padding: 20px; margin-bottom: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">
                        🔐 <?php _e('Configuración de la API', 'spamguard'); ?>
                    </h2>
                    
                    <?php if (empty($api_key)): ?>
                        <!-- No hay API Key - Mostrar wizard de registro -->
                        <div class="spamguard-setup-wizard" style="background: #f0f6fc; border: 2px solid #0c5c99; padding: 25px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">🚀</div>
                            <h3 style="margin-top: 0; color: #0c5c99;"><?php _e('¡Bienvenido a SpamGuard!', 'spamguard'); ?></h3>
                            <p style="font-size: 15px; line-height: 1.6;">
                                <?php _e('Para comenzar a proteger tu sitio, necesitas una API Key.', 'spamguard'); ?><br>
                                <strong><?php _e('Es gratis y solo toma 30 segundos.', 'spamguard'); ?></strong>
                            </p>
                            
                            <div style="margin: 20px 0;">
                                <button type="button" id="spamguard-auto-register" class="button button-primary button-hero" style="padding: 12px 30px; font-size: 16px;">
                                    ✨ <?php _e('Generar API Key Automáticamente', 'spamguard'); ?>
                                </button>
                            </div>
                            
                            <div id="register-progress" style="display: none; margin-top: 15px;">
                                <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                                <p style="color: #666; margin-top: 10px;"><?php _e('Registrando tu sitio...', 'spamguard'); ?></p>
                            </div>
                            
                            <div id="register-result"></div>
                            
                            <p style="color: #666; font-size: 13px; margin-top: 20px;">
                                <?php _e('O introduce tu API Key manualmente si ya tienes una:', 'spamguard'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="spamguard_api_url"><?php _e('URL de la API', 'spamguard'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="spamguard_api_url" 
                                       name="spamguard_api_url" 
                                       value="<?php echo esc_attr($api_url); ?>" 
                                       class="regular-text" 
                                       <?php echo empty($api_key) ? '' : 'readonly style="background: #f0f0f1;"'; ?>>
                                <p class="description">
                                    <?php _e('URL del servidor de SpamGuard. No modificar a menos que uses un servidor propio.', 'spamguard'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="spamguard_api_key"><?php _e('API Key', 'spamguard'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="spamguard_api_key" 
                                       name="spamguard_api_key" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text"
                                       placeholder="Tu API Key aparecerá aquí">
                                
                                <?php if (!empty($api_key)): ?>
                                    <p class="description" style="color: #00a32a;">
                                        ✅ <strong><?php _e('API Key configurada correctamente', 'spamguard'); ?></strong>
                                    </p>
                                <?php else: ?>
                                    <p class="description">
                                        <?php _e('Se generará automáticamente al hacer clic en el botón de arriba.', 'spamguard'); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <?php if (!empty($api_key)): ?>
                        <tr>
                            <th scope="row"><?php _e('Estado de la Conexión', 'spamguard'); ?></th>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=spamguard-settings&test_connection=1'); ?>" 
                                   class="button button-secondary">
                                    🔍 <?php _e('Probar Conexión', 'spamguard'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <?php if (!empty($api_key)): ?>
                
                <!-- ⚙️ Configuración de Filtrado -->
                <div class="spamguard-card" style="background: #fff; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">
                        ⚙️ <?php _e('Configuración de Filtrado', 'spamguard'); ?>
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="spamguard_sensitivity"><?php _e('Sensibilidad', 'spamguard'); ?></label>
                            </th>
                            <td>
                                <input type="range" 
                                       id="spamguard_sensitivity" 
                                       name="spamguard_sensitivity" 
                                       value="<?php echo esc_attr($sensitivity); ?>" 
                                       min="0" 
                                       max="100" 
                                       step="5"
                                       style="width: 300px;">
                                <span id="sensitivity-value" style="font-weight: bold; margin-left: 10px; color: #2271b1;">
                                    <?php echo $sensitivity; ?>%
                                </span>
                                <p class="description">
                                    <?php _e('Umbral de confianza para marcar como spam. Más alto = más estricto.', 'spamguard'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Eliminar spam automáticamente', 'spamguard'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="spamguard_auto_delete" 
                                           name="spamguard_auto_delete" 
                                           <?php checked($auto_delete); ?>>
                                    <?php _e('Bloquear automáticamente (no aparecerá en la carpeta de spam)', 'spamguard'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Si está deshabilitado, el spam se moverá a la carpeta de spam para revisión.', 'spamguard'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Usuarios registrados', 'spamguard'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="spamguard_skip_registered" 
                                           name="spamguard_skip_registered" 
                                           <?php checked($skip_registered); ?>>
                                    <?php _e('No analizar comentarios de usuarios registrados', 'spamguard'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Usar Honeypot', 'spamguard'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="spamguard_use_honeypot" 
                                           name="spamguard_use_honeypot" 
                                           <?php checked($use_honeypot); ?>>
                                    <?php _e('Añadir campo oculto para atrapar bots', 'spamguard'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Técnica efectiva para detectar bots automáticos.', 'spamguard'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="spamguard_time_check"><?php _e('Control de tiempo', 'spamguard'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="spamguard_time_check" 
                                       name="spamguard_time_check" 
                                       value="<?php echo esc_attr($time_check); ?>" 
                                       min="0" 
                                       max="60" 
                                       class="small-text">
                                <?php _e('segundos', 'spamguard'); ?>
                                <p class="description">
                                    <?php _e('Tiempo mínimo antes de permitir envío de comentario (0 para deshabilitar)', 'spamguard'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- 🤖 Aprendizaje Automático -->
                <div class="spamguard-card" style="background: #fff; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">
                        🤖 <?php _e('Aprendizaje Automático', 'spamguard'); ?>
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Aprendizaje activo', 'spamguard'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="spamguard_active_learning" 
                                           name="spamguard_active_learning" 
                                           <?php checked($active_learning); ?>>
                                    <?php _e('Aprender de mis correcciones', 'spamguard'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('El modelo mejora automáticamente cuando corriges errores. Recomendado.', 'spamguard'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Botón de guardar -->
                <p class="submit">
                    <button type="submit" name="spamguard_save_settings" class="button button-primary button-large">
                        💾 <?php _e('Guardar Cambios', 'spamguard'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=spamguard'); ?>" class="button button-secondary button-large">
                        ← <?php _e('Volver al Dashboard', 'spamguard'); ?>
                    </a>
                </p>
                
                <?php endif; ?>
                
            </form>
            
        </div>
        
        <!-- Sidebar -->
        <div class="spamguard-settings-sidebar">
            
            <?php if (!empty($api_key)): ?>
            <!-- Estado del sistema -->
            <div class="spamguard-card" style="background: #fff; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0;">📊 <?php _e('Estado del Sistema', 'spamguard'); ?></h3>
                
                <div style="padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span><?php _e('API Key:', 'spamguard'); ?></span>
                        <span style="color: #00a32a; font-weight: bold;">✅ Configurada</span>
                    </div>
                </div>
                
                <div style="padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span><?php _e('Versión:', 'spamguard'); ?></span>
                        <span style="font-weight: bold;"><?php echo SPAMGUARD_VERSION; ?></span>
                    </div>
                </div>
                
                <div style="padding: 10px 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span><?php _e('Aprendizaje:', 'spamguard'); ?></span>
                        <span style="color: <?php echo $active_learning ? '#00a32a' : '#d63638'; ?>; font-weight: bold;">
                            <?php echo $active_learning ? '✅ Activo' : '❌ Desactivado'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Consejos -->
            <div class="spamguard-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0;">💡 <?php _e('Consejos', 'spamguard'); ?></h3>
                <ul style="line-height: 1.8; color: #50575e;">
                    <li><?php _e('Mantén el aprendizaje activo habilitado para mejor precisión', 'spamguard'); ?></li>
                    <li><?php _e('Revisa ocasionalmente la carpeta de spam por falsos positivos', 'spamguard'); ?></li>
                    <li><?php _e('Ajusta la sensibilidad según tu volumen de spam', 'spamguard'); ?></li>
                    <li><?php _e('El honeypot es muy efectivo contra bots', 'spamguard'); ?></li>
                </ul>
            </div>
            
        </div>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Slider de sensibilidad
    $('#spamguard_sensitivity').on('input', function() {
        $('#sensitivity-value').text($(this).val() + '%');
    });
    
    // Registro automático
    $('#spamguard-auto-register').on('click', function() {
        var $btn = $(this);
        var $progress = $('#register-progress');
        var $result = $('#register-result');
        
        if (!confirm('<?php _e('Esto registrará tu sitio y generará una API Key. ¿Continuar?', 'spamguard'); ?>')) {
            return;
        }
        
        $btn.hide();
        $progress.show();
        $result.empty();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'spamguard_register_site',
                nonce: '<?php echo wp_create_nonce('spamguard_ajax'); ?>'
            },
            success: function(response) {
                $progress.hide();
                
                if (response.success) {
                    // Éxito
                    $result.html(
                        '<div style="background: #d4edda; border: 2px solid #28a745; padding: 20px; border-radius: 8px; text-align: center;">' +
                        '<div style="font-size: 48px; margin-bottom: 10px;">🎉</div>' +
                        '<h3 style="color: #155724; margin-top: 0;">¡Éxito!</h3>' +
                        '<p style="color: #155724;">' + response.data.message + '</p>' +
                        '<p><strong>API Key:</strong><br><code style="background: #fff; padding: 8px; display: block; margin: 10px 0; word-break: break-all;">' + 
                        response.data.api_key + 
                        '</code></p>' +
                        '<p style="margin-top: 15px;"><small>Guardando configuración...</small></p>' +
                        '</div>'
                    );
                    
                    // Rellenar el campo de API key
                    $('#spamguard_api_key').val(response.data.api_key);
                    
                    // Recargar después de 2 segundos
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                    
                } else {
                    // Error
                    $result.html(
                        '<div style="background: #f8d7da; border: 2px solid #dc3545; padding: 15px; border-radius: 8px; text-align: center;">' +
                        '<p style="color: #721c24; margin: 0;"><strong>❌ Error:</strong> ' + response.data.message + '</p>' +
                        '</div>'
                    );
                    $btn.show();
                }
            },
            error: function() {
                $progress.hide();
                $result.html(
                    '<div style="background: #f8d7da; border: 2px solid #dc3545; padding: 15px; border-radius: 8px; text-align: center;">' +
                    '<p style="color: #721c24; margin: 0;"><strong>❌ Error de conexión.</strong> Por favor, inténtalo de nuevo.</p>' +
                    '</div>'
                );
                $btn.show();
            }
        });
    });
    
});
</script>

<style>
.spamguard-settings .spamguard-card {
    border-radius: 8px;
}

.spamguard-settings .form-table th {
    width: 200px;
    padding: 15px 10px 15px 0;
}

.spamguard-settings .form-table td {
    padding: 15px 10px;
}

.spamguard-settings h2 {
    font-size: 18px;
    margin-bottom: 15px;
}

.spamguard-settings .description {
    font-size: 13px;
    line-height: 1.5;
}
</style>
