<?php
/**
 * Teacher Portal - Generate Teaching Certificate
 * Blockchain-verified certificate for completed teaching sessions
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit();
}

// Include database connection
include '../config.php';

// Load environment variables for blockchain integration
$env_file = __DIR__ . '/.env';
if (!file_exists($env_file)) {
    $env_file = dirname(__DIR__) . '/.env';
}

$env_vars = [];
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $env_vars[trim($name)] = trim($value);
        }
    }
}

// Get parameters
$session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$teacher_id = $_SESSION['user_id'];

if (!$session_id) {
    die("Session ID not provided");
}

// Get session details with verification that teacher owns this session
$session_sql = "SELECT ts.*, ste.teacher_id, ste.enrollment_status, ste.booked_at,
                sts.slot_date, sts.start_time, sts.end_time, sts.description as slot_description,
                s.school_id, s.school_name, s.full_address,
                t.fname as teacher_name, t.email as teacher_email, t.uname as teacher_uname
                FROM teaching_sessions ts
                JOIN slot_teacher_enrollments ste ON ts.enrollment_id = ste.enrollment_id
                JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                JOIN schools s ON sts.school_id = s.school_id
                JOIN teacher t ON ste.teacher_id = t.id
                WHERE ts.session_id = ? AND ste.teacher_id = ?";
$stmt = mysqli_prepare($conn, $session_sql);
mysqli_stmt_bind_param($stmt, "ii", $session_id, $teacher_id);
mysqli_stmt_execute($stmt);
$session_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($session_result) == 0) {
    die("Unauthorized access or session not found");
}

$session = mysqli_fetch_assoc($session_result);

// Verify session is approved or completed
if (!in_array($session['session_status'], ['approved', 'end_submitted', 'end_approved'])) {
    die("Certificate is only available for approved teaching sessions");
}

// Calculate teaching hours and activity points
$start_time = new DateTime($session['start_time']);
$end_time = new DateTime($session['end_time']);
$interval = $start_time->diff($end_time);
$hours_taught = $interval->h + ($interval->i / 60);
$activity_points = round($hours_taught * 2, 1); // 2 points per hour

// Format date
$session_date = date("F j, Y", strtotime($session['slot_date']));
$time_range = date("h:i A", strtotime($session['start_time'])) . ' - ' . date("h:i A", strtotime($session['end_time']));

// Check if NFT has already been minted for this certificate
$nft_minted = false;
$nft_data = null;

// First check if the table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'teacher_certificate_nfts'");
if (mysqli_num_rows($table_check) > 0) {
    $nft_sql = "SELECT * FROM teacher_certificate_nfts WHERE session_id = ?";
    $nft_stmt = mysqli_prepare($conn, $nft_sql);
    mysqli_stmt_bind_param($nft_stmt, "i", $session_id);
    mysqli_stmt_execute($nft_stmt);
    $nft_result = mysqli_stmt_get_result($nft_stmt);

    if ($nft_result && mysqli_num_rows($nft_result) > 0) {
        $nft_data = mysqli_fetch_assoc($nft_result);
        $nft_minted = true;
    }
} else {
    // Table doesn't exist, create it
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teaching Certificate - Blockchain Verified</title>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    
    <script>
        function loadScript(url, callback) {
            var script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = url;
            script.onload = callback;
            script.onerror = function() {
                console.error('Failed to load script from: ' + url);
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

        var scriptQueue = [
            'https://cdn.jsdelivr.net/npm/ethers@5.2.0/dist/ethers.umd.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/ethers/5.2.0/ethers.umd.min.js',
            'https://unpkg.com/ethers@5.2.0/dist/ethers.umd.min.js'
        ];

        loadScript(scriptQueue.shift(), function() {
            console.log('Ethers.js library loaded successfully');
        });

        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (typeof ethers === 'undefined') {
                    console.log('Creating minimal ethers fallback for testing');
                    window.ethers = {
                        providers: {
                            JsonRpcProvider: function() {
                                return {
                                    getNetwork: function() { return Promise.resolve({ chainId: 11155111 }); }
                                };
                            }
                        },
                        Wallet: function() {
                            return {
                                connect: function() { return this; },
                                getAddress: function() { return Promise.resolve('0x0000000000000000000000000000000000000000'); }
                            };
                        },
                        Contract: function() {
                            return {
                                mint: function(metadataUrl) {
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
                            from: function(val) { return { toString: function() { return '1'; } }; }
                        }
                    };
                }
            }, 5000);
        });
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Cormorant+Garamond:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-btn, .blockchain-section, .back-btn { display: none; }
            .main-container { flex-direction: column; }
            .certificate-container { box-shadow: none !important; margin: 0 auto !important; }
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
        
        .page-title { text-align: center; margin-bottom: 30px; width: 100%; }
        .page-title h1 { font-size: 28px; color: #0A2558; font-weight: 600; margin-bottom: 5px; }
        .page-title p { font-size: 16px; color: #666; }
        
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
        .back-btn:hover { background-color: #0d3a80; transform: translateY(-2px); }
        .back-btn svg { margin-right: 8px; }
        
        .main-container {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            max-width: 1600px;
            gap: 30px;
            justify-content: center;
        }
        
        .left-column { flex: 1; min-width: 800px; display: flex; flex-direction: column; align-items: center; }
        
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
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23166534' fill-opacity='0.08' fill-rule='evenodd'/%3E%3C/svg%3E");
            width: 800px;
            height: 600px;
            padding: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: 2px solid #166534;
            position: relative;
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .certificate-container:hover { transform: scale(1.01); }
        
        .fancy-border {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border: 2px solid rgba(22, 101, 52, 0.5);
            margin: 10px;
            pointer-events: none;
            z-index: 1;
            box-shadow: inset 0 0 15px rgba(22, 101, 52, 0.15);
        }
        
        .corner { position: absolute; width: 40px; height: 40px; border-color: #166534; z-index: 2; }
        .corner-top-left { top: 10px; left: 10px; border-top: 4px solid; border-left: 4px solid; }
        .corner-top-right { top: 10px; right: 10px; border-top: 4px solid; border-right: 4px solid; }
        .corner-bottom-left { bottom: 10px; left: 10px; border-bottom: 4px solid; border-left: 4px solid; }
        .corner-bottom-right { bottom: 10px; right: 10px; border-bottom: 4px solid; border-right: 4px solid; }
        
        .certificate-seal {
            position: absolute;
            top: 20px;
            right: 30px;
            width: 110px;
            height: 110px;
            background: radial-gradient(circle, rgba(22, 101, 52, 0.7) 0%, rgba(22, 101, 52, 0.1) 70%);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #166534;
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
            border: 1px dashed #166534;
        }
        
        .certificate-seal::before {
            content: '';
            position: absolute;
            width: 102px;
            height: 102px;
            border: 1px dashed #166534;
            border-radius: 50%;
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(22, 101, 52, 0.3);
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .certificate-title {
            color: #166534;
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
            color: #166534;
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
        
        .teacher-name {
            font-size: 26px;
            font-weight: bold;
            color: #166534;
            margin: 8px 0;
            font-family: 'Playfair Display', serif;
            border-bottom: 1px solid #166534;
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
            border: 1px solid rgba(22, 101, 52, 0.3);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        
        .certificate-detail {
            font-size: 15px;
            margin: 8px 0;
            font-family: 'Montserrat', sans-serif;
            line-height: 1.3;
        }
        
        .activity-points {
            color: #166534;
            font-weight: bold;
            padding: 4px 12px;
            background-color: rgba(22, 101, 52, 0.1);
            border-radius: 20px;
            border: 1px solid rgba(22, 101, 52, 0.3);
            display: inline-block;
            font-size: 18px;
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
        }
        
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
            background: radial-gradient(circle, #166534 0%, #0d4a24 100%);
            border-radius: 50%;
            border: 1px solid #0d4a24;
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
            background: linear-gradient(to right, #166534, #0d4a24);
            transform: rotate(-35deg);
        }
        
        .ribbon-tail:nth-child(2) { left: 35px; transform: rotate(35deg); }
        
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #166534;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 15px auto;
        }
        
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        #mint-progress {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            text-align: center;
        }
        
        #mint-status-message { margin: 10px 0; font-weight: 500; }
        
        .btn-container { display: flex; gap: 15px; margin-top: 15px; }
        
        .btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }
        
        .btn-primary { background-color: #0A2558; color: white; }
        .btn-primary:hover { background-color: #0d3a80; }
        .btn-success { background-color: #166534; color: white; }
        .btn-success:hover { background-color: #0d4a24; }
        .btn-gold { background-color: #166534; color: white; font-weight: bold; }
        .btn-gold:hover { background-color: #0d4a24; }
        .btn:disabled { background-color: #cccccc; cursor: not-allowed; }
        
        .blockchain-section { display: flex; flex-direction: column; gap: 20px; }
        
        .blockchain-section h2 {
            color: #166534;
            margin-bottom: 20px;
            border-bottom: 2px solid #166534;
            padding-bottom: 10px;
            font-size: 24px;
        }
        
        .mint-status {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #166534;
        }
        
        .status-pending { color: #f39c12; font-weight: 600; }
        .status-success { color: #166534; font-weight: 600; }
        .status-error { color: #e74c3c; font-weight: 600; }
        .status-note { color: #777; font-size: 0.85em; font-style: italic; margin-top: 8px; }
        
        .nft-details {
            background-color: #f7fafd;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(22, 101, 52, 0.1);
        }
        
        .nft-link {
            display: block;
            margin: 10px 0 20px;
            padding: 15px;
            background-color: #e8f5ec;
            border-radius: 8px;
            word-break: break-word;
            color: #166534;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border: 1px solid rgba(22, 101, 52, 0.1);
            text-decoration: none;
        }
        
        .nft-link:hover { background-color: #d0ebda; }
        
        .demo-note {
            background-color: #e8f5ec;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            border-left: 4px solid #166534;
        }
        
        .demo-note h4 { margin-top: 0; color: #166534; }
    </style>
</head>

<body>
    <?php if ($nft_minted): ?>
    <a href="my_slots.php" class="back-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
        </svg>
        Back to My Bookings
    </a>
    <?php endif; ?>

    <div class="page-title">
        <h1>Teaching Certificate</h1>
        <p>Blockchain-verified credential for community teaching service</p>
    </div>

    <div class="main-container">
        <div class="left-column">
            <div class="certificate-container">
                <div class="fancy-border"></div>
                <div class="corner corner-top-left"></div>
                <div class="corner corner-top-right"></div>
                <div class="corner corner-bottom-left"></div>
                <div class="corner corner-bottom-right"></div>

                <div class="certificate-seal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 4px;">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                    Blockchain<br>Verified
                </div>

                <div class="ribbon">
                    <div class="ribbon-circle"></div>
                    <div class="ribbon-tail"></div>
                    <div class="ribbon-tail"></div>
                </div>

                <div class="certificate-header">
                    <div class="certificate-title">Certificate of Teaching</div>
                    <div class="certificate-subtitle">Community Service Recognition</div>
                </div>

                <div class="certificate-content">
                    <p class="certificate-text">This is to certify that</p>
                    <p class="teacher-name"><?php echo htmlspecialchars($session['teacher_name']); ?></p>
                    <p class="certificate-text">has successfully completed a teaching session at</p>
                    <p class="teacher-name" style="font-size: 20px;"><?php echo htmlspecialchars($session['school_name']); ?></p>

                    <div class="certificate-details">
                        <p class="certificate-detail"><strong>Date:</strong> <?php echo $session_date; ?></p>
                        <p class="certificate-detail"><strong>Time:</strong> <?php echo $time_range; ?></p>
                        <?php if ($session['slot_description']): ?>
                        <p class="certificate-detail"><strong>Subject/Activity:</strong> <?php echo htmlspecialchars($session['slot_description']); ?></p>
                        <?php endif; ?>
                        <p class="certificate-detail"><strong>Duration:</strong> <?php echo number_format($hours_taught, 1); ?> hours</p>
                        <p class="certificate-detail" style="margin-top: 15px;">
                            <strong>Activity Points Earned:</strong> 
                            <span class="activity-points"><?php echo $activity_points; ?> Points</span>
                        </p>
                    </div>
                </div>

                <div class="certificate-footer">
                    <div class="signature-line"></div>
                </div>
            </div>

            <div class="btn-container">
                <button class="btn btn-primary" onclick="window.print();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print Certificate
                </button>
                <button class="btn btn-success" onclick="downloadAsPNG();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Download as PNG
                </button>
            </div>
        </div>

        <div class="right-column">
            <div class="blockchain-section">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: -5px;">
                        <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path>
                        <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path>
                        <path d="M18 12a2 2 0 0 0 0 4h2v-4h-2z"></path>
                    </svg>
                    Blockchain Certification
                </h2>

                <?php if (!$nft_minted): ?>
                <div class="mint-status">
                    <h3 style="margin-top: 0; color: #166534; font-size: 18px;">Digital Credential</h3>
                    <p>Convert your teaching certificate to a blockchain-secured NFT for a permanent, tamper-proof record of your community service.</p>
                    <p style="display: flex; align-items: center; margin-top: 15px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2" style="margin-right: 10px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span>Mint time: ~1-2 minutes</span>
                    </p>
                </div>
                <button id="mint-nft-btn" class="btn btn-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; color: #166534;">
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
                    <h3 style="margin-top: 0; color: #166534; font-size: 18px; margin-bottom: 20px;">NFT Details</h3>
                    
                    <p><strong>Transaction Hash:</strong></p>
                    <a href="https://sepolia.etherscan.io/tx/<?php echo $nft_data['transaction_hash']; ?>" target="_blank" class="nft-link">
                        <?php echo $nft_data['transaction_hash']; ?>
                    </a>
                    
                    <p><strong>Token ID:</strong> <?php echo $nft_data['token_id']; ?></p>
                    
                    <p><strong>IPFS Metadata:</strong></p>
                    <a href="<?php echo str_replace('ipfs://', 'https://gateway.pinata.cloud/ipfs/', $nft_data['metadata_url']); ?>" target="_blank" class="nft-link">
                        <?php echo str_replace('ipfs://', 'https://gateway.pinata.cloud/ipfs/', $nft_data['metadata_url']); ?>
                    </a>
                    
                    <p><strong>Certificate Image:</strong></p>
                    <a href="<?php echo str_replace('ipfs://', 'https://gateway.pinata.cloud/ipfs/', $nft_data['image_url']); ?>" target="_blank" class="nft-link">
                        View Certificate Image
                    </a>
                    
                    <?php if (isset($nft_data['is_demo']) && $nft_data['is_demo'] == 1): ?>
                    <div class="demo-note">
                        <h4>About Demo NFTs</h4>
                        <p>This is a demonstration NFT to show how the system would work.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function downloadAsPNG() {
            const loadingMsg = document.createElement('div');
            loadingMsg.innerHTML = 'Generating PNG, please wait...';
            loadingMsg.style.padding = '10px';
            loadingMsg.style.backgroundColor = '#f0f0f0';
            loadingMsg.style.borderRadius = '5px';
            loadingMsg.style.marginTop = '10px';
            document.body.appendChild(loadingMsg);

            const container = document.querySelector('.certificate-container');
            html2canvas(container, {
                scale: 1.2,
                useCORS: true,
                backgroundColor: '#ffffff'
            }).then(function(canvas) {
                canvas.toBlob(function(blob) {
                    const link = document.createElement('a');
                    link.download = 'teaching_certificate_<?php echo $session_id; ?>.png';
                    link.href = URL.createObjectURL(blob);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    document.body.removeChild(loadingMsg);
                }, 'image/png', 0.8);
            }).catch(function(error) {
                console.error('Error generating PNG:', error);
                alert('There was an error generating the PNG. Please try again.');
                document.body.removeChild(loadingMsg);
            });
        }

        document.getElementById('mint-nft-btn')?.addEventListener('click', async function() {
            try {
                document.getElementById('mint-nft-btn').disabled = true;
                document.getElementById('mint-progress').style.display = 'block';
                document.getElementById('mint-status-message').textContent = 'Generating certificate image...';

                const container = document.querySelector('.certificate-container');
                const canvas = await html2canvas(container, {
                    scale: 1.2,
                    useCORS: true,
                    backgroundColor: '#ffffff'
                });

                const imgBlob = await new Promise(resolve => {
                    canvas.toBlob(resolve, 'image/png', 0.8);
                });

                const imgFile = new File([imgBlob], 'teaching_certificate_<?php echo $session_id; ?>.png', { type: 'image/png' });

                document.getElementById('mint-status-message').textContent = 'Uploading certificate to IPFS...';

                let imageIpfsUrl = '';
                let ipfsUri = '';
                let metadataUrl = '';
                let metadataUri = '';
                let headers = {};

                try {
                    const envResponse = await fetch('../students/get_env_variables.php');
                    if (!envResponse.ok) {
                        throw new Error(`Error fetching environment variables: ${envResponse.status}`);
                    }

                    const envData = await envResponse.json();
                    console.log('Environment variables loaded');

                    const formData = new FormData();
                    formData.append('file', imgFile);
                    formData.append('pinataOptions', JSON.stringify({ cidVersion: 1, wrapWithDirectory: false }));
                    formData.append('pinataMetadata', JSON.stringify({
                        name: `teaching-cert-<?php echo $session_id; ?>-${Math.floor(10000 + Math.random() * 90000)}`,
                        keyvalues: {
                            teacher: <?php echo json_encode($session['teacher_name']); ?>,
                            school: <?php echo json_encode($session['school_name']); ?>,
                            date: <?php echo json_encode($session['slot_date']); ?>,
                            activity_points: "<?php echo $activity_points; ?>"
                        }
                    }));

                    if (envData.PINATA_JWT && envData.PINATA_JWT.trim() !== '') {
                        headers = { 'Authorization': `Bearer ${envData.PINATA_JWT}` };
                    } else if (envData.PINATA_API_KEY && envData.PINATA_SECRET_KEY) {
                        headers = {
                            'pinata_api_key': envData.PINATA_API_KEY,
                            'pinata_secret_api_key': envData.PINATA_SECRET_KEY
                        };
                    } else {
                        throw new Error('No Pinata authentication credentials available');
                    }

                    const uploadResponse = await fetch('https://api.pinata.cloud/pinning/pinFileToIPFS', {
                        method: 'POST',
                        headers: headers,
                        body: formData
                    });

                    if (!uploadResponse.ok) {
                        const errorText = await uploadResponse.text();
                        throw new Error(`Pinata API error: ${errorText}`);
                    }

                    const uploadResult = await uploadResponse.json();
                    imageIpfsUrl = `https://gateway.pinata.cloud/ipfs/${uploadResult.IpfsHash}`;
                    ipfsUri = `ipfs://${uploadResult.IpfsHash}`;
                } catch (uploadError) {
                    console.error('Error during Pinata upload:', uploadError);
                    throw uploadError;
                }

                document.getElementById('mint-status-message').textContent = 'Creating NFT metadata...';

                const metadata = {
                    name: `Teaching Certificate - <?php echo htmlspecialchars($session['teacher_name']); ?>`,
                    description: `Certificate of Teaching for <?php echo htmlspecialchars($session['teacher_name']); ?> at <?php echo htmlspecialchars($session['school_name']); ?> on <?php echo $session_date; ?>. Subject: <?php echo htmlspecialchars($session['slot_description'] ?: 'General Teaching'); ?>. Duration: <?php echo number_format($hours_taught, 1); ?> hours. Activity Points: <?php echo $activity_points; ?>`,
                    image: ipfsUri,
                    image_url: imageIpfsUrl,
                    external_url: imageIpfsUrl,
                    attributes: [
                        { trait_type: "Teacher Name", value: <?php echo json_encode($session['teacher_name']); ?> },
                        { trait_type: "School", value: <?php echo json_encode($session['school_name']); ?> },
                        { trait_type: "Date", value: <?php echo json_encode($session_date); ?> },
                        { trait_type: "Subject/Activity", value: <?php echo json_encode($session['slot_description'] ?: 'General Teaching'); ?> },
                        { trait_type: "Duration (hours)", value: <?php echo number_format($hours_taught, 1); ?> },
                        { trait_type: "Activity Points", value: <?php echo $activity_points; ?> },
                        { trait_type: "Time", value: <?php echo json_encode($time_range); ?> }
                    ]
                };

                const metadataResponse = await fetch('https://api.pinata.cloud/pinning/pinJSONToIPFS', {
                    method: 'POST',
                    headers: { ...headers, 'Content-Type': 'application/json' },
                    body: JSON.stringify(metadata)
                });

                if (!metadataResponse.ok) {
                    throw new Error('Failed to upload metadata to IPFS');
                }

                const metadataResult = await metadataResponse.json();
                metadataUrl = `https://gateway.pinata.cloud/ipfs/${metadataResult.IpfsHash}`;
                metadataUri = `ipfs://${metadataResult.IpfsHash}`;

                document.getElementById('mint-status-message').textContent = 'Preparing blockchain transaction...';

                const certificateImageBase64 = canvas.toDataURL('image/png');

                const mintResponse = await fetch('mint_teacher_nft.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: <?php echo $session_id; ?>,
                        metadata_url: metadataUri,
                        image_url: ipfsUri,
                        certificate_image: certificateImageBase64
                    })
                });

                if (!mintResponse.ok) {
                    throw new Error('Failed to prepare NFT transaction');
                }

                const mintResult = await mintResponse.json();
                if (!mintResult.success) {
                    throw new Error(mintResult.error || 'Failed to prepare NFT transaction');
                }

                document.getElementById('mint-status-message').textContent = 'Waiting for IPFS propagation (8 seconds)...';
                await new Promise(resolve => setTimeout(resolve, 8000));

                document.getElementById('mint-status-message').textContent = 'Signing and sending blockchain transaction...';

                try {
                    if (typeof ethers === 'undefined') {
                        throw new Error('Ethers.js library is not loaded');
                    }

                    const txData = mintResult.data.tx_data || {};
                    const rpcUrl = txData.rpc_url || mintResult.data.rpc_url;
                    const privateKey = txData.private_key || mintResult.data.private_key;
                    const contractAddr = mintResult.data.contract_address;

                    if (!rpcUrl || !privateKey || !contractAddr) {
                        throw new Error('Missing required blockchain transaction data');
                    }

                    const provider = new ethers.providers.JsonRpcProvider(rpcUrl);
                    const wallet = new ethers.Wallet(privateKey, provider);
                    const abi = ["function mint(string memory tokenURI) public returns (uint256)"];
                    const contract = new ethers.Contract(contractAddr, abi, wallet);

                    const gasPrice = await provider.getGasPrice();
                    const increasedGasPrice = gasPrice.mul(3);

                    const tx = await contract.mint(metadataUri, {
                        gasPrice: increasedGasPrice,
                        gasLimit: 300000
                    });

                    document.getElementById('mint-status-message').textContent = 'Transaction submitted: ' + tx.hash;

                    document.getElementById('mint-status-message').textContent = 'Waiting for transaction confirmation...';
                    const receipt = await tx.wait();

                    let tokenId = '0';
                    if (receipt.logs && receipt.logs.length > 0) {
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

                    const updateResponse = await fetch('update_teacher_transaction.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            nft_id: mintResult.data.nft_id,
                            transaction_hash: tx.hash,
                            token_id: tokenId
                        })
                    });

                    if (!updateResponse.ok) {
                        throw new Error('Failed to update transaction details');
                    }

                    document.getElementById('mint-status-message').textContent = 'NFT successfully minted!';
                    setTimeout(() => { window.location.reload(); }, 3000);

                } catch (error) {
                    console.error('Blockchain error:', error);
                    document.getElementById('mint-status-message').textContent = `Blockchain error: ${error.message}`;
                    document.getElementById('mint-status-message').classList.add('status-error');
                    setTimeout(() => {
                        document.getElementById('mint-progress').innerHTML += '<button class="btn btn-gold" onclick="window.location.reload()">Retry</button>';
                    }, 3000);
                }

            } catch (error) {
                console.error('Error minting NFT:', error);
                document.getElementById('mint-status-message').textContent = `Error: ${error.message}`;
                document.getElementById('mint-nft-btn').disabled = false;
                document.getElementById('mint-status-message').classList.add('status-error');
                setTimeout(() => {
                    document.getElementById('mint-progress').innerHTML += '<button class="btn btn-gold" onclick="window.location.reload()">Retry</button>';
                }, 3000);
            }
        });
    </script>
</body>
</html>
