<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Manage Exams';

// Handle form submissions
$message = '';
$messageType = '';

// Add new exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO exams (exam_name, academic_year, start_date, end_date, class_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['exam_name'],
            $_POST['academic_year'],
            $_POST['start_date'] ?? null,
            $_POST['end_date'] ?? null,
            $_POST['class_id'] ?? null
        ]);
        $message = 'Exam added successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error adding exam: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Edit exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $stmt = $pdo->prepare("
            UPDATE exams 
            SET exam_name = ?, academic_year = ?, start_date = ?, end_date = ?, class_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['exam_name'],
            $_POST['academic_year'],
            $_POST['start_date'] ?? null,
            $_POST['end_date'] ?? null,
            $_POST['class_id'] ?? null,
            $_POST['id']
        ]);
        $message = 'Exam updated successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error updating exam: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Delete exam
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $message = 'Exam deleted successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error deleting exam: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch data
try {
    // Get classes
    $classesStmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
    $classes = $classesStmt->fetchAll();
    
    // Get exams
    $examsStmt = $pdo->query("
        SELECT e.*, c.class_name 
        FROM exams e 
        LEFT JOIN classes c ON e.class_id = c.id 
        ORDER BY e.academic_year DESC, e.exam_name
    ");
    $exams = $examsStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Exams error: ' . $e->getMessage());
    $classes = $exams = [];
    $message = 'Error loading data: ' . $e->getMessage();
    $messageType = 'danger';
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-clipboard-list"></i> Manage Exams
            <small class="text-muted">Create and manage examinations</small>
        </h1>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Add New Exam -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus-circle"></i> Add New Exam
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exam_name" class="form-label">Exam Name</label>
                            <input type="text" class="form-control" id="exam_name" name="exam_name" 
                                placeholder="e.g., Final Examination, Mid-term Test" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                placeholder="e.g., 2023-2024" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="class_id" class="form-label">Class (Optional)</label>
                            <select class="form-select" id="class_id" name="class_id">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Exam
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Exams List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Exams List
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($exams)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Academic Year</th>
                                    <th>Class</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exams as $exam): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($exam['academic_year']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['class_name'] ?? 'All Classes'); ?></td>
                                        <td><?php echo $exam['start_date'] ? date('M j, Y', strtotime($exam['start_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $exam['end_date'] ? date('M j, Y', strtotime($exam['end_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editExam(<?php echo $exam['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?action=delete&id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this exam?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No exams found. Add your first exam above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="edit_exam_name" class="form-label">Exam Name</label>
                            <input type="text" class="form-control" id="edit_exam_name" name="exam_name" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="edit_academic_year" class="form-label">Academic Year</label>
                            <input type="text" class="form-control" id="edit_academic_year" name="academic_year" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="edit_start_date" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit_end_date" name="end_date">
                        </div>
                        <div class="col-12 mb-3">
                            <label for="edit_class_id" class="form-label">Class (Optional)</label>
                            <select class="form-select" id="edit_class_id" name="class_id">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editExam(id) {
    fetch('exams.php?action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_exam_name').value = data.exam_name;
            document.getElementById('edit_academic_year').value = data.academic_year;
            document.getElementById('edit_start_date').value = data.start_date || '';
            document.getElementById('edit_end_date').value = data.end_date || '';
            document.getElementById('edit_class_id').value = data.class_id || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching exam data');
        });
}

<?php
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exam) {
            header('Content-Type: application/json');
            echo json_encode($exam);
            exit;
        }
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
