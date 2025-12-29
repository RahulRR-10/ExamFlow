<?php
/**
 * Export Report to CSV
 * 
 * Exports teaching activity submission data as a downloadable CSV file
 */

session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

// Date range filter
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$schoolFilter = intval($_GET['school_id'] ?? 0);
$exportType = $_GET['type'] ?? 'submissions';

// Set headers for CSV download
$filename = "teaching_activity_report_{$startDate}_to_{$endDate}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($exportType === 'summary') {
    // Export summary statistics
    fputcsv($output, ['Teaching Activity Verification Report']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Date Range:', $startDate . ' to ' . $endDate]);
    fputcsv($output, []);
    
    // Get summary stats
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN location_match_status = 'matched' THEN 1 ELSE 0 END) as location_matched,
                SUM(CASE WHEN location_match_status = 'mismatched' THEN 1 ELSE 0 END) as location_mismatched
            FROM teaching_activity_submissions
            WHERE DATE(upload_date) BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($schoolFilter > 0) {
        $sql .= " AND school_id = ?";
        $params[] = $schoolFilter;
        $types .= "i";
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    fputcsv($output, ['Summary Statistics']);
    fputcsv($output, ['Metric', 'Value', 'Percentage']);
    fputcsv($output, ['Total Submissions', $stats['total'], '100%']);
    fputcsv($output, ['Approved', $stats['approved'], $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100, 1) . '%' : '0%']);
    fputcsv($output, ['Rejected', $stats['rejected'], $stats['total'] > 0 ? round(($stats['rejected'] / $stats['total']) * 100, 1) . '%' : '0%']);
    fputcsv($output, ['Pending', $stats['pending'], $stats['total'] > 0 ? round(($stats['pending'] / $stats['total']) * 100, 1) . '%' : '0%']);
    fputcsv($output, ['Location Matched', $stats['location_matched'], $stats['total'] > 0 ? round(($stats['location_matched'] / $stats['total']) * 100, 1) . '%' : '0%']);
    fputcsv($output, ['Location Mismatched', $stats['location_mismatched'], $stats['total'] > 0 ? round(($stats['location_mismatched'] / $stats['total']) * 100, 1) . '%' : '0%']);

} else {
    // Export detailed submissions
    
    // Headers
    fputcsv($output, [
        'Submission ID',
        'Teacher Name',
        'Teacher Email',
        'School Name',
        'Activity Date',
        'Upload Date',
        'Photo Taken At',
        'GPS Latitude',
        'GPS Longitude',
        'Distance from School (m)',
        'Location Status',
        'Verification Status',
        'Verified By',
        'Verified At',
        'Admin Remarks',
        'Is Suspicious',
        'Device Make',
        'Device Model'
    ]);
    
    // Query
    $sql = "SELECT tas.id, t.fname as teacher_name, t.email as teacher_email,
                   s.school_name, tas.activity_date, tas.upload_date, tas.photo_taken_at,
                   tas.gps_latitude, tas.gps_longitude, tas.distance_from_school,
                   tas.location_match_status, tas.verification_status,
                   a.fname as verified_by, tas.verified_at, tas.admin_remarks,
                   tas.is_suspicious, tas.device_make, tas.device_model
            FROM teaching_activity_submissions tas
            JOIN teacher t ON tas.teacher_id = t.id
            JOIN schools s ON tas.school_id = s.school_id
            LEFT JOIN admin a ON tas.verified_by = a.id
            WHERE DATE(tas.upload_date) BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($schoolFilter > 0) {
        $sql .= " AND tas.school_id = ?";
        $params[] = $schoolFilter;
        $types .= "i";
    }
    
    $sql .= " ORDER BY tas.upload_date DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['id'],
            $row['teacher_name'],
            $row['teacher_email'],
            $row['school_name'],
            $row['activity_date'],
            $row['upload_date'],
            $row['photo_taken_at'],
            $row['gps_latitude'],
            $row['gps_longitude'],
            $row['distance_from_school'] ? round($row['distance_from_school']) : '',
            $row['location_match_status'],
            $row['verification_status'],
            $row['verified_by'] ?? '',
            $row['verified_at'] ?? '',
            $row['admin_remarks'] ?? '',
            $row['is_suspicious'] ? 'Yes' : 'No',
            $row['device_make'] ?? '',
            $row['device_model'] ?? ''
        ]);
    }
    
    mysqli_stmt_close($stmt);
}

// Log export action
logAdminAction($conn, $_SESSION['admin_id'], 'export_report', null, null, 
    "Exported {$exportType} report from {$startDate} to {$endDate}");

fclose($output);
exit;
