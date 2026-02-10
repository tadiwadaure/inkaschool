<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Student Management';

try {
    // Get filter parameters
    $classId = $_GET['class_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Get classes for filter
    $classesStmt = $pdo->query("SELECT * FROM classes ORDER BY class_name");
    $classes = $classesStmt->fetchAll();
    
    // Build query for students
    $whereConditions = [];
    $params = [];
    
    if ($classId) {
        $whereConditions[] = "st.class_id = ?";
        $params[] = $classId;
    }
    
    if ($status) {
        $whereConditions[] = "u.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR st.roll_number LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get students
    $studentsQuery = "
        SELECT st.*, u.*, c.class_name
        FROM students st
        JOIN users u ON st.user_id = u.id
        LEFT JOIN classes c ON st.class_id = c.id
        $whereClause
        ORDER BY c.class_name, st.roll_number
        LIMIT 50
    ";
    
    $studentsStmt = $pdo->prepare($studentsQuery);
    $studentsStmt->execute($params);
    $students = $studentsStmt->fetchAll();
    
    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_students,
            COUNT(CASE WHEN u.status = 'active' THEN 1 END) as active_students,
            COUNT(CASE WHEN u.status = 'inactive' THEN 1 END) as inactive_students,
            COUNT(CASE WHEN u.status = 'suspended' THEN 1 END) as suspended_students
        FROM students st
        JOIN users u ON st.user_id = u.id
    ";
    
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log('Students page error: ' . $e->getMessage());
    $classes = [];
    $students = [];
    $stats = null;
    $error = 'Database error occurred. Please try again.';
}

// Handle student status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        
        if (!$userId || !in_array($newStatus, ['active', 'inactive', 'suspended'])) {
            throw new Exception('Invalid data provided');
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET status = ? 
            WHERE id = ? AND role = 'student'
        ");
        $updateStmt->execute([$newStatus, $userId]);
        
        $success = 'Student status updated successfully!';
        
        // Refresh the page
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
            <i class="fas fa-users"></i> Student Management
            <small class="text-muted">Manage student records and accounts</small>
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
                            <div class="stats-number"><?php echo $stats['total_students']; ?></div>
                            <div class="stats-label">Total Students</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stats-number"><?php echo $stats['active_students']; ?></div>
                            <div class="stats-label">Active</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo $stats['inactive_students']; ?></div>
                            <div class="stats-label">Inactive</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-pause-circle fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo $stats['suspended_students']; ?></div>
                            <div class="stats-label">Suspended</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-ban fa-2x opacity-75"></i>
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
                    <i class="fas fa-filter"></i> Filter Students
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
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($status == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo ($status == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Name, roll number, or email">
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

<!-- Students Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Students List
                </h5>
                <div>
                    <button class="btn btn-sm btn-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-sm btn-info" onclick="exportToCSV()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($students)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Roll No</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Class</th>
                                    <th>Admission Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($student['admission_date'])); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = match($student['status']) {
                                                'active' => 'success',
                                                'inactive' => 'warning',
                                                'suspended' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($student['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="viewStudent(<?php echo $student['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="editStudent(<?php echo $student['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if ($student['status'] !== 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                                        <input type="hidden" name="status" value="active">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <button type="submit" class="btn btn-sm btn-success" 
                                                                title="Activate">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($student['status'] === 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                                        <input type="hidden" name="status" value="inactive">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <button type="submit" class="btn btn-sm btn-warning" 
                                                                title="Deactivate">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                                    <input type="hidden" name="status" value="suspended">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <button type="submit" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Suspend this student?')"
                                                            title="Suspend">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No students found matching the criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function viewStudent(studentId) {
    // Placeholder for view student functionality
    alert('View student details - ID: ' + studentId);
}

function editStudent(studentId) {
    // Placeholder for edit student functionality
    alert('Edit student - ID: ' + studentId);
}

function exportToCSV() {
    const table = document.getElementById('studentsTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Add headers
    const headers = [];
    rows[0].querySelectorAll('th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Add data rows
    for (let i = 1; i < rows.length; i++) {
        const row = [];
        rows[i].querySelectorAll('td').forEach(td => {
            row.push('"' + td.textContent.trim() + '"');
        });
        csv.push(row.join(','));
    }
    
    // Download CSV
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'students.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
