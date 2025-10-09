/**
 * SpamGuard Admin JavaScript v3.0
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

(function($) {
    'use strict';
    
    // Wait for DOM ready
    $(document).ready(function() {
        
        // Initialize all components
        SpamGuard.init();
        
    });
    
    /**
     * Main SpamGuard object
     */
    window.SpamGuard = {
        
        /**
         * Initialize
         */
        init: function() {
            this.initCache();
            this.initScanControls();
            this.initThreatActions();
            this.initSettingsPage();
            this.initTooltips();
            this.initConfirmDialogs();
        },
        
        /**
         * Initialize cache actions
         */
        initCache: function() {
            $(document).on('click', '.spamguard-clear-cache', function(e) {
                e.preventDefault();
                
                if (!confirm(spamguardData.i18n.confirmDelete)) {
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text(spamguardData.i18n.loading);
                
                $.ajax({
                    url: spamguardData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_clear_cache',
                        nonce: spamguardData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            SpamGuard.showNotice(response.data.message, 'success');
                        } else {
                            SpamGuard.showNotice(response.data.message || spamguardData.i18n.error, 'error');
                        }
                        $button.prop('disabled', false).text('Clear Cache');
                    },
                    error: function() {
                        SpamGuard.showNotice(spamguardData.i18n.error, 'error');
                        $button.prop('disabled', false).text('Clear Cache');
                    }
                });
            });
        },
        
        /**
         * Initialize scan controls
         */
        initScanControls: function() {
            var currentScanId = null;
            var progressInterval = null;
            
            // Start scan button
            $(document).on('click', '.spamguard-start-scan', function() {
                var scanType = $(this).data('scan-type');
                var $button = $(this);
                var originalText = $button.text();
                
                // Store original text
                $button.data('original-text', originalText);
                
                if (!confirm('Start ' + scanType + ' scan? This may take several minutes.')) {
                    return;
                }
                
                $button.prop('disabled', true).text('Starting...');
                
                $.ajax({
                    url: spamguardData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_start_scan',
                        nonce: spamguardData.nonce,
                        scan_type: scanType
                    },
                    success: function(response) {
                        if (response.success) {
                            currentScanId = response.data.scan_id;
                            
                            // Show progress section
                            $('#spamguard-scan-progress').slideDown();
                            
                            // Scroll to progress
                            $('html, body').animate({
                                scrollTop: $('#spamguard-scan-progress').offset().top - 50
                            }, 500);
                            
                            // Start polling
                            SpamGuard.pollScanProgress(currentScanId);
                            
                        } else {
                            alert(response.data.message || 'Error starting scan');
                            $button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('Connection error. Please try again.');
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        },
        
        /**
         * Poll scan progress
         */
        pollScanProgress: function(scanId) {
            var progressInterval = setInterval(function() {
                $.ajax({
                    url: spamguardData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_scan_progress',
                        nonce: spamguardData.nonce,
                        scan_id: scanId
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            
                            // Update progress bar
                            $('#scan-progress-fill')
                                .css('width', data.progress + '%')
                                .text(data.progress + '%');
                            
                            // Update stats
                            $('#scan-files-scanned').text(data.files_scanned.toLocaleString());
                            $('#scan-threats-found').text(data.threats_found.toLocaleString());
                            $('#scan-status').text(data.status.charAt(0).toUpperCase() + data.status.slice(1));
                            
                            // Check if completed
                            if (data.status === 'completed' || data.status === 'failed') {
                                clearInterval(progressInterval);
                                
                                // Change progress bar color
                                if (data.threats_found > 0) {
                                    $('#scan-progress-fill').css('background', 'linear-gradient(90deg, #d63638, #f86368)');
                                } else {
                                    $('#scan-progress-fill').css('background', 'linear-gradient(90deg, #00a32a, #5fb830)');
                                }
                                
                                // Reload page after 2 seconds
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            }
                        }
                    }
                });
            }, 2000); // Poll every 2 seconds
        },
        
        /**
         * Initialize threat actions
         */
        initThreatActions: function() {
            // Quarantine threat
            $(document).on('click', '.spamguard-quarantine-threat', function() {
                var threatId = $(this).data('threat-id');
                var $button = $(this);
                var $row = $button.closest('tr');
                
                if (!confirm('Move this file to quarantine? The file will be moved to a safe location.')) {
                    return;
                }
                
                $button.prop('disabled', true).text('Processing...');
                
                $.ajax({
                    url: spamguardData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_quarantine_threat',
                        nonce: spamguardData.nonce,
                        threat_id: threatId
                    },
                    success: function(response) {
                        if (response.success) {
                            SpamGuard.showNotice(response.data.message, 'success');
                            
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if no more threats
                                if ($('.spamguard-threats-list tbody tr').length === 0) {
                                    setTimeout(function() {
                                        location.reload();
                                    }, 1000);
                                }
                            });
                        } else {
                            alert(response.data.message || 'Error quarantining threat');
                            $button.prop('disabled', false).text('Quarantine');
                        }
                    },
                    error: function() {
                        alert('Connection error. Please try again.');
                        $button.prop('disabled', false).text('Quarantine');
                    }
                });
            });
            
            // Ignore threat
            $(document).on('click', '.spamguard-ignore-threat', function() {
                var threatId = $(this).data('threat-id');
                var $row = $(this).closest('tr');
                
                if (!confirm('Mark this threat as false positive? You can always scan again later.')) {
                    return;
                }
                
                $.ajax({
                    url: spamguardData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_ignore_threat',
                        nonce: spamguardData.nonce,
                        threat_id: threatId
                    },
                    success: function(response) {
                        SpamGuard.showNotice('Threat marked as false positive', 'success');
                        
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            if ($('.spamguard-threats-list tbody tr').length === 0) {
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            }
                        });
                    }
                });
            });
            
            // Restore from quarantine
            $(document).on('click', '.spamguard-restore-threat', function() {
                var threatId = $(this).data('threat-id');
                var $button = $(this);
                
                if (!confirm('Restore this file from quarantine? This will move the file back to its original location.')) {
                    return;
                }
                
                $button.prop('disabled', true).text('Restoring...');
                
                $.ajax({
                    url: spamguardData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_restore_threat',
                        nonce: spamguardData.nonce,
                        threat_id: threatId
                    },
                    success: function(response) {
                        if (response.success) {
                            SpamGuard.showNotice(response.data.message, 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            alert(response.data.message || 'Error restoring file');
                            $button.prop('disabled', false).text('Restore');
                        }
                    },
                    error: function() {
                        alert('Connection error. Please try again.');
                        $button.prop('disabled', false).text('Restore');
                    }
                });
            });
        },
        
        /**
         * Initialize settings page
         */
        initSettingsPage: function() {
            // Sensitivity slider
            $('#spamguard_sensitivity').on('input', function() {
                $('#sensitivity_value').text($(this).val() + '%');
            });
            
            // Test API connection
            $(document).on('click', '.spamguard-test-api', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                $button.prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: spamguardData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spamguard_test_api',
                        nonce: spamguardData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            SpamGuard.showNotice('API connection successful!', 'success');
                        } else {
                            SpamGuard.showNotice('API connection failed: ' + (response.data.message || 'Unknown error'), 'error');
                        }
                        $button.prop('disabled', false).text('Test Connection');
                    },
                    error: function() {
                        SpamGuard.showNotice('Connection error', 'error');
                        $button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Simple tooltip implementation
            $('[data-tooltip]').hover(
                function() {
                    var tooltip = $(this).data('tooltip');
                    $(this).append('<div class="spamguard-tooltip">' + tooltip + '</div>');
                    
                    var $tooltip = $(this).find('.spamguard-tooltip');
                    var offset = $(this).offset();
                    
                    $tooltip.css({
                        top: offset.top - $tooltip.outerHeight() - 10,
                        left: offset.left + ($(this).outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    });
                },
                function() {
                    $(this).find('.spamguard-tooltip').remove();
                }
            );
        },
        
        /**
         * Initialize confirm dialogs
         */
        initConfirmDialogs: function() {
            $('.spamguard-confirm').on('click', function(e) {
                var message = $(this).data('confirm') || spamguardData.i18n.confirmDelete;
                
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var noticeClass = 'notice-' + type;
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after h1
            $('.wrap h1').after($notice);
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 50
            }, 300);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        /**
         * Format number
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        
        /**
         * Format bytes
         */
        formatBytes: function(bytes, decimals) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var dm = decimals || 2;
            var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
    };
    
})(jQuery);