<?php
/**
 * Attendance Report Generation - CORRECTED VERSION
 */

session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/error_handler.php';

requireRole([1, 2]); // Admin and Staff only

$month = isset($_GET['month']) ? sanitizeInput($_GET['month']) : date('m');
$year = isset($_GET['year']) ? sanitizeInput($_GET['year']) : date('Y');
$department = isset($_GET['department']) ? sanitizeInput($_GET['department']) : null;
$export_format = isset($_GET['export']) ? sanitizeInput($_GET['export']) : null;

// Validate month and year
$month = str_pad($month, 2, '0', STR_PAD_LEFT);
$year = intval($year);

// Build query
$query = "
    SELECT 
        e.employee_id, 
        e.employee_name, 
        e.department,
        COUNT(a.attendance_id) as total_days,
        SUM(CASE WHEN a.clock_out IS NOT NULL THEN 1 ELSE 0 END) as present_days,
        COUNT(CASE WHEN a.clock_out IS NULL THEN 1 END) as incomplete_days,
        AVG(TIMESTAMPDIFF(HOUR, a.clock_in, COALESCE(a.clock_out, NOW()))) as avg_hours
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id 
        AND MONTH(a.clock_in) = ? 
        AND YEAR(a.clock_in) = ?
    WHERE e.status = 'active'
";

$params = [$month, $year];
$types = 'ii';

if ($department && !empty($department)) {
    $query .= " AND e.department = ?";
    $params[] = $department;
    $types .= 's';
}

$query .= " GROUP BY e.employee_id, e.employee_name, e.department ORDER BY e.employee_name";

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
    header('Content-Disposition: attachment; filename=attendance_report_' . $year . '_' . $month . '.csv');
    
    $output = fopen('php://output', 'w');
    if ($output) {
        fputcsv($output, ['Employee ID', 'Name', 'Department', 'Total Days', 'Present', 'Incomplete', 'Avg Hours']);
        
        foreach ($records as $row) {
            fputcsv($output, [
                $row['employee_id'],
                $row['employee_name'],
                $row['department'],
                $row['total_days'],
                $row['present_days'],
                $row['incomplete_days'],
                $row['avg_hours'] ? round($row['avg_hours'], 2) : 0
            ]);
        }
        fclose($output);
    }
    exit;
}

// Get departments for filter
$dept_stmt = $conn->prepare("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND status = 'active' ORDER BY department");
if ($dept_stmt) {
    $dept_stmt->execute();
    $departments = $dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dept_stmt->close();
} else {
    $departments = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - HRGetafe</title>
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
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-card h3 { font-size: 14px; opacity: 0.9; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; }
        @media print { .filters, button { display: none; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Attendance Report</h1>
        
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
                    <label>Department</label>
                    <select name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo escapeOutput($dept['department']); ?>" <?php echo $department == $dept['department'] ? 'selected' : ''; ?>>
                                <?php echo escapeOutput($dept['department']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit">🔍 Filter</button>
            </form>
            
            <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&department=<?php echo escapeOutput($department); ?>&export=csv" class="export-btn">📥 Export CSV</a>
        </div>
        
        <?php
        $total_present = 0;
        $total_days = 0;
        foreach ($records as $row) {
            $total_present += $row['present_days'] ? $row['present_days'] : 0;
            $total_days += $row['total_days'] ? $row['total_days'] : 0;
        }
        $total_employees = count($records);
        $avg_attendance = $total_days > 0 ? round(($total_present / $total_days) * 100, 2) : 0;
        ?>
        
        <div class="summary">
            <div class="stat-card">
                <h3>Total Employees</h3>
                <div class="value"><?php echo $total_employees; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Present</h3>
                <div class="value"><?php echo $total_present; ?></div>
            </div>
            <div class="stat-card">
                <h3>Attendance Rate</h3>
                <div class="value"><?php echo $avg_attendance; ?>%</div>
            </div>
            <div class="stat-card">
                <h3>Total Records</h3>
                <div class="value"><?php echo $total_days; ?></div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Total Days</th>
                    <th>Present</th>
                    <th>Incomplete</th>
                    <th>Avg Hours</th>
                    <th>Attendance %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                <tr>
                    <td><?php echo escapeOutput((string)$row['employee_id']); ?></td>
                    <td><?php echo escapeOutput($row['employee_name']); ?></td>
                    <td><?php echo escapeOutput($row['department']); ?></td>
                    <td><?php echo $row['total_days'] ? $row['total_days'] : 0; ?></td>
                    <td><?php echo $row['present_days'] ? $row['present_days'] : 0; ?></td>
                    <td><?php echo $row['incomplete_days'] ? $row['incomplete_days'] : 0; ?></td>
                    <td><?php echo $row['avg_hours'] ? round($row['avg_hours'], 2) : 0; ?> hrs</td>
                    <td>
                        <?php 
                        $attendance_pct = $row['total_days'] > 0 ? round(($row['present_days'] / $row['total_days']) * 100, 2) : 0;
                        echo $attendance_pct . '%';
                        ?>
                    </td>
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