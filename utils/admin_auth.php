<?php
/**
 * Admin Authentication Helper Functions
 * 
 * Provides authentication and authorization utilities for the Admin role.
 * Admins can only verify teaching activities - no access to exams, grading, etc.
 */

/**
 * Check if an admin is currently logged in
 * 
 * @return bool True if admin is logged in, false otherwise
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require admin authentication - redirects to login if not authenticated
 */
function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header("Location: ../login_admin.php");
        exit;
    }
}

/**
 * Log an admin action to the audit log
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin user ID
 * @param string $action_type Type of action (e.g., 'login', 'verify_approve', 'verify_reject')
 * @param string|null $target_table Table affected by the action
 * @param int|null $target_id ID of the record affected
 * @param string|null $details Additional details about the action
 * @return bool True on success, false on failure
 */
function logAdminAction($conn, $admin_id, $action_type, $target_table = null, $target_id = null, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO admin_audit_log (admin_id, action_type, target_table, target_id, action_details, ip_address) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "ississ", $admin_id, $action_type, $target_table, $target_id, $details, $ip);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Get admin user by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin user ID
 * @return array|null Admin data or null if not found
 */
function getAdminById($conn, $admin_id) {
    $stmt = mysqli_prepare($conn, "SELECT id, uname, fname, email, phone, status, created_at FROM admin WHERE id = ?");
    
    if (!$stmt) {
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Get admin user by username
 * 
 * @param mysqli $conn Database connection
 * @param string $uname Admin username
 * @return array|null Admin data or null if not found
 */
function getAdminByUsername($conn, $uname) {
    $stmt = mysqli_prepare($conn, "SELECT id, uname, fname, email, phone, status, created_at FROM admin WHERE uname = ?");
    
    if (!$stmt) {
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "s", $uname);
    mysqli_stmt_execute($stmt);
    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Get recent admin audit log entries
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin user ID (optional, null for all admins)
 * @param int $limit Number of entries to return
 * @return array Array of audit log entries
 */
function getAdminAuditLog($conn, $admin_id = null, $limit = 50) {
    if ($admin_id) {
        $sql = "SELECT al.*, a.fname as admin_name 
                FROM admin_audit_log al 
                JOIN admin a ON al.admin_id = a.id 
                WHERE al.admin_id = ? 
                ORDER BY al.created_at DESC 
                LIMIT ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $admin_id, $limit);
    } else {
        $sql = "SELECT al.*, a.fname as admin_name 
                FROM admin_audit_log al 
                JOIN admin a ON al.admin_id = a.id 
                ORDER BY al.created_at DESC 
                LIMIT ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $limit);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $logs;
}

/**
 * Check if admin table exists in database
 * 
 * @param mysqli $conn Database connection
 * @return bool True if table exists
 */
function adminTableExists($conn) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'admin'");
    return mysqli_num_rows($result) > 0;
}
?>
