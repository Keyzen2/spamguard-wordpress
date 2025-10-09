<?php
if (!defined('ABSPATH')) exit;

// Guardar configuracion
if (isset($_POST['spamguard_save_settings']) && check_admin_referer('spamguard_settings')) {
    update_option('spamguard_api_key', sanitize_text_field($_POST['spamguard_api_key']));
    update_option('spamguard_api_url', esc_url_raw($_POST['spamguard_api_url']));
    update_option('spamguard_auto_delete', isset($_POST['spamguard_auto_delete']));
    update_option('spamguard_sensitivity', floatval($_POST['spamguard_sensitivity']));
    update_option('spamguard_learning_enabled', isset($_POST['spamguard_learning_enabled']));
    update_option('spamguard_skip_registered_users', isset($_POST['spamguard_skip_registered_users']));
    
    echo '<div class="notice notice-success"><p>Configuracion guardada correctamente.</p></div>';
}

$api_key = get_option('spamguard_api_key', '');
$api_url = get_option('spamguard_api_url', SPAMGUARD_API_URL);
$auto_delete = get_option('spamguard_auto_delete', false);
$sensitivity = get_option('spamguard_sensitivity', 0.5);
$learning_enabled = get_option('spamguard_learning_enabled', true);
$skip_registered = get_option('spamguard_skip_registered_users', false);
?>

<div class="wrap spamguard-admin">
    <h1>Configuracion de SpamGuard AI</h1>
    
    <div class="spamguard-settings-container">
        <div class="spamguard-settings-main">
            <form method="post" action="">
                <?php wp_nonce_field('spamguard_settings'); ?>
                
                <div class="spamguard-card">
                    <h2>Configuracion de la API</h2>
                    
                    <?php if (empty($api_key)): ?>
                    <div class="spamguard-setup-wizard">
                        <h3>Bienvenido a SpamGuard AI</h3>
                        <p>Para comenzar, necesitas una API Key. Es gratis y toma solo 30 segundos.</p>
                        
                        <button type="button" id="auto-register-btn" class="button button-primary button-hero">
                            Generar API Key Automaticamente
                        </button>
                        
                        <p class="description">
                            O introduce tu API Key manualmente si ya tienes una:
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="spamguard_api_key">API Key</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="spamguard_api_key" 
                                       name="spamguard_api_key" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text"
                                       placeholder="sg_...">
                                <?php if (!empty($api_key)): ?>
                                <p class="description">
                                    Sitio registrado correctamente
                                    <button type="button" id="test-connection-btn" class="button button-small">
                                        Probar Conexion
                                    </button>
                                </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="spamguard_api_url">URL de la API</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="spamguard_api_url" 
                                       name="spamguard_api_url" 
                                       value="<?php echo esc_attr($api_url); ?>" 
                                       class="regular-text"
                                       readonly
                                       style="background-color: #f0f0f1; cursor: not-allowed;">
                                <p class="description">URL del servidor de SpamGuard AI.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="spamguard-card">
                    <h2>Configuracion de Filtrado</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="spamguard_sensitivity">Sensibilidad</label>
                            </th>
                            <td>
                                <input type="range" 
                                       id="spamguard_sensitivity" 
                                       name="spamguard_sensitivity" 
                                       min="0" 
                                       max="1" 
                                       step="0.1" 
                                       value="<?php echo esc_attr($sensitivity); ?>">
                                <span id="sensitivity-value"><?php echo round($sensitivity * 100); ?>%</span>
                                <p class="description">
                                    Umbral de confianza para marcar como spam. Mas alto = mas estricto.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="spamguard_auto_delete">Eliminar spam automaticamente</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="spamguard_auto_delete" 
                                           name="spamguard_auto_delete" 
                                           <?php checked($auto_delete); ?>>
                                    Mover automaticamente a spam
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="spamguard_skip_registered_users">Usuarios registrados</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="spamguard_skip_registered_users" 
                                           name="spamguard_skip_registered_users" 
                                           <?php checked($skip_registered); ?>>
                                    No analizar comentarios de usuarios registrados
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="spamguard-card">
                    <h2>Aprendizaje Automatico</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="spamguard_learning_enabled">Aprendizaje activo</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="spamguard_learning_enabled" 
                                           name="spamguard_learning_enabled" 
                                           <?php checked($learning_enabled); ?>>
                                    Aprender de mis correcciones
                                </label>
                                <p class="description">
                                    El modelo mejora cuando corriges errores.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="spamguard_save_settings" class="button button-primary button-large">
                        Guardar Cambios
                    </button>
                </p>
            </form>
        </div>
        
        <div class="spamguard-settings-sidebar">
            <div class="spamguard-card">
                <h3>Consejos</h3>
                <ul>
                    <li>Deja el aprendizaje activo habilitado</li>
                    <li>Revisa ocasionalmente la carpeta de spam</li>
                    <li>El plugin mejora con el uso</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#spamguard_sensitivity').on('input', function() {
        $('#sensitivity-value').text(Math.round($(this).val() * 100) + '%');
    });
    
    $('#test-connection-btn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Probando...');
        
        $.post(ajaxurl, {
            action: 'spamguard_test_connection',
            nonce: spamguardData.nonce
        }).done(function(response) {
            if (response.success) {
                alert('Conexion exitosa');
            } else {
                alert('Error: ' + response.data.message);
            }
        }).always(function() {
            btn.prop('disabled', false).text('Probar Conexion');
        });
    });
    
    $('#auto-register-btn').on('click', function() {
        if (!confirm('Esto registrara tu sitio y generara una API Key. Continuar?')) {
            return;
        }
        
        const btn = $(this);
        btn.prop('disabled', true).text('Registrando...');
        
        $.post(ajaxurl, {
            action: 'spamguard_register_site',
            nonce: spamguardData.nonce
        }).done(function(response) {
            if (response.success) {
                $('#spamguard_api_key').val(response.data.api_key);
                alert('Exito! ' + response.data.message + '\n\nAPI Key: ' + response.data.api_key);
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        }).always(function() {
            btn.prop('disabled', false).text('Generar API Key Automaticamente');
        });
    });
});
</script>