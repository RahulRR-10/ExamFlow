<?php
/**
 * Teacher Portal - Mint Teaching Certificate NFT
 * Prepares and initiates blockchain transaction for teacher certificate
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

function json_error_handler($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => "Server error: $errstr in $errfile on line $errline",
        'error_type' => $errno
    ]);
    exit();
}
set_error_handler('json_error_handler', E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => "Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}",
            'error_type' => $error['type']
        ]);
    }
});

session_start();
if (!isset($_SESSION["user_id"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

try {
    include '../config.php';
    require_once '../students/Web3Helper.php';
    require_once '../utils/env_loader.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to include required files: ' . $e->getMessage()
    ]);
    exit();
}

$env = loadEnv(__DIR__ . '/../.env');

$env_vars = [
    'WALLET_PRIVATE_KEY' => env('WALLET_PRIVATE_KEY', ''),
    'SEPOLIA_RPC_URL' => env('SEPOLIA_RPC_URL', ''),
    'NFT_CONTRACT_ADDRESS' => env('NFT_CONTRACT_ADDRESS', '')
];

// Get JSON data from POST request
try {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (!$data || !isset($data['session_id']) || !isset($data['metadata_url'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        exit();
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to parse request data: ' . $e->getMessage()
    ]);
    exit();
}

$session_id = intval($data['session_id']);
$metadata_url = $data['metadata_url'];
$image_url = $data['image_url'] ?? '';
$teacher_id = $_SESSION['user_id'];

// Verify this session belongs to the logged-in teacher and is approved
$verify_sql = "SELECT ts.*, ste.teacher_id 
               FROM teaching_sessions ts
               JOIN slot_teacher_enrollments ste ON ts.enrollment_id = ste.enrollment_id
               WHERE ts.session_id = ? AND ste.teacher_id = ? 
               AND ts.session_status IN ('approved', 'end_submitted', 'end_approved')";
$stmt = mysqli_prepare($conn, $verify_sql);
mysqli_stmt_bind_param($stmt, "ii", $session_id, $teacher_id);
mysqli_stmt_execute($stmt);
$verify_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($verify_result) == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access or session not approved']);
    exit();
}

// Check if NFT has already been minted for this certificate
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'teacher_certificate_nfts'");
if (mysqli_num_rows($table_check) > 0) {
    $nft_sql = "SELECT * FROM teacher_certificate_nfts WHERE session_id = ?";
    $nft_stmt = mysqli_prepare($conn, $nft_sql);
    mysqli_stmt_bind_param($nft_stmt, "i", $session_id);
    mysqli_stmt_execute($nft_stmt);
    $nft_result = mysqli_stmt_get_result($nft_stmt);

    if (mysqli_num_rows($nft_result) > 0) {
        $nft_data = mysqli_fetch_assoc($nft_result);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'NFT already minted',
            'data' => $nft_data
        ]);
        exit();
    }
} else {
    // Create table if it doesn't exist
    $create_table = "CREATE TABLE teacher_certificate_nfts (
        id INT(11) NOT NULL AUTO_INCREMENT,
        session_id INT(11) NOT NULL,
        teacher_id INT(11) NOT NULL,
        transaction_hash VARCHAR(255) NOT NULL,
        token_id VARCHAR(100) NOT NULL,
        contract_address VARCHAR(255) NOT NULL,
        metadata_url VARCHAR(255) NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_demo TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (session_id)
    )";
    mysqli_query($conn, $create_table);
}

// Set up blockchain configuration
$privateKey = $env_vars['WALLET_PRIVATE_KEY'] ?? '';
$rpcUrl = $env_vars['SEPOLIA_RPC_URL'] ?? '';
$contractAddress = $env_vars['NFT_CONTRACT_ADDRESS'] ?? '';

if (empty($privateKey) || empty($rpcUrl)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing blockchain configuration. Please check your .env file.']);
    exit();
}

// Use Web3Helper to prepare the transaction
$web3Helper = new Web3Helper($rpcUrl, $privateKey, $contractAddress);
$mintResult = $web3Helper->mintNFT($metadata_url);

if (!$mintResult['success']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $mintResult['error']]);
    exit();
}

// Create a temporary transaction hash
$txHash = '0x' . bin2hex(random_bytes(32));
$tokenId = '0';

// Insert initial NFT data
$metadata_url_escaped = mysqli_real_escape_string($conn, $metadata_url);
$image_url_escaped = mysqli_real_escape_string($conn, $image_url);
$contractAddress_escaped = mysqli_real_escape_string($conn, $contractAddress);

$insert_sql = "INSERT INTO teacher_certificate_nfts (session_id, teacher_id, transaction_hash, token_id, contract_address, metadata_url, image_url, is_demo, created_at) 
               VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())";
$insert_stmt = mysqli_prepare($conn, $insert_sql);
mysqli_stmt_bind_param($insert_stmt, "iisssss", $session_id, $teacher_id, $txHash, $tokenId, $contractAddress_escaped, $metadata_url_escaped, $image_url_escaped);

if (mysqli_stmt_execute($insert_stmt)) {
    $nft_id = mysqli_insert_id($conn);
    $txData = $mintResult['data']['tx_data'] ?? [];

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'NFT record created',
        'data' => [
            'nft_id' => $nft_id,
            'session_id' => $session_id,
            'contract_address' => $contractAddress,
            'rpc_url' => $rpcUrl,
            'private_key' => $privateKey,
            'tx_data' => $txData
        ]
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . mysqli_error($conn)
    ]);
}
