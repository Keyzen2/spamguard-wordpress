<?php
/**
 * SpamGuard Antivirus Dashboard
 * Dashboard del antivirus con validación de API
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

// ✅ VALIDACIÓN: Verificar si está configurado
if (!SpamGuard::get_instance()->is_configured()) {
    ?>
    <div class="wrap spamguard-admin spamguard-antivirus">
        <h1>
            <span class="dashicons dashicons-shield-alt"></span>
            <?php _e('SpamGuard - Antivirus Scanner', 'spamguard'); ?>
        </h1>
        
        <div class="notice notice-error" style="padding: 30px; margin: 20px 0; text-align: center; border-left: 4px solid #d63638;">
            <div style="font-size: 64px; margin-bottom: 20px;">🔒</div>
            <h2 style="margin-top: 0; color: #d63638;">
                <?php _e('⚠️ Configuración Requerida', 'spamguard'); ?>
            </h2>
            <p style="font-size: 16px; line-height: 1.6; max-width: 600px; margin: 20px auto;">
                <?php _e('El antivirus de SpamGuard requiere una API Key activa para funcionar.', 'spamguard'); ?><br>
                <?php _e('Por favor, configura tu API Key primero para comenzar a proteger tu sitio.', 'spamguard'); ?>
            </p>
            <p style="margin-top: 30px;">
                <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-primary button-hero">
                    ✨ <?php _e('Generar API Key Gratis', 'spamguard'); ?>
                </a>
            </p>
            <p style="color: #666; font-size: 14px; margin-top: 20px;">
                <?php _e('Solo toma 30 segundos • 100% Gratis • 1,000 escaneos/mes', 'spamguard'); ?>
            </p>
        </div>
    </div>
    <?php
    return; // ✅ Detener ejecución aquí
}

// ✅ Si llegamos aquí, está configurado - continuar normalmente
$av_stats = SpamGuard_Antivirus_Results::get_antivirus_stats();
$last_scan = SpamGuard_Antivirus_Results::get_last_scan();
$active_threats = SpamGuard_Antivirus_Results::get_active_threats(10);
?>

<div class="wrap spamguard-admin spamguard-antivirus">
    <h1>
        <span class="dashicons dashicons-shield-alt"></span>
        <?php _e('SpamGuard - Antivirus Scanner', 'spamguard'); ?>
    </h1>
    
    <!-- Resumen de seguridad -->
    <div class="spamguard-security-overview">
        <div class="security-score-card">
            <?php
            $score = 100;
            $threats_critical = $av_stats['threats_by_severity']['critical'];
            $threats_high = $av_stats['threats_by_severity']['high'];
            
            // Calcular score basado en amenazas
            if ($threats_critical > 0) {
                $score = max(0, $score - ($threats_critical * 20));
            }
            if ($threats_high > 0) {
                $score = max(0, $score - ($threats_high * 10));
            }
            
            $score_color = $score >= 80 ? '#00a32a' : ($score >= 50 ? '#dba617' : '#d63638');
            $score_label = $score >= 80 ? 'Seguro' : ($score >= 50 ? 'Advertencia' : 'Peligro');
            ?>
            
            <div class="score-circle" style="--score: <?php echo $score; ?>; --score-color: <?php echo $score_color; ?>">
                <svg viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" class="score-bg"></circle>
                    <circle cx="50" cy="50" r="45" class="score-progress" 
                            style="stroke-dashoffset: <?php echo 283 - (283 * $score / 100); ?>"></circle>
                </svg>
                <div class="score-content">
                    <div class="score-number"><?php echo $score; ?></div>
                    <div class="score-label"><?php echo $score_label; ?></div>
                </div>
            </div>
            
            <div class="score-details">
                <h3><?php _e('Estado de Seguridad', 'spamguard'); ?></h3>
                <p class="score-description">
                    <?php if ($score >= 80): ?>
                        <?php _e('✅ Tu sitio está protegido. No se detectaron amenazas críticas.', 'spamguard'); ?>
                    <?php elseif ($score >= 50): ?>
                        <?php _e('⚠️ Se detectaron algunas amenazas. Revisa y toma acción.', 'spamguard'); ?>
                    <?php else: ?>
                        <?php _e('🚨 ¡Atención! Se detectaron amenazas críticas. Acción inmediata requerida.', 'spamguard'); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Grid principal -->
    <div class="spamguard-antivirus-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-top: 30px;">
        
        <!-- Panel de escaneo -->
        <div class="spamguard-card scan-panel">
            <h2>
                <span class="dashicons dashicons-search"></span>
                <?php _e('Escanear Archivos', 'spamguard'); ?>
            </h2>
            
            <div class="scan-options" style="margin: 20px 0;">
                <div style="margin-bottom: 20px;">
                    <button type="button" class="button button-primary button-hero scan-btn" data-scan-type="quick" style="width: 100%; margin-bottom: 10px;">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Escaneo Rápido', 'spamguard'); ?>
                    </button>
                    <p class="description" style="margin: 5px 0 0 0;">
                        <?php _e('Escanea plugins y themes activos (5-10 minutos)', 'spamguard'); ?>
                    </p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <button type="button" class="button button-secondary button-large scan-btn" data-scan-type="full" style="width: 100%; margin-bottom: 10px;">
                        <span class="dashicons dashicons-admin-site"></span>
                        <?php _e('Escaneo Completo', 'spamguard'); ?>
                    </button>
                    <p class="description" style="margin: 5px 0 0 0;">
                        <?php _e('Escanea todo WordPress incluyendo core (15-30 minutos)', 'spamguard'); ?>
                    </p>
                </div>
            </div>
            
            <!-- Barra de progreso -->
            <div id="scan-progress-container" style="display: none; margin-top: 20px; padding: 20px; background: #f0f6fc; border-radius: 8px; border-left: 4px solid #2271b1;">
                <div class="scan-progress-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #2271b1;">
                        <span class="dashicons dashicons-update" style="animation: spin 2s linear infinite;"></span>
                        <?php _e('Escaneando...', 'spamguard'); ?>
                    </h3>
                    <span class="scan-percentage" style="font-size: 18px; font-weight: bold; color: #2271b1;">0%</span>
                </div>
                
                <div class="scan-progress-bar" style="background: #ddd; height: 30px; border-radius: 15px; overflow: hidden; margin-bottom: 15px;">
                    <div class="scan-progress-fill" style="width: 0%; height: 100%; background: linear-gradient(90deg, #2271b1, #72aee6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; transition: width 0.3s ease;"></div>
                </div>
                
                <div class="scan-progress-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="stat" style="text-align: center; padding: 10px; background: white; border-radius: 6px;">
                        <div class="label" style="font-size: 12px; color: #666; margin-bottom: 5px;"><?php _e('Archivos:', 'spamguard'); ?></div>
                        <div class="value" id="files-scanned" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                    </div>
                    <div class="stat" style="text-align: center; padding: 10px; background: white; border-radius: 6px;">
                        <div class="label" style="font-size: 12px; color: #666; margin-bottom: 5px;"><?php _e('Amenazas:', 'spamguard'); ?></div>
                        <div class="value threat" id="threats-found" style="font-size: 24px; font-weight: bold; color: #d63638;">0</div>
                    </div>
                </div>
                
                <p class="scan-status-message" id="scan-status-message" style="text-align: center; color: #666; font-style: italic; margin: 0;">
                    <?php _e('Inicializando escaneo...', 'spamguard'); ?>
                </p>
            </div>
            
            <!-- Último escaneo -->
            <?php if ($last_scan): ?>
            <div class="last-scan-info" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 6px; border-left: 4px solid #666;">
                <h4 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Último Escaneo', 'spamguard'); ?>
                </h4>
                <p style="margin: 0; line-height: 1.8;">
                    <strong><?php echo date('d/m/Y H:i', strtotime($last_scan->started_at)); ?></strong><br>
                    📁 <?php echo number_format($last_scan->files_scanned); ?> archivos escaneados<br>
                    <?php if ($last_scan->threats_found > 0): ?>
                        <span class="threat-badge" style="display: inline-block; padding: 4px 10px; background: #d63638; color: white; border-radius: 4px; font-size: 12px; font-weight: bold; margin-top: 5px;">
                            ⚠️ <?php echo $last_scan->threats_found; ?> amenazas encontradas
                        </span>
                    <?php else: ?>
                        <span class="safe-badge" style="display: inline-block; padding: 4px 10px; background: #00a32a; color: white; border-radius: 4px; font-size: 12px; font-weight: bold; margin-top: 5px;">
                            ✓ Sin amenazas
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Estadísticas del antivirus -->
        <div class="spamguard-card">
            <h2>
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('Estadísticas', 'spamguard'); ?>
            </h2>
            
            <div class="stats-grid-av" style="display: grid; gap: 15px; margin: 20px 0;">
                <div class="stat-item" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #2271b1;">
                    <div class="stat-icon" style="font-size: 32px;">🔍</div>
                    <div class="stat-content">
                        <div class="stat-value" style="font-size: 28px; font-weight: bold; color: #2271b1;"><?php echo number_format($av_stats['total_scans']); ?></div>
                        <div class="stat-label" style="font-size: 13px; color: #666;"><?php _e('Escaneos Totales', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <div class="stat-item <?php echo $av_stats['active_threats'] > 0 ? 'danger' : 'success'; ?>" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid <?php echo $av_stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>;">
                    <div class="stat-icon" style="font-size: 32px;">🦠</div>
                    <div class="stat-content">
                        <div class="stat-value" style="font-size: 28px; font-weight: bold; color: <?php echo $av_stats['active_threats'] > 0 ? '#d63638' : '#00a32a'; ?>;">
                            <?php echo number_format($av_stats['active_threats']); ?>
                        </div>
                        <div class="stat-label" style="font-size: 13px; color: #666;"><?php _e('Amenazas Activas', 'spamguard'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Amenazas por severidad -->
            <?php if ($av_stats['active_threats'] > 0): ?>
            <div class="threats-by-severity" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <h4 style="margin-top: 0; margin-bottom: 15px;"><?php _e('Por Severidad', 'spamguard'); ?></h4>
                <div class="severity-list">
                    <?php if ($av_stats['threats_by_severity']['critical'] > 0): ?>
                    <div class="severity-item critical" style="display: flex; justify-content: space-between; align-items: center; padding: 10px; margin-bottom: 8px; background: #fef7f7; border-left: 4px solid #d63638; border-radius: 4px;">
                        <span class="severity-badge" style="font-weight: 600; color: #d63638;">🔴 Crítico</span>
                        <span class="severity-count" style="font-weight: bold; color: #d63638;"><?php echo $av_stats['threats_by_severity']['critical']; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($av_stats['threats_by_severity']['high'] > 0): ?>
                    <div class="severity-item high" style="display: flex; justify-content: space-between; align-items: center; padding: 10px; margin-bottom: 8px; background: #fff7ed; border-left: 4px solid #f56e28; border-radius: 4px;">
                        <span class="severity-badge" style="font-weight: 600; color: #f56e28;">🟠 Alto</span>
                        <span class="severity-count" style="font-weight: bold; color: #f56e28;"><?php echo $av_stats['threats_by_severity']['high']; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($av_stats['threats_by_severity']['medium'] > 0): ?>
                    <div class="severity-item medium" style="display: flex; justify-content: space-between; align-items: center; padding: 10px; margin-bottom: 8px; background: #fffef0; border-left: 4px solid #dba617; border-radius: 4px;">
                        <span class="severity-badge" style="font-weight: 600; color: #dba617;">🟡 Medio</span>
                        <span class="severity-count" style="font-weight: bold; color: #dba617;"><?php echo $av_stats['threats_by_severity']['medium']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Amenazas detectadas -->
        <div class="spamguard-card threats-panel" style="grid-column: 1 / -1;">
            <h2>
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Amenazas Detectadas', 'spamguard'); ?>
            </h2>
            
            <?php if (empty($active_threats)): ?>
                <div class="no-threats" style="text-align: center; padding: 60px 40px; background: #f0f9ff; border-radius: 8px; margin-top: 20px;">
                    <div class="no-threats-icon" style="font-size: 80px; color: #00a32a; margin-bottom: 20px; animation: pulse 2s ease-in-out infinite;">✓</div>
                    <h3 style="color: #00a32a; margin: 0 0 10px 0;"><?php _e('¡Todo está limpio!', 'spamguard'); ?></h3>
                    <p style="color: #666; font-size: 16px; margin: 0;"><?php _e('No se detectaron amenazas activas. Tu sitio está seguro.', 'spamguard'); ?></p>
                </div>
            <?php else: ?>
                <div class="threats-list" style="margin-top: 20px;">
                    <?php foreach ($active_threats as $threat): 
                        $severity_info = SpamGuard_Antivirus_Results::format_severity($threat->severity);
                    ?>
                    <div class="threat-item" style="padding: 20px; margin-bottom: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid <?php echo $severity_info['color']; ?>; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div class="threat-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span class="threat-severity" style="font-weight: bold; color: <?php echo $severity_info['color']; ?>;">
                                <?php echo $severity_info['icon']; ?> <?php echo $severity_info['label']; ?>
                            </span>
                            <span class="threat-date" style="font-size: 12px; color: #666;">
                                <?php echo date('d/m/Y H:i', strtotime($threat->detected_at)); ?>
                            </span>
                        </div>
                        
                        <div class="threat-file" style="margin-bottom: 10px;">
                            <strong style="font-size: 12px; color: #666;">📁 Archivo:</strong><br>
                            <code style="display: block; background: #f0f0f1; padding: 8px; border-radius: 4px; font-size: 12px; word-break: break-all; margin-top: 5px;">
                                <?php echo esc_html($threat->file_path); ?>
                            </code>
                        </div>
                        
                        <div class="threat-signature" style="margin-bottom: 15px;">
                            <strong style="font-size: 12px; color: #666;">🔍 Firma detectada:</strong>
                            <span style="display: block; margin-top: 5px; color: #333;">
                                <?php echo esc_html($threat->signature_matched); ?>
                            </span>
                        </div>
                        
                        <div class="threat-actions" style="display: flex; gap: 10px; padding-top: 15px; border-top: 1px solid #f0f0f1;">
                            <button type="button" class="button button-primary spamguard-quarantine-threat" data-threat-id="<?php echo $threat->id; ?>">
                                🗄️ <?php _e('Cuarentena', 'spamguard'); ?>
                            </button>
                            <button type="button" class="button button-secondary spamguard-ignore-threat" data-threat-id="<?php echo $threat->id; ?>">
                                👁️ <?php _e('Marcar como Falso Positivo', 'spamguard'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.spamguard-antivirus-grid {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.scan-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.scan-btn:active {
    transform: translateY(0);
}
</style>
