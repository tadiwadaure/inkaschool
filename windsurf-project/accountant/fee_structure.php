<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require accountant role
$auth->requireRole('accountant');

// Set page title
$pageTitle = 'Fee Structure Management';

try {
    // Get all classes
    $classesStmt = $pdo->query("SELECT * FROM classes ORDER BY class_name");
    $classes = $classesStmt->fetchAll();
    
    // Get fee structures with filtering
    $classFilter = $_GET['class_id'] ?? '';
    $yearFilter = $_GET['academic_year'] ?? date('Y');
    
    $whereConditions = [];
    $params = [];
    
    if ($classFilter) {
        $whereConditions[] = "fs.class_id = ?";
        $params[] = $classFilter;
    }
    
    if ($yearFilter) {
        $whereConditions[] = "fs.academic_year = ?";
        $params[] = $yearFilter;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $feeStructuresQuery = "
        SELECT fs.*, c.class_name
        FROM fee_structure fs
        LEFT JOIN classes c ON fs.class_id = c.id
        $whereClause
        ORDER BY c.class_name, fs.fee_type, fs.frequency
    ";
    
    $feeStructuresStmt = $pdo->prepare($feeStructuresQuery);
    $feeStructuresStmt->execute($params);
    $feeStructures = $feeStructuresStmt->fetchAll();
    
    // Get academic years for filter
    $yearsStmt = $pdo->query("SELECT DISTINCT academic_year FROM fee_structure ORDER BY academic_year DESC");
    $years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log('Fee structure page error: ' . $e->getMessage());
    $classes = [];
    $feeStructures = [];
    $years = [];
    $error = 'Database error occurred. Please try again.';
}

// Handle form submission for adding/editing fee structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee_structure'])) {
    try {
        $classId = (int)($_POST['class_id'] ?? 0);
        $feeType = trim($_POST['fee_type'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $frequency = $_POST['frequency'] ?? '';
        $academicYear = trim($_POST['academic_year'] ?? '');
        $feeId = (int)($_POST['fee_id'] ?? 0);
        
        // Validate inputs
        if (!$classId || !$feeType || $amount <= 0 || !$frequency || !$academicYear) {
            throw new Exception('Please fill all required fields with valid values');
        }
        
        if (!in_array($frequency, ['monthly', 'quarterly', 'half-yearly', 'yearly'])) {
            throw new Exception('Invalid frequency selected');
        }
        
        if ($feeId > 0) {
            // Update existing fee structure
            $updateStmt = $pdo->prepare("
                UPDATE fee_structure 
                SET class_id = ?, fee_type = ?, amount = ?, frequency = ?, academic_year = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$classId, $feeType, $amount, $frequency, $academicYear, $feeId]);
            $success = 'Fee structure updated successfully!';
        } else {
            // Insert new fee structure
            $insertStmt = $pdo->prepare("
                INSERT INTO fee_structure (class_id, fee_type, amount, frequency, academic_year)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$classId, $feeType, $amount, $frequency, $academicYear]);
            $success = 'Fee structure added successfully!';
        }
        
        // Refresh the page
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fee_structure'])) {
    try {
        $feeId = (int)($_POST['fee_id'] ?? 0);
        
        if (!$feeId) {
            throw new Exception('Invalid fee structure ID');
        }
        
        // Check if fee structure has associated payments
        $checkPayments = $pdo->prepare("SELECT COUNT(*) FROM fee_payments WHERE fee_structure_id = ?");
        $checkPayments->execute([$feeId]);
        $paymentCount = $checkPayments->fetchColumn();
        
        if ($paymentCount > 0) {
            throw new Exception('Cannot delete fee structure with existing payments');
        }
        
        $deleteStmt = $pdo->prepare("DELETE FROM fee_structure WHERE id = ?");
        $deleteStmt->execute([$feeId]);
        
        $success = 'Fee structure deleted successfully!';
        
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
            <i class="fas fa-cog"></i> Fee Structure Management
            <small class="text-muted">Define fee types and amounts for classes</small>
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

<!-- Add/Edit Fee Structure Form -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus"></i> Add Fee Structure
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="fee_id" id="fee_id" value="0">
                    <div class="row">
                        <div class="col-md-2">
                            <label for="class_id" class="form-label">Class</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="fee_type" class="form-label">Fee Type</label>
                            <input type="text" class="form-control" id="fee_type" name="fee_type" 
                                   placeholder="e.g., Tuition Fee, Lab Fee" required>
                        </div>
                        <div class="col-md-2">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <label for="frequency" class="form-label">Frequency</label>
                            <select class="form-select" id="frequency" name="frequency" required>
                                <option value="">Select Frequency</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="half-yearly">Half-Yearly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                   value="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" name="save_fee_structure" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter"></i> Filter Fee Structures
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="class_id" class="form-label">Class</label>
                            <select class="form-select" id="class_id" name="class_id">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" 
                                            <?php echo ($classFilter == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <select class="form-select" id="academic_year" name="academic_year">
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" 
                                            <?php echo ($yearFilter == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="<?php echo date('Y'); ?>" 
                                        <?php echo (!$yearFilter || $yearFilter == date('Y')) ? 'selected' : ''; ?>>
                                    <?php echo date('Y'); ?> (Current)
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="/accountant/fees.php" class="btn btn-success">
                                <i class="fas fa-money-bill-wave"></i> Manage Payments
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Fee Structures Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Fee Structures
                </h5>
                <button class="btn btn-sm btn-success" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <div class="card-body">
                <?php if (!empty($feeStructures)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Frequency</th>
                                    <th>Academic Year</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feeStructures as $fee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fee['class_name'] ?? 'All Classes'); ?></td>
                                        <td><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                                        <td>
                                            <span class="fw-bold text-success">
                                                <?php echo number_format($fee['amount'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($fee['frequency']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($fee['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" 
                                                    onclick="editFeeStructure(<?php echo $fee['id']; ?>, 
                                                                            '<?php echo htmlspecialchars($fee['class_name'] ?? ''); ?>', 
                                                                            '<?php echo htmlspecialchars($fee['fee_type']); ?>', 
                                                                            '<?php echo $fee['amount']; ?>', 
                                                                            '<?php echo $fee['frequency']; ?>', 
                                                                            '<?php echo htmlspecialchars($fee['academic_year']); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="fee_id" value="<?php echo $fee['id']; ?>">
                                                <input type="hidden" name="delete_fee_structure" value="1">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this fee structure?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No fee structures found matching the criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function editFeeStructure(id, className, feeType, amount, frequency, academicYear) {
    document.getElementById('fee_id').value = id;
    document.getElementById('class_id').value = className;
    document.getElementById('fee_type').value = feeType;
    document.getElementById('amount').value = amount;
    document.getElementById('frequency').value = frequency;
    document.getElementById('academic_year').value = academicYear;
    
    // Scroll to form
    document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
    
    // Focus on first field
    document.getElementById('class_id').focus();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
