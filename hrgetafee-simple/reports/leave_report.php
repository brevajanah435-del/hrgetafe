<?php
/**
 * Leave Report Generation - CORRECTED VERSION
 */

session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/error_handler.php';

requireRole([1, 2]); // Admin and Staff only

$month = isset($_GET['month']) ? sanitizeInput($_GET['month']) : date('m');
$year = isset($_GET['year']) ? sanitizeInput($_GET['year']) : date('Y');
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$export_format = isset($_GET['export']) ? sanitizeInput($_GET['export']) : null;

// Validate month and year
$month = str_pad($month, 2, '0', STR_PAD_LEFT);
$year = intval($year);

// Build query
$query = "
    SELECT 
        l.leave_id,
        e.employee_id,
        e.employee_name,
        e.department,
        l.leave_type_id,
        l.start_date,
        l.end_date,
        DATEDIFF(l.end_date, l.start_date) + 1 as days_used,
        l.status,
        l.reason,
        l.created_date
    FROM leave_requests l
    JOIN employees e ON l.employee_id = e.employee_id
    WHERE MONTH(l.created_date) = ? AND YEAR(l.created_date) = ?
";

$params = [$month, $year];
$types = 'ii';

if ($status !== 'all' && !empty($status)) {
    $query .= " AND l.status = ?";
    $params[] = $status;
    $types .= 's';
}

$query .= " ORDER BY l.created_date DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Query error: ' . $conn->error);
}

if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Export to CSV
if ($export_format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=leave_report_' . $year . '_' . $month . '.csv');
    
    $output = fopen('php://output', 'w');
    if ($output) {
        fputcsv($output, ['Leave ID', 'Employee ID', 'Name', 'Department', 'Start Date', 'End Date', 'Days', 'Status', 'Reason']);
        
        foreach ($records as $row) {
            fputcsv($output, [
                $row['leave_id'],
                $row['employee_id'],
                $row['employee_name'],
                $row['department'],
                $row['start_date'],
                $row['end_date'],
                $row['days_used'],
                $row['status'],
                $row['reason']
            ]);
        }
        fclose($output);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Report - HRGetafe</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; }
        .filters { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { color: #666; font-size: 12px; font-weight: bold; margin-bottom: 5px; }
        .filter-group select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 120px; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #5568d3; }
        .export-btn { background: #28a745; margin-left: 10px; text-decoration: none; display: inline-block; }
        .export-btn:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
        table td { padding: 12px; border-bottom: 1px solid #ddd; }
        table tbody tr:hover { background: #f9f9f9; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-card h3 { font-size: 14px; opacity: 0.9; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; }
        @media print { .filters, button { display: none; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Leave Report</h1>
        
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div class="filter-group">
                    <label>Month</label>
                    <select name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year">
                        <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <button type="submit">🔍 Filter</button>
            </form>
            
            <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&status=<?php echo $status; ?>&export=csv" class="export-btn">📥 Export CSV</a>
        </div>
        
        <?php
        $total_approved = 0;
        $total_pending = 0;
        $total_rejected = 0;
        $total_days = 0;
        
        foreach ($records as $row) {
            $total_days += $row['days_used'] ? $row['days_used'] : 0;
            if ($row['status'] === 'approved') $total_approved++;
            elseif ($row['status'] === 'pending') $total_pending++;
            elseif ($row['status'] === 'rejected') $total_rejected++;
        }
        ?>
        
        <div class="summary">
            <div class="stat-card">
                <h3>Approved</h3>
                <div class="value"><?php echo $total_approved; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="value"><?php echo $total_pending; ?></div>
            </div>
            <div class="stat-card">
                <h3>Rejected</h3>
                <div class="value"><?php echo $total_rejected; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Days</h3>
                <div class="value"><?php echo $total_days; ?></div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Leave ID</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                <tr>
                    <td><?php echo escapeOutput((string)$row['leave_id']); ?></td>
                    <td><?php echo escapeOutput($row['employee_name']); ?></td>
                    <td><?php echo escapeOutput($row['department']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
                    <td><?php echo $row['days_used']; ?></td>
                    <td class="status-<?php echo strtolower($row['status']); ?>"><?php echo ucfirst(escapeOutput($row['status'])); ?></td>
                    <td><?php echo escapeOutput(substr($row['reason'], 0, 50)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()">🖨️ Print Report</button>
            <a href="../admin/dashboard.php" style="margin-left: 10px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">← Back</a>
        </div>
    </div>
</body>
</html>