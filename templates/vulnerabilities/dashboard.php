<?php
if (!defined('ABSPATH')) exit;

$checker = SpamGuard_Vulnerability_Checker::get_instance();
$stats = $checker->get_stats();
$vulnerabilities = $checker->get_vulnerabilities();

// Si se solicitó un nuevo escaneo
if (isset($_POST['scan_now'])) {
    check_admin_referer('spamguard_scan_vulnerabilities');
    $scan_result = $checker->scan_all();
    
    if ($scan_result['success']) {
        echo '<div class="notice notice-success"><p>✅ Escaneo completado. Vulnerabilidades encontradas: ' . $scan_result['vulnerable_count'] . '</p></div>';
        // Recargar stats
        $stats = $checker->get_stats();
        $vulnerabilities = $checker->get_vulnerabilities();
    } else {
        echo '<div class="notice notice-error"><p>❌ Error: ' . esc_html($scan_result['error']) . '</p></div>';
    }
}
?>

<div class="wrap spamguard-vulnerabilities">
    
    <h1>
        <span class="dashicons dashicons-shield-alt"></span>
        <?php _e('Vulnerabilidades Detectadas', 'spamguard'); ?>
    </h1>
    
    <!-- Stats Cards -->
    <div class="vulnerability-stats">
        <div class="stat-card total">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Vulnerabilidades</div>
        </div>
        
        <div class="stat-card critical">
            <div class="stat-number"><?php echo $stats['by_severity']['critical']; ?></div>
            <div class="stat-label">Críticas</div>
        </div>
        
        <div class="stat-card high">
            <div class="stat-number"><?php echo $stats['by_severity']['high']; ?></div>
            <div class="stat-label">Altas</div>
        </div>
        
        <div class="stat-card medium">
            <div class="stat-number"><?php echo $stats['by_severity']['medium']; ?></div>
            <div class="stat-label">Medias</div>
        </div>
        
        <div class="stat-card low">
            <div class="stat-number"><?php echo $stats['by_severity']['low']; ?></div>
            <div class="stat-label">Bajas</div>
        </div>
    </div>
    
    <!-- Scan Button -->
    <div class="scan-actions">
        <form method="post">
            <?php wp_nonce_field('spamguard_scan_vulnerabilities'); ?>
            <button type="submit" name="scan_now" class="button button-primary button-large">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Escanear Ahora', 'spamguard'); ?>
            </button>
        </form>
        
        <?php if ($stats['last_scan']): ?>
        <p class="last-scan">
            Último escaneo: <?php echo human_time_diff(strtotime($stats['last_scan']), current_time('timestamp')); ?> ago
        </p>
        <?php endif; ?>
    </div>
    
    <!-- Vulnerabilities List -->
    <?php if (!empty($vulnerabilities)): ?>
    <div class="vulnerabilities-list">
        <h2><?php _e('Vulnerabilidades Encontradas', 'spamguard'); ?></h2>
        
        <?php foreach ($vulnerabilities as $vuln): ?>
        <div class="vulnerability-item severity-<?php echo esc_attr($vuln->severity); ?>">
            <div class="vuln-header">
                <div class="vuln-severity">
                    <?php
                    $severity_labels = array(
                        'critical' => '🔴 CRÍTICO',
                        'high' => '🟠 ALTO',
                        'medium' => '🟡 MEDIO',
                        'low' => '🟢 BAJO'
                    );
                    echo $severity_labels[$vuln->severity];
                    ?>
                </div>
                <h3><?php echo esc_html($vuln->title); ?></h3>
            </div>
            
            <div class="vuln-details">
                <div class="detail-row">
                    <strong>Componente:</strong>
                    <?php echo esc_html($vuln->component_type); ?> - 
                    <code><?php echo esc_html($vuln->component_slug); ?></code>
                    (v<?php echo esc_html($vuln->component_version); ?>)
                </div>
                
                <?php if ($vuln->cve_id): ?>
                <div class="detail-row">
                    <strong>CVE:</strong>
                    <a href="https://nvd.nist.gov/vuln/detail/<?php echo esc_attr($vuln->cve_id); ?>" target="_blank">
                        <?php echo esc_html($vuln->cve_id); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <strong>Tipo:</strong>
                    <?php echo esc_html($vuln->vuln_type); ?>
                </div>
                
                <?php if ($vuln->patched_in): ?>
                <div class="detail-row fix">
                    <strong>✅ Parcheado en versión:</strong>
                    <code><?php echo esc_html($vuln->patched_in); ?></code>
                    <a href="<?php echo admin_url('update-core.php'); ?>" class="button button-small">
                        Actualizar Ahora
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($vuln->description): ?>
                <div class="detail-row description">
                    <strong>Descripción:</strong>
                    <p><?php echo esc_html($vuln->description); ?></p>
                </div>
                <?php endif; ?>
                
                <?php
                $references = json_decode($vuln->reference_urls, true);
                if ($references && !empty($references['references'])):
                ?>
                <div class="detail-row">
                    <strong>Referencias:</strong>
                    <ul>
                        <?php foreach ($references['references'] as $url): ?>
                        <li><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($url); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
    <div class="no-vulnerabilities">
        <p>✅ ¡Excelente! No se encontraron vulnerabilidades conocidas.</p>
    </div>
    <?php endif; ?>
    
</div>

<style>
.spamguard-vulnerabilities {
    margin: 20px;
}

.vulnerability-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-card.critical .stat-number { color: #d63638; }
.stat-card.high .stat-number { color: #f56e28; }
.stat-card.medium .stat-number { color: #dba617; }
.stat-card.low .stat-number { color: #00a32a; }

.vulnerability-item {
    background: white;
    padding: 20px;
    margin: 15px 0;
    border-left: 4px solid;
    border-radius: 4px;
}

.vulnerability-item.severity-critical { border-left-color: #d63638; }
.vulnerability-item.severity-high { border-left-color: #f56e28; }
.vulnerability-item.severity-medium { border-left-color: #dba617; }
.vulnerability-item.severity-low { border-left-color: #00a32a; }

.vuln-header {
    margin-bottom: 15px;
}

.vuln-severity {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 10px;
}

.detail-row {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.detail-row.fix {
    background: #e7f5e7;
    padding: 12px;
    border-radius: 4px;
    border: 1px solid #00a32a;
}

.no-vulnerabilities {
    background: #e7f5e7;
    padding: 40px;
    text-align: center;
    border-radius: 8px;
    font-size: 18px;
}
</style>
