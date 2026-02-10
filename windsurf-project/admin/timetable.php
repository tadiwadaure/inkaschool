<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Timetable Management';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_timetable'])) {
            // Add new timetable entry
            $stmt = $pdo->prepare("
                INSERT INTO teaching_timetable 
                (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room, academic_year, term) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['class_id'],
                $_POST['subject_id'],
                $_POST['teacher_id'],
                $_POST['day_of_week'],
                $_POST['start_time'],
                $_POST['end_time'],
                $_POST['room'] ?? null,
                $_POST['academic_year'],
                $_POST['term']
            ]);
            
            // Sync with student timetables
            syncStudentTimetables($pdo, $pdo->lastInsertId());
            
            $message = 'Timetable entry added successfully!';
            $messageType = 'success';
            
        } elseif (isset($_POST['update_timetable'])) {
            // Update timetable entry
            $stmt = $pdo->prepare("
                UPDATE teaching_timetable 
                SET class_id = ?, subject_id = ?, teacher_id = ?, day_of_week = ?, 
                    start_time = ?, end_time = ?, room = ?, academic_year = ?, term = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['class_id'],
                $_POST['subject_id'],
                $_POST['teacher_id'],
                $_POST['day_of_week'],
                $_POST['start_time'],
                $_POST['end_time'],
                $_POST['room'] ?? null,
                $_POST['academic_year'],
                $_POST['term'],
                $_POST['id']
            ]);
            
            // Sync student timetables
            syncStudentTimetables($pdo, $_POST['id']);
            
            $message = 'Timetable entry updated successfully!';
            $messageType = 'success';
            
        } elseif (isset($_POST['delete_timetable'])) {
            // Delete timetable entry
            $stmt = $pdo->prepare("DELETE FROM teaching_timetable WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            // Remove from student timetables
            $stmt = $pdo->prepare("DELETE FROM student_timetable WHERE teaching_timetable_id = ?");
            $stmt->execute([$_POST['id']]);
            
            $message = 'Timetable entry deleted successfully!';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get data for dropdowns
$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name")->fetchAll();
$subjects = $pdo->query("
    SELECT s.id, s.subject_name, s.subject_code, c.class_name 
    FROM subjects s 
    LEFT JOIN classes c ON s.class_id = c.id 
    ORDER BY s.subject_name
")->fetchAll();
$teachers = $pdo->query("
    SELECT t.id, u.first_name, u.last_name, t.specialization 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.first_name, u.last_name
")->fetchAll();

// Get current academic year and term
$currentAcademicYear = date('Y') . '-' . (date('Y') + 1);
$currentTerm = 'Term 1';

// Get timetable entries
$stmt = $pdo->prepare("
    SELECT tt.*, c.class_name, s.subject_name, s.subject_code,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM teaching_timetable tt
    JOIN classes c ON tt.class_id = c.id
    JOIN subjects s ON tt.subject_id = s.id
    JOIN teachers t ON tt.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE tt.academic_year = ? AND tt.term = ?
    ORDER BY tt.day_of_week, tt.start_time
");
$stmt->execute([$currentAcademicYear, $currentTerm]);
$timetableEntries = $stmt->fetchAll();

// Function to sync student timetables
function syncStudentTimetables($pdo, $teachingTimetableId) {
    // Get the teaching timetable entry
    $stmt = $pdo->prepare("SELECT * FROM teaching_timetable WHERE id = ?");
    $stmt->execute([$teachingTimetableId]);
    $teachingTimetable = $stmt->fetch();
    
    if (!$teachingTimetable) return;
    
    // Get all students in the class
    $stmt = $pdo->prepare("SELECT id FROM students WHERE class_id = ?");
    $stmt->execute([$teachingTimetable['class_id']]);
    $students = $stmt->fetchAll();
    
    // Add to student timetables
    foreach ($students as $student) {
        // Check if already exists
        $stmt = $pdo->prepare("
            SELECT id FROM student_timetable 
            WHERE student_id = ? AND teaching_timetable_id = ?
        ");
        $stmt->execute([$student['id'], $teachingTimetableId]);
        
        if (!$stmt->fetch()) {
            // Add to student timetable
            $stmt = $pdo->prepare("
                INSERT INTO student_timetable (student_id, teaching_timetable_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$student['id'], $teachingTimetableId]);
        }
        
        // Auto-enroll student in subject if not already enrolled
        $stmt = $pdo->prepare("
            SELECT id FROM student_subject_enrollment 
            WHERE student_id = ? AND subject_id = ? AND academic_year = ? AND term = ?
        ");
        $stmt->execute([
            $student['id'], 
            $teachingTimetable['subject_id'], 
            $teachingTimetable['academic_year'], 
            $teachingTimetable['term']
        ]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO student_subject_enrollment 
                (student_id, subject_id, academic_year, term)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $student['id'], 
                $teachingTimetable['subject_id'], 
                $teachingTimetable['academic_year'], 
                $teachingTimetable['term']
            ]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-calendar-alt me-2"></i>Timetable Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTimetableModal">
                        <i class="fas fa-plus me-2"></i>Add Timetable Entry
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Timetable Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Teaching Timetable - <?php echo $currentAcademicYear; ?> - <?php echo $currentTerm; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Time</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Room</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($timetableEntries)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No timetable entries found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($timetableEntries as $entry): ?>
                                            <tr>
                                                <td><?php echo $entry['day_of_week']; ?></td>
                                                <td><?php echo $entry['start_time']; ?> - <?php echo $entry['end_time']; ?></td>
                                                <td><?php echo $entry['class_name']; ?></td>
                                                <td><?php echo $entry['subject_name']; ?> (<?php echo $entry['subject_code']; ?>)</td>
                                                <td><?php echo $entry['teacher_name']; ?></td>
                                                <td><?php echo $entry['room'] ?? 'N/A'; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary edit-timetable" 
                                                            data-id="<?php echo $entry['id']; ?>"
                                                            data-class="<?php echo $entry['class_id']; ?>"
                                                            data-subject="<?php echo $entry['subject_id']; ?>"
                                                            data-teacher="<?php echo $entry['teacher_id']; ?>"
                                                            data-day="<?php echo $entry['day_of_week']; ?>"
                                                            data-start="<?php echo $entry['start_time']; ?>"
                                                            data-end="<?php echo $entry['end_time']; ?>"
                                                            data-room="<?php echo $entry['room']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                                        <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                                        <button type="submit" name="delete_timetable" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Timetable Modal -->
    <div class="modal fade" id="addTimetableModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Timetable Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class</label>
                                <select name="class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['class_name']; ?>
                                            <?php echo $class['section'] ? ' - ' . $class['section'] : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject</label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo $subject['subject_name']; ?>
                                            (<?php echo $subject['subject_code']; ?>)
                                            <?php echo $subject['class_name'] ? ' - ' . $subject['class_name'] : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teacher</label>
                                <select name="teacher_id" class="form-select" required>
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                                            <?php echo $teacher['specialization'] ? ' - ' . $teacher['specialization'] : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Day of Week</label>
                                <select name="day_of_week" class="form-select" required>
                                    <option value="">Select Day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Room</label>
                                <input type="text" name="room" class="form-control" placeholder="Room number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Year</label>
                                <input type="text" name="academic_year" class="form-control" 
                                       value="<?php echo $currentAcademicYear; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Term</label>
                                <select name="term" class="form-select" required>
                                    <option value="Term 1" <?php echo $currentTerm === 'Term 1' ? 'selected' : ''; ?>>Term 1</option>
                                    <option value="Term 2">Term 2</option>
                                    <option value="Term 3">Term 3</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_timetable" class="btn btn-primary">Add Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Timetable Modal (populated via JavaScript) -->
    <div class="modal fade" id="editTimetableModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Timetable Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class</label>
                                <select name="class_id" id="edit_class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['class_name']; ?>
                                            <?php echo $class['section'] ? ' - ' . $class['section'] : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject</label>
                                <select name="subject_id" id="edit_subject_id" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo $subject['subject_name']; ?>
                                            (<?php echo $subject['subject_code']; ?>)
                                            <?php echo $subject['class_name'] ? ' - ' . $subject['class_name'] : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teacher</label>
                                <select name="teacher_id" id="edit_teacher_id" class="form-select" required>
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                                            <?php echo $teacher['specialization'] ? ' - ' . $teacher['specialization'] : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Day of Week</label>
                                <select name="day_of_week" id="edit_day_of_week" class="form-select" required>
                                    <option value="">Select Day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="start_time" id="edit_start_time" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" id="edit_end_time" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Room</label>
                                <input type="text" name="room" id="edit_room" class="form-control" placeholder="Room number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Year</label>
                                <input type="text" name="academic_year" id="edit_academic_year" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Term</label>
                                <select name="term" id="edit_term" class="form-select" required>
                                    <option value="Term 1">Term 1</option>
                                    <option value="Term 2">Term 2</option>
                                    <option value="Term 3">Term 3</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_timetable" class="btn btn-primary">Update Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit button clicks
        document.querySelectorAll('.edit-timetable').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_id').value = this.dataset.id;
                document.getElementById('edit_class_id').value = this.dataset.class;
                document.getElementById('edit_subject_id').value = this.dataset.subject;
                document.getElementById('edit_teacher_id').value = this.dataset.teacher;
                document.getElementById('edit_day_of_week').value = this.dataset.day;
                document.getElementById('edit_start_time').value = this.dataset.start;
                document.getElementById('edit_end_time').value = this.dataset.end;
                document.getElementById('edit_room').value = this.dataset.room;
                
                new bootstrap.Modal(document.getElementById('editTimetableModal')).show();
            });
        });
    </script>
</body>
</html>
