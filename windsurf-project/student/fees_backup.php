<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require student role
$auth->requireRole('student');

// Set page title
$pageTitle = 'Fee Details';

// Get student-specific data
try {
    // Get student info
    $studentStmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, u.email, c.class_name, c.section
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE u.id = ?
    ");
    $studentStmt->execute([$_SESSION['user_id']]);
    $student = $studentStmt->fetch();
    
    if (!$student) {
        throw new Exception('Student information not found');
    }
    
    // Get fee payments with filtering
    $statusFilter = $_GET['status'] ?? '';
    $yearFilter = $_GET['year'] ?? date('Y');
    
    $whereClause = "WHERE fp.student_id = ? AND YEAR(fp.payment_date) = ?";
    $params = [$student['id'], $yearFilter];
    
    if ($statusFilter) {
        $whereClause .= " AND fp.status = ?";
        $params[] = $statusFilter;
    }
    
    $feesStmt = $pdo->prepare("
        SELECT fp.*, fs.fee_type, fs.amount as expected_amount, fs.frequency, fs.academic_year
        FROM fee_payments fp
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        $whereClause
        ORDER BY fp.payment_date DESC
    ");
    $feesStmt->execute($params);
    $feePayments = $feesStmt->fetchAll();
    
    // Get fee structure for current class
    $feeStructureStmt = $pdo->prepare("
        SELECT fs.*, 
               (SELECT COUNT(*) FROM fee_payments fp WHERE fp.fee_structure_id = fs.id AND fp.student_id = ? AND fp.status = 'completed') as payment_count
        FROM fee_structure fs
        WHERE fs.class_id = ? AND fs.academic_year = ?
        ORDER BY fs.fee_type
    ");
    $feeStructureStmt->execute([$student['id'], $student['class_id'], date('Y')]);
    $feeStructure = $feeStructureStmt->fetchAll();
    
    // Calculate statistics
    $totalPaid = 0;
    $totalPending = 0;
    $totalExpected = 0;
    
    foreach ($feePayments as $payment) {
        if ($payment['status'] === 'completed') {
            $totalPaid += $payment['amount_paid'];
        } else {
            $totalPending += $payment['amount_paid'];
        }
        $totalExpected += $payment['expected_amount'];
    }
    
    // Handle payment processing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
        try {
            $paymentMethod = $_POST['payment_method'] ?? '';
            $amount = (float)($_POST['amount'] ?? 0);
            $feeStructureId = (int)($_POST['fee_structure_id'] ?? 0);
            
            if (!$paymentMethod || $amount <= 0 || $feeStructureId <= 0) {
                throw new Exception('Please fill in all required fields');
            }
            
            // Verify CSRF token
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid request. Please try again.');
            }
            
            // Get fee details
            $feeStmt = $pdo->prepare("SELECT * FROM fee_structure WHERE id = ? AND class_id = ?");
            $feeStmt->execute([$feeStructureId, $student['class_id']]);
            $fee = $feeStmt->fetch();
            
            if (!$fee) {
                throw new Exception('Fee not found');
            }
            
            // Check if already paid
            $paidStmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM fee_payments 
                WHERE student_id = ? AND fee_structure_id = ? AND status = 'completed'
            ");
            $paidStmt->execute([$student['id'], $feeStructureId]);
            $alreadyPaid = $paidStmt->fetch()['count'] > 0;
            
            if ($alreadyPaid) {
                throw new Exception('This fee has already been paid');
            }
            
            $feeAmount = (float)$fee['amount'];
            $remainingAmount = $amount;
            $processedFees = [];
            $receiptNumbers = [];
            
            // Process the selected fee first
            $paymentAmount = min($remainingAmount, $feeAmount);
            $receiptNumber = 'RCPT' . date('Y') . str_pad((string)$student['id'], 4, '0', STR_PAD_LEFT) . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $insertStmt = $pdo->prepare("
                INSERT INTO fee_payments (student_id, fee_structure_id, amount_paid, payment_date, payment_method, transaction_id, receipt_number, status)
                VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'completed')
            ");
            $insertStmt->execute([
                $student['id'],
                $feeStructureId,
                $paymentAmount,
                $paymentMethod,
                $_POST['transaction_id'] ?? null,
                $receiptNumber
            ]);
            
            $processedFees[] = $fee['fee_type'];
            $receiptNumbers[] = $receiptNumber;
            $remainingAmount -= $paymentAmount;
            
            // If there's overpayment, apply to other unpaid fees
            if ($remainingAmount > 0) {
                $unpaidFeesStmt = $pdo->prepare("
                    SELECT fs.* FROM fee_structure fs
                    WHERE fs.class_id = ? AND fs.academic_year = ?
                    AND fs.id != ?
                    AND fs.id NOT IN (
                        SELECT DISTINCT fee_structure_id 
                        FROM fee_payments 
                        WHERE student_id = ? AND status = 'completed'
                    )
                    ORDER BY fs.amount ASC
                ");
                $unpaidFeesStmt->execute([$student['class_id'], date('Y'), $feeStructureId, $student['id']]);
                $unpaidFees = $unpaidFeesStmt->fetchAll();
                
                foreach ($unpaidFees as $unpaidFee) {
                    if ($remainingAmount <= 0) break;
                    
                    $unpaidFeeAmount = (float)$unpaidFee['amount'];
                    $unpaidPaymentAmount = min($remainingAmount, $unpaidFeeAmount);
                    
                    $unpaidReceiptNumber = 'RCPT' . date('Y') . str_pad((string)$student['id'], 4, '0', STR_PAD_LEFT) . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $insertStmt = $pdo->prepare("
                        INSERT INTO fee_payments (student_id, fee_structure_id, amount_paid, payment_date, payment_method, transaction_id, receipt_number, status)
                        VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'completed')
                    ");
                    $insertStmt->execute([
                        $student['id'],
                        $unpaidFee['id'],
                        $unpaidPaymentAmount,
                        $paymentMethod,
                        $_POST['transaction_id'] ?? null,
                        $unpaidReceiptNumber
                    ]);
                    
                    $processedFees[] = $unpaidFee['fee_type'];
                    $receiptNumbers[] = $unpaidReceiptNumber;
                    $remainingAmount -= $unpaidPaymentAmount;
                }
            }
            
            // Create success message
            if (count($processedFees) == 1) {
                $success = "Payment successful! Fee: {$processedFees[0]}, Receipt: {$receiptNumbers[0]}";
            } else {
                $success = "Payment processed! Overpayment applied to " . count($processedFees) . " fees: " . implode(', ', $processedFees) . ". Receipts: " . implode(', ', array_slice($receiptNumbers, 0, 3)) . (count($receiptNumbers) > 3 ? '...' : '');
            }
            
            if ($remainingAmount > 0) {
                $success .= " Remaining balance: $" . number_format((float)$remainingAmount, 2) . " (will be credited to your account)";
            }
            
            // Redirect to avoid form resubmission
            header("Location: fees.php?success=" . urlencode($success));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Handle success message from redirect
    if (isset($_GET['success'])) {
        $success = htmlspecialchars($_GET['success']);
    }

    // Get available years for filter
    $yearsStmt = $pdo->prepare("
        SELECT DISTINCT YEAR(payment_date) as year 
        FROM fee_payments 
        WHERE student_id = ? 
        ORDER BY year DESC
    ");
    $yearsStmt->execute([$student['id']]);
    $availableYears = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log('Student fees error: ' . $e->getMessage());
    $student = [];
    $feePayments = [];
    $feeStructure = [];
    $totalPaid = 0;
    $totalPending = 0;
    $totalExpected = 0;
    $availableYears = [date('Y')];
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

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

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-receipt"></i> Fee Details
            <small class="text-muted">Payment History & Status</small>
        </h1>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stats-number">$<?php echo number_format((float)$totalPaid, 2); ?></div>
                        <div class="stats-label">Total Paid</div>
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
                        <div class="stats-number">$<?php echo number_format((float)$totalPending, 2); ?></div>
                        <div class="stats-label">Pending</div>
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
                        <div class="stats-number">$<?php echo number_format((float)($totalExpected - $totalPaid), 2); ?></div>
                        <div class="stats-label">Remaining</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-wallet fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter"></i> Filter Payments
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-select" id="year" name="year">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $yearFilter == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $statusFilter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="fees.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Payment History -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Payment History
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($feePayments)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feePayments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['fee_type']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo ucfirst($payment['frequency']); ?></small>
                                        </td>
                                        <td>
                                            $<?php echo number_format((float)$payment['amount_paid'], 2); ?>
                                            <?php if ($payment['amount_paid'] != $payment['expected_amount']): ?>
                                                <br>
                                                <small class="text-muted">Expected: $<?php echo number_format((float)$payment['expected_amount'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td>
                                            <?php if ($payment['status'] === 'completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($payment['status'] === 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($payment['receipt_number']); ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Payment Records</h5>
                        <p class="text-muted">Your payment history will appear here once payments are made.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Fee Structure Information -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Fee Structure - <?php echo date('Y'); ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($feeStructure)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Found <?php echo count($feeStructure); ?> fee structure(s) for your class. 
                        Payment buttons appear for unpaid fees.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Frequency</th>
                                    <th>Academic Year</th>
                                    <th>Payment Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feeStructure as $fee): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($fee['fee_type']); ?></strong></td>
                                        <td>$<?php echo number_format((float)$fee['amount'], 2); ?></td>
                                        <td><?php echo ucfirst($fee['frequency']); ?></td>
                                        <td><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                                        <td>
                                            <?php if ($fee['payment_count'] > 0): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Not Paid</span>
                                                <button type="button" class="btn btn-sm btn-primary ms-2" 
                                                        data-bs-toggle="modal" data-bs-target="#paymentModal"
                                                        onclick="setupIndividualPayment(<?php echo $fee['id']; ?>, '<?php echo htmlspecialchars($fee['fee_type']); ?>', <?php echo (float)$fee['amount']; ?>)">
                                                    <i class="fas fa-credit-card"></i> Pay Now
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary fw-bold">
                                    <td colspan="4">Total Expected Fees</td>
                                    <td>
                                        $<?php 
                                        $totalExpectedFees = array_sum(array_column($feeStructure, 'amount')); 
                                        echo number_format((float)$totalExpectedFees, 2); 
                                        ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        No fee structure information available for your class. 
                        Please contact the administrator to set up fee structures.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">
                    <i class="fas fa-credit-card"></i> Process Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="fee_structure_id" id="feeStructureId">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="process_payment" value="1">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div id="paymentInfo"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount ($)</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.01" min="0.01" required>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Overpayment will be automatically applied to other unpaid fees
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transaction_id" class="form-label">Transaction ID (Optional)</label>
                        <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                               placeholder="Enter transaction ID if available">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Process Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setupIndividualPayment(feeId, feeType, amount) {
    console.log('Setting up individual payment:', feeId, feeType, amount);
    
    // Set form values
    document.getElementById('feeStructureId').value = feeId;
    document.getElementById('amount').value = amount;
    
    // Update payment info with overpayment notice
    document.getElementById('paymentInfo').innerHTML = 
        '<strong>Fee Type:</strong> ' + feeType + '<br>' +
        '<strong>Required Amount:</strong> $' + amount.toFixed(2) + '<br>' +
        '<small class="text-muted">Any overpayment will be applied to other unpaid fees automatically</small>';
}

// Verify page is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Student fees page loaded successfully');
    
    // Test modal element exists
    var modal = document.getElementById('paymentModal');
    if (modal) {
        console.log('Payment modal found');
    } else {
        console.error('Payment modal not found');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
