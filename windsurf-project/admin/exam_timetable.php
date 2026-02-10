<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Exam Timetable Management';

// Handle form submissions
$message = '';
$messageType = '';

// Add new timetable entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO exam_timetables (exam_id, subject_id, exam_date, start_time, end_time, venue, instructions)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['exam_id'],
            $_POST['subject_id'],
            $_POST['exam_date'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['venue'] ?? null,
            $_POST['instructions'] ?? null
        ]);
        $message = 'Exam timetable entry added successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error adding timetable entry: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Edit timetable entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $stmt = $pdo->prepare("
            UPDATE exam_timetables 
            SET exam_id = ?, subject_id = ?, exam_date = ?, start_time = ?, end_time = ?, venue = ?, instructions = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['exam_id'],
            $_POST['subject_id'],
            $_POST['exam_date'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['venue'] ?? null,
            $_POST['instructions'] ?? null,
            $_POST['id']
        ]);
        $message = 'Exam timetable entry updated successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error updating timetable entry: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Delete timetable entry
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM exam_timetables WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $message = 'Exam timetable entry deleted successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error deleting timetable entry: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch data for dropdowns and display
try {
    // Get exams
    $examsStmt = $pdo->query("SELECT id, exam_name, academic_year FROM exams ORDER BY academic_year DESC, exam_name");
    $exams = $examsStmt->fetchAll();
    
    // Get subjects
    $subjectsStmt = $pdo->query("
        SELECT s.id, s.subject_name, s.subject_code, c.class_name 
        FROM subjects s 
        LEFT JOIN classes c ON s.class_id = c.id 
        ORDER BY c.class_name, s.subject_name
    ");
    $subjects = $subjectsStmt->fetchAll();
    
    // Get timetable entries
    $timetableStmt = $pdo->query("
        SELECT et.*, e.exam_name, e.academic_year, s.subject_name, s.subject_code, c.class_name
        FROM exam_timetables et
        JOIN exams e ON et.exam_id = e.id
        JOIN subjects s ON et.subject_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        ORDER BY e.academic_year DESC, e.exam_name, et.exam_date, et.start_time
    ");
    $timetables = $timetableStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Exam timetable error: ' . $e->getMessage());
    $exams = $subjects = $timetables = [];
    $message = 'Error loading data: ' . $e->getMessage();
    $messageType = 'danger';
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-calendar-alt"></i> Exam Timetable Management
            <small class="text-muted">Manage exam schedules</small>
        </h1>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Add New Timetable Entry -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus-circle"></i> Add New Timetable Entry
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exam_id" class="form-label">Exam</label>
                            <select class="form-select" id="exam_id" name="exam_id" required>
                                <option value="">Select Exam</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>">
                                        <?php echo htmlspecialchars($exam['exam_name'] . ' - ' . $exam['academic_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="subject_id" class="form-label">Subject</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')' . 
                                            ($subject['class_name'] ? ' - ' . $subject['class_name'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="exam_date" class="form-label">Exam Date</label>
                            <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="venue" class="form-label">Venue</label>
                            <input type="text" class="form-control" id="venue" name="venue" placeholder="e.g., Main Hall, Room 101">
                        </div>
                        <div class="col-12 mb-3">
                            <label for="instructions" class="form-label">Instructions</label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="2" 
                                placeholder="Special instructions for students (e.g., Bring calculator, No extra sheets)"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Timetable Entry
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Timetable Entries -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Exam Timetable Entries
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($timetables)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Exam</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Venue</th>
                                    <th>Instructions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timetables as $timetable): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($timetable['exam_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($timetable['academic_year']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($timetable['subject_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($timetable['subject_code']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($timetable['class_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($timetable['exam_date'])); ?></td>
                                        <td>
                                            <?php echo date('h:i A', strtotime($timetable['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($timetable['end_time'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($timetable['venue'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($timetable['instructions']): ?>
                                                <small><?php echo htmlspecialchars(substr($timetable['instructions'], 0, 50)); ?>...</small>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editTimetable(<?php echo $timetable['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?action=delete&id=<?php echo $timetable['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this timetable entry?')">
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
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No timetable entries found. Add your first timetable entry above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Timetable Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_exam_id" class="form-label">Exam</label>
                            <select class="form-select" id="edit_exam_id" name="exam_id" required>
                                <option value="">Select Exam</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>">
                                        <?php echo htmlspecialchars($exam['exam_name'] . ' - ' . $exam['academic_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_subject_id" class="form-label">Subject</label>
                            <select class="form-select" id="edit_subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')' . 
                                            ($subject['class_name'] ? ' - ' . $subject['class_name'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_exam_date" class="form-label">Exam Date</label>
                            <input type="date" class="form-control" id="edit_exam_date" name="exam_date" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="edit_start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="edit_end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_venue" class="form-label">Venue</label>
                            <input type="text" class="form-control" id="edit_venue" name="venue">
                        </div>
                        <div class="col-12 mb-3">
                            <label for="edit_instructions" class="form-label">Instructions</label>
                            <textarea class="form-control" id="edit_instructions" name="instructions" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Timetable Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTimetable(id) {
    // Fetch timetable data and populate modal
    fetch('exam_timetable.php?action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_exam_id').value = data.exam_id;
            document.getElementById('edit_subject_id').value = data.subject_id;
            document.getElementById('edit_exam_date').value = data.exam_date;
            document.getElementById('edit_start_time').value = data.start_time;
            document.getElementById('edit_end_time').value = data.end_time;
            document.getElementById('edit_venue').value = data.venue || '';
            document.getElementById('edit_instructions').value = data.instructions || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching timetable data');
        });
}

// Handle AJAX request for getting timetable data
<?php
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM exam_timetables WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $timetable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($timetable) {
            header('Content-Type: application/json');
            echo json_encode($timetable);
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
