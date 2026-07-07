<?php
/**
 * QR Code Generation for Employee Attendance
 * Creates unique QR codes for each employee
 */

session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole([1, 2]); // Admin and Staff only

$employee_id = $_GET['employee_id'] ?? null;
$action = $_GET['action'] ?? 'generate';

if (!$employee_id) {
    die('Employee ID is required');
}

// Get Employee Info
$stmt = $conn->prepare("SELECT employee_id, employee_name, qr_code FROM employees WHERE employee_id = ?");
$stmt->bind_param('i', $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    die('Employee not found');
}

if ($action === 'generate') {
    // Install phpqrcode if not exists
    if (!file_exists('../vendor/phpqrcode/qrlib.php')) {
        mkdir('../vendor/phpqrcode', 0755, true);
        // Download phpqrcode library (simplified version)
    }
    
    // Generate QR Code Data
    $qr_data = 'EMP-' . $employee_id . '-' . date('Y') . '-' . hash('sha256', $employee_id . SECRET_KEY);
    $qr_filename = '../assets/qrcodes/' . $employee_id . '_' . date('YmdHis') . '.png';
    
    // Create QR directory if not exists
    if (!is_dir('../assets/qrcodes')) {
        mkdir('../assets/qrcodes', 0755, true);
    }
    
    // Simple QR Code generation using online API (for demo)
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_data);
    $qr_image = file_get_contents($qr_url);
    file_put_contents($qr_filename, $qr_image);
    
    // Update database with QR code path
    $qr_path = 'assets/qrcodes/' . basename($qr_filename);
    $update_stmt = $conn->prepare("UPDATE employees SET qr_code = ?, qr_generated_date = NOW() WHERE employee_id = ?");
    $update_stmt->bind_param('si', $qr_path, $employee_id);
    $update_stmt->execute();
    
    logSecurityEvent($conn, $_SESSION['user_id'], 'QR_CODE_GENERATED', "Generated QR code for employee $employee_id");
    
    $message = 'QR Code generated successfully';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR Code - HRGetafe</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; }
        .employee-info { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .employee-info p { margin: 8px 0; color: #555; }
        .qr-code-section { text-align: center; padding: 20px; background: #f0f0f0; border-radius: 5px; }
        .qr-code-section img { max-width: 300px; margin: 15px 0; border: 2px solid #ddd; padding: 10px; background: white; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin-top: 10px; }
        button:hover { background: #5568d3; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; }
        .print-btn { background: #28a745; }
        .print-btn:hover { background: #218838; }
        @media print { body { background: white; } button { display: none; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>QR Code Generation</h1>
        
        <?php if (isset($message)): ?>
            <div class="success"><?php echo escapeOutput($message); ?></div>
        <?php endif; ?>
        
        <div class="employee-info">
            <p><strong>Employee ID:</strong> <?php echo escapeOutput($employee['employee_id']); ?></p>
            <p><strong>Name:</strong> <?php echo escapeOutput($employee['employee_name']); ?></p>
            <p><strong>QR Code Status:</strong> <?php echo $employee['qr_code'] ? 'Generated' : 'Not Generated'; ?></p>
        </div>
        
        <div class="qr-code-section">
            <h3>Employee QR Code</h3>
            <?php if ($employee['qr_code']): ?>
                <img src="<?php echo BASE_URL . escapeOutput($employee['qr_code']); ?>" alt="Employee QR Code">
                <p>Use this QR code for attendance tracking</p>
                <button class="print-btn" onclick="window.print()">🖨️ Print QR Code</button>
            <?php else: ?>
                <p style="color: #999; padding: 40px 0;">QR code not yet generated</p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <button onclick="history.back()">← Back</button>
        </div>
    </div>
</body>
</html>