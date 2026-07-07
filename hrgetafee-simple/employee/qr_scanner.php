<?php
/**
 * QR Code Scanner for Attendance
 * Allows employees to scan QR codes for clock-in/clock-out
 */

session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireLogin();

$message = '';
$error = '';
$employee_id = $_SESSION['employee_id'] ?? null;

// Handle QR Code Scanning
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qr_data = sanitizeInput($_POST['qr_data'] ?? '');
    
    if (empty($qr_data)) {
        $error = 'Please scan a QR code';
    } else {
        // Verify QR code format
        if (strpos($qr_data, 'EMP-') === 0) {
            // Extract employee ID from QR code
            preg_match('/EMP-(\d+)-/', $qr_data, $matches);
            $scanned_employee_id = $matches[1] ?? null;
            
            if ($scanned_employee_id) {
                // Check if already clocked in
                $check_stmt = $conn->prepare(
                    "SELECT attendance_id, clock_in, clock_out FROM attendance 
                     WHERE employee_id = ? AND DATE(clock_in) = CURDATE() AND clock_out IS NULL"
                );
                $check_stmt->bind_param('i', $scanned_employee_id);
                $check_stmt->execute();
                $existing = $check_stmt->get_result()->fetch_assoc();
                
                if ($existing) {
                    // Clock out
                    $update_stmt = $conn->prepare(
                        "UPDATE attendance SET clock_out = NOW() WHERE attendance_id = ?"
                    );
                    $update_stmt->bind_param('i', $existing['attendance_id']);
                    $update_stmt->execute();
                    
                    logSecurityEvent($conn, $_SESSION['user_id'], 'CLOCK_OUT', "Employee $scanned_employee_id clocked out");
                    $message = '✓ Clock Out Successful! See you tomorrow.';
                } else {
                    // Clock in
                    $insert_stmt = $conn->prepare(
                        "INSERT INTO attendance (employee_id, clock_in) VALUES (?, NOW())"
                    );
                    $insert_stmt->bind_param('i', $scanned_employee_id);
                    $insert_stmt->execute();
                    
                    logSecurityEvent($conn, $_SESSION['user_id'], 'CLOCK_IN', "Employee $scanned_employee_id clocked in");
                    $message = '✓ Clock In Successful! Have a productive day.';
                }
            } else {
                $error = 'Invalid QR code format';
            }
        } else {
            $error = 'QR code does not belong to this system';
        }
    }
}

// Get today's attendance
$today_stmt = $conn->prepare(
    "SELECT attendance_id, clock_in, clock_out FROM attendance 
     WHERE employee_id = ? AND DATE(clock_in) = CURDATE() ORDER BY clock_in DESC"
);
$today_stmt->bind_param('i', $employee_id);
$today_stmt->execute();
$today_records = $today_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Scanner - HRGetafe</title>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.2.0/dist/html5-qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .scanner-section { margin-bottom: 30px; }
        #qr-reader { width: 100%; margin-bottom: 20px; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .manual-input { margin-top: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; color: #333; font-weight: 600; margin-bottom: 8px; }
        input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; width: 100%; margin-top: 10px; }
        button:hover { background: #5568d3; }
        .records-section { margin-top: 30px; }
        .record-item { background: #f9f9f9; padding: 15px; border-left: 4px solid #667eea; margin-bottom: 10px; border-radius: 5px; }
        .record-item strong { color: #333; }
        .record-item span { color: #666; margin-left: 10px; }
        .status-in { color: #28a745; }
        .status-out { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⏱️ Attendance Scanner</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo escapeOutput($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo escapeOutput($error); ?></div>
        <?php endif; ?>
        
        <div class="scanner-section">
            <h3 style="margin-bottom: 15px; font-size: 16px;">📱 Scan QR Code</h3>
            <div id="qr-reader" style="width: 100%;"></div>
        </div>
        
        <div class="manual-input">
            <h3 style="margin-bottom: 15px; font-size: 16px;">📝 Or Enter Manually</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="qr_data">QR Code Data:</label>
                    <textarea id="qr_data" name="qr_data" rows="3" placeholder="Paste QR code data here"></textarea>
                </div>
                <button type="submit">Submit</button>
            </form>
        </div>
        
        <?php if (!empty($today_records)): ?>
        <div class="records-section">
            <h3>Today's Records</h3>
            <?php foreach ($today_records as $record): ?>
            <div class="record-item">
                <strong>Clock In:</strong> 
                <span><?php echo date('h:i A', strtotime($record['clock_in'])); ?></span>
                <?php if ($record['clock_out']): ?>
                    <br><strong>Clock Out:</strong> 
                    <span class="status-out"><?php echo date('h:i A', strtotime($record['clock_out'])); ?></span>
                <?php else: ?>
                    <br><span class="status-in" style="font-weight: bold;">⏳ Currently Clocked In</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="dashboard.php" style="color: #667eea; text-decoration: none;">← Back to Dashboard</a>
        </div>
    </div>
    
    <script>
        function onScanSuccess(decodedText, decodedResult) {
            // Auto-submit the form with QR data
            document.getElementById('qr_data').value = decodedText;
            // Optional: Auto-submit
            // document.querySelector('form').submit();
            console.log(`QR Code scanned: ${decodedText}`);
        }
        
        const html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader",
            { fps: 10, qrbox: { width: 250, height: 250 } },
            false
        );
        
        html5QrcodeScanner.render(onScanSuccess);
    </script>
</body>
</html>