<?php
/**
 * Environment Variable Loader
 * Loads variables from .env file in the project root
 */

function loadEnv($path = null) {
    if ($path === null) {
        $path = __DIR__ . '/../.env';
    }
    
    if (!file_exists($path)) {
        error_log("env_loader: .env file not found at: " . $path);
        return [];
    }
    
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            $env[$key] = $value;
            
            // Also set as environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
    
    error_log("env_loader: Loaded " . count($env) . " environment variables");
    return $env;
}

/**
 * Get an environment variable
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    return $value ?: $default;
}
?>
