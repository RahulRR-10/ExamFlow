<?php
/**
 * Admin Logout Handler
 */

session_start();
require_once '../utils/admin_auth.php';
include '../config.php';

// Log the logout action if admin was logged in
if (isset($_SESSION['admin_id'])) {
    logAdminAction($conn, $_SESSION['admin_id'], 'logout', null, null, 'Admin logout');
}

// Clear all admin session variables
unset($_SESSION['admin_id']);
unset($_SESSION['admin_fname']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_uname']);
unset($_SESSION['user_role']);

// Destroy session if only admin was using it
if (empty($_SESSION)) {
    session_destroy();
}

// Redirect to admin login
header("Location: ../login_admin.php");
exit;
?>
