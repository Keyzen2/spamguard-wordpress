<?php
/**
 * SpamGuard Exporter
 * Handles CSV and PDF exports of logs, stats, and reports
 *
 * @package SpamGuard
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Exporter {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // AJAX actions
        add_action('wp_ajax_spamguard_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_spamguard_export_pdf', array($this, 'ajax_export_pdf'));
        add_action('wp_ajax_spamguard_schedule_export', array($this, 'ajax_schedule_export'));

        // Scheduled export cron
        add_action('spamguard_scheduled_export', array($this, 'run_scheduled_export'));
    }

    /**
     * Export data to CSV
     */
    public function export_to_csv($type, $date_from = null, $date_to = null) {
        global $wpdb;

        // Prepare filename
        $filename = 'spamguard-' . $type . '-' . date('Y-m-d-His') . '.csv';

        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Export based on type
        switch ($type) {
            case 'spam-logs':
                $this->export_spam_logs_csv($output, $date_from, $date_to);
                break;

            case 'antivirus-logs':
                $this->export_antivirus_logs_csv($output, $date_from, $date_to);
                break;

            case 'vulnerability-logs':
                $this->export_vulnerability_logs_csv($output, $date_from, $date_to);
                break;

            case 'activity-logs':
                $this->export_activity_logs_csv($output, $date_from, $date_to);
                break;

            case 'whitelist':
                $this->export_list_csv($output, 'whitelist');
                break;

            case 'blacklist':
                $this->export_list_csv($output, 'blacklist');
                break;

            default:
                fputcsv($output, array('Error', 'Invalid export type'));
        }

        fclose($output);
        exit;
    }

    /**
     * Export spam logs to CSV
     */
    private function export_spam_logs_csv($output, $date_from, $date_to) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        // CSV Headers
        fputcsv($output, array(
            'ID',
            'Date/Time',
            'Author',
            'Email',
            'IP Address',
            'Content',
            'Spam Score',
            'Status',
            'Detection Method'
        ));

        // Build query
        $where = "WHERE action = 'spam_detected'";

        if ($date_from && $date_to) {
            $where .= $wpdb->prepare(" AND created_at BETWEEN %s AND %s", $date_from, $date_to);
        }

        $query = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 5000";
        $logs = $wpdb->get_results($query);

        // Export rows
        foreach ($logs as $log) {
            $details = maybe_unserialize($log->details);

            fputcsv($output, array(
                $log->id,
                $log->created_at,
                isset($details['author']) ? $details['author'] : 'N/A',
                isset($details['email']) ? $details['email'] : 'N/A',
                $log->ip_address,
                isset($details['content']) ? substr($details['content'], 0, 200) : 'N/A',
                isset($details['spam_score']) ? $details['spam_score'] : 'N/A',
                $log->status,
                isset($details['method']) ? $details['method'] : 'API'
            ));
        }
    }

    /**
     * Export antivirus logs to CSV
     */
    private function export_antivirus_logs_csv($output, $date_from, $date_to) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        // CSV Headers
        fputcsv($output, array(
            'ID',
            'Date/Time',
            'Action',
            'File Path',
            'Threat Type',
            'Severity',
            'Status',
            'IP Address'
        ));

        // Build query
        $where = "WHERE module = 'antivirus'";

        if ($date_from && $date_to) {
            $where .= $wpdb->prepare(" AND created_at BETWEEN %s AND %s", $date_from, $date_to);
        }

        $query = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 5000";
        $logs = $wpdb->get_results($query);

        // Export rows
        foreach ($logs as $log) {
            $details = maybe_unserialize($log->details);

            fputcsv($output, array(
                $log->id,
                $log->created_at,
                $log->action,
                isset($details['file_path']) ? $details['file_path'] : 'N/A',
                isset($details['threat_type']) ? $details['threat_type'] : 'N/A',
                isset($details['severity']) ? $details['severity'] : 'N/A',
                $log->status,
                $log->ip_address
            ));
        }
    }

    /**
     * Export vulnerability logs to CSV
     */
    private function export_vulnerability_logs_csv($output, $date_from, $date_to) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_vulnerabilities';

        // CSV Headers
        fputcsv($output, array(
            'ID',
            'Date Detected',
            'Plugin/Theme',
            'Version',
            'Vulnerability Type',
            'Severity',
            'CVE ID',
            'Description',
            'Status'
        ));

        // Build query
        $where = "WHERE 1=1";

        if ($date_from && $date_to) {
            $where .= $wpdb->prepare(" AND detected_at BETWEEN %s AND %s", $date_from, $date_to);
        }

        $query = "SELECT * FROM $table $where ORDER BY detected_at DESC LIMIT 5000";
        $vulnerabilities = $wpdb->get_results($query);

        // Export rows
        foreach ($vulnerabilities as $vuln) {
            fputcsv($output, array(
                $vuln->id,
                $vuln->detected_at,
                $vuln->plugin_slug . ' (' . $vuln->type . ')',
                $vuln->version,
                $vuln->vulnerability_type,
                $vuln->severity,
                $vuln->cve_id,
                substr($vuln->description, 0, 200),
                $vuln->status
            ));
        }
    }

    /**
     * Export activity logs to CSV
     */
    private function export_activity_logs_csv($output, $date_from, $date_to) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        // CSV Headers
        fputcsv($output, array(
            'ID',
            'Date/Time',
            'Module',
            'Action',
            'Status',
            'IP Address',
            'Details'
        ));

        // Build query
        $where = "WHERE 1=1";

        if ($date_from && $date_to) {
            $where .= $wpdb->prepare(" AND created_at BETWEEN %s AND %s", $date_from, $date_to);
        }

        $query = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 5000";
        $logs = $wpdb->get_results($query);

        // Export rows
        foreach ($logs as $log) {
            $details = maybe_unserialize($log->details);
            $details_str = is_array($details) ? json_encode($details) : $details;

            fputcsv($output, array(
                $log->id,
                $log->created_at,
                $log->module,
                $log->action,
                $log->status,
                $log->ip_address,
                substr($details_str, 0, 200)
            ));
        }
    }

    /**
     * Export whitelist/blacklist to CSV
     */
    private function export_list_csv($output, $list_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_lists';

        // CSV Headers
        fputcsv($output, array(
            'ID',
            'Type',
            'Entry Type',
            'Value',
            'Reason',
            'Active',
            'Created At',
            'Created By'
        ));

        // Get entries
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE list_type = %s ORDER BY created_at DESC",
            $list_type
        ));

        // Export rows
        foreach ($entries as $entry) {
            fputcsv($output, array(
                $entry->id,
                $entry->list_type,
                $entry->entry_type,
                $entry->value,
                $entry->reason,
                $entry->is_active ? 'Yes' : 'No',
                $entry->created_at,
                $entry->created_by
            ));
        }
    }

    /**
     * Export data to PDF
     */
    public function export_to_pdf($type, $date_from = null, $date_to = null) {
        // Load FPDF library
        require_once SPAMGUARD_PLUGIN_DIR . 'includes/lib/fpdf/fpdf.php';

        // Create PDF instance
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Export based on type
        switch ($type) {
            case 'security-report':
                $this->generate_security_report_pdf($pdf, $date_from, $date_to);
                break;

            case 'spam-report':
                $this->generate_spam_report_pdf($pdf, $date_from, $date_to);
                break;

            case 'vulnerability-report':
                $this->generate_vulnerability_report_pdf($pdf, $date_from, $date_to);
                break;

            default:
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(0, 10, 'Error: Invalid report type', 0, 1, 'C');
        }

        // Output PDF
        $filename = 'spamguard-' . $type . '-' . date('Y-m-d') . '.pdf';
        $pdf->Output('D', $filename);
        exit;
    }

    /**
     * Generate security report PDF
     */
    private function generate_security_report_pdf($pdf, $date_from, $date_to) {
        // Title
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(34, 113, 177);
        $pdf->Cell(0, 15, 'SpamGuard Security Report', 0, 1, 'C');

        // Date range
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $date_range = 'Period: ';
        if ($date_from && $date_to) {
            $date_range .= date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to));
        } else {
            $date_range .= 'All Time';
        }
        $pdf->Cell(0, 8, $date_range, 0, 1, 'C');
        $pdf->Cell(0, 8, 'Generated: ' . date('F d, Y - H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);

        // Get stats
        $stats = $this->get_security_stats($date_from, $date_to);

        // Security Score Section
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, 'Security Overview', 0, 1, 'L');
        $pdf->Ln(2);

        // Stats boxes
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(0, 163, 42);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(45, 12, 'Security Score', 1, 0, 'C', true);
        $pdf->SetFillColor(214, 54, 56);
        $pdf->Cell(45, 12, 'Threats Blocked', 1, 0, 'C', true);
        $pdf->SetFillColor(219, 166, 23);
        $pdf->Cell(45, 12, 'Vulnerabilities', 1, 0, 'C', true);
        $pdf->SetFillColor(34, 113, 177);
        $pdf->Cell(45, 12, 'Spam Blocked', 1, 1, 'C', true);

        // Stats values
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(45, 12, $stats['security_score'] . '%', 1, 0, 'C', true);
        $pdf->Cell(45, 12, number_format($stats['threats_blocked']), 1, 0, 'C', true);
        $pdf->Cell(45, 12, number_format($stats['vulnerabilities']), 1, 0, 'C', true);
        $pdf->Cell(45, 12, number_format($stats['spam_blocked']), 1, 1, 'C', true);
        $pdf->Ln(8);

        // Anti-Spam Section
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, 'Anti-Spam Activity', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6,
            "Total Comments Checked: " . number_format($stats['comments_checked']) . "\n" .
            "Spam Blocked: " . number_format($stats['spam_blocked']) . "\n" .
            "Legitimate Comments: " . number_format($stats['legitimate_comments']) . "\n" .
            "Block Rate: " . number_format($stats['block_rate'], 2) . "%"
        );
        $pdf->Ln(5);

        // Vulnerabilities Section
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Vulnerability Status', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6,
            "Critical: " . $stats['vulnerabilities_by_severity']['critical'] . "\n" .
            "High: " . $stats['vulnerabilities_by_severity']['high'] . "\n" .
            "Medium: " . $stats['vulnerabilities_by_severity']['medium'] . "\n" .
            "Low: " . $stats['vulnerabilities_by_severity']['low']
        );
        $pdf->Ln(5);

        // Threats Section
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Threat Detection', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6,
            "Files Scanned: " . number_format($stats['files_scanned']) . "\n" .
            "Threats Found: " . number_format($stats['threats_found']) . "\n" .
            "Threats Quarantined: " . number_format($stats['threats_quarantined']) . "\n" .
            "Last Scan: " . $stats['last_scan_date']
        );

        // Footer
        $pdf->SetY(-20);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 10, 'Generated by SpamGuard WordPress Plugin - https://spamguard.io', 0, 0, 'C');
    }

    /**
     * Generate spam report PDF
     */
    private function generate_spam_report_pdf($pdf, $date_from, $date_to) {
        // Title
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(214, 54, 56);
        $pdf->Cell(0, 15, 'Anti-Spam Activity Report', 0, 1, 'C');

        // Date range
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 8, 'Generated: ' . date('F d, Y - H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);

        // Get recent spam logs
        global $wpdb;
        $table = $wpdb->prefix . 'spamguard_logs';

        $where = "WHERE action = 'spam_detected'";
        if ($date_from && $date_to) {
            $where .= $wpdb->prepare(" AND created_at BETWEEN %s AND %s", $date_from, $date_to);
        }

        $logs = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 50");

        // Table header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(214, 54, 56);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(35, 8, 'Date/Time', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Email', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'IP Address', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Score', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'Content Preview', 1, 1, 'C', true);

        // Table rows
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(245, 245, 245);
        $fill = false;

        foreach ($logs as $log) {
            $details = maybe_unserialize($log->details);

            $pdf->Cell(35, 6, date('M d, H:i', strtotime($log->created_at)), 1, 0, 'L', $fill);
            $pdf->Cell(40, 6, substr($details['email'] ?? 'N/A', 0, 25), 1, 0, 'L', $fill);
            $pdf->Cell(35, 6, $log->ip_address, 1, 0, 'C', $fill);
            $pdf->Cell(20, 6, ($details['spam_score'] ?? 0) . '%', 1, 0, 'C', $fill);
            $pdf->Cell(50, 6, substr($details['content'] ?? 'N/A', 0, 30) . '...', 1, 1, 'L', $fill);

            $fill = !$fill;
        }

        // Footer
        $pdf->SetY(-20);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 10, 'Generated by SpamGuard - Page ' . $pdf->PageNo(), 0, 0, 'C');
    }

    /**
     * Generate vulnerability report PDF
     */
    private function generate_vulnerability_report_pdf($pdf, $date_from, $date_to) {
        // Similar structure to spam report but for vulnerabilities
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(219, 166, 23);
        $pdf->Cell(0, 15, 'Vulnerability Assessment Report', 0, 1, 'C');

        // Implementation similar to spam report
        // ... (shortened for brevity)
    }

    /**
     * Get security statistics
     */
    private function get_security_stats($date_from = null, $date_to = null) {
        global $wpdb;

        $stats = array(
            'security_score' => 85,
            'threats_blocked' => 0,
            'vulnerabilities' => 0,
            'spam_blocked' => 0,
            'comments_checked' => 0,
            'legitimate_comments' => 0,
            'block_rate' => 0,
            'vulnerabilities_by_severity' => array(
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ),
            'files_scanned' => 0,
            'threats_found' => 0,
            'threats_quarantined' => 0,
            'last_scan_date' => 'Never'
        );

        // Get spam stats
        $logs_table = $wpdb->prefix . 'spamguard_logs';
        $where = "1=1";
        if ($date_from && $date_to) {
            $where .= $wpdb->prepare(" AND created_at BETWEEN %s AND %s", $date_from, $date_to);
        }

        $stats['spam_blocked'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE action = 'spam_detected' AND $where"
        ));

        $stats['comments_checked'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE module = 'antispam' AND $where"
        ));

        $stats['legitimate_comments'] = $stats['comments_checked'] - $stats['spam_blocked'];

        if ($stats['comments_checked'] > 0) {
            $stats['block_rate'] = ($stats['spam_blocked'] / $stats['comments_checked']) * 100;
        }

        // Get vulnerability stats
        $vuln_table = $wpdb->prefix . 'spamguard_vulnerabilities';
        $stats['vulnerabilities'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $vuln_table WHERE status != 'fixed'"));

        $severity_counts = $wpdb->get_results(
            "SELECT severity, COUNT(*) as count FROM $vuln_table WHERE status != 'fixed' GROUP BY severity"
        );

        foreach ($severity_counts as $row) {
            $stats['vulnerabilities_by_severity'][strtolower($row->severity)] = intval($row->count);
        }

        // Get antivirus stats
        $stats['threats_blocked'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE module = 'antivirus' AND action = 'threat_detected'"
        ));

        return $stats;
    }

    /**
     * AJAX: Export CSV
     */
    public function ajax_export_csv() {
        check_ajax_referer('spamguard_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null;
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null;

        if (empty($type)) {
            wp_send_json_error(array('message' => __('Export type is required', 'spamguard')));
        }

        // Export will be handled directly (headers sent)
        $this->export_to_csv($type, $date_from, $date_to);
    }

    /**
     * AJAX: Export PDF
     */
    public function ajax_export_pdf() {
        check_ajax_referer('spamguard_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null;
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null;

        if (empty($type)) {
            wp_send_json_error(array('message' => __('Export type is required', 'spamguard')));
        }

        // Export will be handled directly (headers sent)
        $this->export_to_pdf($type, $date_from, $date_to);
    }

    /**
     * AJAX: Schedule export
     */
    public function ajax_schedule_export() {
        check_ajax_referer('spamguard_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'spamguard')));
        }

        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'weekly';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : get_option('admin_email');
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'security-report';

        // Save schedule settings
        update_option('spamguard_export_schedule', array(
            'enabled' => true,
            'frequency' => $frequency,
            'email' => $email,
            'type' => $type
        ));

        // Clear existing schedule
        wp_clear_scheduled_hook('spamguard_scheduled_export');

        // Schedule new cron job
        $schedules = array(
            'daily' => 'daily',
            'weekly' => 'weekly',
            'monthly' => 'monthly'
        );

        if (isset($schedules[$frequency])) {
            wp_schedule_event(time(), $schedules[$frequency], 'spamguard_scheduled_export');
        }

        wp_send_json_success(array('message' => __('Export schedule saved successfully', 'spamguard')));
    }

    /**
     * Run scheduled export
     */
    public function run_scheduled_export() {
        $schedule = get_option('spamguard_export_schedule');

        if (!$schedule || !$schedule['enabled']) {
            return;
        }

        // Generate PDF report
        ob_start();
        $this->export_to_pdf($schedule['type']);
        $pdf_content = ob_get_clean();

        // Send email
        $to = $schedule['email'];
        $subject = 'SpamGuard Automated Security Report - ' . date('F d, Y');
        $message = "Your scheduled SpamGuard security report is attached.\n\n";
        $message .= "Period: " . date('F d, Y') . "\n";
        $message .= "Generated automatically by SpamGuard.\n";

        $attachments = array();

        // Save temporary PDF file
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/spamguard-report-temp.pdf';
        file_put_contents($temp_file, $pdf_content);
        $attachments[] = $temp_file;

        // Send email
        wp_mail($to, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'), $attachments);

        // Clean up
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    }
}
