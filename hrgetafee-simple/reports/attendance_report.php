<?php
/**
 * Attendance Report Generation
 */

session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole([1, 2]); // Admin and Staff only

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$department = $_GET['department'] ?? null;
$export_format = $_GET['export'] ?? null;

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
    WHERE 1=1
";

$params = [$month, $year];
$types = 'ii';

if ($department) {
    $query .= " AND e.department = ?";
    $params[] = $department;
    $types .= 's';
}

$query .= " GROUP BY e.employee_id, e.employee_name, e.department ORDER BY e.employee_name";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);

// Export to CSV
if ($export_format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . $year . '_' . $month . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee ID', 'Name', 'Department', 'Total Days', 'Present', 'Incomplete', 'Avg Hours']);
    
    foreach ($records as $row) {
        fputcsv($output, [
            $row['employee_id'],
            $row['employee_name'],
            $row['department'],
            $row['total_days'],
            $row['present_days'],
            $row['incomplete_days'],
            round($row['avg_hours'], 2)
        ]);
    }
    fclose($output);
    exit;
}

// Export to PDF (requires TCPDF or FPDF)
if ($export_format === 'pdf') {
    // Simplified PDF generation
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename=attendance_report_' . $year . '_' . $month . '.pdf');
    
    $pdf_content = "Attendance Report - " . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . "\n\n";
    $pdf_content .= str_repeat("-", 100) . "\n";
    $pdf_content .= sprintf("%-12s %-30s %-20s %-12s %-10s %-12s %-10s\n", 
        'Employee ID', 'Name', 'Department', 'Total Days', 'Present', 'Incomplete', 'Avg Hrs');
    $pdf_content .= str_repeat("-", 100) . "\n";
    
    foreach ($records as $row) {
        $pdf_content .= sprintf("%-12s %-30s %-20s %-12s %-10s %-12s %-10.1f\n",
            $row['employee_id'],
            substr($row['employee_name'], 0, 29),
            $row['department'],
            $row['total_days'],
            $row['present_days'],
            $row['incomplete_days'],
            $row['avg_hours']
        );
    }
    
    echo $pdf_content;
    exit;
}

// Get departments for filter
$dept_stmt = $conn->prepare("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");
$dept_stmt->execute();
$departments = $dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
        .filters { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { color: #666; font-size: 12px; font-weight: bold; margin-bottom: 5px; }
        .filter-group select, .filter-group input { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #5568d3; }
        .export-btn { background: #28a745; margin-left: 10px; }
        .export-btn:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
        table td { padding: 12px; border-bottom: 1px solid #ddd; }
        table tbody tr:hover { background: #f9f9f9; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-card h3 { font-size: 14px; opacity: 0.9; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; }
        .actions { text-align: center; margin-top: 30px; }
        @media print { .filters, .actions, button { display: none; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Attendance Report</h1>
        
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap;">
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
                
                <div class="filter-group" style="justify-content: flex-end;">
                    <button type="submit">🔍 Filter</button>
                </div>
            </form>
            
            <div style="display: flex; gap: 10px;">
                <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&department=<?php echo $department; ?>&export=csv" class="export-btn" style="padding: 10px 20px; text-decoration: none; color: white; border-radius: 5px;">📥 Export CSV</a>
                <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&department=<?php echo $department; ?>&export=pdf" class="export-btn" style="padding: 10px 20px; text-decoration: none; color: white; border-radius: 5px;">📄 Export PDF</a>
            </div>
        </div>
        
        <?php
        $total_present = array_sum(array_column($records, 'present_days'));
        $total_employees = count($records);
        $total_days = array_sum(array_column($records, 'total_days'));
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
                    <td><?php echo escapeOutput($row['employee_id']); ?></td>
                    <td><?php echo escapeOutput($row['employee_name']); ?></td>
                    <td><?php echo escapeOutput($row['department']); ?></td>
                    <td><?php echo escapeOutput($row['total_days']); ?></td>
                    <td><?php echo escapeOutput($row['present_days']); ?></td>
                    <td><?php echo escapeOutput($row['incomplete_days']); ?></td>
                    <td><?php echo round($row['avg_hours'], 2); ?> hrs</td>
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
        
        <div class="actions">
            <button onclick="window.print()">🖨️ Print Report</button>
            <a href="../admin/dashboard.php" style="margin-left: 10px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">← Back</a>
        </div>
    </div>
</body>
</html>