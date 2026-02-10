<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Subject Management';

try {
    // Get filter parameters
    $classId = $_GET['class_id'] ?? '';
    $teacherId = $_GET['teacher_id'] ?? '';
    
    // Get classes and teachers for filters
    $classesStmt = $pdo->query("SELECT * FROM classes ORDER BY class_name");
    $classes = $classesStmt->fetchAll();
    
    $teachersStmt = $pdo->query("
        SELECT t.*, u.first_name, u.last_name 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $teachers = $teachersStmt->fetchAll();
    
    // Build query for subjects
    $whereConditions = [];
    $params = [];
    
    if ($classId) {
        $whereConditions[] = "s.class_id = ?";
        $params[] = $classId;
    }
    
    if ($teacherId) {
        $whereConditions[] = "s.teacher_id = ?";
        $params[] = $teacherId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get subjects with class and teacher info
    $subjectsQuery = "
        SELECT s.*, c.class_name, t.id as teacher_id, 
               u.first_name as teacher_first, u.last_name as teacher_last
        FROM subjects s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        $whereClause
        ORDER BY c.class_name, s.subject_name
    ";
    
    $subjectsStmt = $pdo->prepare($subjectsQuery);
    $subjectsStmt->execute($params);
    $subjects = $subjectsStmt->fetchAll();
    
    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_subjects,
            COUNT(CASE WHEN s.teacher_id IS NOT NULL THEN 1 END) as assigned_subjects,
            COUNT(CASE WHEN s.teacher_id IS NULL THEN 1 END) as unassigned_subjects,
            COUNT(DISTINCT s.class_id) as classes_with_subjects
        FROM subjects s
    ";
    
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log('Subjects page error: ' . $e->getMessage());
    $classes = [];
    $teachers = [];
    $subjects = [];
    $stats = null;
    $error = 'Database error occurred. Please try again.';
}

// Handle subject addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    try {
        $subjectName = trim($_POST['subject_name'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0);
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        
        if (!$subjectName || !$subjectCode) {
            throw new Exception('Subject name and code are required');
        }
        
        // Check if subject code already exists
        $checkStmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ?");
        $checkStmt->execute([$subjectCode]);
        
        if ($checkStmt->fetch()) {
            throw new Exception('Subject code already exists');
        }
        
        $insertStmt = $pdo->prepare("
            INSERT INTO subjects (subject_name, subject_code, class_id, teacher_id) 
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->execute([$subjectName, $subjectCode, $classId ?: null, $teacherId ?: null]);
        
        $success = 'Subject added successfully!';
        
        // Refresh the page
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle subject deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
    try {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        
        if (!$subjectId) {
            throw new Exception('Invalid subject ID');
        }
        
        // Check if subject has results
        $checkResults = $pdo->prepare("SELECT COUNT(*) FROM results WHERE subject_id = ?");
        $checkResults->execute([$subjectId]);
        $resultCount = $checkResults->fetchColumn();
        
        if ($resultCount > 0) {
            throw new Exception('Cannot delete subject with existing results');
        }
        
        // Delete the subject
        $deleteStmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $deleteStmt->execute([$subjectId]);
        
        $success = 'Subject deleted successfully!';
        
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
            <i class="fas fa-book"></i> Subject Management
            <small class="text-muted">Manage academic subjects and assignments</small>
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
                            <div class="stats-number"><?php echo $stats['total_subjects']; ?></div>
                            <div class="stats-label">Total Subjects</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-book fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo $stats['assigned_subjects']; ?></div>
                            <div class="stats-label">Assigned</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-check fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo $stats['unassigned_subjects']; ?></div>
                            <div class="stats-label">Unassigned</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-times fa-2x opacity-75"></i>
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
                            <div class="stats-number"><?php echo $stats['classes_with_subjects']; ?></div>
                            <div class="stats-label">Active Classes</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-school fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add Subject Form -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus"></i> Add New Subject
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="subject_name" class="form-label">Subject Name</label>
                            <input type="text" class="form-control" id="subject_name" name="subject_name" 
                                   placeholder="e.g., Mathematics" required>
                        </div>
                        <div class="col-md-2">
                            <label for="subject_code" class="form-label">Subject Code</label>
                            <input type="text" class="form-control" id="subject_code" name="subject_code" 
                                   placeholder="e.g., MATH101" required>
                        </div>
                        <div class="col-md-3">
                            <label for="class_id" class="form-label">Class (Optional)</label>
                            <select class="form-select" id="class_id" name="class_id">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="teacher_id" class="form-label">Teacher (Optional)</label>
                            <select class="form-select" id="teacher_id" name="teacher_id">
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" name="add_subject" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Add
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
                    <i class="fas fa-filter"></i> Filter Subjects
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
                                            <?php echo ($classId == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="teacher_id" class="form-label">Teacher</label>
                            <select class="form-select" id="teacher_id" name="teacher_id">
                                <option value="">All Teachers</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" 
                                            <?php echo ($teacherId == $teacher['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="/admin/teachers.php" class="btn btn-success me-2">
                                <i class="fas fa-chalkboard-teacher"></i> Manage Teachers
                            </a>
                            <a href="/admin/classes.php" class="btn btn-info">
                                <i class="fas fa-school"></i> Manage Classes
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Subjects Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Subjects List
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
                <?php if (!empty($subjects)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="subjectsTable">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Subject Code</th>
                                    <th>Class</th>
                                    <th>Assigned Teacher</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($subject['subject_code']); ?></code>
                                        </td>
                                        <td>
                                            <?php if ($subject['class_name']): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($subject['class_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">General</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($subject['teacher_first']): ?>
                                                <span class="badge bg-success">
                                                    <?php echo htmlspecialchars($subject['teacher_first'] . ' ' . $subject['teacher_last']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($subject['teacher_first']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Needs Assignment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="viewSubject(<?php echo $subject['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="editSubject(<?php echo $subject['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <input type="hidden" name="delete_subject" value="1">
                                                    <button type="submit" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Delete this subject?')"
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
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
                        <i class="fas fa-info-circle"></i> No subjects found matching the criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function viewSubject(subjectId) {
    // Placeholder for view subject functionality
    alert('View subject details - ID: ' + subjectId);
}

function editSubject(subjectId) {
    // Placeholder for edit subject functionality
    alert('Edit subject - ID: ' + subjectId);
}

function exportToCSV() {
    const table = document.getElementById('subjectsTable');
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
    a.download = 'subjects.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
