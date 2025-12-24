<?php
session_start();
if (!isset($_SESSION["uname"])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

// Load environment variables from .env file
require_once __DIR__ . '/../utils/env_loader.php';
loadEnv(__DIR__ . '/../.env');

// Get Pinata credentials from environment
$response = [
    'PINATA_JWT' => env('PINATA_JWT', ''),
    'PINATA_API_KEY' => env('PINATA_API_KEY', ''),
    'PINATA_SECRET_KEY' => env('PINATA_SECRET_KEY', ''),
    'diagnostic' => [
        'using_fallback' => false,
        'source' => 'environment_variables',
        'info' => 'Loaded credentials from .env file'
    ]
];

echo json_encode($response);
