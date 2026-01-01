<?php
/**
 * Email Configuration
 * 
 * IMPORTANT: For Gmail, you need to:
 * 1. Enable 2-Factor Authentication on your Google account
 * 2. Create an App Password: https://myaccount.google.com/apppasswords
 * 3. Set SMTP_USERNAME and SMTP_PASSWORD in your .env file
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/../.env');

// SMTP server settings (loaded from .env with fallbacks)
define('SMTP_SERVER', env('SMTP_SERVER', 'smtp.gmail.com'));
define('SMTP_PORT', (int) env('SMTP_PORT', '587'));
define('SMTP_SECURE', env('SMTP_SECURE', 'tls'));

// SMTP authentication (loaded from .env - NO hardcoded values)
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));

// Validate that credentials are configured
if (empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
    error_log('WARNING: SMTP_USERNAME and SMTP_PASSWORD must be set in .env file');
}

// Sender information
define('FROM_EMAIL', SMTP_USERNAME);
define('FROM_NAME', env('FROM_NAME', 'EduCertify System'));

// Debug level (0-4)
// 0 = No output
// 1 = Client commands
// 2 = Client commands and server responses
// 3 = As 2, plus connection status
// 4 = Low-level data output
define('SMTP_DEBUG', (int) env('SMTP_DEBUG', '0'));

?> 