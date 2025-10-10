<?php
/**
 * SpamGuard Antivirus Dashboard v3.0 - CORREGIDO
 * 
 * ✅ JavaScript mejorado con polling correcto
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Antivirus_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor privado
    }
    
    /**
     * Renderizar dashboard
     */
    public function render() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        // ✅ Verificar configuración
        if (!SpamGuard::get_instance()->is_configured()) {
            $this->render_not_configured();
            return;
        }
        
        // Obtener datos
        $av_stats = SpamGuard_Antivirus_Results::get_antivirus_stats();
        $last_scan = SpamGuard_Antivirus_Results::get_last_scan();
        $active_threats = SpamGuard_Antivirus_Results::get_active_threats(10);
        
        // Calcular security score
        $score = 100;
        $threats_critical = $av_stats['threats_by_severity']['critical'];
        $threats_high = $av_stats['threats_by_severity']['high'];
        
        if ($threats_critical > 0) {
            $score = max(0, $score - ($threats_critical * 20));
        }
        if ($threats_high > 0) {
            $score = max(0, $score - ($threats_high * 10));
        }
        
        $score_color = $score >= 80 ? '#00a32a' : ($score >= 50 ? '#dba617' : '#d63638');
        $score_label = $score >= 80 ? 'Seguro' : ($score >= 50 ? 'Advertencia' : 'Peligro');
        
        ?>
        <div class="wrap spamguard-antivirus">
            <h1>
                <span class="dashicons dashicons-shield"></span>
                <?php _e('SpamGuard Antivirus', 'spamguard'); ?>
            </h1>
            
            <!-- Security Score -->
            <div class="security-score-card" style="background: #fff; padding: 30px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; gap: 30px;">
                    <div class="score-circle" style="width: 120px; height: 120px; position: relative;">
                        <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                            <circle cx="50" cy="50" r="45" fill="none" stroke="#f0f0f1" stroke-width="8"></circle>
                            <circle cx="50" cy="50" r="45" fill="none" stroke="<?php echo $score_color; ?>" stroke-width="8" 
                                    stroke-linecap="round"
                                    stroke-dasharray="283" 
                                    stroke-dashoffset="<?php echo 283 - (283 * $score / 100); ?>">
                            </circle>
                        </svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                            <div style="font-size: 32px; font-weight: bold; color: <?php echo $score_color; ?>;"><?php echo $score; ?></div>
                            <div style="font-size: 12px; color: #666;"><?php echo $score_label; ?></div>
                        </div>
                    </div>
                    <div>
                        <h2 style="margin: 0 0 10px 0;"><?php _e('Estado de Seguridad', 'spamguard'); ?></h2>
                        <p style="margin: 0; color: #666;">
                            <?php if ($score >= 80): ?>
                                ✅ <?php _e('Tu sitio está protegido', 'spamguard'); ?>
                            <?php elseif ($score >= 50): ?>
                                ⚠️ <?php _e('Algunas amenazas detectadas', 'spamguard'); ?>
                            <?php else: ?>
                                🚨 <?php _e('Acción requerida inmediatamente', 'spamguard'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #2271b1;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format($av_stats['total_scans']); ?></div>
                    <div style="color: #666; margin-top: 5px;"><?php _e('Total Scans', 'spamguard'); ?></div>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid <?php echo $av_stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>;">
                    <div style="font-size: 32px; font-weight: bold; color: <?php echo $av_stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo number_format($av_stats['active_threats']); ?></div>
                    <div style="color: #666; margin-top: 5px;"><?php _e('Active Threats', 'spamguard'); ?></div>
                </div>
            </div>
            
            <!-- Scan Panel -->
            <div style="background: #fff; padding: 30px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h2><?php _e('Escanear Archivos', 'spamguard'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0;">
                    <button type="button" class="button button-primary button-large spamguard-start-scan" data-scan-type="quick" style="height: auto; padding: 15px;">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Escaneo Rápido', 'spamguard'); ?>
                        <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">(~5-10 min)</p>
                    </button>
                    
                    <button type="button" class="button button-secondary button-large spamguard-start-scan" data-scan-type="full" style="height: auto; padding: 15px;">
                        <span class="dashicons dashicons-admin-site"></span>
                        <?php _e('Escaneo Completo', 'spamguard'); ?>
                        <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">(~15-30 min)</p>
                    </button>
                </div>
                
                <!-- Progress Bar -->
                <div id="scan-progress" style="display: none; margin-top: 20px; padding: 20px; background: #f0f6fc; border-radius: 8px;">
                    <h3 style="margin: 0 0 15px 0;">
                        <span class="dashicons dashicons-update spin"></span>
                        <?php _e('Escaneando...', 'spamguard'); ?>
                        <span id="scan-progress-percent" style="float: right;">0%</span>
                    </h3>
                    
                    <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden; margin-bottom: 15px;">
                        <div id="scan-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #2271b1, #72aee6); transition: width 0.3s;"></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div style="text-align: center; padding: 10px; background: #fff; border-radius: 4px;">
                            <div id="files-scanned" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                            <div style="font-size: 12px; color: #666;"><?php _e('Archivos', 'spamguard'); ?></div>
                        </div>
                        <div style="text-align: center; padding: 10px; background: #fff; border-radius: 4px;">
                            <div id="threats-found" style="font-size: 24px; font-weight: bold; color: #d63638;">0</div>
                            <div style="font-size: 12px; color: #666;"><?php _e('Amenazas', 'spamguard'); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if ($last_scan): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                    <h4 style="margin: 0 0 10px 0;"><?php _e('Último Escaneo', 'spamguard'); ?></h4>
                    <p style="margin: 0;">
                        <?php echo date('d/m/Y H:i', strtotime($last_scan->started_at)); ?> •
                        <?php echo number_format($last_scan->files_scanned); ?> archivos •
                        <?php if ($last_scan->threats_found > 0): ?>
                            <span style="color: #d63638; font-weight: bold;"><?php echo $last_scan->threats_found; ?> amenazas</span>
                        <?php else: ?>
                            <span style="color: #00a32a; font-weight: bold;">✓ Sin amenazas</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Threats List -->
            <?php if (!empty($active_threats)): ?>
            <div style="background: #fff; padding: 30px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h2><?php _e('Amenazas Detectadas', 'spamguard'); ?></h2>
                
                <?php foreach ($active_threats as $threat): 
                    $severity_info = SpamGuard_Antivirus_Results::format_severity($threat->severity);
                ?>
                <div style="padding: 20px; margin: 15px 0; border: 1px solid #ddd; border-left: 4px solid <?php echo $severity_info['color']; ?>; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-weight: bold; color: <?php echo $severity_info['color']; ?>;">
                            <?php echo $severity_info['icon']; ?> <?php echo $severity_info['label']; ?>
                        </span>
                        <span style="font-size: 12px; color: #666;">
                            <?php echo date('d/m/Y H:i', strtotime($threat->detected_at)); ?>
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <strong>📁 Archivo:</strong><br>
                        <code style="background: #f0f0f1; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                            <?php echo esc_html($threat->file_path); ?>
                        </code>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <strong>🔍 Firma:</strong> <?php echo esc_html($threat->signature_matched); ?>
                    </div>
                    
                    <div style="border-top: 1px solid #f0f0f1; padding-top: 15px;">
                        <button type="button" class="button button-primary spamguard-quarantine-threat" data-threat-id="<?php echo $threat->id; ?>">
                            <?php _e('Cuarentena', 'spamguard'); ?>
                        </button>
                        <button type="button" class="button button-secondary spamguard-ignore-threat" data-threat-id="<?php echo $threat->id; ?>">
                            <?php _e('Falso Positivo', 'spamguard'); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="background: #f0f9ff; padding: 60px 40px; margin: 20px 0; border-radius: 8px; text-align: center;">
                <div style="font-size: 64px; color: #00a32a; margin-bottom: 20px;">✓</div>
                <h3 style="color: #00a32a; margin: 0 0 10px 0;"><?php _e('Todo limpio', 'spamguard'); ?></h3>
                <p style="color: #666; margin: 0;"><?php _e('No se detectaron amenazas activas', 'spamguard'); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var currentScanId = null;
            var pollInterval = null;
            
            // Iniciar escaneo
            $('.spamguard-start-scan').on('click', function() {
                var scanType = $(this).data('scan-type');
                
                if (!confirm('<?php _e('Iniciar escaneo? Esto puede tardar varios minutos.', 'spamguard'); ?>')) {
                    return;
                }
                
                $('.spamguard-start-scan').prop('disabled', true);
                $('#scan-progress').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_start_scan',
                        nonce: '<?php echo wp_create_nonce('spamguard_ajax'); ?>',
                        scan_type: scanType
                    },
                    success: function(response) {
                        if (response.success) {
                            currentScanId = response.data.scan_id;
                            startPolling();
                        } else {
                            alert(response.data.message || 'Error');
                            $('.spamguard-start-scan').prop('disabled', false);
                            $('#scan-progress').hide();
                        }
                    },
                    error: function() {
                        alert('<?php _e('Error de conexión', 'spamguard'); ?>');
                        $('.spamguard-start-scan').prop('disabled', false);
                        $('#scan-progress').hide();
                    }
                });
            });
            
            // Polling de progreso
            function startPolling() {
                pollInterval = setInterval(function() {
                    if (!currentScanId) {
                        clearInterval(pollInterval);
                        return;
                    }
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'spamguard_scan_progress',
                            nonce: '<?php echo wp_create_nonce('spamguard_ajax'); ?>',
                            scan_id: currentScanId
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                
                                $('#scan-progress-bar').css('width', data.progress + '%');
                                $('#scan-progress-percent').text(data.progress + '%');
                                $('#files-scanned').text(data.files_scanned);
                                $('#threats-found').text(data.threats_found);
                                
                                if (data.status === 'completed') {
                                    clearInterval(pollInterval);
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                }
                            }
                        }
                    });
                }, 2000); // Cada 2 segundos
            }
            
            // Cuarentena
            $('.spamguard-quarantine-threat').on('click', function() {
                var threatId = $(this).data('threat-id');
                var $row = $(this).closest('div');
                
                if (!confirm('<?php _e('Mover a cuarentena?', 'spamguard'); ?>')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_quarantine_threat',
                        nonce: '<?php echo wp_create_nonce('spamguard_ajax'); ?>',
                        threat_id: threatId
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            });
            
            // Ignorar
            $('.spamguard-ignore-threat').on('click', function() {
                var threatId = $(this).data('threat-id');
                var $row = $(this).closest('div');
                
                if (!confirm('<?php _e('Marcar como falso positivo?', 'spamguard'); ?>')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_ignore_threat',
                        nonce: '<?php echo wp_create_nonce('spamguard_ajax'); ?>',
                        threat_id: threatId
                    },
                    success: function(response) {
                        $row.fadeOut();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderizar cuando no está configurado
     */
    private function render_not_configured() {
        ?>
        <div class="wrap">
            <h1><?php _e('SpamGuard Antivirus', 'spamguard'); ?></h1>
            
            <div style="background: #fff; padding: 60px 40px; margin: 20px 0; text-align: center; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <div style="font-size: 64px; margin-bottom: 20px;">🔒</div>
                <h2 style="color: #d63638; margin-top: 0;"><?php _e('Configuración Requerida', 'spamguard'); ?></h2>
                <p style="font-size: 16px; max-width: 600px; margin: 20px auto; color: #666;">
                    <?php _e('El antivirus requiere una API Key para funcionar. Genera tu clave gratuita para comenzar.', 'spamguard'); ?>
                </p>
                <p style="margin-top: 30px;">
                    <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-primary button-hero">
                        <?php _e('Generar API Key', 'spamguard'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}

