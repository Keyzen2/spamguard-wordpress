/**
 * SpamGuard Dashboard v2 - JavaScript
 * Handles charts, auto-refresh, and interactivity
 */

(function($) {
    'use strict';

    const SpamGuardDashboard = {

        charts: {},

        init: function() {
            console.log('SpamGuard Dashboard v2 initialized');

            this.initCharts();
            this.initEventListeners();
            this.initAutoRefresh();
            this.initActivityFilters();
        },

        /**
         * Initialize all charts
         */
        initCharts: function() {
            // Anti-Spam Mini Chart
            this.createMiniLineChart('chart-antispam', 'Anti-Spam Activity');

            // Vulnerabilities Pie Chart
            this.createPieChart('chart-vulnerabilities');

            // Main Timeline Chart
            this.createMainTimelineChart('chart-main-timeline');
        },

        /**
         * Create mini line chart for modules
         */
        createMiniLineChart: function(canvasId, label) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Mock data - replace with AJAX call
            const data = {
                labels: this.getLast7Days(),
                datasets: [{
                    label: label,
                    data: [45, 52, 38, 65, 55, 72, 58],
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 0,
                    borderWidth: 2
                }]
            };

            this.charts[canvasId] = new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            display: false
                        },
                        y: {
                            display: false
                        }
                    }
                }
            });
        },

        /**
         * Create pie chart for vulnerabilities
         */
        createPieChart: function(canvasId) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            const data = {
                labels: ['Critical', 'High', 'Medium', 'Low'],
                datasets: [{
                    data: [2, 5, 8, 3],
                    backgroundColor: [
                        '#d63638',
                        '#dba617',
                        '#00a32a',
                        '#2271b1'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            };

            this.charts[canvasId] = new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Create main timeline chart (30 days)
         */
        createMainTimelineChart: function(canvasId) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Mock data - replace with AJAX
            const labels = this.getLast30Days();

            const data = {
                labels: labels,
                datasets: [
                    {
                        label: 'Spam Blocked',
                        data: this.generateRandomData(30, 10, 50),
                        borderColor: '#d63638',
                        backgroundColor: 'rgba(214, 54, 56, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Legitimate Comments',
                        data: this.generateRandomData(30, 30, 80),
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Threats Found',
                        data: this.generateRandomData(30, 0, 5),
                        borderColor: '#dba617',
                        backgroundColor: 'rgba(219, 166, 23, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            };

            this.charts[canvasId] = new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 3,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Init event listeners
         */
        initEventListeners: function() {
            const self = this;

            // Refresh button
            $('#sg-refresh-dashboard').on('click', function(e) {
                e.preventDefault();
                self.refreshDashboard();
            });

            // Quick scan button
            $('.sg-scan-btn').on('click', function(e) {
                e.preventDefault();
                const scanType = $(this).data('scan-type');
                self.startQuickScan(scanType);
            });
        },

        /**
         * Init activity filters
         */
        initActivityFilters: function() {
            $('.sg-filter-btn').on('click', function() {
                const filter = $(this).data('filter');

                $('.sg-filter-btn').removeClass('active');
                $(this).addClass('active');

                // Filter activity items
                if (filter === 'all') {
                    $('.sg-activity-item').show();
                } else {
                    $('.sg-activity-item').hide();
                    $('.sg-activity-' + filter).show();
                }
            });
        },

        /**
         * Init auto-refresh (every 30 seconds)
         */
        initAutoRefresh: function() {
            const self = this;
            const interval = spamguardDashboard.refreshInterval || 30000;

            setInterval(function() {
                self.refreshDashboard(true); // Silent refresh
            }, interval);
        },

        /**
         * Refresh dashboard data
         */
        refreshDashboard: function(silent = false) {
            const $btn = $('#sg-refresh-dashboard');

            if (!silent) {
                $btn.addClass('loading').prop('disabled', true);
            }

            $.ajax({
                url: spamguardDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_refresh_dashboard',
                    nonce: spamguardDashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update stats
                        self.updateStats(response.data);

                        if (!silent) {
                            self.showNotification('Dashboard refreshed successfully', 'success');
                        }
                    }
                },
                complete: function() {
                    $btn.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Update stats on page
         */
        updateStats: function(data) {
            // Update status cards
            if (data.status) {
                $('.sg-card-critical .sg-card-number').text(data.status.critical);
                $('.sg-card-warning .sg-card-number').text(data.status.warnings);
                $('.sg-card-safe .sg-card-number').text(data.status.safe);
            }

            // Update security score
            if (data.security_score) {
                this.updateSecurityScore(data.security_score);
            }

            // Update charts
            if (data.chart_data) {
                this.updateCharts(data.chart_data);
            }
        },

        /**
         * Update security score circle
         */
        updateSecurityScore: function(score) {
            const $scoreNumber = $('.sg-score-number');
            const $scoreFill = $('.sg-score-fill');

            // Animate number
            $({ score: parseInt($scoreNumber.text()) }).animate({ score: score }, {
                duration: 1000,
                step: function() {
                    $scoreNumber.text(Math.floor(this.score));
                }
            });

            // Animate circle
            const offset = 283 - (283 * score / 100);
            $scoreFill.css('stroke-dashoffset', offset);

            // Change color based on score
            let color = '#00ff88';
            if (score < 50) color = '#d63638';
            else if (score < 75) color = '#dba617';

            $scoreFill.css('stroke', color);
        },

        /**
         * Update charts with new data
         */
        updateCharts: function(chartData) {
            Object.keys(chartData).forEach((chartId) => {
                if (this.charts[chartId]) {
                    this.charts[chartId].data = chartData[chartId];
                    this.charts[chartId].update();
                }
            });
        },

        /**
         * Start quick scan
         */
        startQuickScan: function(scanType) {
            const $btn = $('.sg-scan-btn[data-scan-type="' + scanType + '"]');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scanning...');

            $.ajax({
                url: spamguardDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spamguard_start_scan',
                    scan_type: scanType,
                    nonce: spamguardDashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url ||
                            adminpage = 'admin.php?page=spamguard-antivirus';
                    } else {
                        alert(response.data.message || 'Failed to start scan');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Quick Scan');
                    }
                },
                error: function() {
                    alert('An error occurred while starting the scan');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Quick Scan');
                }
            });
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.spamguard-dashboard-v2').prepend($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Helper: Get last 7 days labels
         */
        getLast7Days: function() {
            const days = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            return days;
        },

        /**
         * Helper: Get last 30 days labels
         */
        getLast30Days: function() {
            const days = [];
            for (let i = 29; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            return days;
        },

        /**
         * Helper: Generate random data for demo
         */
        generateRandomData: function(length, min, max) {
            const data = [];
            for (let i = 0; i < length; i++) {
                data.push(Math.floor(Math.random() * (max - min + 1)) + min);
            }
            return data;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.spamguard-dashboard-v2').length) {
            SpamGuardDashboard.init();
        }
    });

})(jQuery);
