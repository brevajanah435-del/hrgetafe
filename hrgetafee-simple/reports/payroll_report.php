<?php
/**
 * Payroll Report Generation
 */

session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/payroll_functions.php';

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
        e.salary,
        p.basic_pay,
        p.allowances,
        p.deductions,
        p.tax,
        p.net_pay,
        p.payment_date
    FROM employees e
    LEFT JOIN payroll p ON e.employee_id = p.employee_id
        AND MONTH(p.payment_date) = ?
        AND YEAR(p.payment_date) = ?
    WHERE 1=1
";

$params = [$month, $year];
$types = 'ii';

if ($department) {
    $query .= " AND e.department = ?";
    $params[] = $department;
    $types .= 's';
}

$query .= " ORDER BY e.employee_name";

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
    header('Content-Disposition: attachment; filename=payroll_report_' . $year . '_' . $month . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee ID', 'Name', 'Department', 'Basic Pay', 'Allowances', 'Deductions', 'Tax', 'Net Pay', 'Payment Date']);
    
    foreach ($records as $row) {
        fputcsv($output, [
            $row['employee_id'],
            $row['employee_name'],
            $row['department'],
            $row['basic_pay'],
            $row['allowances'],
            $row['deductions'],
            $row['tax'],
            $row['net_pay'],
            $row['payment_date']
        ]);
    }
    fclose($output);
    exit;
}

// Get departments
$dept_stmt = $conn->prepare("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");
$dept_stmt->execute();
$departments = $dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Report - HRGetafe</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; }
        .filters { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { color: #666; font-size: 12px; font-weight: bold; margin-bottom: 5px; }
        .filter-group select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #5568d3; }
        .export-btn { background: #28a745; }
        .export-btn:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
        table td { padding: 12px; border-bottom: 1px solid #ddd; text-align: right; }
        table td:first-child, table th:first-child { text-align: left; }
        table tbody tr:hover { background: #f9f9f9; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-card h3 { font-size: 14px; opacity: 0.9; margin-bottom: 10px; }
        .stat-card .value { font-size: 28px; font-weight: bold; }
        .amount { font-weight: bold; color: #667eea; }
        @media print { .filters, button { display: none; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 Payroll Report</h1>
        
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
            
            <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&department=<?php echo $department; ?>&export=csv" class="export-btn" style="padding: 10px 20px; text-decoration: none; color: white; border-radius: 5px;">📥 Export CSV</a>
        </div>
        
        <?php
        $total_basic = array_sum(array_column($records, 'basic_pay'));
        $total_allowances = array_sum(array_column($records, 'allowances'));
        $total_deductions = array_sum(array_column($records, 'deductions'));
        $total_tax = array_sum(array_column($records, 'tax'));
        $total_net = array_sum(array_column($records, 'net_pay'));
        ?>
        
        <div class="summary">
            <div class="stat-card">
                <h3>Total Basic Pay</h3>
                <div class="value">₱<?php echo number_format($total_basic, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Allowances</h3>
                <div class="value">₱<?php echo number_format($total_allowances, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Deductions</h3>
                <div class="value">₱<?php echo number_format($total_deductions, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Net Pay</h3>
                <div class="value">₱<?php echo number_format($total_net, 2); ?></div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Basic Pay</th>
                    <th>Allowances</th>
                    <th>Deductions</th>
                    <th>Tax</th>
                    <th>Net Pay</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                <tr>
                    <td><?php echo escapeOutput($row['employee_id']); ?></td>
                    <td><?php echo escapeOutput($row['employee_name']); ?></td>
                    <td><?php echo escapeOutput($row['department']); ?></td>
                    <td class="amount">₱<?php echo number_format($row['basic_pay'], 2); ?></td>
                    <td class="amount">₱<?php echo number_format($row['allowances'], 2); ?></td>
                    <td class="amount">₱<?php echo number_format($row['deductions'], 2); ?></td>
                    <td class="amount">₱<?php echo number_format($row['tax'], 2); ?></td>
                    <td class="amount">₱<?php echo number_format($row['net_pay'], 2); ?></td>
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