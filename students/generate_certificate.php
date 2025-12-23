<?php
session_start();
if (!isset($_SESSION["uname"])) {
    header("Location: ../login_student.php");
    exit();
}

// Include database connection
include '../config.php';
require_once '../utils/school_access_control.php';

// Load environment variables for blockchain integration
$env_file = __DIR__ . '/.env';
if (!file_exists($env_file)) {
    // Try parent directory
    $env_file = dirname(__DIR__) . '/.env';
}

$env_vars = [];
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) { // Make sure the line contains an equals sign
            list($name, $value) = explode('=', $line, 2);
            $env_vars[trim($name)] = trim($value);
        }
    }
}

// Get parameters
$attempt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$uname = $_SESSION['uname'];
$school_id = $_SESSION['school_id'] ?? 0;

if (!$attempt_id) {
    die("Certificate ID not provided");
}

// Verify this attempt belongs to the logged-in user AND is from their school
$verify_sql = "SELECT a.*, e.school_id FROM atmpt_list a 
               INNER JOIN exm_list e ON a.exid = e.exid 
               WHERE a.id = ? AND a.uname = ? AND e.school_id = ?";
$stmt = mysqli_prepare($conn, $verify_sql);
mysqli_stmt_bind_param($stmt, "isi", $attempt_id, $uname, $school_id);
mysqli_stmt_execute($stmt);
$verify_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($verify_result) == 0) {
    die("Unauthorized access");
}

// Get attempt data
$attempt_data = mysqli_fetch_assoc($verify_result);
$exid = $attempt_data['exid'];
$score = $attempt_data['cnq'];
$total = $attempt_data['nq'];
$percentage = $attempt_data['ptg'];
$completion_date = date("F j, Y", strtotime($attempt_data['subtime']));
$integrity_score = $attempt_data['integrity_score'] ?? 100;
$integrity_category = $attempt_data['integrity_category'] ?? 'Good';

// Get exam name
$exam_sql = "SELECT exname, subject FROM exm_list WHERE exid = $exid";
$exam_result = mysqli_query($conn, $exam_sql);
$exam_data = mysqli_fetch_assoc($exam_result);
$exam_name = $exam_data['exname'];
$subject = $exam_data['subject'];

// Get student name
$student_sql = "SELECT fname FROM student WHERE uname = '$uname'";
$student_result = mysqli_query($conn, $student_sql);
$student_data = mysqli_fetch_assoc($student_result);
$student_name = $student_data['fname'];

// Check if NFT has already been minted for this certificate
$nft_minted = false;
$nft_data = null;

// First check if the table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'certificate_nfts'");
if (mysqli_num_rows($table_check) > 0) {
    // Table exists, now we can query it
    $nft_sql = "SELECT * FROM certificate_nfts WHERE attempt_id = $attempt_id";
    $nft_result = mysqli_query($conn, $nft_sql);

    if ($nft_result && mysqli_num_rows($nft_result) > 0) {
        $nft_data = mysqli_fetch_assoc($nft_result);
        $nft_minted = true;
    }
} else {
    // Table doesn't exist, let's create it
    $create_table = "CREATE TABLE certificate_nfts (
        id INT(11) NOT NULL AUTO_INCREMENT,
        attempt_id INT(11) NOT NULL,
        uname VARCHAR(100) NOT NULL,
        transaction_hash VARCHAR(255) NOT NULL,
        token_id VARCHAR(100) NOT NULL,
        contract_address VARCHAR(255) NOT NULL,
        metadata_url VARCHAR(255) NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (attempt_id)
    )";
    mysqli_query($conn, $create_table);
}

// Create HTML certificate instead of GD image
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - Blockchain Verified</title>
    <!-- Include libraries -->
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

    <!-- Multiple CDN sources for ethers.js with fallback -->
    <script>
        // Function to load script from URL
        function loadScript(url, callback) {
            var script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = url;
            script.onload = callback;
            script.onerror = function() {
                console.error('Failed to load script from: ' + url);
                // If there are more URLs in the queue, try the next one
                if (scriptQueue.length > 0) {
                    loadScript(scriptQueue.shift(), callback);
                } else {
                    console.error('All script loading attempts failed');
                    document.getElementById('mint-progress').innerHTML =
                        '<p class="status-error">Failed to load required libraries. Please check your internet connection and try again.</p>' +
                        '<button class="mint-btn" onclick="window.location.reload()">Retry</button>';
                }
            };
            document.head.appendChild(script);
        }

        // Queue of script URLs to try
        var scriptQueue = [
            'https://cdn.jsdelivr.net/npm/ethers@5.2.0/dist/ethers.umd.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/ethers/5.2.0/ethers.umd.min.js',
            'https://unpkg.com/ethers@5.2.0/dist/ethers.umd.min.js'
        ];

        // Start loading the first script
        loadScript(scriptQueue.shift(), function() {
            console.log('Ethers.js library loaded successfully');
        });

        // Fallback function if all CDNs fail - we'll create a minimal ethers object
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (typeof ethers === 'undefined') {
                    console.log('Creating minimal ethers fallback for testing');
                    // Create a mock ethers object with the minimal functionality needed
                    window.ethers = {
                        providers: {
                            JsonRpcProvider: function() {
                                return {
                                    // Mock methods
                                    getNetwork: function() {
                                        return Promise.resolve({
                                            chainId: 11155111
                                        });
                                    }
                                };
                            }
                        },
                        Wallet: function() {
                            return {
                                // Mock methods
                                connect: function() {
                                    return this;
                                },
                                getAddress: function() {
                                    return Promise.resolve('0x0000000000000000000000000000000000000000');
                                }
                            };
                        },
                        Contract: function() {
                            return {
                                // Mock method that will record the transaction
                                mint: function(metadataUrl) {
                                    console.log('Mock contract mint called with:', metadataUrl);
                                    // Create mock transaction object
                                    return {
                                        hash: '0x' + Array(64).fill(0).map(() => Math.floor(Math.random() * 16).toString(16)).join(''),
                                        wait: function() {
                                            return Promise.resolve({
                                                status: 1,
                                                logs: [{
                                                    topics: [
                                                        '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                                                        '0x0000000000000000000000000000000000000000000000000000000000000000',
                                                        '0x0000000000000000000000000000000000000000000000000000000000000000',
                                                        '0x0000000000000000000000000000000000000000000000000000000000000001'
                                                    ]
                                                }]
                                            });
                                        }
                                    };
                                }
                            };
                        },
                        BigNumber: {
                            from: function(val) {
                                return {
                                    toString: function() {
                                        return '1';
                                    }
                                };
                            }
                        }
                    };
                }
            }, 5000); // Check after 5 seconds
        });
    </script>
    <!-- Include better fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Cormorant+Garamond:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-btn,
            .blockchain-section,
            .back-btn {
                display: none;
            }

            .main-container {
                flex-direction: column;
            }

            .certificate-container {
                box-shadow: none !important;
                margin: 0 auto !important;
            }
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #333;
            line-height: 1.6;
        }

        .page-title {
            text-align: center;
            margin-bottom: 30px;
            width: 100%;
        }

        .page-title h1 {
            font-size: 28px;
            color: #0A2558;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .page-title p {
            font-size: 16px;
            color: #666;
        }

        .back-btn {
            align-self: flex-start;
            margin-bottom: 20px;
            background-color: #0A2558;
            color: white;
            border: none;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background-color: #0d3a80;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .back-btn svg {
            margin-right: 8px;
        }

        .main-container {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            max-width: 1600px;
            gap: 30px;
            justify-content: center;
        }

        .left-column {
            flex: 1;
            min-width: 800px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .right-column {
            flex: 1;
            min-width: 400px;
            max-width: 600px;
            padding: 30px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .certificate-container {
            background-color: #fefef9;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23d0b15e' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            width: 800px;
            height: 600px;
            padding: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: 2px solid #d4af37;
            position: relative;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .certificate-container:hover {
            transform: scale(1.01);
        }

        /* Fancy border with corner decorations */
        .fancy-border {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 2px solid rgba(212, 175, 55, 0.5);
            margin: 10px;
            pointer-events: none;
            z-index: 1;
            box-shadow: inset 0 0 15px rgba(212, 175, 55, 0.15);
        }

        .corner {
            position: absolute;
            width: 40px;
            height: 40px;
            border-color: #d4af37;
            z-index: 2;
        }

        .corner-top-left {
            top: 10px;
            left: 10px;
            border-top: 4px solid;
            border-left: 4px solid;
        }

        .corner-top-right {
            top: 10px;
            right: 10px;
            border-top: 4px solid;
            border-right: 4px solid;
        }

        .corner-bottom-left {
            bottom: 10px;
            left: 10px;
            border-bottom: 4px solid;
            border-left: 4px solid;
        }

        .corner-bottom-right {
            bottom: 10px;
            right: 10px;
            border-bottom: 4px solid;
            border-right: 4px solid;
        }

        /* Certificate seal */
        .certificate-seal {
            position: absolute;
            top: 20px;
            right: 30px;
            width: 110px;
            height: 110px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.7) 0%, rgba(212, 175, 55, 0.1) 70%);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #7a5c00;
            font-size: 14px;
            text-align: center;
            font-weight: bold;
            font-family: 'Playfair Display', serif;
            transform: rotate(10deg);
            text-transform: uppercase;
            line-height: 1.3;
            padding: 10px;
            z-index: 3;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px dashed #7a5c00;
        }

        .certificate-seal::before {
            content: '';
            position: absolute;
            width: 102px;
            height: 102px;
            border: 1px dashed #7a5c00;
            border-radius: 50%;
        }

        .certificate-header {
            text-align: center;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(212, 175, 55, 0.3);
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }

        .certificate-title {
            color: #0A2558;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            font-family: 'Playfair Display', serif;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 25px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }

        .certificate-subtitle {
            color: #0A2558;
            font-size: 20px;
            font-weight: bold;
            font-family: 'Cormorant Garamond', serif;
            letter-spacing: 1px;
        }

        .certificate-content {
            padding: 10px 40px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .student-name {
            font-size: 24px;
            font-weight: bold;
            color: #0A2558;
            margin: 8px 0;
            font-family: 'Playfair Display', serif;
            border-bottom: 1px solid #d4af37;
            display: inline-block;
            padding: 0 20px 3px;
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.05);
            letter-spacing: 0.5px;
        }

        .certificate-text {
            font-size: 16px;
            margin: 6px 0;
            line-height: 1.4;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 500;
        }

        .certificate-details {
            margin: 15px 0;
            text-align: center;
            position: relative;
            z-index: 2;
            background-color: rgba(255, 255, 255, 0.7);
            padding: 12px;
            border-radius: 8px;
            width: 90%;
            margin-left: auto;
            margin-right: auto;
            border: 1px solid rgba(212, 175, 55, 0.3);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .certificate-detail {
            font-size: 15px;
            margin: 8px 0;
            font-family: 'Montserrat', sans-serif;
            line-height: 1.3;
        }

        .integrity-score-good {
            color: #28a745;
            font-weight: bold;
            padding: 2px 6px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 4px;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .integrity-score-at-risk {
            color: #ffc107;
            font-weight: bold;
            padding: 2px 6px;
            background-color: rgba(255, 193, 7, 0.1);
            border-radius: 4px;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .integrity-score-cheating {
            color: #dc3545;
            font-weight: bold;
            padding: 2px 6px;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 4px;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .certificate-date {
            font-style: italic;
        }

        .certificate-footer {
            position: absolute;
            bottom: 15px;
            width: calc(100% - 40px);
            text-align: center;
            z-index: 2;
        }

        .signature-line {
            width: 200px;
            height: 1px;
            background-color: #000;
            margin: 30px auto 5px auto;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .signature-title {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            margin-top: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
            display: none;
        }

        .print-btn {
            background-color: #0A2558;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 15px;
            cursor: pointer;
            border-radius: 8px;
            margin-top: 20px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .print-btn:hover {
            background-color: #0d3a80;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }

        /* Ribbon decoration */
        .ribbon {
            position: absolute;
            bottom: 70px;
            left: 30px;
            width: 80px;
            height: 80px;
            z-index: 3;
            opacity: 0.9;
        }

        .ribbon-circle {
            position: absolute;
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, #d4af37 0%, #bb9632 100%);
            border-radius: 50%;
            border: 1px solid #946e00;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ribbon-circle::after {
            content: '';
            position: absolute;
            width: 50px;
            height: 50px;
            border: 1px dashed rgba(255, 255, 255, 0.5);
            border-radius: 50%;
        }

        .ribbon-tail {
            position: absolute;
            top: 45px;
            left: 15px;
            width: 15px;
            height: 40px;
            background: linear-gradient(to right, #d4af37, #bb9632);
            transform: rotate(-35deg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .ribbon-tail:nth-child(2) {
            left: 35px;
            transform: rotate(35deg);
        }

        /* Loading spinner */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #d4af37;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 15px auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        #mint-progress {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            text-align: center;
        }

        #mint-status-message {
            margin: 10px 0;
            font-weight: 500;
        }

        .demo-note {
            background-color: #fffde7;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            border-left: 4px solid #f39c12;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .demo-note p {
            margin: 8px 0;
            line-height: 1.5;
        }

        .demo-note ol {
            margin-top: 10px;
            padding-left: 20px;
        }

        .demo-note li {
            margin-bottom: 8px;
        }

        /* Button styles */
        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: normal;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-primary {
            background-color: #0A2558;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0d3a80;
        }

        .btn-success {
            background-color: #19875b;
            color: white;
        }

        .btn-success:hover {
            background-color: #156b49;
        }

        .btn-gold {
            background-color: #d4af37;
            color: white;
            font-weight: bold;
        }

        .btn-gold:hover {
            background-color: #c4a028;
        }

        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        /* Blockchain section styles */
        .blockchain-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .blockchain-section h2 {
            color: #0A2558;
            margin-bottom: 20px;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 10px;
            font-size: 24px;
            letter-spacing: 0.5px;
            font-weight: 600;
            position: relative;
        }

        .blockchain-section h2:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 4px;
            background-color: #d4af37;
            border-radius: 2px;
        }

        .mint-status {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #d4af37;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .status-pending {
            color: #f39c12;
            font-weight: 600;
        }

        .status-success {
            color: #2ecc71;
            font-weight: 600;
        }

        .status-error {
            color: #e74c3c;
            font-weight: 600;
        }

        .status-note {
            color: #777;
            font-size: 0.85em;
            font-style: italic;
            margin-top: 8px;
        }

        .nft-details {
            background-color: #f7fafd;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(10, 37, 88, 0.1);
        }

        .nft-details p {
            margin: 12px 0 8px;
            font-weight: 500;
        }

        /* NFT Link and transaction hash fixes */
        .nft-link {
            display: block;
            margin: 10px 0 20px;
            padding: 15px;
            background-color: #e8f4fd;
            border-radius: 8px;
            word-break: break-word;
            overflow-wrap: break-word;
            color: #0A2558;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border: 1px solid rgba(10, 37, 88, 0.1);
            transition: all 0.2s ease;
            text-decoration: none;
            max-width: 100%;
            overflow: hidden;
        }

        .nft-link:hover {
            background-color: #d9edfb;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .nft-link span {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }

        .nft-link span svg {
            flex-shrink: 0;
        }

        .transaction-hash {
            word-break: break-all;
            overflow-wrap: break-word;
            font-size: 12px;
            display: inline-block;
            width: 100%;
        }
    </style>
</head>

<body>
    <?php if ($nft_minted): ?>
        <a href="results.php" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z" />
            </svg>
            Back to Results
        </a>
    <?php endif; ?>

    <div class="page-title">
        <h1>Blockchain-Verified Certificate</h1>
        <p>Your digital credential for academic achievement</p>
    </div>

    <div class="main-container">
        <!-- Left column: Certificate display -->
        <div class="left-column">
            <div class="certificate-container">
                <!-- Fancy border with corner decorations -->
                <div class="fancy-border"></div>
                <div class="corner corner-top-left"></div>
                <div class="corner corner-top-right"></div>
                <div class="corner corner-bottom-left"></div>
                <div class="corner corner-bottom-right"></div>

                <!-- Certificate seal -->
                <div class="certificate-seal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#7a5c00" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 4px;">
                        <path d="M13 2H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9z"></path>
                        <polyline points="13 2 13 9 20 9"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polygon points="10 9 9 9 8 9"></polygon>
                    </svg>
                    Blockchain<br>Verified
                </div>

                <!-- Ribbon decoration -->
                <div class="ribbon">
                    <div class="ribbon-circle"></div>
                    <div class="ribbon-tail"></div>
                    <div class="ribbon-tail"></div>
                </div>

                <div class="certificate-header">
                    <div class="certificate-title">Certificate of Completion</div>
                    <div class="certificate-subtitle">ExamFlow</div>
                </div>

                <div class="certificate-content">
                    <p class="certificate-text">This is to certify that</p>
                    <p class="student-name"><?php echo htmlspecialchars($student_name); ?></p>
                    <p class="certificate-text">has successfully completed</p>
                    <p class="student-name"><?php echo htmlspecialchars($exam_name); ?></p>
                    <p class="certificate-text">in the subject of <?php echo htmlspecialchars($subject); ?></p>

                    <div class="certificate-details">
                        <p class="certificate-detail">Score: <strong><?php echo $score; ?>/<?php echo $total; ?></strong> (<?php echo $percentage; ?>%)</p>
                        <p class="certificate-detail">Integrity Score: <span class="integrity-score-<?php echo strtolower($integrity_category); ?>"><?php echo $integrity_score; ?>/100</span> (<?php echo $integrity_category; ?>)</p>
                        <p class="certificate-detail certificate-date">Completed on: <strong><?php echo $completion_date; ?></strong></p>
                    </div>
                </div>

                <div class="certificate-footer">
                    <div class="signature-line"></div>
                </div>
            </div>

            <div class="btn-container">
                <button class="btn btn-primary" onclick="window.print();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print Certificate
                </button>
                <button class="btn btn-success" onclick="downloadAsPNG();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Download as PNG
                </button>
            </div>
        </div>

        <!-- Right column: Blockchain & NFT section -->
        <div class="right-column">
            <div class="blockchain-section">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: -5px;">
                        <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path>
                        <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path>
                        <path d="M18 12a2 2 0 0 0 0 4h2v-4h-2z"></path>
                    </svg>
                    Blockchain Certification
                </h2>

                <?php if (!$nft_minted): ?>
                    <div class="mint-status">
                        <h3 style="margin-top: 0; color: #0A2558; font-size: 18px;">Digital Credential</h3>
                        <p>Convert your certificate to a blockchain-secured NFT for a permanent, tamper-proof record of your achievement.</p>
                        <p style="display: flex; align-items: center; margin-top: 15px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0A2558" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 10px; min-width: 20px;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span>Mint time: ~1-2 minutes</span>
                        </p>
                        <?php if (isset($_GET['auto_mint']) && $_GET['auto_mint'] == 1): ?>
                            <div class="status-pending" style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px; display: flex; align-items: center;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 10px; color: #f39c12;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <span><strong>Automatic Processing:</strong> Your certificate is being converted to an NFT. Please wait...</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button id="mint-nft-btn" class="btn btn-gold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        Mint Certificate as NFT
                    </button>
                    <div id="mint-progress" style="display: none;">
                        <div class="spinner"></div>
                        <p id="mint-status-message">Processing your NFT...</p>
                    </div>
                <?php else: ?>
                    <div class="mint-status">
                        <p class="status-success" style="display: flex; align-items: center;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: #2ecc71;">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            Certificate successfully minted as NFT
                        </p>
                        <?php if (isset($nft_data['is_demo']) && $nft_data['is_demo'] == 1): ?>
                            <p class="status-note"><i>Note: This is a demonstration NFT using simulated blockchain transactions.</i></p>
                        <?php endif; ?>
                    </div>
                    <div class="nft-details">
                        <h3 style="margin-top: 0; color: #0A2558; font-size: 18px; margin-bottom: 20px;">NFT Details</h3>

                        <p><strong>Transaction Hash:</strong></p>
                        <a href="https://sepolia.etherscan.io/tx/<?php echo $nft_data['transaction_hash']; ?>" target="_blank" class="nft-link">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                <span class="transaction-hash"><?php echo $nft_data['transaction_hash']; ?></span>
                            </span>
                            <?php if (isset($nft_data['is_demo']) && $nft_data['is_demo'] == 1): ?>
                                <br><span class="status-note"><i>(Demo transaction - may not be found on blockchain)</i></span>
                            <?php endif; ?>
                        </a>

                        <p><strong>Token ID:</strong> <?php echo $nft_data['token_id']; ?></p>

                        <p><strong>View on OpenSea:</strong></p>
                        <a href="https://testnets.opensea.io/assets/sepolia/<?php echo $nft_data['contract_address']; ?>/<?php echo $nft_data['token_id']; ?>" target="_blank" class="nft-link">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                </svg>
                                View your NFT on OpenSea
                            </span>
                            <?php if (isset($nft_data['is_demo']) && $nft_data['is_demo'] == 1): ?>
                                <br><span class="status-note"><i>(Demo NFT - may not be found on OpenSea)</i></span>
                            <?php endif; ?>
                        </a>

                        <p><strong>IPFS Metadata:</strong></p>
                        <a href="<?php echo $nft_data['metadata_url']; ?>" target="_blank" class="nft-link">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                View metadata
                            </span>
                        </a>

                        <?php if (isset($nft_data['is_demo']) && $nft_data['is_demo'] == 1): ?>
                            <div class="demo-note">
                                <h4 style="margin-top: 0; color: #f39c12;">About Demo NFTs</h4>
                                <p>This is a demonstration NFT to show how the system would work. In a production environment, real blockchain transactions would be used to mint actual NFTs on the Ethereum network.</p>
                                <p>To implement real NFT minting, the system administrator would need to:</p>
                                <ol>
                                    <li>Install a proper Web3 PHP library</li>
                                    <li>Configure a funded Ethereum wallet</li>
                                    <li>Set up proper contract interactions</li>
                                </ol>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-print dialog for saving as PDF when the page loads
        window.onload = function() {
            // Give a moment for the page to fully render
            setTimeout(function() {
                // Uncomment the next line if you want the print dialog to open automatically
                // window.print();

                // Check if auto_mint parameter is present in URL
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('auto_mint') === '1' && document.getElementById('mint-nft-btn')) {
                    console.log('Auto-minting NFT certificate...');
                    document.getElementById('mint-nft-btn').click();
                }
            }, 1500); // Slightly longer delay to ensure everything is loaded
        };

        // Function to download certificate as PNG for NFT use
        function downloadAsPNG() {
            // Show a loading message
            const loadingMsg = document.createElement('div');
            loadingMsg.innerHTML = 'Generating PNG, please wait...';
            loadingMsg.style.padding = '10px';
            loadingMsg.style.backgroundColor = '#f0f0f0';
            loadingMsg.style.borderRadius = '5px';
            loadingMsg.style.marginTop = '10px';
            document.body.appendChild(loadingMsg);

            // Get the certificate container
            const container = document.querySelector('.certificate-container');

            // Use html2canvas to convert HTML to canvas
            html2canvas(container, {
                scale: 1.2, // Reduced scale from 2 to 1.2 (40% reduction)
                useCORS: true,
                backgroundColor: '#ffffff'
            }).then(function(canvas) {
                // Convert canvas to blob with reduced quality
                const imgBlob = new Promise(resolve => {
                    canvas.toBlob(resolve, 'image/png', 0.8); // Reduced quality from 0.95 to 0.8
                });

                // Create file from blob
                const imgFile = new File([imgBlob], 'certificate_<?php echo $attempt_id; ?>.png', {
                    type: 'image/png'
                });

                // Create download link
                const link = document.createElement('a');
                link.download = 'certificate_<?php echo $attempt_id; ?>.png';
                link.href = URL.createObjectURL(imgBlob);

                // Trigger download
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Remove loading message
                document.body.removeChild(loadingMsg);
            }).catch(function(error) {
                console.error('Error generating PNG:', error);
                alert('There was an error generating the PNG. Please try again.');
                document.body.removeChild(loadingMsg);
            });
        }

        // Blockchain integration
        document.getElementById('mint-nft-btn')?.addEventListener('click', async function() {
            try {
                // Show progress
                document.getElementById('mint-nft-btn').disabled = true;
                document.getElementById('mint-progress').style.display = 'block';
                document.getElementById('mint-status-message').textContent = 'Generating certificate image...';

                // First, generate the PNG image
                const container = document.querySelector('.certificate-container');
                const canvas = await html2canvas(container, {
                    scale: 1.2, // Reduced scale from 2 to 1.2 (40% reduction)
                    useCORS: true,
                    backgroundColor: '#ffffff'
                });

                // Convert canvas to blob with reduced quality
                const imgBlob = await new Promise(resolve => {
                    canvas.toBlob(resolve, 'image/png', 0.8); // Reduced quality from 0.95 to 0.8
                });

                // Create file from blob
                const imgFile = new File([imgBlob], 'certificate_<?php echo $attempt_id; ?>.png', {
                    type: 'image/png'
                });

                // Update status
                document.getElementById('mint-status-message').textContent = 'Uploading certificate to IPFS...';

                // Define imageIpfsUrl and ipfsUri at a higher scope
                let imageIpfsUrl = '';
                let ipfsUri = '';
                let metadataUrl = '';
                let metadataUri = '';
                let headers = {};

                // Prepare Pinata request
                try {
                    // First load environment variables
                    const envResponse = await fetch('get_env_variables.php');
                    if (!envResponse.ok) {
                        throw new Error(`Error fetching environment variables: ${envResponse.status}`);
                    }

                    const envData = await envResponse.json();
                    console.log('Environment variables loaded:', envData.diagnostic);

                    // Create FormData
                    const formData = new FormData();

                    // Add the image file
                    formData.append('file', imgFile);

                    // Add Pinata options
                    const pinataOptions = JSON.stringify({
                        cidVersion: 1,
                        wrapWithDirectory: false
                    });
                    formData.append('pinataOptions', pinataOptions);

                    // Add metadata
                    const pinataMetadata = JSON.stringify({
                        name: `${<?php echo json_encode(htmlspecialchars($_SESSION["uname"])); ?>}-${<?php echo json_encode(htmlspecialchars($exam_name)); ?>}-${Math.floor(10000 + Math.random() * 90000)}`,
                        keyvalues: {
                            student: <?php echo json_encode(htmlspecialchars($student_name)); ?>,
                            student_id: <?php echo json_encode(htmlspecialchars($_SESSION["uname"])); ?>,
                            exam: <?php echo json_encode(htmlspecialchars($exam_name)); ?>,
                            score: "<?php echo $score; ?>",
                            integrity_score: "<?php echo $integrity_score; ?>",
                            integrity_category: "<?php echo $integrity_category; ?>",
                            timestamp: new Date().toISOString()
                        }
                    });
                    formData.append('pinataMetadata', pinataMetadata);

                    // Set up headers for authentication - do NOT add Content-Type for FormData
                    headers = {};

                    // Prioritize JWT if available
                    if (envData.PINATA_JWT && envData.PINATA_JWT.trim() !== '') {
                        console.log('Using JWT authentication');
                        headers = {
                            'Authorization': `Bearer ${envData.PINATA_JWT}`
                        };
                    }
                    // Fall back to API key auth if JWT is not available
                    else if (envData.PINATA_API_KEY && envData.PINATA_SECRET_KEY &&
                        envData.PINATA_API_KEY.trim() !== '' && envData.PINATA_SECRET_KEY.trim() !== '') {
                        console.log('Using API key authentication');
                        headers = {
                            'pinata_api_key': envData.PINATA_API_KEY,
                            'pinata_secret_api_key': envData.PINATA_SECRET_KEY
                        };
                    } else {
                        throw new Error('No Pinata authentication credentials available');
                    }

                    // Log request details for debugging
                    console.log('Auth method:', headers.Authorization ? 'JWT' : 'API Keys');
                    console.log('FormData keys:', [...formData.keys()]);
                    console.log('File type:', imgFile.type);
                    console.log('File size:', imgFile.size, 'bytes');

                    // Upload file to Pinata
                    console.log('Sending request to Pinata with headers:', Object.keys(headers));
                    const uploadResponse = await fetch('https://api.pinata.cloud/pinning/pinFileToIPFS', {
                        method: 'POST',
                        headers: headers,
                        body: formData
                    });

                    console.log('Pinata response status:', uploadResponse.status);

                    if (!uploadResponse.ok) {
                        const errorText = await uploadResponse.text();
                        console.error('Pinata error response:', errorText);
                        let errorMessage = 'Unknown error';
                        try {
                            const errorData = JSON.parse(errorText);
                            errorMessage = errorData.error?.reason || errorData.error?.details || errorData.message || `HTTP error ${uploadResponse.status}`;
                        } catch (e) {
                            errorMessage = errorText || `HTTP error ${uploadResponse.status}`;
                        }
                        throw new Error(`Pinata API error: ${errorMessage}`);
                    }

                    const uploadResult = await uploadResponse.json();
                    console.log('Pinata upload successful:', uploadResult);

                    // Set the imageIpfsUrl here
                    imageIpfsUrl = `https://gateway.pinata.cloud/ipfs/${uploadResult.IpfsHash}`;
                    // Also create an ipfs:// URI format that OpenSea prefers
                    ipfsUri = `ipfs://${uploadResult.IpfsHash}`;
                    console.log('Image IPFS URL:', imageIpfsUrl);
                    console.log('Image IPFS URI:', ipfsUri);
                } catch (uploadError) {
                    console.error('Error during Pinata upload:', uploadError);
                    throw uploadError;
                }

                // Update status
                document.getElementById('mint-status-message').textContent = 'Creating NFT metadata...';

                // Verify imageIpfsUrl is defined before proceeding
                if (!imageIpfsUrl) {
                    throw new Error('IPFS upload failed - no image URL was generated');
                }

                // Create metadata
                const metadata = {
                    name: `${<?php echo json_encode(htmlspecialchars($_SESSION["uname"])); ?>}-${<?php echo json_encode(htmlspecialchars($exam_name)); ?>}-${Math.floor(10000 + Math.random() * 90000)}`,
                    description: `Certificate of completion for ${<?php echo json_encode(htmlspecialchars($student_name)); ?>} (ID: ${<?php echo json_encode(htmlspecialchars($_SESSION["uname"])); ?>}) in ${<?php echo json_encode(htmlspecialchars($subject)); ?>} with a score of ${<?php echo $score; ?>}/${<?php echo $total; ?>} (${<?php echo $percentage; ?>}%) and integrity score of ${<?php echo $integrity_score; ?>}/100 (${<?php echo json_encode(htmlspecialchars($integrity_category)); ?>})`,
                    image: ipfsUri, // Use IPFS URI format instead of gateway URL
                    image_url: imageIpfsUrl, // Fallback gateway URL for OpenSea
                    external_url: imageIpfsUrl, // Alternative fallback that some marketplaces use
                    attributes: [{
                            trait_type: "Student ID",
                            value: <?php echo json_encode(htmlspecialchars($_SESSION["uname"])); ?>
                        },
                        {
                            trait_type: "Student Name",
                            value: <?php echo json_encode(htmlspecialchars($student_name)); ?>
                        },
                        {
                            trait_type: "Exam",
                            value: <?php echo json_encode(htmlspecialchars($exam_name)); ?>
                        },
                        {
                            trait_type: "Subject",
                            value: <?php echo json_encode(htmlspecialchars($subject)); ?>
                        },
                        {
                            trait_type: "Score",
                            value: <?php echo $score; ?>
                        },
                        {
                            trait_type: "Total",
                            value: <?php echo $total; ?>
                        },
                        {
                            trait_type: "Percentage",
                            value: <?php echo $percentage; ?>
                        },
                        {
                            trait_type: "Marks",
                            value: `${<?php echo $score; ?>}/${<?php echo $total; ?>} (${<?php echo $percentage; ?>}%)`
                        },
                        {
                            trait_type: "Integrity Score",
                            value: <?php echo $integrity_score; ?>
                        },
                        {
                            trait_type: "Integrity Category",
                            value: <?php echo json_encode(htmlspecialchars($integrity_category)); ?>
                        },
                        {
                            trait_type: "Exam Integrity",
                            value: `${<?php echo $integrity_score; ?>}/100 (${<?php echo json_encode(htmlspecialchars($integrity_category)); ?>})`
                        },
                        {
                            trait_type: "Completion Date",
                            value: <?php echo json_encode($completion_date); ?>
                        }
                    ]
                };

                // Upload metadata to Pinata
                const metadataResponse = await fetch('https://api.pinata.cloud/pinning/pinJSONToIPFS', {
                    method: 'POST',
                    headers: {
                        ...headers,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(metadata)
                });

                if (!metadataResponse.ok) {
                    const errorText = await metadataResponse.text();
                    console.error('Pinata metadata error response:', errorText);
                    let errorMessage = 'Unknown error';
                    try {
                        const errorData = JSON.parse(errorText);
                        errorMessage = errorData.error?.reason || errorData.error?.details || errorData.message || `HTTP error ${metadataResponse.status}`;
                    } catch (e) {
                        errorMessage = errorText || `HTTP error ${metadataResponse.status}`;
                    }
                    throw new Error(`Pinata metadata API error: ${errorMessage}`);
                }

                const metadataResult = await metadataResponse.json();
                metadataUrl = `https://gateway.pinata.cloud/ipfs/${metadataResult.IpfsHash}`;
                metadataUri = `ipfs://${metadataResult.IpfsHash}`;
                console.log('Metadata Gateway URL:', metadataUrl);
                console.log('Metadata IPFS URI:', metadataUri);

                // Update status
                document.getElementById('mint-status-message').textContent = 'Preparing blockchain transaction...';

                // Get certificate image as base64 for email attachment
                const certificateImageBase64 = canvas.toDataURL('image/png');
                console.log('Certificate image data length:', certificateImageBase64.length);

                // Send info to server to prepare the transaction
                const mintResponse = await fetch('mint_nft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        attempt_id: <?php echo $attempt_id; ?>,
                        metadata_url: metadataUri, // Use IPFS URI format for blockchain
                        image_url: ipfsUri, // Use IPFS URI format for image reference
                        certificate_image: certificateImageBase64 // Pass certificate image for email
                    })
                });

                if (!mintResponse.ok) {
                    throw new Error('Failed to prepare NFT transaction. Server error.');
                }

                const mintResult = await mintResponse.json();

                if (!mintResult.success) {
                    throw new Error(mintResult.error || 'Failed to prepare NFT transaction');
                }

                // Show email status if available
                if (mintResult.data.email_sent) {
                    document.getElementById('mint-status-message').textContent = 'NFT certificate has been emailed to your registered email address.';
                }

                // Update status
                document.getElementById('mint-status-message').textContent = 'Waiting for IPFS propagation (8 seconds)...';

                // Add a delay to ensure IPFS propagation before sending blockchain transaction
                await new Promise(resolve => setTimeout(resolve, 8000));

                document.getElementById('mint-status-message').textContent = 'Signing and sending blockchain transaction...';

                // REAL BLOCKCHAIN INTERACTION
                // Use ethers.js to sign and send the transaction
                try {
                    console.log('Blockchain transaction data:', mintResult.data);

                    // Check if ethers library is loaded
                    if (typeof ethers === 'undefined') {
                        throw new Error('Ethers.js library is not loaded. Please check your internet connection and try again.');
                    }

                    // Get transaction data, handling both formats
                    const txData = mintResult.data.tx_data || {};
                    const rpcUrl = txData.rpc_url || mintResult.data.rpc_url;
                    const privateKey = txData.private_key || mintResult.data.private_key;
                    const contractAddr = mintResult.data.contract_address;

                    if (!rpcUrl || !privateKey || !contractAddr) {
                        throw new Error('Missing required blockchain transaction data');
                    }

                    // Create provider
                    const provider = new ethers.providers.JsonRpcProvider(rpcUrl);

                    // Create wallet with private key
                    const wallet = new ethers.Wallet(privateKey, provider);

                    // Create contract instance
                    const abi = [
                        "function mint(string memory tokenURI) public returns (uint256)"
                    ];
                    const contract = new ethers.Contract(contractAddr, abi, wallet);

                    // Add gas price information for faster transaction processing
                    const gasPrice = await provider.getGasPrice();
                    // Triple the gas price (multiply by 300%) for faster processing and more reliability
                    const increasedGasPrice = gasPrice.mul(3);
                    console.log('Using increased gas price:', ethers.utils.formatUnits(increasedGasPrice, 'gwei'), 'gwei');

                    // Call mint function with increased gas price
                    const tx = await contract.mint(metadataUri, {
                        gasPrice: increasedGasPrice,
                        gasLimit: 300000 // Set explicit gas limit to avoid underestimation
                    });

                    // Update status with transaction hash
                    document.getElementById('mint-status-message').textContent = 'Transaction submitted: ' + tx.hash;

                    // Wait for transaction to be mined
                    document.getElementById('mint-status-message').textContent = 'Waiting for transaction confirmation...';
                    const receipt = await tx.wait();

                    // Get token ID from logs
                    let tokenId = '0';
                    if (receipt.logs && receipt.logs.length > 0) {
                        // Parse logs to find the Transfer event
                        try {
                            const transferEvent = receipt.logs.find(log =>
                                log.topics && log.topics[0] === '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef'
                            );
                            if (transferEvent && transferEvent.topics.length >= 4) {
                                tokenId = ethers.BigNumber.from(transferEvent.topics[3]).toString();
                            }
                        } catch (e) {
                            console.error('Error parsing logs:', e);
                        }
                    }

                    // Update the transaction hash and token ID on the server
                    const updateResponse = await fetch('update_transaction.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            nft_id: mintResult.data.nft_id,
                            transaction_hash: tx.hash,
                            token_id: tokenId,
                            send_email: true,
                            certificate_image: certificateImageBase64
                        })
                    });

                    if (!updateResponse.ok) {
                        throw new Error('Failed to update transaction details. Server error.');
                    }

                    const updateResult = await updateResponse.json();

                    if (!updateResult.success) {
                        throw new Error(updateResult.error || 'Failed to update transaction details');
                    }

                    // Success message about email
                    if (updateResult.data.email_sent) {
                        document.getElementById('mint-status-message').textContent = 'NFT minted successfully! Certificate has been emailed to your registered address.';
                    } else {
                        document.getElementById('mint-status-message').textContent = 'NFT successfully minted!';
                    }

                    // Reload the page after 3 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);

                } catch (error) {
                    console.error('Blockchain error:', error);
                    document.getElementById('mint-status-message').textContent = `Blockchain error: ${error.message}`;
                    document.getElementById('mint-status-message').classList.add('status-error');

                    // Show retry button
                    setTimeout(() => {
                        document.getElementById('mint-progress').innerHTML += '<button class="btn btn-gold" onclick="window.location.reload()">Retry</button>';
                    }, 3000);
                }

            } catch (error) {
                console.error('Error minting NFT:', error);
                document.getElementById('mint-status-message').textContent = `Error: ${error.message}`;
                document.getElementById('mint-nft-btn').disabled = false;

                // Make error message red
                document.getElementById('mint-status-message').classList.add('status-error');

                // Log full error details to console for debugging
                console.error('Full error details:', error);
                if (error.stack) console.error('Error stack:', error.stack);

                // Show a retry button after 3 seconds
                setTimeout(() => {
                    document.getElementById('mint-progress').innerHTML += '<button class="btn btn-gold" onclick="window.location.reload()">Retry</button>';
                }, 3000);
            }
        });
    </script>
</body>

</html>
<?php
// End the script to prevent any additional output
exit();
?>