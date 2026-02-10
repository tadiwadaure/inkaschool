<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require accountant role
$auth->requireRole('accountant');

// Set page title
$pageTitle = 'Outstanding Dues Management';

try {
    // Get filter parameters
    $classId = $_GET['class_id'] ?? '';
    $academicYear = $_GET['academic_year'] ?? date('Y');
    $overdueDays = $_GET['overdue_days'] ?? '';
    
    // Get classes for filter
    $classesStmt = $pdo->query("SELECT * FROM classes ORDER BY class_name");
    $classes = $classesStmt->fetchAll();
    
    // Build query for outstanding dues
    $whereConditions = ["fp.status = 'pending'", "fs.academic_year = ?"];
    $params = [$academicYear];
    
    if ($classId) {
        $whereConditions[] = "st.class_id = ?";
        $params[] = $classId;
    }
    
    if ($overdueDays) {
        if ($overdueDays == '30') {
            $whereConditions[] = "DATEDIFF(CURRENT_DATE, fp.payment_date) > 30";
        } elseif ($overdueDays == '60') {
            $whereConditions[] = "DATEDIFF(CURRENT_DATE, fp.payment_date) > 60";
        } elseif ($overdueDays == '90') {
            $whereConditions[] = "DATEDIFF(CURRENT_DATE, fp.payment_date) > 90";
        }
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Get outstanding dues
    $duesQuery = "
        SELECT 
            fp.*,
            st.roll_number,
            u.first_name as student_first,
            u.last_name as student_last,
            u.phone,
            u.email,
            c.class_name,
            fs.fee_type,
            fs.frequency,
            DATEDIFF(CURRENT_DATE, fp.payment_date) as days_overdue,
            CASE 
                WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) <= 15 THEN 'Recent'
                WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) <= 30 THEN 'Overdue'
                WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) <= 60 THEN 'Critical'
                ELSE 'Severe'
            END as overdue_category
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        LEFT JOIN classes c ON st.class_id = c.id
        $whereClause
        ORDER BY days_overdue DESC, c.class_name, st.roll_number
    ";
    
    $duesStmt = $pdo->prepare($duesQuery);
    $duesStmt->execute($params);
    $dues = $duesStmt->fetchAll();
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_dues,
            COALESCE(SUM(fp.amount_paid), 0) as total_amount,
            COUNT(CASE WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) > 30 THEN 1 END) as overdue_30,
            COUNT(CASE WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) > 60 THEN 1 END) as overdue_60,
            COUNT(CASE WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) > 90 THEN 1 END) as overdue_90,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) > 30 THEN fp.amount_paid ELSE 0 END), 0) as amount_overdue_30,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) > 60 THEN fp.amount_paid ELSE 0 END), 0) as amount_overdue_60,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE, fp.payment_date) > 90 THEN fp.amount_paid ELSE 0 END), 0) as amount_overdue_90
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        $whereClause
    ";
    
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch();
    
} catch (PDOException $e) {
    error_log('Dues page error: ' . $e->getMessage());
    $classes = [];
    $dues = [];
    $summary = null;
    $error = 'Database error occurred. Please try again.';
}

// Handle sending reminders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    try {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        
        if (!$paymentId) {
            throw new Exception('Invalid payment ID');
        }
        
        // Get payment details for reminder
        $paymentQuery = "
            SELECT fp.*, u.email, u.first_name, u.last_name, fs.fee_type
            FROM fee_payments fp
            JOIN students st ON fp.student_id = st.id
            JOIN users u ON st.user_id = u.id
            JOIN fee_structure fs ON fp.fee_structure_id = fs.id
            WHERE fp.id = ?
        ";
        $paymentStmt = $pdo->prepare($paymentQuery);
        $paymentStmt->execute([$paymentId]);
        $payment = $paymentStmt->fetch();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        // In a real application, you would send an email here
        // For demonstration, we'll just log it
        error_log("Reminder sent to {$payment['email']} for {$payment['fee_type']} - Amount: {$payment['amount_paid']}");
        
        $success = 'Reminder sent successfully to ' . htmlspecialchars($payment['email']);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    try {
        $selectedPayments = $_POST['selected_payments'] ?? [];
        $action = $_POST['action'] ?? '';
        
        if (empty($selectedPayments) || !$action) {
            throw new Exception('Please select payments and an action');
        }
        
        $placeholders = str_repeat('?,', count($selectedPayments) - 1) . '?';
        
        switch ($action) {
            case 'send_bulk_reminders':
                // Get all selected payments and send reminders
                $paymentsQuery = "
                    SELECT fp.*, u.email, u.first_name, u.last_name, fs.fee_type
                    FROM fee_payments fp
                    JOIN students st ON fp.student_id = st.id
                    JOIN users u ON st.user_id = u.id
                    JOIN fee_structure fs ON fp.fee_structure_id = fs.id
                    WHERE fp.id IN ($placeholders)
                ";
                $paymentsStmt = $pdo->prepare($paymentsQuery);
                $paymentsStmt->execute($selectedPayments);
                $payments = $paymentsStmt->fetchAll();
                
                foreach ($payments as $payment) {
                    // In a real application, send email
                    error_log("Bulk reminder sent to {$payment['email']} for {$payment['fee_type']}");
                }
                
                $success = 'Reminders sent to ' . count($payments) . ' students';
                break;
                
            case 'mark_as_paid':
                // Mark selected payments as completed
                $updateQuery = "UPDATE fee_payments SET status = 'completed' WHERE id IN ($placeholders)";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute($selectedPayments);
                
                $success = count($selectedPayments) . ' payments marked as completed';
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        // Refresh the page
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-exclamation-triangle"></i> Outstanding Dues Management
            <small class="text-muted">Track and manage overdue payments</small>
        </h1>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<?php if ($summary): ?>
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stats-number"><?php echo $summary['total_dues'] ?? 0; ?></div>
                            <div class="stats-label">Total Dues</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-file-invoice-dollar fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stats-number"><?php echo number_format((float)($summary['total_amount'] ?? 0), 2); ?></div>
                            <div class="stats-label">Total Amount</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stats-number"><?php echo $summary['overdue_30'] ?? 0; ?></div>
                            <div class="stats-label">>30 Days Overdue</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white bg-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stats-number"><?php echo $summary['overdue_90'] ?? 0; ?></div>
                            <div class="stats-label">>90 Days Overdue</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter"></i> Filter Dues
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-3">
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
                        <div class="col-md-3">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                   value="<?php echo $academicYear; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="overdue_days" class="form-label">Overdue Period</label>
                            <select class="form-select" id="overdue_days" name="overdue_days">
                                <option value="">All Dues</option>
                                <option value="30" <?php echo ($overdueDays == '30') ? 'selected' : ''; ?>>Over 30 Days</option>
                                <option value="60" <?php echo ($overdueDays == '60') ? 'selected' : ''; ?>>Over 60 Days</option>
                                <option value="90" <?php echo ($overdueDays == '90') ? 'selected' : ''; ?>>Over 90 Days</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Dues Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Outstanding Dues
                </h5>
                <div>
                    <button class="btn btn-sm btn-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="/accountant/reports.php?report_type=dues" class="btn btn-sm btn-info ms-2">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($dues)): ?>
                    <!-- Bulk Actions -->
                    <form method="POST" action="" id="bulkActionsForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <button type="submit" name="bulk_action" class="btn btn-warning" disabled id="bulkRemindersBtn">
                                        <i class="fas fa-envelope"></i> Send Reminders
                                    </button>
                                    <button type="submit" name="bulk_action" class="btn btn-success" disabled id="bulkPaidBtn">
                                        <i class="fas fa-check"></i> Mark as Paid
                                    </button>
                                    <input type="hidden" name="action" id="bulkAction" value="">
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th>Student</th>
                                        <th>Roll No</th>
                                        <th>Class</th>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Days Overdue</th>
                                        <th>Category</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dues as $due): ?>
                                        <tr class="<?php echo $due['days_overdue'] > 90 ? 'table-danger' : ($due['days_overdue'] > 30 ? 'table-warning' : ''); ?>">
                                            <td>
                                                <input type="checkbox" name="selected_payments[]" 
                                                       value="<?php echo $due['id']; ?>" 
                                                       class="payment-checkbox"
                                                       onchange="updateBulkButtons()">
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($due['student_first'] . ' ' . $due['student_last']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($due['roll_number']); ?></td>
                                            <td><?php echo htmlspecialchars($due['class_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($due['fee_type']); ?></td>
                                            <td>
                                                <span class="fw-bold text-danger">
                                                    <?php echo number_format($due['amount_paid'], 2); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($due['payment_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $due['days_overdue'] > 90 ? 'danger' : ($due['days_overdue'] > 30 ? 'warning' : 'info'); ?>">
                                                    <?php echo $due['days_overdue']; ?> days
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo match($due['overdue_category']) {
                                                    'Recent' => 'info',
                                                    'Overdue' => 'warning',
                                                    'Critical' => 'danger',
                                                    'Severe' => 'dark',
                                                    default => 'secondary'
                                                }; ?>">
                                                    <?php echo $due['overdue_category']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <div><?php echo htmlspecialchars($due['phone'] ?? 'N/A'); ?></div>
                                                    <div><?php echo htmlspecialchars($due['email'] ?? 'N/A'); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?php echo $due['id']; ?>">
                                                    <input type="hidden" name="send_reminder" value="1">
                                                    <button type="submit" class="btn btn-sm btn-warning" 
                                                            title="Send Reminder">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                </form>
                                                <a href="/accountant/fees.php?search=<?php echo urlencode($due['receipt_number']); ?>" 
                                                   class="btn btn-sm btn-info" title="View Payment">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No outstanding dues found matching the criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.payment-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateBulkButtons();
}

function updateBulkButtons() {
    const checkedBoxes = document.querySelectorAll('.payment-checkbox:checked');
    const bulkRemindersBtn = document.getElementById('bulkRemindersBtn');
    const bulkPaidBtn = document.getElementById('bulkPaidBtn');
    const hasSelection = checkedBoxes.length > 0;
    
    bulkRemindersBtn.disabled = !hasSelection;
    bulkPaidBtn.disabled = !hasSelection;
    
    if (hasSelection) {
        bulkRemindersBtn.onclick = function() {
            document.getElementById('bulkAction').value = 'send_bulk_reminders';
            document.getElementById('bulkActionsForm').submit();
        };
        
        bulkPaidBtn.onclick = function() {
            if (confirm('Mark selected payments as completed?')) {
                document.getElementById('bulkAction').value = 'mark_as_paid';
                document.getElementById('bulkActionsForm').submit();
            }
        };
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
