<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require accountant role
$auth->requireRole('accountant');

// Set page title
$pageTitle = 'Financial Reports';

try {
    // Get report parameters
    $reportType = $_GET['report_type'] ?? 'summary';
    $classId = $_GET['class_id'] ?? '';
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    $academicYear = $_GET['academic_year'] ?? date('Y');
    
    // Get classes for filter
    $classesStmt = $pdo->query("SELECT * FROM classes ORDER BY class_name");
    $classes = $classesStmt->fetchAll();
    
    // Generate report based on type
    switch ($reportType) {
        case 'summary':
            $reportData = generateSummaryReport($pdo, $classId, $academicYear);
            break;
        case 'payments':
            $reportData = generatePaymentsReport($pdo, $classId, $startDate, $endDate);
            break;
        case 'dues':
            $reportData = generateDuesReport($pdo, $classId, $academicYear);
            break;
        case 'class_wise':
            $reportData = generateClassWiseReport($pdo, $academicYear);
            break;
        default:
            $reportData = generateSummaryReport($pdo, $classId, $academicYear);
    }
    
} catch (PDOException $e) {
    error_log('Reports page error: ' . $e->getMessage());
    $error = 'Database error occurred. Please try again.';
    $reportData = null;
}

// Report generation functions
function generateSummaryReport($pdo, $classId, $academicYear) {
    $whereConditions = ["fs.academic_year = ?"];
    $params = [$academicYear];
    
    if ($classId) {
        $whereConditions[] = "st.class_id = ?";
        $params[] = $classId;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Total expected fees
    $expectedQuery = "
        SELECT SUM(fs.amount) as total_expected
        FROM fee_structure fs
        LEFT JOIN students st ON fs.class_id = st.class_id
        $whereClause
    ";
    $expectedStmt = $pdo->prepare($expectedQuery);
    $expectedStmt->execute($params);
    $totalExpected = $expectedStmt->fetch()['total_expected'] ?? 0;
    
    // Total collected
    $collectedQuery = "
        SELECT SUM(fp.amount_paid) as total_collected
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        $whereClause AND fp.status = 'completed'
    ";
    $collectedStmt = $pdo->prepare($collectedQuery);
    $collectedStmt->execute($params);
    $totalCollected = $collectedStmt->fetch()['total_collected'] ?? 0;
    
    // Total pending
    $pendingQuery = "
        SELECT SUM(fp.amount_paid) as total_pending
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        $whereClause AND fp.status = 'pending'
    ";
    $pendingStmt = $pdo->prepare($pendingQuery);
    $pendingStmt->execute($params);
    $totalPending = $pendingStmt->fetch()['total_pending'] ?? 0;
    
    // Payment counts
    $countsQuery = "
        SELECT 
            fp.status,
            COUNT(*) as count,
            SUM(fp.amount_paid) as amount
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        $whereClause
        GROUP BY fp.status
    ";
    $countsStmt = $pdo->prepare($countsQuery);
    $countsStmt->execute($params);
    $statusCounts = $countsStmt->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_GROUP);
    
    return [
        'total_expected' => $totalExpected,
        'total_collected' => $totalCollected,
        'total_pending' => $totalPending,
        'collection_rate' => $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 2) : 0,
        'status_counts' => $statusCounts
    ];
}

function generatePaymentsReport($pdo, $classId, $startDate, $endDate) {
    $whereConditions = ["fp.payment_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($classId) {
        $whereConditions[] = "st.class_id = ?";
        $params[] = $classId;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $query = "
        SELECT 
            fp.*,
            st.roll_number,
            u.first_name as student_first,
            u.last_name as student_last,
            c.class_name,
            fs.fee_type,
            fs.frequency
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        LEFT JOIN classes c ON st.class_id = c.id
        $whereClause
        ORDER BY fp.payment_date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generateDuesReport($pdo, $classId, $academicYear) {
    $whereConditions = ["fs.academic_year = ?", "fp.status = 'pending'"];
    $params = [$academicYear];
    
    if ($classId) {
        $whereConditions[] = "st.class_id = ?";
        $params[] = $classId;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $query = "
        SELECT 
            fp.*,
            st.roll_number,
            u.first_name as student_first,
            u.last_name as student_last,
            c.class_name,
            fs.fee_type,
            fs.frequency,
            DATEDIFF(CURRENT_DATE, fp.payment_date) as days_overdue
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        LEFT JOIN classes c ON st.class_id = c.id
        $whereClause
        ORDER BY days_overdue DESC, fp.payment_date ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generateClassWiseReport($pdo, $academicYear) {
    $query = "
        SELECT 
            c.class_name,
            COUNT(DISTINCT st.id) as total_students,
            COUNT(DISTINCT CASE WHEN fp.status = 'completed' THEN fp.id END) as paid_students,
            COUNT(DISTINCT CASE WHEN fp.status = 'pending' THEN fp.id END) as unpaid_students,
            SUM(CASE WHEN fp.status = 'completed' THEN fp.amount_paid ELSE 0 END) as total_collected,
            SUM(fs.amount) as total_expected,
            ROUND(
                (SUM(CASE WHEN fp.status = 'completed' THEN fp.amount_paid ELSE 0 END) / 
                NULLIF(SUM(fs.amount), 0)) * 100, 2
            ) as collection_rate
        FROM classes c
        LEFT JOIN students st ON c.id = st.class_id
        LEFT JOIN fee_structure fs ON c.id = fs.class_id AND fs.academic_year = ?
        LEFT JOIN fee_payments fp ON st.id = fp.student_id AND fs.id = fp.fee_structure_id
        GROUP BY c.id, c.class_name
        ORDER BY c.class_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$academicYear]);
    return $stmt->fetchAll();
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-chart-bar"></i> Financial Reports
            <small class="text-muted">Generate and view financial analytics</small>
        </h1>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Report Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter"></i> Report Filters
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type" onchange="this.form.submit()">
                                <option value="summary" <?php echo ($reportType == 'summary') ? 'selected' : ''; ?>>Summary Report</option>
                                <option value="payments" <?php echo ($reportType == 'payments') ? 'selected' : ''; ?>>Payment Details</option>
                                <option value="dues" <?php echo ($reportType == 'dues') ? 'selected' : ''; ?>>Outstanding Dues</option>
                                <option value="class_wise" <?php echo ($reportType == 'class_wise') ? 'selected' : ''; ?>>Class-wise Report</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="class_id" class="form-label">Class</label>
                            <select class="form-select" id="class_id" name="class_id">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" 
                                            <?php echo ($classId == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($reportType == 'payments'): ?>
                            <div class="col-md-2">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $endDate; ?>">
                            </div>
                        <?php else: ?>
                            <div class="col-md-4">
                                <label for="academic_year" class="form-label">Academic Year</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                       value="<?php echo $academicYear; ?>">
                            </div>
                        <?php endif; ?>
                        <div class="col-md-<?php echo ($reportType == 'payments') ? '3' : '2'; ?> d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Report Content -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-alt"></i> 
                    <?php 
                    $reportTitles = [
                        'summary' => 'Financial Summary',
                        'payments' => 'Payment Details Report',
                        'dues' => 'Outstanding Dues Report',
                        'class_wise' => 'Class-wise Performance Report'
                    ];
                    echo $reportTitles[$reportType] ?? 'Report';
                    ?>
                </h5>
                <div>
                    <button class="btn btn-sm btn-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-sm btn-info" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if ($reportType == 'summary' && $reportData): ?>
                    <!-- Summary Report -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">Total Expected</h5>
                                    <h3><?php echo number_format($reportData['total_expected'], 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-success">Total Collected</h5>
                                    <h3><?php echo number_format($reportData['total_collected'], 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-warning">Total Pending</h5>
                                    <h3><?php echo number_format($reportData['total_pending'], 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-info">Collection Rate</h5>
                                    <h3><?php echo $reportData['collection_rate']; ?>%</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="progress mb-4" style="height: 30px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $reportData['collection_rate']; ?>%">
                            <?php echo $reportData['collection_rate']; ?>% Collected
                        </div>
                    </div>
                    
                <?php elseif ($reportType == 'payments' && $reportData): ?>
                    <!-- Payments Report -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['student_first'] . ' ' . $payment['student_last']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['class_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($payment['fee_type']); ?></td>
                                        <td><?php echo number_format($payment['amount_paid'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php elseif ($reportType == 'dues' && $reportData): ?>
                    <!-- Dues Report -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $due): ?>
                                    <tr class="<?php echo $due['days_overdue'] > 30 ? 'table-danger' : ($due['days_overdue'] > 15 ? 'table-warning' : ''); ?>">
                                        <td><?php echo htmlspecialchars($due['student_first'] . ' ' . $due['student_last']); ?></td>
                                        <td><?php echo htmlspecialchars($due['class_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($due['fee_type']); ?></td>
                                        <td><?php echo number_format($due['amount_paid'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($due['payment_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $due['days_overdue'] > 30 ? 'danger' : ($due['days_overdue'] > 15 ? 'warning' : 'info'); ?>">
                                                <?php echo $due['days_overdue']; ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">Pending</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php elseif ($reportType == 'class_wise' && $reportData): ?>
                    <!-- Class-wise Report -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Total Students</th>
                                    <th>Paid Students</th>
                                    <th>Unpaid Students</th>
                                    <th>Total Expected</th>
                                    <th>Total Collected</th>
                                    <th>Collection Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $class): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                        <td><?php echo $class['total_students']; ?></td>
                                        <td>
                                            <span class="text-success"><?php echo $class['paid_students']; ?></span>
                                        </td>
                                        <td>
                                            <span class="text-danger"><?php echo $class['unpaid_students']; ?></span>
                                        </td>
                                        <td><?php echo number_format($class['total_expected'], 2); ?></td>
                                        <td><?php echo number_format($class['total_collected'], 2); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-2" style="width: 100px; height: 20px;">
                                                    <div class="progress-bar" style="width: <?php echo $class['collection_rate']; ?>%"></div>
                                                </div>
                                                <span><?php echo $class['collection_rate']; ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No data available for the selected criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Simple CSV export for demonstration
    const table = document.querySelector('table');
    if (!table) {
        alert('No table data to export');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].textContent.trim() + '"');
        }
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'financial_report.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
