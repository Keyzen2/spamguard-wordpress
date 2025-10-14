<?php
/**
 * Template: Quarantine Dashboard
 * Manage quarantined files
 */

if (!defined('ABSPATH')) exit;

// Get quarantine manager
$quarantine_manager = SpamGuard_Quarantine_Manager::get_instance();
$quarantine_data = $quarantine_manager->get_quarantine_list(array('status' => 'active'));
?>

<div class="wrap spamguard-quarantine">

    <h1>
        <span class="dashicons dashicons-vault"></span>
        <?php _e('Quarantine Manager', 'spamguard'); ?>
    </h1>

    <p class="description">
        <?php _e('Manage quarantined files detected as security threats. You can restore, delete, or download files for analysis.', 'spamguard'); ?>
    </p>

    <!-- Stats Cards -->
    <div class="sg-quarantine-stats">
        <div class="sg-stat-card sg-stat-quarantined">
            <div class="sg-stat-icon">
                <span class="dashicons dashicons-shield-alt"></span>
            </div>
            <div class="sg-stat-content">
                <div class="sg-stat-number"><?php echo $quarantine_data['total']; ?></div>
                <div class="sg-stat-label"><?php _e('Quarantined Files', 'spamguard'); ?></div>
            </div>
        </div>

        <div class="sg-stat-card sg-stat-storage">
            <div class="sg-stat-icon">
                <span class="dashicons dashicons-database"></span>
            </div>
            <div class="sg-stat-content">
                <div class="sg-stat-number"><?php echo $quarantine_data['quarantine_dir_size']; ?></div>
                <div class="sg-stat-label"><?php _e('Storage Used', 'spamguard'); ?></div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="sg-quarantine-controls">
        <div class="sg-quarantine-filters">
            <label for="sg-quarantine-filter"><?php _e('Filter:', 'spamguard'); ?></label>
            <select id="sg-quarantine-filter">
                <option value="all"><?php _e('All Files', 'spamguard'); ?></option>
                <option value="active" selected><?php _e('Active (Not Restored)', 'spamguard'); ?></option>
                <option value="restored"><?php _e('Restored', 'spamguard'); ?></option>
            </select>

            <button type="button" class="button sg-refresh-list-btn">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'spamguard'); ?>
            </button>
        </div>

        <div class="sg-bulk-actions">
            <select id="sg-bulk-action">
                <option value=""><?php _e('Bulk Actions', 'spamguard'); ?></option>
                <option value="restore"><?php _e('Restore Selected', 'spamguard'); ?></option>
                <option value="delete"><?php _e('Delete Selected', 'spamguard'); ?></option>
            </select>

            <button type="button" class="button sg-apply-bulk-btn">
                <?php _e('Apply', 'spamguard'); ?>
            </button>
        </div>
    </div>

    <!-- Quarantine Table -->
    <div class="sg-quarantine-table-wrapper">
        <table class="sg-quarantine-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" id="sg-select-all">
                    </th>
                    <th><?php _e('File Path', 'spamguard'); ?></th>
                    <th><?php _e('Quarantined', 'spamguard'); ?></th>
                    <th><?php _e('Status', 'spamguard'); ?></th>
                    <th><?php _e('Actions', 'spamguard'); ?></th>
                </tr>
            </thead>
            <tbody id="sg-quarantine-tbody">
                <tr>
                    <td colspan="5" class="sg-loading">
                        <span class="dashicons dashicons-update spin"></span>
                        <?php _e('Loading quarantine data...', 'spamguard'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="sg-pagination">
        <button type="button" class="button sg-prev-page-btn" disabled>
            <?php _e('Previous', 'spamguard'); ?>
        </button>
        <span class="sg-page-info">
            <?php _e('Page', 'spamguard'); ?> <span id="sg-current-page">1</span>
        </span>
        <button type="button" class="button sg-next-page-btn">
            <?php _e('Next', 'spamguard'); ?>
        </button>
    </div>

    <!-- Info Box -->
    <div class="sg-info-box">
        <h3><?php _e('About Quarantine', 'spamguard'); ?></h3>
        <p>
            <?php _e('When SpamGuard detects a potential security threat, the file is automatically moved to a secure quarantine directory. The original file is replaced with a harmless placeholder.', 'spamguard'); ?>
        </p>
        <p>
            <strong><?php _e('Actions you can take:', 'spamguard'); ?></strong>
        </p>
        <ul>
            <li><strong><?php _e('Restore:', 'spamguard'); ?></strong> <?php _e('If you believe the file is safe (false positive), you can restore it to its original location.', 'spamguard'); ?></li>
            <li><strong><?php _e('Delete:', 'spamguard'); ?></strong> <?php _e('Permanently remove the quarantined file from your system.', 'spamguard'); ?></li>
            <li><strong><?php _e('Download:', 'spamguard'); ?></strong> <?php _e('Download the file for further analysis or to send to security experts.', 'spamguard'); ?></li>
        </ul>
        <p class="sg-warning">
            ⚠️ <strong><?php _e('Warning:', 'spamguard'); ?></strong>
            <?php _e('Only restore files if you are absolutely certain they are safe. Restoring malicious files can compromise your site security.', 'spamguard'); ?>
        </p>
    </div>

</div>

<style>
.spamguard-quarantine {
    max-width: 1400px;
}

.spamguard-quarantine h1 {
    display: flex;
    align-items: center;
    gap: 10px;
}

.spamguard-quarantine h1 .dashicons {
    color: #d63638;
    font-size: 32px;
    width: 32px;
    height: 32px;
}

/* Stats Cards */
.sg-quarantine-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0 30px 0;
}

.sg-stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.sg-stat-quarantined {
    border-left: 4px solid #d63638;
}

.sg-stat-storage {
    border-left: 4px solid #2271b1;
}

.sg-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.sg-stat-quarantined .sg-stat-icon {
    background: #fef7f7;
    color: #d63638;
}

.sg-stat-storage .sg-stat-icon {
    background: #f0f6fc;
    color: #2271b1;
}

.sg-stat-number {
    font-size: 28px;
    font-weight: bold;
    line-height: 1;
}

.sg-stat-label {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

/* Controls */
.sg-quarantine-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    flex-wrap: wrap;
    gap: 15px;
}

.sg-quarantine-filters,
.sg-bulk-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sg-quarantine-filters label {
    font-weight: 600;
}

#sg-quarantine-filter,
#sg-bulk-action {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

/* Table */
.sg-quarantine-table-wrapper {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.sg-quarantine-table {
    margin: 0;
}

.sg-quarantine-table th {
    background: #f9f9f9;
    padding: 12px;
    font-weight: 600;
}

.sg-quarantine-table td {
    padding: 12px;
}

.sg-quarantine-table .check-column {
    width: 40px;
}

.sg-quarantine-table .sg-loading {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.sg-quarantine-table .dashicons.spin {
    animation: rotation 1s infinite linear;
}

@keyframes rotation {
    from { transform: rotate(0deg); }
    to { transform: rotate(359deg); }
}

.sg-file-path {
    font-family: monospace;
    font-size: 13px;
    word-break: break-all;
}

.sg-status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.sg-status-active {
    background: #fef7f7;
    color: #d63638;
}

.sg-status-restored {
    background: #f0f6f0;
    color: #00a32a;
}

.sg-action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.sg-action-btn {
    padding: 4px 8px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    transition: all 0.2s;
}

.sg-action-btn:hover {
    background: #f0f0f0;
}

.sg-action-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.sg-action-restore:hover {
    background: #00a32a;
    color: white;
    border-color: #00a32a;
}

.sg-action-delete:hover {
    background: #d63638;
    color: white;
    border-color: #d63638;
}

.sg-action-download:hover {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

/* Pagination */
.sg-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin: 20px 0;
}

.sg-page-info {
    font-weight: 600;
}

/* Info Box */
.sg-info-box {
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
    padding: 20px;
    margin: 30px 0;
    border-radius: 4px;
}

.sg-info-box h3 {
    margin-top: 0;
}

.sg-info-box ul {
    margin: 10px 0;
    padding-left: 20px;
}

.sg-info-box li {
    margin: 8px 0;
}

.sg-warning {
    background: #fff8e5;
    border-left: 3px solid #dba617;
    padding: 10px 15px;
    margin-top: 15px;
}

@media (max-width: 768px) {
    .sg-quarantine-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .sg-quarantine-filters,
    .sg-bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const quarantineManager = {
        currentPage: 1,
        currentFilter: 'active',

        init: function() {
            this.loadQuarantineList();
            this.bindEvents();
        },

        bindEvents: function() {
            // Select all checkbox
            $('#sg-select-all').on('change', function() {
                $('.sg-quarantine-checkbox').prop('checked', $(this).is(':checked'));
            });

            // Filter change
            $('#sg-quarantine-filter').on('change', () => {
                this.currentFilter = $('#sg-quarantine-filter').val();
                this.currentPage = 1;
                this.loadQuarantineList();
            });

            // Refresh button
            $('.sg-refresh-list-btn').on('click', () => {
                this.loadQuarantineList();
            });

            // Bulk actions
            $('.sg-apply-bulk-btn').on('click', () => {
                this.applyBulkAction();
            });

            // Pagination
            $('.sg-prev-page-btn').on('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadQuarantineList();
                }
            });

            $('.sg-next-page-btn').on('click', () => {
                this.currentPage++;
                this.loadQuarantineList();
            });

            // Action buttons (delegated events)
            $(document).on('click', '.sg-action-restore', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                quarantineManager.restoreFile(id);
            });

            $(document).on('click', '.sg-action-delete', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (confirm('<?php _e('Are you sure you want to permanently delete this file?', 'spamguard'); ?>')) {
                    quarantineManager.deleteFile(id);
                }
            });

            $(document).on('click', '.sg-action-download', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                quarantineManager.downloadFile(id);
            });
        },

        loadQuarantineList: function() {
            const $tbody = $('#sg-quarantine-tbody');
            $tbody.html(`
                <tr>
                    <td colspan="5" class="sg-loading">
                        <span class="dashicons dashicons-update spin"></span>
                        <?php _e('Loading...', 'spamguard'); ?>
                    </td>
                </tr>
            `);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_get_quarantine_list',
                    nonce: spamguardData.nonce,
                    status: this.currentFilter,
                    page: this.currentPage
                },
                success: (response) => {
                    if (response.success) {
                        this.renderQuarantineList(response.data);
                    } else {
                        $tbody.html(`
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: #d63638;">
                                    <?php _e('Error loading quarantine data', 'spamguard'); ?>
                                </td>
                            </tr>
                        `);
                    }
                },
                error: () => {
                    $tbody.html(`
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #d63638;">
                                <?php _e('Connection error', 'spamguard'); ?>
                            </td>
                        </tr>
                    `);
                }
            });
        },

        renderQuarantineList: function(data) {
            const $tbody = $('#sg-quarantine-tbody');
            $tbody.empty();

            if (data.items.length === 0) {
                $tbody.html(`
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #00a32a; opacity: 0.5;"></span>
                            <p><?php _e('No quarantined files found.', 'spamguard'); ?></p>
                        </td>
                    </tr>
                `);
                return;
            }

            data.items.forEach((item) => {
                const isRestored = item.restored_at !== null;
                const statusBadge = isRestored
                    ? '<span class="sg-status-badge sg-status-restored"><?php _e('Restored', 'spamguard'); ?></span>'
                    : '<span class="sg-status-badge sg-status-active"><?php _e('Quarantined', 'spamguard'); ?></span>';

                const actions = isRestored
                    ? `<span style="color: #666;"><?php _e('Restored', 'spamguard'); ?></span>`
                    : `
                        <div class="sg-action-buttons">
                            <button class="sg-action-btn sg-action-restore" data-id="${item.id}">
                                <span class="dashicons dashicons-backup"></span>
                                <?php _e('Restore', 'spamguard'); ?>
                            </button>
                            <button class="sg-action-btn sg-action-download" data-id="${item.id}">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Download', 'spamguard'); ?>
                            </button>
                            <button class="sg-action-btn sg-action-delete" data-id="${item.id}">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Delete', 'spamguard'); ?>
                            </button>
                        </div>
                    `;

                const row = `
                    <tr>
                        <td class="check-column">
                            ${!isRestored ? `<input type="checkbox" class="sg-quarantine-checkbox" value="${item.id}">` : ''}
                        </td>
                        <td>
                            <div class="sg-file-path">${item.file_path}</div>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                ID: ${item.id}
                            </div>
                        </td>
                        <td>${item.quarantined_at}</td>
                        <td>${statusBadge}</td>
                        <td>${actions}</td>
                    </tr>
                `;

                $tbody.append(row);
            });

            // Update pagination
            $('#sg-current-page').text(this.currentPage);
            $('.sg-prev-page-btn').prop('disabled', this.currentPage === 1);
            $('.sg-next-page-btn').prop('disabled', data.items.length < 20);
        },

        restoreFile: function(id) {
            if (!confirm('<?php _e('Are you sure you want to restore this file? Only restore if you are certain it is safe.', 'spamguard'); ?>')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_restore_file',
                    nonce: spamguardData.nonce,
                    quarantine_id: id
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        this.loadQuarantineList();
                    } else {
                        this.showNotice(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showNotice('<?php _e('An error occurred', 'spamguard'); ?>', 'error');
                }
            });
        },

        deleteFile: function(id) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_delete_quarantine',
                    nonce: spamguardData.nonce,
                    quarantine_id: id
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        this.loadQuarantineList();
                    } else {
                        this.showNotice(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showNotice('<?php _e('An error occurred', 'spamguard'); ?>', 'error');
                }
            });
        },

        downloadFile: function(id) {
            window.location.href = `${ajaxurl}?action=spamguard_download_quarantine&quarantine_id=${id}&nonce=${spamguardData.nonce}`;
        },

        applyBulkAction: function() {
            const action = $('#sg-bulk-action').val();
            const selectedIds = $('.sg-quarantine-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (!action) {
                alert('<?php _e('Please select an action', 'spamguard'); ?>');
                return;
            }

            if (selectedIds.length === 0) {
                alert('<?php _e('Please select at least one file', 'spamguard'); ?>');
                return;
            }

            if (!confirm(`<?php _e('Are you sure you want to', 'spamguard'); ?> ${action} ${selectedIds.length} <?php _e('files?', 'spamguard'); ?>`)) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_quarantine_bulk_action',
                    nonce: spamguardData.nonce,
                    bulk_action: action,
                    quarantine_ids: selectedIds
                },
                success: (response) => {
                    if (response.success) {
                        const result = response.data;
                        this.showNotice(
                            `<?php _e('Success:', 'spamguard'); ?> ${result.success}, <?php _e('Failed:', 'spamguard'); ?> ${result.failed}`,
                            result.failed > 0 ? 'warning' : 'success'
                        );
                        this.loadQuarantineList();
                    } else {
                        this.showNotice(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showNotice('<?php _e('An error occurred', 'spamguard'); ?>', 'error');
                }
            });
        },

        showNotice: function(message, type = 'info') {
            const $notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.spamguard-quarantine').prepend($notice);

            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        }
    };

    quarantineManager.init();
});
</script>
