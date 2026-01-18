<?php
/**
 * Teacher Portal - Update NFT Transaction Details
 * Updates the transaction hash and token ID after blockchain confirmation
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION["user_id"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$teacher_id = $_SESSION["user_id"];

require_once '../config.php';

// Get JSON data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data || !isset($data['nft_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit();
}

// Extract data
$nft_id = intval($data['nft_id']);
$transaction_hash = isset($data['transaction_hash']) ? $data['transaction_hash'] : '';
$token_id = isset($data['token_id']) ? $data['token_id'] : '';

// Verify this NFT record belongs to the logged-in teacher
$verify_sql = "SELECT * FROM teacher_certificate_nfts WHERE id = ? AND teacher_id = ?";
$verify_stmt = mysqli_prepare($conn, $verify_sql);
mysqli_stmt_bind_param($verify_stmt, "ii", $nft_id, $teacher_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Update the transaction hash and token ID
$transaction_hash_escaped = mysqli_real_escape_string($conn, $transaction_hash);
$token_id_escaped = mysqli_real_escape_string($conn, $token_id);

$update_sql = "UPDATE teacher_certificate_nfts SET 
               transaction_hash = ?,
               token_id = ?
               WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "ssi", $transaction_hash_escaped, $token_id_escaped, $nft_id);

$update_success = mysqli_stmt_execute($update_stmt);

if (!$update_success) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . mysqli_error($conn)
    ]);
    exit();
}

error_log("Teacher NFT transaction data updated successfully: Hash=$transaction_hash, TokenID=$token_id");

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Transaction details updated successfully',
    'data' => [
        'nft_id' => $nft_id,
        'transaction_hash' => $transaction_hash,
        'token_id' => $token_id
    ]
]);
