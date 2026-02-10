<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require accountant role
$auth->requireRole('accountant');

// Set page title
$pageTitle = 'Fee Management';

// Get fee payments with filtering
try {
    // Get filter parameters
    $status = $_GET['status'] ?? '';
    $classId = $_GET['class_id'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build query
    $whereConditions = [];
    $params = [];
    
    if ($status) {
        $whereConditions[] = "fp.status = ?";
        $params[] = $status;
    }
    
    if ($classId) {
        $whereConditions[] = "st.class_id = ?";
        $params[] = $classId;
    }
    
    if ($search) {
        $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR st.roll_number LIKE ? OR fp.receipt_number LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get fee payments
    $paymentsQuery = "
        SELECT fp.*, 
               st.roll_number, 
               u.first_name as student_first, 
               u.last_name as student_last,
               c.class_name,
               fs.fee_type,
               fs.amount as fee_amount,
               fs.frequency
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        LEFT JOIN classes c ON st.class_id = c.id
        $whereClause
        ORDER BY fp.payment_date DESC, fp.created_at DESC
        LIMIT 50
    ";
    
    $paymentsStmt = $pdo->prepare($paymentsQuery);
    $paymentsStmt->execute($params);
    $payments = $paymentsStmt->fetchAll();
    
    // Get classes for filter
    $classesStmt = $pdo->query("SELECT * FROM classes ORDER BY class_name");
    $classes = $classesStmt->fetchAll();
    
    // Get payment statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_payments,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN amount_paid ELSE 0 END), 0) as total_collected,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN amount_paid ELSE 0 END), 0) as total_pending,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments
        FROM fee_payments fp
        JOIN students st ON fp.student_id = st.id
        " . ($classId ? "WHERE st.class_id = ?" : "") . "
    ";
    
    $statsParams = $classId ? [$classId] : [];
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($statsParams);
    $stats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log('Fees page error: ' . $e->getMessage());
    $payments = [];
    $classes = [];
    $stats = null;
    $error = 'Database error occurred. Please try again.';
}

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    try {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        
        if (!$paymentId || !in_array($newStatus, ['pending', 'completed', 'failed'])) {
            throw new Exception('Invalid payment data');
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE fee_payments 
            SET status = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([$newStatus, $paymentId]);
        
        $success = 'Payment status updated successfully!';
        
        // Refresh the page to show updated data
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
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
            <i class="fas fa-money-bill-wave"></i> Fee Management
            <small class="text-muted">Manage student fee payments</small>
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

<!-- Statistics Cards -->
<?php if ($stats): ?>
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stats-number"><?php echo number_format((float)($stats['total_collected'] ?? 0), 2); ?></div>
                            <div class="stats-label">Total Collected</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-money-check-alt fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo number_format((float)($stats['total_pending'] ?? 0), 2); ?></div>
                            <div class="stats-label">Total Pending</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo $stats['completed_payments'] ?? 0; ?></div>
                            <div class="stats-label">Completed</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo $stats['pending_payments'] ?? 0; ?></div>
                            <div class="stats-label">Pending</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-hourglass-half fa-2x opacity-75"></i>
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
                    <i class="fas fa-filter"></i> Filter Payments
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo ($status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo ($status == 'failed') ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
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
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Student name, roll number, or receipt">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
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

<!-- Payments Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Fee Payments
                </h5>
                <div>
                    <button class="btn btn-sm btn-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="/accountant/fee_structure.php" class="btn btn-sm btn-info ms-2">
                        <i class="fas fa-cog"></i> Fee Structure
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($payments)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Student</th>
                                    <th>Roll No</th>
                                    <th>Class</th>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <code><?php echo htmlspecialchars($payment['receipt_number']); ?></code>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['student_first'] . ' ' . $payment['student_last']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['roll_number']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['class_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($payment['fee_type']); ?></td>
                                        <td>
                                            <span class="fw-bold"><?php echo number_format($payment['amount_paid'], 2); ?></span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = match($payment['status']) {
                                                'completed' => 'success',
                                                'pending' => 'warning',
                                                'failed' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($payment['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <input type="hidden" name="update_payment" value="1">
                                                    <button type="submit" class="btn btn-sm btn-success" 
                                                            onclick="return confirm('Mark this payment as completed?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($payment['status'] === 'completed'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                    <input type="hidden" name="status" value="pending">
                                                    <input type="hidden" name="update_payment" value="1">
                                                    <button type="submit" class="btn btn-sm btn-warning" 
                                                            onclick="return confirm('Mark this payment as pending?')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No fee payments found matching the criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
