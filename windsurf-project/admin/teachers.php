<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Teacher Management';

try {
    // Get filter parameters
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build query for teachers
    $whereConditions = [];
    $params = [];
    
    if ($status) {
        $whereConditions[] = "u.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.specialization LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get teachers with their assigned subjects count
    $teachersQuery = "
        SELECT t.*, u.*, COUNT(s.id) as assigned_subjects
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN subjects s ON t.id = s.teacher_id
        $whereClause
        GROUP BY t.id, u.id
        ORDER BY u.first_name, u.last_name
        LIMIT 50
    ";
    
    $teachersStmt = $pdo->prepare($teachersQuery);
    $teachersStmt->execute($params);
    $teachers = $teachersStmt->fetchAll();
    
    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_teachers,
            COUNT(CASE WHEN u.status = 'active' THEN 1 END) as active_teachers,
            COUNT(CASE WHEN u.status = 'inactive' THEN 1 END) as inactive_teachers,
            COUNT(CASE WHEN u.status = 'suspended' THEN 1 END) as suspended_teachers,
            COUNT(s.id) as total_subjects_assigned
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN subjects s ON t.id = s.teacher_id
    ";
    
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log('Teachers page error: ' . $e->getMessage());
    $teachers = [];
    $stats = null;
    $error = 'Database error occurred. Please try again.';
}

// Handle teacher status update
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
            WHERE id = ? AND role = 'teacher'
        ");
        $updateStmt->execute([$newStatus, $userId]);
        
        $success = 'Teacher status updated successfully!';
        
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
            <i class="fas fa-chalkboard-teacher"></i> Teacher Management
            <small class="text-muted">Manage teacher accounts and assignments</small>
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
                            <div class="stats-number"><?php echo $stats['total_teachers']; ?></div>
                            <div class="stats-label">Total Teachers</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chalkboard-teacher fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo $stats['active_teachers']; ?></div>
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
                            <div class="stats-number"><?php echo $stats['inactive_teachers']; ?></div>
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
            <div class="card stats-card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_subjects_assigned']; ?></div>
                            <div class="stats-label">Subjects Assigned</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-book fa-2x opacity-75"></i>
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
                    <i class="fas fa-filter"></i> Filter Teachers
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($status == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo ($status == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Name, email, or specialization">
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

<!-- Teachers Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Teachers List
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
                <?php if (!empty($teachers)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="teachersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Qualification</th>
                                    <th>Specialization</th>
                                    <th>Subjects Assigned</th>
                                    <th>Joining Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['qualification'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['specialization'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $teacher['assigned_subjects']; ?> subjects
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($teacher['joining_date'])); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = match($teacher['status']) {
                                                'active' => 'success',
                                                'inactive' => 'warning',
                                                'suspended' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($teacher['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="viewTeacher(<?php echo $teacher['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="editTeacher(<?php echo $teacher['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if ($teacher['status'] !== 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $teacher['user_id']; ?>">
                                                        <input type="hidden" name="status" value="active">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <button type="submit" class="btn btn-sm btn-success" 
                                                                title="Activate">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($teacher['status'] === 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $teacher['user_id']; ?>">
                                                        <input type="hidden" name="status" value="inactive">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <button type="submit" class="btn btn-sm btn-warning" 
                                                                title="Deactivate">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $teacher['user_id']; ?>">
                                                    <input type="hidden" name="status" value="suspended">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <button type="submit" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Suspend this teacher?')"
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
                        <i class="fas fa-info-circle"></i> No teachers found matching the criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function viewTeacher(teacherId) {
    // Placeholder for view teacher functionality
    alert('View teacher details - ID: ' + teacherId);
}

function editTeacher(teacherId) {
    // Placeholder for edit teacher functionality
    alert('Edit teacher - ID: ' + teacherId);
}

function exportToCSV() {
    const table = document.getElementById('teachersTable');
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
    a.download = 'teachers.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
