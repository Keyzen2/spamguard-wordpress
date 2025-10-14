<?php
/**
 * Template: Whitelist/Blacklist Manager
 * Manage allowed and blocked IPs, emails, domains, keywords
 */

if (!defined('ABSPATH')) exit;

// Get lists manager instance
$lists_manager = SpamGuard_Lists::get_instance();
$stats = $lists_manager->get_stats();

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'whitelist';
?>

<div class="wrap spamguard-lists-manager">

    <h1>
        <span class="dashicons dashicons-list-view"></span>
        <?php _e('Whitelist & Blacklist Manager', 'spamguard'); ?>
    </h1>

    <p class="description">
        <?php _e('Manage allowed and blocked IPs, emails, domains, and keywords.', 'spamguard'); ?>
    </p>

    <!-- Stats Cards -->
    <div class="sg-lists-stats">
        <div class="sg-stat-card sg-stat-whitelist">
            <div class="sg-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="sg-stat-content">
                <div class="sg-stat-number"><?php echo $stats['whitelist']['total']; ?></div>
                <div class="sg-stat-label"><?php _e('Whitelist Entries', 'spamguard'); ?></div>
            </div>
        </div>

        <div class="sg-stat-card sg-stat-blacklist">
            <div class="sg-stat-icon">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div class="sg-stat-content">
                <div class="sg-stat-number"><?php echo $stats['blacklist']['total']; ?></div>
                <div class="sg-stat-label"><?php _e('Blacklist Entries', 'spamguard'); ?></div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=spamguard-lists&tab=whitelist"
           class="nav-tab <?php echo $current_tab === 'whitelist' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php _e('Whitelist', 'spamguard'); ?>
        </a>
        <a href="?page=spamguard-lists&tab=blacklist"
           class="nav-tab <?php echo $current_tab === 'blacklist' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-dismiss"></span>
            <?php _e('Blacklist', 'spamguard'); ?>
        </a>
    </h2>

    <div class="sg-lists-content">

        <?php if ($current_tab === 'whitelist'): ?>
            <!-- WHITELIST -->
            <div class="sg-list-section">
                <div class="sg-list-header">
                    <h2><?php _e('Whitelist (Always Allow)', 'spamguard'); ?></h2>
                    <p class="description">
                        <?php _e('IPs, emails, or domains in this list will never be blocked by SpamGuard.', 'spamguard'); ?>
                    </p>
                </div>

                <?php render_list_manager('whitelist'); ?>
            </div>

        <?php elseif ($current_tab === 'blacklist'): ?>
            <!-- BLACKLIST -->
            <div class="sg-list-section">
                <div class="sg-list-header">
                    <h2><?php _e('Blacklist (Always Block)', 'spamguard'); ?></h2>
                    <p class="description">
                        <?php _e('IPs, emails, domains, or keywords in this list will always be blocked.', 'spamguard'); ?>
                    </p>
                </div>

                <?php render_list_manager('blacklist'); ?>
            </div>

        <?php endif; ?>

    </div>

</div>

<style>
.sg-lists-stats {
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

.sg-stat-whitelist {
    border-left: 4px solid #00a32a;
}

.sg-stat-blacklist {
    border-left: 4px solid #d63638;
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

.sg-stat-whitelist .sg-stat-icon {
    background: #f0f6f0;
    color: #00a32a;
}

.sg-stat-blacklist .sg-stat-icon {
    background: #fef7f7;
    color: #d63638;
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

.sg-lists-content {
    background: white;
    padding: 20px;
    margin-top: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.sg-list-header {
    margin-bottom: 20px;
}

.sg-list-header h2 {
    margin: 0 0 10px 0;
}

/* Add Entry Form */
.sg-add-entry-form {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.sg-form-row {
    display: grid;
    grid-template-columns: 150px 1fr 1fr 150px auto;
    gap: 15px;
    align-items: end;
}

.sg-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 13px;
}

.sg-form-group select,
.sg-form-group input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.sg-add-btn {
    padding: 8px 20px !important;
    height: auto !important;
}

/* Entries Table */
.sg-entries-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.sg-entries-table th {
    background: #f9f9f9;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #ddd;
}

.sg-entries-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.sg-entries-table tr:hover {
    background: #fafafa;
}

.sg-entry-type {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.sg-entry-type-ip {
    background: #e3f2fd;
    color: #1976d2;
}

.sg-entry-type-email {
    background: #f3e5f5;
    color: #7b1fa2;
}

.sg-entry-type-domain {
    background: #e8f5e9;
    color: #388e3c;
}

.sg-entry-type-keyword {
    background: #fff3e0;
    color: #f57c00;
}

.sg-remove-btn {
    color: #d63638;
    cursor: pointer;
    transition: all 0.3s ease;
}

.sg-remove-btn:hover {
    color: #a00;
}

.sg-no-entries {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.sg-no-entries .dashicons {
    font-size: 48px;
    opacity: 0.3;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .sg-form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const listsManager = {

        init: function() {
            this.bindEvents();
            this.loadEntries();
        },

        bindEvents: function() {
            // Add entry
            $('#sg-add-entry-btn').on('click', (e) => {
                e.preventDefault();
                this.addEntry();
            });

            // Remove entry
            $(document).on('click', '.sg-remove-btn', function(e) {
                e.preventDefault();
                const entryId = $(this).data('entry-id');
                if (confirm('<?php _e('Are you sure you want to remove this entry?', 'spamguard'); ?>')) {
                    listsManager.removeEntry(entryId);
                }
            });
        },

        addEntry: function() {
            const listType = $('#sg-list-type').val();
            const entryType = $('#sg-entry-type').val();
            const value = $('#sg-entry-value').val();
            const reason = $('#sg-entry-reason').val();

            if (!value) {
                alert('<?php _e('Please enter a value', 'spamguard'); ?>');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_add_list_entry',
                    nonce: '<?php echo wp_create_nonce('spamguard_lists'); ?>',
                    list_type: listType,
                    entry_type: entryType,
                    value: value,
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        $('#sg-entry-value').val('');
                        $('#sg-entry-reason').val('');
                        listsManager.loadEntries();
                        listsManager.showNotice(response.data.message, 'success');
                    } else {
                        listsManager.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    listsManager.showNotice('<?php _e('An error occurred', 'spamguard'); ?>', 'error');
                }
            });
        },

        removeEntry: function(entryId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_remove_list_entry',
                    nonce: '<?php echo wp_create_nonce('spamguard_lists'); ?>',
                    entry_id: entryId
                },
                success: function(response) {
                    if (response.success) {
                        listsManager.loadEntries();
                        listsManager.showNotice(response.data.message, 'success');
                    } else {
                        listsManager.showNotice(response.data.message, 'error');
                    }
                }
            });
        },

        loadEntries: function() {
            const listType = '<?php echo $current_tab; ?>';

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_get_list_entries',
                    nonce: '<?php echo wp_create_nonce('spamguard_lists'); ?>',
                    list_type: listType
                },
                success: function(response) {
                    if (response.success) {
                        listsManager.renderEntries(response.data.entries);
                    }
                }
            });
        },

        renderEntries: function(entries) {
            const $tbody = $('#sg-entries-tbody');
            $tbody.empty();

            if (entries.length === 0) {
                $tbody.html(`
                    <tr>
                        <td colspan="5" class="sg-no-entries">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php _e('No entries yet. Add your first entry above.', 'spamguard'); ?></p>
                        </td>
                    </tr>
                `);
                return;
            }

            entries.forEach((entry) => {
                const row = `
                    <tr>
                        <td>
                            <span class="sg-entry-type sg-entry-type-${entry.entry_type}">
                                ${entry.entry_type}
                            </span>
                        </td>
                        <td><code>${entry.value}</code></td>
                        <td>${entry.reason || '-'}</td>
                        <td>${entry.created_at}</td>
                        <td>
                            <a href="#" class="sg-remove-btn" data-entry-id="${entry.id}">
                                <span class="dashicons dashicons-trash"></span>
                            </a>
                        </td>
                    </tr>
                `;
                $tbody.append(row);
            });
        },

        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap').prepend($notice);

            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 3000);
        }
    };

    listsManager.init();
});
</script>

<?php
/**
 * Render list manager section
 */
function render_list_manager($list_type) {
    ?>
    <!-- Add Entry Form -->
    <div class="sg-add-entry-form">
        <h3><?php _e('Add New Entry', 'spamguard'); ?></h3>

        <div class="sg-form-row">
            <input type="hidden" id="sg-list-type" value="<?php echo esc_attr($list_type); ?>">

            <div class="sg-form-group">
                <label for="sg-entry-type"><?php _e('Type', 'spamguard'); ?></label>
                <select id="sg-entry-type">
                    <option value="ip"><?php _e('IP Address', 'spamguard'); ?></option>
                    <option value="email"><?php _e('Email', 'spamguard'); ?></option>
                    <option value="domain"><?php _e('Domain', 'spamguard'); ?></option>
                    <option value="keyword"><?php _e('Keyword', 'spamguard'); ?></option>
                </select>
            </div>

            <div class="sg-form-group">
                <label for="sg-entry-value"><?php _e('Value', 'spamguard'); ?></label>
                <input type="text" id="sg-entry-value" placeholder="e.g., 192.168.1.*, @spam.com, viagra">
            </div>

            <div class="sg-form-group">
                <label for="sg-entry-reason"><?php _e('Reason (Optional)', 'spamguard'); ?></label>
                <input type="text" id="sg-entry-reason" placeholder="Why add this entry?">
            </div>

            <div class="sg-form-group">
                <button type="button" id="sg-add-entry-btn" class="button button-primary sg-add-btn">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add', 'spamguard'); ?>
                </button>
            </div>
        </div>

        <div class="sg-form-help">
            <p><strong><?php _e('Examples:', 'spamguard'); ?></strong></p>
            <ul>
                <li><code>192.168.1.100</code> - <?php _e('Specific IP', 'spamguard'); ?></li>
                <li><code>192.168.1.*</code> - <?php _e('IP range', 'spamguard'); ?></li>
                <li><code>user@spam.com</code> - <?php _e('Specific email', 'spamguard'); ?></li>
                <li><code>@spam.com</code> - <?php _e('All emails from domain', 'spamguard'); ?></li>
                <li><code>viagra</code> - <?php _e('Keyword in content', 'spamguard'); ?></li>
            </ul>
        </div>
    </div>

    <!-- Entries Table -->
    <table class="sg-entries-table">
        <thead>
            <tr>
                <th style="width: 100px;"><?php _e('Type', 'spamguard'); ?></th>
                <th><?php _e('Value', 'spamguard'); ?></th>
                <th><?php _e('Reason', 'spamguard'); ?></th>
                <th style="width: 150px;"><?php _e('Added', 'spamguard'); ?></th>
                <th style="width: 80px;"><?php _e('Actions', 'spamguard'); ?></th>
            </tr>
        </thead>
        <tbody id="sg-entries-tbody">
            <tr>
                <td colspan="5" class="sg-no-entries">
                    <span class="dashicons dashicons-update spin"></span>
                    <p><?php _e('Loading...', 'spamguard'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}
?>
