<?php
// Ensure all errors are caught and not displayed directly
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set up custom error handler to ensure JSON output instead of HTML
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

// Catch fatal errors
register_shutdown_function(function() {
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

// Start session and check authentication
session_start();
if (!isset($_SESSION["uname"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Include database connection, Web3Helper, and mailer utility
try {
    include '../config.php';
    require_once 'Web3Helper.php';
    require_once '../utils/mailer.php';
    require_once '../utils/env_loader.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to include required files: ' . $e->getMessage()
    ]);
    exit();
}

// Load environment variables from .env file
$env = loadEnv(__DIR__ . '/../.env');

// Get blockchain configuration from .env
$env_vars = [
    'WALLET_PRIVATE_KEY' => env('WALLET_PRIVATE_KEY', ''),
    'SEPOLIA_RPC_URL' => env('SEPOLIA_RPC_URL', ''),
    'INFURA_PROJECT_ID' => env('INFURA_PROJECT_ID', ''),
    'NFT_CONTRACT_ADDRESS' => env('NFT_CONTRACT_ADDRESS', '0xfE9c584F6360966B949a8804414B07C546a6F69F')
];

// Log the configuration 
error_log("mint_nft.php: Loaded blockchain configuration from .env file");

// Get JSON data from POST request
try {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (!$data || !isset($data['attempt_id']) || !isset($data['metadata_url'])) {
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

$attempt_id = intval($data['attempt_id']);
$metadata_url = $data['metadata_url'];
$image_url = $data['image_url'] ?? '';
$uname = $_SESSION['uname'];

// Certificate image may be passed as base64 data
$certificate_image = isset($data['certificate_image']) ? $data['certificate_image'] : null;

// Verify this attempt belongs to the logged-in user
$verify_sql = "SELECT * FROM atmpt_list WHERE id = $attempt_id AND uname = '$uname'";
$verify_result = mysqli_query($conn, $verify_sql);

if (mysqli_num_rows($verify_result) == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if NFT has already been minted for this certificate
$nft_sql = "SELECT * FROM certificate_nfts WHERE attempt_id = $attempt_id";
$nft_result = mysqli_query($conn, $nft_sql);

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

// Set up blockchain configuration
$privateKey = $env_vars['WALLET_PRIVATE_KEY'] ?? '';
$rpcUrl = $env_vars['SEPOLIA_RPC_URL'] ?? '';
$contractAddress = $env_vars['NFT_CONTRACT_ADDRESS'] ?? '0xfE9c584F6360966B949a8804414B07C546a6F69F';

if (empty($privateKey) || empty($rpcUrl)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing blockchain configuration. Please check your .env file.']);
    exit();
}

// Use our Web3Helper to perform the blockchain transaction
$web3Helper = new Web3Helper($rpcUrl, $privateKey, $contractAddress);
$mintResult = $web3Helper->mintNFT($metadata_url);

if (!$mintResult['success']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $mintResult['error']]);
    exit();
}

// For our implementation, we'll use the transaction data for client-side processing
// first ensure the certificate_nfts table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'certificate_nfts'");
if (mysqli_num_rows($table_check) == 0) {
    $create_table = "CREATE TABLE certificate_nfts (
        id INT(11) NOT NULL AUTO_INCREMENT,
        attempt_id INT(11) NOT NULL,
        uname VARCHAR(100) NOT NULL,
        transaction_hash VARCHAR(255) NOT NULL,
        token_id VARCHAR(100) NOT NULL,
        contract_address VARCHAR(255) NOT NULL,
        metadata_url VARCHAR(255) NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_demo TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (attempt_id)
    )";
    mysqli_query($conn, $create_table);
}

// Create a temporary transaction hash
$txHash = '0x' . bin2hex(random_bytes(32));
$tokenId = '0'; // To be updated by the client

// Insert initial NFT data
$insert_sql = "INSERT INTO certificate_nfts (attempt_id, uname, transaction_hash, token_id, contract_address, metadata_url, image_url, is_demo, created_at) 
               VALUES ($attempt_id, '$uname', '$txHash', '$tokenId', '$contractAddress', '$metadata_url', '$image_url', 0, NOW())";

if (mysqli_query($conn, $insert_sql)) {
    // Get the ID of the inserted record
    $nft_id = mysqli_insert_id($conn);
    
    // Prepare the data for the client
    $txData = $mintResult['data']['tx_data'] ?? [];
    
    // Save certificate image for email attachment
    $certificate_path = null;
    $email_sent = false;
    
    // Process certificate image if available
    if ($certificate_image) {
        try {
            // Ensure the certificates directory exists
            $certificates_dir = '../certificates';
            if (!file_exists($certificates_dir)) {
                if (!mkdir($certificates_dir, 0755, true)) {
                    error_log("Failed to create certificates directory: $certificates_dir");
                }
            }
            
            if (!is_writable($certificates_dir)) {
                error_log("Certificates directory is not writable: $certificates_dir");
            } else {
                // Save the certificate image
                $certificate_path = $certificates_dir . "/certificate_{$uname}_{$attempt_id}.png";
                $save_success = false;
                
                // If image is base64 encoded
                if (strpos($certificate_image, 'base64,') !== false) {
                    error_log("Received base64 image data of length: " . strlen($certificate_image));
                    list($mime_type, $base64_data) = explode('base64,', $certificate_image, 2);
                    error_log("MIME type: $mime_type, Data length: " . strlen($base64_data));
                    
                    $image_data = base64_decode($base64_data, true);
                    if ($image_data !== false) {
                        $save_success = file_put_contents($certificate_path, $image_data) !== false;
                        error_log("Base64 decode and save " . ($save_success ? "successful" : "failed"));
                    } else {
                        error_log("Failed to decode base64 certificate image for {$uname}");
                    }
                }
                // If image is a URL
                else if (filter_var($certificate_image, FILTER_VALIDATE_URL)) {
                    $certificate_content = @file_get_contents($certificate_image);
                    if ($certificate_content !== false) {
                        $save_success = file_put_contents($certificate_path, $certificate_content) !== false;
                    } else {
                        error_log("Failed to fetch certificate image from URL for {$uname}");
                    }
                }
                
                if (!$save_success) {
                    error_log("Failed to save certificate image to: $certificate_path");
                }
                
                // Email sending is now handled by update_transaction.php to avoid duplicate emails
                // Do not send email here, just indicate that the image was saved successfully
                $email_sent = false;
                if ($save_success) {
                    error_log("Certificate image saved successfully at: $certificate_path");
                    error_log("Email will be sent by update_transaction.php");
                }
            }
        } catch (Exception $e) {
            error_log("Exception processing certificate image: " . $e->getMessage());
        }
    }
    
    // Return the transaction data for client-side processing
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Transaction prepared for client-side signing',
        'data' => [
            'nft_id' => $nft_id,
            'transaction_hash' => $txHash,
            'token_id' => $tokenId,
            'contract_address' => $contractAddress,
            'metadata_url' => $metadata_url,
            'image_url' => $image_url,
            'rpc_url' => $rpcUrl,
            'private_key' => $privateKey,
            'tx_data' => $txData,
            'email_sent' => $email_sent
        ]
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($conn)]);
}
?> 