<?php
if (!defined('ABSPATH')) exit;

// Obtener logs
$logs = SpamGuard_Stats::get_recent_logs(100);

// Filtros
$filter_spam = isset($_GET['filter']) && $_GET['filter'] === 'spam';
$filter_ham = isset($_GET['filter']) && $_GET['filter'] === 'ham';
?>

<div class="wrap spamguard-admin">
    <h1>
        <span class="dashicons dashicons-list-view"></span>
        <?php _e('SpamGuard AI - Logs de Análisis', 'spamguard'); ?>
    </h1>
    
    <div class="spamguard-logs-header">
        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=spamguard-logs'); ?>" 
                   class="button <?php echo !$filter_spam && !$filter_ham ? 'button-primary' : ''; ?>">
                    <?php _e('Todos', 'spamguard'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=spamguard-logs&filter=spam'); ?>" 
                   class="button <?php echo $filter_spam ? 'button-primary' : ''; ?>">
                    <?php _e('Solo Spam', 'spamguard'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=spamguard-logs&filter=ham'); ?>" 
                   class="button <?php echo $filter_ham ? 'button-primary' : ''; ?>">
                    <?php _e('Solo Legítimos', 'spamguard'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="spamguard-card">
        <?php if (empty($logs)): ?>
            <p class="no-logs">
                <?php _e('No hay logs disponibles. Los comentarios analizados aparecerán aquí.', 'spamguard'); ?>
            </p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php _e('ID', 'spamguard'); ?></th>
                        <th style="width: 150px;"><?php _e('Fecha', 'spamguard'); ?></th>
                        <th style="width: 120px;"><?php _e('Autor', 'spamguard'); ?></th>
                        <th><?php _e('Comentario', 'spamguard'); ?></th>
                        <th style="width: 100px;"><?php _e('Clasificación', 'spamguard'); ?></th>
                        <th style="width: 100px;"><?php _e('Confianza', 'spamguard'); ?></th>
                        <th style="width: 200px;"><?php _e('Razones', 'spamguard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        // Aplicar filtros
                        if ($filter_spam && !$log->is_spam) continue;
                        if ($filter_ham && $log->is_spam) continue;
                        
                        $reasons = json_decode($log->reasons, true);
                        if (!is_array($reasons)) $reasons = [];
                        ?>
                        <tr>
                            <td><?php echo esc_html($log->comment_id); ?></td>
                            <td><?php echo esc_html(date('d/m/Y H:i', strtotime($log->created_at))); ?></td>
                            <td><?php echo esc_html($log->comment_author); ?></td>
                            <td>
                                <div class="comment-preview">
                                    <?php echo esc_html(wp_trim_words($log->comment_content, 15)); ?>
                                </div>
                                <div class="row-actions">
                                    <a href="<?php echo admin_url('comment.php?action=editcomment&c=' . $log->comment_id); ?>">
                                        <?php _e('Ver comentario', 'spamguard'); ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <?php if ($log->is_spam): ?>
                                    <span class="badge badge-spam"><?php _e('SPAM', 'spamguard'); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-ham"><?php _e('LEGÍTIMO', 'spamguard'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="confidence-mini-bar">
                                    <div class="confidence-mini-fill" style="width: <?php echo ($log->confidence * 100); ?>%"></div>
                                </div>
                                <span class="confidence-text"><?php echo round($log->confidence * 100, 1); ?>%</span>
                            </td>
                            <td>
                                <ul class="reasons-list">
                                    <?php foreach (array_slice($reasons, 0, 2) as $reason): ?>
                                        <li><?php echo esc_html($reason); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($reasons) > 2): ?>
                                        <li class="more-reasons">
                                            +<?php echo (count($reasons) - 2); ?> <?php _e('más', 'spamguard'); ?>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.spamguard-logs-header {
    margin: 20px 0;
}

.comment-preview {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.badge-spam {
    background: #fef7f7;
    color: #d63638;
    border: 1px solid #d63638;
}

.badge-ham {
    background: #f0f6f0;
    color: #00a32a;
    border: 1px solid #00a32a;
}

.confidence-mini-bar {
    width: 60px;
    height: 6px;
    background: #ddd;
    border-radius: 3px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
    margin-right: 5px;
}

.confidence-mini-fill {
    height: 100%;
    background: linear-gradient(90deg, #00a32a 0%, #d63638 100%);
}

.confidence-text {
    font-size: 12px;
    color: #646970;
}

.reasons-list {
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 12px;
}

.reasons-list li {
    margin: 2px 0;
    color: #646970;
}

.more-reasons {
    font-style: italic;
    color: #999;
}

.no-logs {
    text-align: center;
    padding: 40px;
    color: #646970;
    font-size: 14px;
}
</style>