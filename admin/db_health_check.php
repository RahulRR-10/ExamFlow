<?php
/**
 * Teaching Slots Database Health Check
 * Phase 8: Backward Compatibility & Safety
 * 
 * Run this script to verify database tables and indexes are properly configured.
 */

session_start();
require_once '../config.php';
require_once '../utils/teaching_slots_compat.php';

// Admin authentication check
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login_teacher.php');
    exit;
}

$status = verifyTeachingSlotsDatabase($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Health Check - Teaching Slots</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            padding: 30px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #1a2a6c, #2d4a7c);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .header p {
            opacity: 0.9;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header h2 {
            font-size: 18px;
        }
        .card-body {
            padding: 20px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        .check-list {
            list-style: none;
        }
        .check-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .check-item:last-child {
            border-bottom: none;
        }
        .check-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .check-icon.pass {
            background: #d4edda;
            color: #28a745;
        }
        .check-icon.fail {
            background: #f8d7da;
            color: #dc3545;
        }
        .check-name {
            flex: 1;
            font-weight: 500;
        }
        .check-status {
            font-size: 13px;
            color: #666;
        }
        .issues-list {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px 20px;
            margin-top: 20px;
        }
        .issues-list h4 {
            color: #856404;
            margin-bottom: 10px;
        }
        .issues-list ul {
            margin-left: 20px;
            color: #856404;
        }
        .issues-list li {
            margin-bottom: 5px;
        }
        .sql-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .action-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 15px 20px;
            margin-top: 20px;
        }
        .action-box h4 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary {
            background: #1a2a6c;
            color: #fff;
        }
        .btn-primary:hover {
            background: #0d1b4c;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #1a2a6c;
        }
        .summary-card .label {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class='bx bx-check-shield'></i> Database Health Check</h1>
            <p>Verify Teaching Slots feature database configuration</p>
        </div>

        <!-- Overall Status -->
        <div class="card">
            <div class="card-header">
                <i class='bx bx-pulse' style="font-size: 24px; color: <?= $status['all_tables_exist'] ? '#28a745' : '#dc3545' ?>;"></i>
                <h2>Overall Status</h2>
                <?php if ($status['all_tables_exist'] && empty($status['issues'])): ?>
                    <span class="status-badge success"><i class='bx bx-check'></i> All Systems Go</span>
                <?php elseif ($status['all_tables_exist']): ?>
                    <span class="status-badge warning"><i class='bx bx-error'></i> Minor Issues</span>
                <?php else: ?>
                    <span class="status-badge error"><i class='bx bx-x'></i> Setup Required</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="number"><?= count(array_filter($status['tables'], fn($t) => $t['exists'])) ?>/<?= count($status['tables']) ?></div>
                        <div class="label">Tables Exist</div>
                    </div>
                    <div class="summary-card">
                        <div class="number"><?= count(array_filter($status['tables'], fn($t) => $t['columns_ok'])) ?>/<?= count($status['tables']) ?></div>
                        <div class="label">Tables Complete</div>
                    </div>
                    <div class="summary-card">
                        <div class="number"><?= count(array_filter($status['indexes'])) ?>/<?= count($status['indexes']) ?></div>
                        <div class="label">Indexes Present</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Check -->
        <div class="card">
            <div class="card-header">
                <i class='bx bx-table' style="font-size: 22px; color: #1a2a6c;"></i>
                <h2>Database Tables</h2>
            </div>
            <div class="card-body">
                <ul class="check-list">
                    <?php foreach ($status['tables'] as $table => $info): ?>
                        <li class="check-item">
                            <span class="check-icon <?= $info['exists'] ? 'pass' : 'fail' ?>">
                                <i class='bx <?= $info['exists'] ? 'bx-check' : 'bx-x' ?>'></i>
                            </span>
                            <span class="check-name"><?= htmlspecialchars($table) ?></span>
                            <span class="check-status">
                                <?php if ($info['exists']): ?>
                                    <?= $info['columns_ok'] ? '✓ Complete' : '<i class="bx bx-error"></i> Missing columns' ?>
                                <?php else: ?>
                                    ✗ Not found
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Indexes Check -->
        <div class="card">
            <div class="card-header">
                <i class='bx bx-search-alt' style="font-size: 22px; color: #1a2a6c;"></i>
                <h2>Performance Indexes</h2>
            </div>
            <div class="card-body">
                <ul class="check-list">
                    <?php foreach ($status['indexes'] as $index => $exists): ?>
                        <li class="check-item">
                            <span class="check-icon <?= $exists ? 'pass' : 'fail' ?>">
                                <i class='bx <?= $exists ? 'bx-check' : 'bx-x' ?>'></i>
                            </span>
                            <span class="check-name"><?= htmlspecialchars($index) ?></span>
                            <span class="check-status">
                                <?= $exists ? '✓ Present' : '✗ Missing' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Issues -->
        <?php if (!empty($status['issues'])): ?>
        <div class="issues-list">
            <h4><i class='bx bx-error'></i> Issues Found</h4>
            <ul>
                <?php foreach ($status['issues'] as $issue): ?>
                    <li><?= htmlspecialchars($issue) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Fix Instructions -->
        <?php if (!$status['all_tables_exist']): ?>
        <div class="action-box">
            <h4><i class='bx bx-wrench'></i> How to Fix</h4>
            <p>Run the following migration script to create missing tables:</p>
            <div class="sql-box">
-- Run this file in phpMyAdmin or MySQL CLI:
db/migrate_teaching_slots.sql

-- Or execute via PHP:
php run_db_update.php
            </div>
            <p style="margin-top: 15px;">
                <a href="dash.php" class="btn btn-primary">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </p>
        </div>
        <?php else: ?>
        <div class="action-box" style="background: #d4edda; border-color: #c3e6cb;">
            <h4 style="color: #155724;"><i class='bx bx-check-circle'></i> Ready to Use</h4>
            <p style="color: #155724;">The Teaching Slots feature is properly configured and ready for use.</p>
            <p style="margin-top: 15px;">
                <a href="teaching_slots.php" class="btn btn-primary">
                    <i class='bx bx-calendar'></i> Manage Teaching Slots
                </a>
            </p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
