<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require teacher role
$auth->requireRole('teacher');

// Set page title
$pageTitle = 'Add Student Results';

// Get teacher info and assigned subjects
try {
    $teacherStmt = $pdo->prepare("
        SELECT t.id as teacher_id 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.id = ?
    ");
    $teacherStmt->execute([$_SESSION['user_id']]);
    $teacher = $teacherStmt->fetch();
    
    if (!$teacher) {
        throw new Exception('Teacher not found');
    }
    
    // Get assigned subjects for this teacher
    $subjectsStmt = $pdo->prepare("
        SELECT s.*, c.class_name 
        FROM subjects s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.teacher_id = ?
        ORDER BY s.subject_name
    ");
    $subjectsStmt->execute([$teacher['teacher_id']]);
    $assignedSubjects = $subjectsStmt->fetchAll();
    
    // Get exams
    $examsStmt = $pdo->prepare("
        SELECT * FROM exams 
        ORDER BY academic_year DESC, exam_name
    ");
    $examsStmt->execute();
    $exams = $examsStmt->fetchAll();
    
    // Get students if subject is selected
    $students = [];
    if (isset($_GET['subject_id']) && is_numeric($_GET['subject_id'])) {
        $subjectId = (int)$_GET['subject_id'];
        
        // Verify this subject is assigned to the teacher
        $subjectCheck = $pdo->prepare("
            SELECT id FROM subjects 
            WHERE id = ? AND teacher_id = ?
        ");
        $subjectCheck->execute([$subjectId, $teacher['teacher_id']]);
        
        if ($subjectCheck->fetch()) {
            $studentsStmt = $pdo->prepare("
                SELECT st.id, st.roll_number, u.first_name, u.last_name, c.class_name
                FROM students st
                JOIN users u ON st.user_id = u.id
                LEFT JOIN classes c ON st.class_id = c.id
                LEFT JOIN subjects s ON st.class_id = s.class_id
                WHERE s.id = ? AND u.status = 'active'
                ORDER BY st.roll_number
            ");
            $studentsStmt->execute([$subjectId]);
            $students = $studentsStmt->fetchAll();
        }
    }
    
} catch (PDOException $e) {
    error_log('Results page error: ' . $e->getMessage());
    $assignedSubjects = [];
    $exams = [];
    $students = [];
    $error = 'Database error occurred. Please try again.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $examId = (int)($_POST['exam_id'] ?? 0);
        $results = $_POST['results'] ?? [];
        
        // Validate inputs
        if (!$subjectId || !$examId || empty($results)) {
            throw new Exception('Please select subject, exam and provide at least one result');
        }
        
        // Verify subject belongs to teacher
        $subjectCheck = $pdo->prepare("
            SELECT id FROM subjects 
            WHERE id = ? AND teacher_id = ?
        ");
        $subjectCheck->execute([$subjectId, $teacher['teacher_id']]);
        
        if (!$subjectCheck->fetch()) {
            throw new Exception('You are not authorized to add results for this subject');
        }
        
        // Insert results
        $insertStmt = $pdo->prepare("
            INSERT INTO results (student_id, exam_id, subject_id, marks_obtained, max_marks, grade, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            marks_obtained = VALUES(marks_obtained),
            max_marks = VALUES(max_marks),
            grade = VALUES(grade),
            remarks = VALUES(remarks),
            created_at = CURRENT_TIMESTAMP
        ");
        
        $successCount = 0;
        foreach ($results as $studentId => $result) {
            $marksObtained = (float)($result['marks_obtained'] ?? 0);
            $maxMarks = (float)($result['max_marks'] ?? 0);
            $grade = trim($result['grade'] ?? '');
            $remarks = trim($result['remarks'] ?? '');
            
            // Only insert if marks are provided
            if ($marksObtained >= 0 && $maxMarks > 0) {
                $insertStmt->execute([
                    $studentId,
                    $examId,
                    $subjectId,
                    $marksObtained,
                    $maxMarks,
                    $grade ?: null,
                    $remarks ?: null
                ]);
                $successCount++;
            }
        }
        
        if ($successCount > 0) {
            $success = "Successfully added/updated $successCount student results.";
        } else {
            $error = "No valid results were provided.";
        }
        
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
            <i class="fas fa-plus-circle"></i> Add Student Results
            <small class="text-muted">Enter marks for your assigned subjects</small>
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

<!-- Subject and Exam Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter"></i> Select Subject and Exam
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">Subject</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select a subject</option>
                                <?php foreach ($assignedSubjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" 
                                            <?php echo (isset($_GET['subject_id']) && $_GET['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        <?php echo $subject['class_name'] ? ' (' . htmlspecialchars($subject['class_name']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="exam_id" class="form-label">Exam</label>
                            <select class="form-select" id="exam_id" name="exam_id" required>
                                <option value="">Select an exam</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>">
                                        <?php echo htmlspecialchars($exam['exam_name']); ?> 
                                        (<?php echo htmlspecialchars($exam['academic_year']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Load Students
                            </button>
                            <a href="/teacher/dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($students) && isset($_GET['subject_id']) && isset($_GET['exam_id'])): ?>
<!-- Results Entry Form -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-edit"></i> Enter Results
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="subject_id" value="<?php echo (int)$_GET['subject_id']; ?>">
                    <input type="hidden" name="exam_id" value="<?php echo (int)$_GET['exam_id']; ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Roll No</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Marks Obtained</th>
                                    <th>Max Marks</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   name="results[<?php echo $student['id']; ?>][marks_obtained]" 
                                                   min="0" 
                                                   step="0.01" 
                                                   placeholder="0">
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   name="results[<?php echo $student['id']; ?>][max_marks]" 
                                                   min="1" 
                                                   step="0.01" 
                                                   placeholder="100">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   class="form-control form-control-sm" 
                                                   name="results[<?php echo $student['id']; ?>][grade]" 
                                                   maxlength="5" 
                                                   placeholder="A+">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   class="form-control form-control-sm" 
                                                   name="results[<?php echo $student['id']; ?>][remarks]" 
                                                   placeholder="Optional remarks">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Results
                            </button>
                            <a href="/teacher/dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif (isset($_GET['subject_id']) && isset($_GET['exam_id'])): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No students found for the selected subject.
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
