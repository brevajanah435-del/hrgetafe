<?php
/**
 * Attendance Management - Clock In/Out
 */

session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? null;
$response = ['success' => false, 'message' => ''];

if ($action === 'clock_in') {
    $employee_id = $_SESSION['employee_id'];
    
    // Check if already clocked in today
    $check = $conn->prepare(
        "SELECT attendance_id FROM attendance WHERE employee_id = ? AND DATE(clock_in) = CURDATE() AND clock_out IS NULL"
    );
    $check->bind_param('i', $employee_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $response['message'] = 'Already clocked in today';
    } else {
        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, clock_in) VALUES (?, NOW())");
        $stmt->bind_param('i', $employee_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Clock in successful';
            logSecurityEvent($conn, $_SESSION['user_id'], 'CLOCK_IN', "Employee clocked in");
        } else {
            $response['message'] = 'Clock in failed';
        }
    }
}

elseif ($action === 'clock_out') {
    $employee_id = $_SESSION['employee_id'];
    
    $stmt = $conn->prepare(
        "UPDATE attendance SET clock_out = NOW() 
         WHERE employee_id = ? AND DATE(clock_in) = CURDATE() AND clock_out IS NULL LIMIT 1"
    );
    $stmt->bind_param('i', $employee_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Clock out successful';
        logSecurityEvent($conn, $_SESSION['user_id'], 'CLOCK_OUT', "Employee clocked out");
    } else {
        $response['message'] = 'No active clock in found';
    }
}

echo json_encode($response);
?>