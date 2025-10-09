<?php
if (!defined('ABSPATH')) exit;

$stats = SpamGuard_Core::get_option('stats', array());
$is_configured = SpamGuard_Core::is_configured();
$daily_stats = SpamGuard_Stats::get_daily_stats(30);
?>

<div class="wrap spamguard-admin">
    <h1>
        <span class="dashicons dashicons-shield-alt"></span>
        <?php _e('SpamGuard AI - Dashboard', 'spamguard'); ?>
    </h1>
    
    <?php if (!$is_configured): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('¡Configuración necesaria!', 'spamguard'); ?></strong>
                <?php _e('Para comenzar a proteger tu sitio, configura tu API Key.', 'spamguard'); ?>
                <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-primary">
                    <?php _e('Configurar Ahora', 'spamguard'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="spamguard-dashboard-grid">
        <!-- Estadísticas principales -->
        <div class="spamguard-card">
            <h2><?php _e('Estadísticas Generales', 'spamguard'); ?></h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_analyzed'] ?? 0); ?></div>
                        <div class="stat-label"><?php _e('Total Analizados', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <div class="stat-item spam">
                    <div class="stat-icon">🛡️</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['spam_blocked'] ?? 0); ?></div>
                        <div class="stat-label"><?php _e('Spam Bloqueado', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <div class="stat-item warning">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['false_positives'] ?? 0); ?></div>
                        <div class="stat-label"><?php _e('Falsos Positivos', 'spamguard'); ?></div>
                    </div>
                </div>
                
                <div class="stat-item error">
                    <div class="stat-icon">❌</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['false_negatives'] ?? 0); ?></div>
                        <div class="stat-label"><?php _e('Falsos Negativos', 'spamguard'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de actividad -->
        <div class="spamguard-card">
            <h2><?php _e('Actividad de los Últimos 30 Días', 'spamguard'); ?></h2>
            <canvas id="spamguardChart" width="400" height="200"></canvas>
        </div>
        
        <!-- Estado del sistema -->
        <div class="spamguard-card">
            <h2><?php _e('Estado del Sistema', 'spamguard'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Estado de la API:', 'spamguard'); ?></strong></td>
                        <td id="api-status">
                            <span class="spinner is-active" style="float: none; margin: 0;"></span>
                            <?php _e('Verificando...', 'spamguard'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('API Key configurada:', 'spamguard'); ?></strong></td>
                        <td>
                            <?php if ($is_configured): ?>
                                <span class="status-badge success">✓ <?php _e('Sí', 'spamguard'); ?></span>
                            <?php else: ?>
                                <span class="status-badge error">✗ <?php _e('No', 'spamguard'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Aprendizaje activo:', 'spamguard'); ?></strong></td>
                        <td>
                            <?php if (SpamGuard_Core::get_option('learning_enabled', true)): ?>
                                <span class="status-badge success">✓ <?php _e('Habilitado', 'spamguard'); ?></span>
                            <?php else: ?>
                                <span class="status-badge warning"><?php _e('Deshabilitado', 'spamguard'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Versión del plugin:', 'spamguard'); ?></strong></td>
                        <td><?php echo SPAMGUARD_VERSION; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Acciones rápidas -->
        <div class="spamguard-card">
            <h2><?php _e('Acciones Rápidas', 'spamguard'); ?></h2>
            <div class="quick-actions">
                <a href="<?php echo admin_url('edit-comments.php?comment_status=spam'); ?>" class="button button-large">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Ver Comentarios Spam', 'spamguard'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=spamguard-settings'); ?>" class="button button-large">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Configuración', 'spamguard'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=spamguard-logs'); ?>" class="button button-large">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('Ver Logs', 'spamguard'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Verificar estado de la API
    $.post(ajaxurl, {
        action: 'spamguard_test_connection',
        nonce: spamguardData.nonce
    }, function(response) {
        if (response.success) {
            $('#api-status').html('<span class="status-badge success">✓ Conectado</span>');
        } else {
            $('#api-status').html('<span class="status-badge error">✗ Desconectado</span>');
        }
    });
    
    // Crear gráfico
    <?php if (!empty($daily_stats)): ?>
    const ctx = document.getElementById('spamguardChart').getContext('2d');
    const chartData = {
        labels: [
            <?php foreach ($daily_stats as $day): ?>
                '<?php echo date('d/m', strtotime($day->date)); ?>',
            <?php endforeach; ?>
        ],
        datasets: [
            {
                label: '<?php _e('Spam', 'spamguard'); ?>',
                data: [
                    <?php foreach ($daily_stats as $day): ?>
                        <?php echo $day->spam_count; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(214, 54, 56, 0.2)',
                borderColor: 'rgba(214, 54, 56, 1)',
                borderWidth: 2
            },
            {
                label: '<?php _e('Legítimos', 'spamguard'); ?>',
                data: [
                    <?php foreach ($daily_stats as $day): ?>
                        <?php echo $day->ham_count; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(0, 163, 42, 0.2)',
                borderColor: 'rgba(0, 163, 42, 1)',
                borderWidth: 2
            }
        ]
    };
    
    new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
});
</script>