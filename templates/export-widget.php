<?php
/**
 * Template: Export Widget
 * Reusable export component for dashboards
 */

if (!defined('ABSPATH')) exit;

// Get export type from parameter or default
$export_type = isset($export_type) ? $export_type : 'spam-logs';
$export_label = isset($export_label) ? $export_label : __('Export Data', 'spamguard');
?>

<div class="sg-export-widget">
    <div class="sg-export-header">
        <h3>
            <span class="dashicons dashicons-download"></span>
            <?php echo esc_html($export_label); ?>
        </h3>
        <p class="description">
            <?php _e('Export your data to CSV or generate PDF reports', 'spamguard'); ?>
        </p>
    </div>

    <div class="sg-export-controls">
        <!-- Date Range Selector -->
        <div class="sg-export-dates">
            <div class="sg-date-group">
                <label for="sg-export-date-from"><?php _e('From:', 'spamguard'); ?></label>
                <input type="date"
                       id="sg-export-date-from"
                       class="sg-export-date-from"
                       value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
            </div>

            <div class="sg-date-group">
                <label for="sg-export-date-to"><?php _e('To:', 'spamguard'); ?></label>
                <input type="date"
                       id="sg-export-date-to"
                       class="sg-export-date-to"
                       value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>

        <!-- Export Type Selector (for multi-type exports) -->
        <?php if (isset($export_types) && is_array($export_types)): ?>
        <div class="sg-export-type-selector">
            <label for="sg-export-type"><?php _e('Export Type:', 'spamguard'); ?></label>
            <select id="sg-export-type" class="sg-export-type">
                <?php foreach ($export_types as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>">
                    <?php echo esc_html($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <input type="hidden" class="sg-export-type" value="<?php echo esc_attr($export_type); ?>">
        <?php endif; ?>

        <!-- Export Buttons -->
        <div class="sg-export-actions">
            <button type="button"
                    class="button button-secondary sg-export-csv-btn"
                    data-export-type="<?php echo esc_attr($export_type); ?>">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <?php _e('Export to CSV', 'spamguard'); ?>
            </button>

            <button type="button"
                    class="button button-secondary sg-export-pdf-btn"
                    data-export-type="<?php echo esc_attr($export_type); ?>">
                <span class="dashicons dashicons-pdf"></span>
                <?php _e('Export to PDF', 'spamguard'); ?>
            </button>
        </div>

        <!-- Quick Export Links -->
        <div class="sg-quick-exports">
            <p class="description"><?php _e('Quick Exports:', 'spamguard'); ?></p>
            <div class="sg-quick-export-links">
                <a href="#" class="sg-quick-export" data-type="spam-logs" data-format="csv">
                    <?php _e('Spam Logs (CSV)', 'spamguard'); ?>
                </a>
                <a href="#" class="sg-quick-export" data-type="whitelist" data-format="csv">
                    <?php _e('Whitelist (CSV)', 'spamguard'); ?>
                </a>
                <a href="#" class="sg-quick-export" data-type="blacklist" data-format="csv">
                    <?php _e('Blacklist (CSV)', 'spamguard'); ?>
                </a>
                <a href="#" class="sg-quick-export" data-type="security-report" data-format="pdf">
                    <?php _e('Security Report (PDF)', 'spamguard'); ?>
                </a>
            </div>
        </div>

        <!-- Scheduled Export -->
        <div class="sg-scheduled-export">
            <h4><?php _e('Scheduled Reports', 'spamguard'); ?></h4>
            <p class="description">
                <?php _e('Receive automated reports via email', 'spamguard'); ?>
            </p>

            <div class="sg-schedule-form">
                <div class="sg-schedule-row">
                    <label>
                        <input type="checkbox" id="sg-schedule-enabled" class="sg-schedule-enabled">
                        <?php _e('Enable scheduled exports', 'spamguard'); ?>
                    </label>
                </div>

                <div class="sg-schedule-options" style="display: none;">
                    <div class="sg-schedule-field">
                        <label for="sg-schedule-frequency"><?php _e('Frequency:', 'spamguard'); ?></label>
                        <select id="sg-schedule-frequency" class="sg-schedule-frequency">
                            <option value="daily"><?php _e('Daily', 'spamguard'); ?></option>
                            <option value="weekly" selected><?php _e('Weekly', 'spamguard'); ?></option>
                            <option value="monthly"><?php _e('Monthly', 'spamguard'); ?></option>
                        </select>
                    </div>

                    <div class="sg-schedule-field">
                        <label for="sg-schedule-email"><?php _e('Email:', 'spamguard'); ?></label>
                        <input type="email"
                               id="sg-schedule-email"
                               class="sg-schedule-email regular-text"
                               value="<?php echo esc_attr(get_option('admin_email')); ?>"
                               placeholder="admin@example.com">
                    </div>

                    <div class="sg-schedule-field">
                        <label for="sg-schedule-report-type"><?php _e('Report Type:', 'spamguard'); ?></label>
                        <select id="sg-schedule-report-type" class="sg-schedule-report-type">
                            <option value="security-report"><?php _e('Security Report (PDF)', 'spamguard'); ?></option>
                            <option value="spam-report"><?php _e('Spam Activity (PDF)', 'spamguard'); ?></option>
                            <option value="vulnerability-report"><?php _e('Vulnerabilities (PDF)', 'spamguard'); ?></option>
                        </select>
                    </div>

                    <button type="button" class="button button-primary sg-save-schedule-btn">
                        <?php _e('Save Schedule', 'spamguard'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sg-export-widget {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.sg-export-header h3 {
    margin: 0 0 10px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
}

.sg-export-header .dashicons {
    color: #2271b1;
    font-size: 20px;
}

.sg-export-controls {
    margin-top: 20px;
}

.sg-export-dates {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.sg-date-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.sg-date-group label {
    font-weight: 600;
    font-size: 13px;
}

.sg-date-group input[type="date"] {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.sg-export-type-selector {
    margin-bottom: 20px;
}

.sg-export-type-selector label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 13px;
}

.sg-export-type-selector select {
    width: 100%;
    max-width: 300px;
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.sg-export-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.sg-export-actions .button {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sg-export-actions .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.sg-quick-exports {
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
    margin-bottom: 20px;
}

.sg-quick-exports .description {
    margin: 0 0 10px 0;
    font-weight: 600;
}

.sg-quick-export-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.sg-quick-export {
    padding: 6px 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s;
}

.sg-quick-export:hover {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

.sg-scheduled-export {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 20px;
}

.sg-scheduled-export h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.sg-schedule-form {
    margin-top: 15px;
}

.sg-schedule-row {
    margin-bottom: 15px;
}

.sg-schedule-options {
    margin-top: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
}

.sg-schedule-field {
    margin-bottom: 15px;
}

.sg-schedule-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 13px;
}

.sg-schedule-field select,
.sg-schedule-field input {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .sg-export-dates {
        flex-direction: column;
    }

    .sg-export-actions {
        flex-direction: column;
    }

    .sg-export-actions .button {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const exportWidget = {

        init: function() {
            this.bindEvents();
            this.loadScheduleSettings();
        },

        bindEvents: function() {
            // CSV Export
            $('.sg-export-csv-btn').on('click', (e) => {
                e.preventDefault();
                this.exportCSV();
            });

            // PDF Export
            $('.sg-export-pdf-btn').on('click', (e) => {
                e.preventDefault();
                this.exportPDF();
            });

            // Quick exports
            $('.sg-quick-export').on('click', (e) => {
                e.preventDefault();
                const type = $(e.currentTarget).data('type');
                const format = $(e.currentTarget).data('format');
                this.quickExport(type, format);
            });

            // Schedule toggle
            $('.sg-schedule-enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.sg-schedule-options').slideDown();
                } else {
                    $('.sg-schedule-options').slideUp();
                }
            });

            // Save schedule
            $('.sg-save-schedule-btn').on('click', (e) => {
                e.preventDefault();
                this.saveSchedule();
            });
        },

        exportCSV: function() {
            const type = $('.sg-export-type').val();
            const dateFrom = $('.sg-export-date-from').val();
            const dateTo = $('.sg-export-date-to').val();

            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: ajaxurl,
                target: '_blank'
            });

            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'spamguard_export_csv'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: '<?php echo wp_create_nonce('spamguard_export'); ?>'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'type',
                value: type
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'date_from',
                value: dateFrom
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'date_to',
                value: dateTo
            }));

            $('body').append(form);
            form.submit();
            form.remove();
        },

        exportPDF: function() {
            const type = $('.sg-export-type').val();
            const dateFrom = $('.sg-export-date-from').val();
            const dateTo = $('.sg-export-date-to').val();

            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: ajaxurl,
                target: '_blank'
            });

            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'spamguard_export_pdf'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: '<?php echo wp_create_nonce('spamguard_export'); ?>'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'type',
                value: type
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'date_from',
                value: dateFrom
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'date_to',
                value: dateTo
            }));

            $('body').append(form);
            form.submit();
            form.remove();
        },

        quickExport: function(type, format) {
            if (format === 'csv') {
                const form = $('<form>', {
                    method: 'POST',
                    action: ajaxurl,
                    target: '_blank'
                });

                form.append($('<input>', { type: 'hidden', name: 'action', value: 'spamguard_export_csv' }));
                form.append($('<input>', { type: 'hidden', name: 'nonce', value: '<?php echo wp_create_nonce('spamguard_export'); ?>' }));
                form.append($('<input>', { type: 'hidden', name: 'type', value: type }));

                $('body').append(form);
                form.submit();
                form.remove();
            } else if (format === 'pdf') {
                const form = $('<form>', {
                    method: 'POST',
                    action: ajaxurl,
                    target: '_blank'
                });

                form.append($('<input>', { type: 'hidden', name: 'action', value: 'spamguard_export_pdf' }));
                form.append($('<input>', { type: 'hidden', name: 'nonce', value: '<?php echo wp_create_nonce('spamguard_export'); ?>' }));
                form.append($('<input>', { type: 'hidden', name: 'type', value: type }));

                $('body').append(form);
                form.submit();
                form.remove();
            }
        },

        saveSchedule: function() {
            const enabled = $('.sg-schedule-enabled').is(':checked');
            const frequency = $('.sg-schedule-frequency').val();
            const email = $('.sg-schedule-email').val();
            const reportType = $('.sg-schedule-report-type').val();

            if (!email) {
                alert('<?php _e('Please enter an email address', 'spamguard'); ?>');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_schedule_export',
                    nonce: '<?php echo wp_create_nonce('spamguard_export'); ?>',
                    enabled: enabled ? 1 : 0,
                    frequency: frequency,
                    email: email,
                    type: reportType
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || '<?php _e('Schedule saved successfully', 'spamguard'); ?>');
                    } else {
                        alert(response.data.message || '<?php _e('Failed to save schedule', 'spamguard'); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e('An error occurred', 'spamguard'); ?>');
                }
            });
        },

        loadScheduleSettings: function() {
            // Load current schedule settings via AJAX
            // (Implementation would fetch settings from options)
        }
    };

    if ($('.sg-export-widget').length) {
        exportWidget.init();
    }
});
</script>
