<?php
if (!defined('ABSPATH')) exit;

// Obtener estadísticas
$av_stats = SpamGuard_Antivirus_Results::get_antivirus_stats();
$is_configured = SpamGuard_Core::is_configured();
$last_scan = SpamGuard_Antivirus_Results::get_last_scan();
$active_threats = SpamGuard_Antivirus_Results::get_active_threats(10);
?>

<div class="wrap spamguard-admin spamguard-antivirus">
    <h1>
        <span class="dashicons dashicons-shield-alt"></span>
        <?php _e('SpamGuard - Antivirus Scanner', 'spamguard'); ?>
    </h1>
    
    <?php if (!$is_configured): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('¡Configuración necesaria!', 'spamguard'); ?></strong>
                <?php _e('Para usar el antivirus, configura tu API Key primero.', 'spamguard'); ?>
                <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-primary">
                    <?php _e('Configurar Ahora', 'spamguard'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Resumen de seguridad -->
    <div class="spamguard-security-overview">
        <div class="security-score-card">
            <?php
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
                        <?php _e('Tu sitio está protegido. No se detectaron amenazas críticas.', 'spamguard'); ?>
                    <?php elseif ($score >= 50): ?>
                        <?php _e('Se detectaron algunas amenazas. Revisa y toma acción.', 'spamguard'); ?>
                    <?php else: ?>
                        <?php _e('¡Atención! Se detectaron amenazas críticas. Acción inmediata requerida.', 'spamguard'); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Grid principal -->
    <div class="spamguard-antivirus-grid">
        
        <!-- Panel de escaneo -->
        <div class="spamguard-card scan-panel">
            <h2><?php _e('Escanear Archivos', 'spamguard'); ?></h2>
            
            <div class="scan-options">
                <button type="button" class="button button-primary button-hero scan-btn" data-scan-type="quick">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Escaneo Rápido', 'spamguard'); ?>
                </button>
                <p class="description"><?php _e('Escanea plugins y themes (5-10 minutos)', 'spamguard'); ?></p>
                
                <button type="button" class="button button-secondary button-large scan-btn" data-scan-type="full">
                    <span class="dashicons dashicons-admin-site"></span>
                    <?php _e('Escaneo Completo', 'spamguard'); ?>
                </button>
                <p class="description"><?php _e('Escanea todo WordPress (15-30 minutos)', 'spamguard'); ?></p>
            </div>
            
            <!-- Barra de progreso -->
            <div id="scan-progress-container" style="display: none;">
                <div class="scan-progress-header">
                    <h3><?php _e('Escaneando...', 'spamguard'); ?></h3>
                    <span class="scan-percentage">0%</span>
                </div>
                
                <div class="scan-progress-bar">
                    <div class="scan-progress-fill" style="width: 0%"></div>
                </div>
                
                <div class="scan-progress-stats">
                    <div class="stat">
                        <span class="label"><?php _e('Archivos:', 'spamguard'); ?></span>
                        <span class="value" id="files-scanned">0</span>
                    </div>
                    <div class="stat">
                        <span class="label"><?php _e('Amenazas:', 'spamguard'); ?></span>
                        <span class="value threat" id="threats-found">0</span>
                    </div>
                </div>
                
                <p class="scan-status-message" id="scan-status-message">
                    <?php _e('Inicializando escaneo...', 'spamguard'); ?>
                </p>
            </div>
            
            <!-- Último escaneo -->
            <?php if ($last_scan): ?>
            <div class="last-scan-info">
                <h4><?php _e('Último Escaneo', 'spamguard'); ?></h4>
                <p>
                    <strong><?php echo date('d/m/Y H:i', strtotime($last_scan->started_at)); ?></strong><br>
                    <?php echo $last_scan->files_scanned; ?> archivos escaneados<br>
                    <?php if ($last_scan->threats_found > 0): ?>
                        <span class="threat-badge"><?php echo $last_scan->threats_found; ?> amenazas encontradas</span>
                    <?php else: ?>
                        <span class="safe-badge">✓ Sin amenazas</span>
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Estadísticas del antivirus -->
        <div class="spamguard-card">
            <h2><?php _e('Estadísticas', 'spamguard'); ?></h2>
            <div class="stats-grid-av">
                <div class="stat-item">
                    <div class="stat-icon">🔍</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($av_stats['total_scans']); ?></div>
                        <div class="stat-label"><?php _e('Escaneos Totales', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <div class="stat-item <?php echo $av_stats['active_threats'] > 0 ? 'danger' : 'success'; ?>">
                    <div class="stat-icon">🦠</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($av_stats['active_threats']); ?></div>
                        <div class="stat-label"><?php _e('Amenazas Activas', 'spamguard'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Amenazas por severidad -->
            <?php if ($av_stats['active_threats'] > 0): ?>
            <div class="threats-by-severity">
                <h4><?php _e('Por Severidad', 'spamguard'); ?></h4>
                <div class="severity-list">
                    <?php if ($av_stats['threats_by_severity']['critical'] > 0): ?>
                    <div class="severity-item critical">
                        <span class="severity-badge">🔴 Crítico</span>
                        <span class="severity-count"><?php echo $av_stats['threats_by_severity']['critical']; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($av_stats['threats_by_severity']['high'] > 0): ?>
                    <div class="severity-item high">
                        <span class="severity-badge">🟠 Alto</span>
                        <span class="severity-count"><?php echo $av_stats['threats_by_severity']['high']; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($av_stats['threats_by_severity']['medium'] > 0): ?>
                    <div class="severity-item medium">
                        <span class="severity-badge">🟡 Medio</span>
                        <span class="severity-count"><?php echo $av_stats['threats_by_severity']['medium']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Amenazas detectadas -->
        <div class="spamguard-card threats-panel">
            <h2><?php _e('Amenazas Detectadas', 'spamguard'); ?></h2>
            
            <?php if (empty($active_threats)): ?>
                <div class="no-threats">
                    <div class="no-threats-icon">✓</div>
                    <p><?php _e('No se detectaron amenazas activas', 'spamguard'); ?></p>
                    <p class="description"><?php _e('Tu sitio está limpio y seguro', 'spamguard'); ?></p>
                </div>
            <?php else: ?>
                <div class="threats-list">
                    <?php foreach ($active_threats as $threat): 
                        $severity_info = SpamGuard_Antivirus_Results::format_severity($threat->severity);
                    ?>
                    <div class="threat-item">
                        <div class="threat-header">
                            <span class="threat-severity" style="color: <?php echo $severity_info['color']; ?>">
                                <?php echo $severity_info['icon']; ?> <?php echo $severity_info['label']; ?>
                            </span>
                            <span class="threat-date"><?php echo date('d/m/Y H:i', strtotime($threat->detected_at)); ?></span>
                        </div>
                        <div class="threat-file">
                            <code><?php echo esc_html($threat->file_path); ?></code>
                        </div>
                        <div class="threat-signature">
                            <strong><?php _e('Firma:', 'spamguard'); ?></strong>
                            <?php echo esc_html($threat->signature_matched); ?>
                        </div>
                        <div class="threat-actions">
                            <button type="button" class="button button-small quarantine-btn" data-threat-id="<?php echo $threat->id; ?>">
                                <?php _e('Cuarentena', 'spamguard'); ?>
                            </button>
                            <button type="button" class="button button-small button-link-delete ignore-btn" data-threat-id="<?php echo $threat->id; ?>">
                                <?php _e('Ignorar', 'spamguard'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>