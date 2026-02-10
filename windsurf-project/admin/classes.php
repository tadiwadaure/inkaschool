<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Class Management';

try {
    // Get all classes with student and teacher counts
    $classesQuery = "
        SELECT 
            c.*,
            COUNT(DISTINCT st.id) as student_count,
            COUNT(DISTINCT t.id) as teacher_count,
            COUNT(DISTINCT s.id) as subject_count
        FROM classes c
        LEFT JOIN students st ON c.id = st.class_id
        LEFT JOIN subjects s ON c.id = s.class_id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        GROUP BY c.id
        ORDER BY c.class_name
    ";
    
    $classesStmt = $pdo->query($classesQuery);
    $classes = $classesStmt->fetchAll();
    
    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_classes,
            COUNT(DISTINCT st.id) as total_students,
            COUNT(DISTINCT t.id) as total_teachers,
            COUNT(DISTINCT s.id) as total_subjects
        FROM classes c
        LEFT JOIN students st ON c.id = st.class_id
        LEFT JOIN subjects s ON c.id = s.class_id
        LEFT JOIN teachers t ON s.teacher_id = t.id
    ";
    
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log('Classes page error: ' . $e->getMessage());
    $classes = [];
    $stats = null;
    $error = 'Database error occurred. Please try again.';
}

// Handle class addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    try {
        $className = trim($_POST['class_name'] ?? '');
        $section = trim($_POST['section'] ?? '');
        
        if (!$className) {
            throw new Exception('Class name is required');
        }
        
        // Check if class already exists
        $checkStmt = $pdo->prepare("SELECT id FROM classes WHERE class_name = ? AND section = ?");
        $checkStmt->execute([$className, $section]);
        
        if ($checkStmt->fetch()) {
            throw new Exception('Class with this name and section already exists');
        }
        
        $insertStmt = $pdo->prepare("
            INSERT INTO classes (class_name, section) 
            VALUES (?, ?)
        ");
        $insertStmt->execute([$className, $section]);
        
        $success = 'Class added successfully!';
        
        // Refresh the page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle class deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    try {
        $classId = (int)($_POST['class_id'] ?? 0);
        
        if (!$classId) {
            throw new Exception('Invalid class ID');
        }
        
        // Check if class has students
        $checkStudents = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
        $checkStudents->execute([$classId]);
        $studentCount = $checkStudents->fetchColumn();
        
        if ($studentCount > 0) {
            throw new Exception('Cannot delete class with enrolled students');
        }
        
        // Delete the class
        $deleteStmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $deleteStmt->execute([$classId]);
        
        $success = 'Class deleted successfully!';
        
        // Refresh the page
        header("Location: " . $_SERVER['PHP_SELF']);
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
            <i class="fas fa-school"></i> Class Management
            <small class="text-muted">Manage academic classes and sections</small>
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
                            <div class="stats-number"><?php echo $stats['total_classes']; ?></div>
                            <div class="stats-label">Total Classes</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-school fa-2x opacity-75"></i>
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
            <div class="card stats-card text-white bg-info">
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
            <div class="card stats-card text-white bg-warning">
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
    </div>
<?php endif; ?>

<!-- Add Class Form -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus"></i> Add New Class
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="class_name" class="form-label">Class Name</label>
                            <input type="text" class="form-control" id="class_name" name="class_name" 
                                   placeholder="e.g., Grade 10, Class 8A" required>
                        </div>
                        <div class="col-md-4">
                            <label for="section" class="form-label">Section (Optional)</label>
                            <input type="text" class="form-control" id="section" name="section" 
                                   placeholder="e.g., A, B, Science">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="add_class" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Add Class
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Classes Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Classes List
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
                <?php if (!empty($classes)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="classesTable">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Section</th>
                                    <th>Students</th>
                                    <th>Teachers</th>
                                    <th>Subjects</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($class['section']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($class['section']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $class['student_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $class['teacher_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?php echo $class['subject_count']; ?></span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($class['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="viewClass(<?php echo $class['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="editClass(<?php echo $class['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if ($class['student_count'] == 0): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                        <input type="hidden" name="delete_class" value="1">
                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Delete this class?')"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary" disabled title="Cannot delete class with students">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No classes found. Start by adding a new class.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function viewClass(classId) {
    // Placeholder for view class functionality
    alert('View class details - ID: ' + classId);
}

function editClass(classId) {
    // Placeholder for edit class functionality
    alert('Edit class - ID: ' + classId);
}

function exportToCSV() {
    const table = document.getElementById('classesTable');
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
    a.download = 'classes.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
