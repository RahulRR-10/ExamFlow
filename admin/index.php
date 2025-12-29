<?php
/**
 * Admin Portal Entry Point
 * Redirects to dashboard if logged in, otherwise to login page
 */

session_start();

if (isset($_SESSION["admin_id"]) && isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin") {
    header("Location: dash.php");
} else {
    header("Location: ../login_admin.php");
}
exit;
?>
