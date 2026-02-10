<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require accountant role
$auth->requireRole('accountant');

// Set page title
$pageTitle = 'Accountant Dashboard';

// Get accountant-specific data
try {
    // Get accountant info
    $accountantStmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email 
        FROM users u 
        WHERE u.id = ?
    ");
    $accountantStmt->execute([$_SESSION['user_id']]);
    $accountant = $accountantStmt->fetch();
    
    // Get fee statistics
    $feeStatsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN fp.status = 'completed' THEN fp.amount_paid ELSE 0 END) as total_collected,
            SUM(CASE WHEN fp.status = 'pending' THEN fs.amount ELSE 0 END) as total_pending,
            COUNT(CASE WHEN fp.status = 'pending' THEN 1 END) as pending_count
        FROM students s
        LEFT JOIN fee_payments fp ON s.id = fp.student_id
        LEFT JOIN fee_structure fs ON fp.fee_structure_id = fs.id
    ");
    $feeStats = $feeStatsStmt->fetch();
    
    // Get recent payments
    $recentPaymentsStmt = $pdo->query("
        SELECT fp.*, u.first_name, u.last_name, s.roll_number, fs.fee_type
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN fee_structure fs ON fp.fee_structure_id = fs.id
        ORDER BY fp.payment_date DESC
        LIMIT 5
    ");
    $recentPayments = $recentPaymentsStmt->fetchAll();
    
    // Get monthly collection data
    $monthlyCollectionStmt = $pdo->query("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount_paid) as total
        FROM fee_payments 
        WHERE status = 'completed' 
            AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month DESC
    ");
    $monthlyCollections = $monthlyCollectionStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Accountant dashboard error: ' . $e->getMessage());
    $accountant = [];
    $feeStats = [
        'total_students' => 0,
        'total_collected' => 0,
        'total_pending' => 0,
        'pending_count' => 0
    ];
    $recentPayments = [];
    $monthlyCollections = [];
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-calculator"></i> Accountant Dashboard
            <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</small>
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
                        <div class="stats-number"><?php echo $feeStats['total_students']; ?></div>
                        <div class="stats-label">Total Students</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-graduate fa-2x opacity-75"></i>
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
                        <div class="stats-number">$<?php echo number_format((float)$feeStats['total_collected'], 2); ?></div>
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
                        <div class="stats-number">$<?php echo number_format((float)$feeStats['total_pending'], 2); ?></div>
                        <div class="stats-label">Pending Fees</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo $feeStats['pending_count']; ?></div>
                        <div class="stats-label">Pending Payments</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="/accountant/fees.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle"></i> Record Payment
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/accountant/fee_structure.php" class="btn btn-success w-100">
                            <i class="fas fa-cog"></i> Fee Structure
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/accountant/reports.php" class="btn btn-info w-100">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/accountant/dues.php" class="btn btn-warning w-100">
                            <i class="fas fa-list"></i> Pending Dues
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Payments -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Recent Payments
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentPayments)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentPayments as $payment): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Roll: <?php echo htmlspecialchars($payment['roll_number']); ?> - 
                                            <?php echo htmlspecialchars($payment['fee_type']); ?>
                                        </small>
                                        <br>
                                        <small class="text-success">
                                            Amount: $<?php echo number_format((float)$payment['amount_paid'], 2); ?>
                                        </small>
                                        <br>
                                        <small class="badge bg-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="/accountant/payments.php" class="btn btn-sm btn-outline-primary">View All Payments</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No payments recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Monthly Collections -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Monthly Collections
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($monthlyCollections)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($monthlyCollections as $collection): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo date('F Y', strtotime($collection['month'] . '-01')); ?></strong>
                                    </div>
                                    <div class="text-success">
                                        <strong>$<?php echo number_format((float)$collection['total'], 2); ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No collection data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie"></i> Financial Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                            <h6>Collection Rate</h6>
                            <?php 
                            $collectionRate = (float)$feeStats['total_pending'] > 0 ? 
                                (((float)$feeStats['total_collected'] / ((float)$feeStats['total_collected'] + (float)$feeStats['total_pending'])) * 100) : 100;
                            ?>
                            <small class="text-success"><?php echo round($collectionRate, 1); ?>%</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h6>Active Students</h6>
                            <small><?php echo $feeStats['total_students']; ?></small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-receipt fa-2x text-warning mb-2"></i>
                            <h6>Pending Dues</h6>
                            <small class="text-warning"><?php echo $feeStats['pending_count']; ?></small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-calendar fa-2x text-primary mb-2"></i>
                            <h6>Current Month</h6>
                            <small><?php echo date('F Y'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
