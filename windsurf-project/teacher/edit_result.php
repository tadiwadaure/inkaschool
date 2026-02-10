<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/functions.php';

// Require teacher role
$auth->requireRole('teacher');

// Set page title
$pageTitle = 'Edit Student Result';

// Get result ID
$resultId = $_GET['id'] ?? 0;
if (!$resultId || !is_numeric($resultId)) {
    $_SESSION['error'] = 'Invalid result ID';
    header('Location: /teacher/view_results.php');
    exit;
}

$resultId = (int)$resultId;

// Get teacher info
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
    
    // Get result details and verify ownership
    $resultStmt = $pdo->prepare("
        SELECT 
            r.id,
            r.marks_obtained,
            r.max_marks,
            r.grade,
            r.remarks,
            r.student_id,
            r.exam_id,
            r.subject_id,
            st.roll_number,
            u.first_name,
            u.last_name,
            c.class_name,
            s.subject_name,
            e.exam_name,
            e.academic_year
        FROM results r
        JOIN students st ON r.student_id = st.id
        JOIN users u ON st.user_id = u.id
        LEFT JOIN classes c ON st.class_id = c.id
        JOIN subjects s ON r.subject_id = s.id
        JOIN exams e ON r.exam_id = e.id
        WHERE r.id = ? AND s.teacher_id = ?
    ");
    $resultStmt->execute([$resultId, $teacher['teacher_id']]);
    $result = $resultStmt->fetch();
    
    if (!$result) {
        throw new Exception('Result not found or you are not authorized to edit this result');
    }
    
} catch (PDOException $e) {
    error_log('Edit result page error: ' . $e->getMessage());
    $error = 'Database error occurred. Please try again.';
    $result = null;
} catch (Exception $e) {
    $error = $e->getMessage();
    $result = null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $result) {
    try {
        $marksObtained = (float)($_POST['marks_obtained'] ?? 0);
        $maxMarks = (float)($_POST['max_marks'] ?? 0);
        $grade = trim($_POST['grade'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Validate inputs
        if ($marksObtained < 0 || $marksObtained > 100 || $maxMarks <= 0 || $maxMarks > 100) {
            throw new Exception('Marks must be between 0 and 100');
        }
        
        if ($marksObtained > $maxMarks) {
            throw new Exception('Marks obtained cannot be greater than maximum marks');
        }
        
        // Auto-calculate grade if not provided
        if (empty($grade)) {
            $percentage = round(($marksObtained / $maxMarks) * 100, 2);
            $grade = calculate_grade($percentage);
        }
        
        // Update result
        $updateStmt = $pdo->prepare("
            UPDATE results 
            SET marks_obtained = ?, max_marks = ?, grade = ?, remarks = ?, created_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $updateStmt->execute([$marksObtained, $maxMarks, $grade ?: null, $remarks ?: null, $resultId]);
        
        $_SESSION['success'] = 'Result updated successfully.';
        header('Location: /teacher/view_results.php?subject_id=' . $result['subject_id'] . '&exam_id=' . $result['exam_id']);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Include header
render_page_header($pageTitle);
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-edit"></i> Edit Student Result
            <small class="text-muted">Modify student performance record</small>
        </h1>
    </div>
</div>

<?php if (!$result): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> 
        <?php echo htmlspecialchars($error ?? 'Result not found'); ?>
        <div class="mt-3">
            <a href="/teacher/view_results.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Results
            </a>
        </div>
    </div>
<?php else: ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Student Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Student:</strong><br>
                            <?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Roll Number:</strong><br>
                            <?php echo htmlspecialchars($result['roll_number']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Class:</strong><br>
                            <?php echo htmlspecialchars($result['class_name'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Subject:</strong><br>
                            <?php echo htmlspecialchars($result['subject_name']); ?>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <strong>Exam:</strong><br>
                            <?php echo htmlspecialchars($result['exam_name']); ?> (<?php echo htmlspecialchars($result['academic_year']); ?>)
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-edit"></i> Edit Result Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="editResultForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="marks_obtained" class="form-label">
                                        <i class="fas fa-chart-line"></i> Marks Obtained
                                    </label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="marks_obtained" 
                                           name="marks_obtained" 
                                           value="<?php echo htmlspecialchars($result['marks_obtained']); ?>"
                                           min="0" 
                                           max="100"
                                           step="0.01" 
                                           required>
                                    <div class="form-text">Enter the marks the student obtained (0-100)</div>
                                    <div class="invalid-feedback">
                                        Marks must be between 0 and 100
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_marks" class="form-label">
                                        <i class="fas fa-trophy"></i> Maximum Marks
                                    </label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="max_marks" 
                                           name="max_marks" 
                                           value="<?php echo htmlspecialchars($result['max_marks']); ?>"
                                           min="1" 
                                           max="100"
                                           step="0.01" 
                                           required>
                                    <div class="form-text">Total possible marks for this exam (1-100)</div>
                                    <div class="invalid-feedback">
                                        Maximum marks must be between 1 and 100
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="grade" class="form-label">
                                        <i class="fas fa-medal"></i> Grade
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="grade" 
                                           name="grade" 
                                           value="<?php echo htmlspecialchars($result['grade'] ?? ''); ?>"
                                           maxlength="5" 
                                           placeholder="A+, A, B+, etc.">
                                    <div class="form-text">Optional - will be auto-calculated if left empty</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="percentage_display" class="form-label">
                                        <i class="fas fa-percentage"></i> Percentage
                                    </label>
                                    <div class="form-control-plaintext">
                                        <span id="percentage_display" class="fs-5 fw-bold">0%</span>
                                        <span id="grade_badge" class="badge bg-secondary ms-2">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">
                                        <i class="fas fa-comment"></i> Remarks
                                    </label>
                                    <textarea class="form-control" 
                                              id="remarks" 
                                              name="remarks" 
                                              rows="3"
                                              placeholder="Optional remarks about the student's performance"><?php echo htmlspecialchars($result['remarks'] ?? ''); ?></textarea>
                                    <div class="form-text">Any additional comments about the student's performance</div>
                                </div>
                            </div>
                        </div>
                        
                                                
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success me-2">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="/teacher/view_results.php?subject_id=<?php echo $result['subject_id']; ?>&exam_id=<?php echo $result['exam_id']; ?>" 
                                   class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($result): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const marksObtainedInput = document.getElementById('marks_obtained');
    const maxMarksInput = document.getElementById('max_marks');
    const gradeInput = document.getElementById('grade');
    const percentageDisplay = document.getElementById('percentage_display');
    const gradeBadge = document.getElementById('grade_badge');
    
    function updatePercentage() {
        const marksObtained = parseFloat(marksObtainedInput.value) || 0;
        const maxMarks = parseFloat(maxMarksInput.value) || 1;
        
        // Validate ranges
        let hasError = false;
        
        if (marksObtained < 0 || marksObtained > 100) {
            marksObtainedInput.classList.add('is-invalid');
            marksObtainedInput.classList.remove('is-valid');
            hasError = true;
        } else {
            marksObtainedInput.classList.remove('is-invalid');
            marksObtainedInput.classList.add('is-valid');
        }
        
        if (maxMarks <= 0 || maxMarks > 100) {
            maxMarksInput.classList.add('is-invalid');
            maxMarksInput.classList.remove('is-valid');
            hasError = true;
        } else {
            maxMarksInput.classList.remove('is-invalid');
            maxMarksInput.classList.add('is-valid');
        }
        
        // Check if marks obtained exceeds max marks
        if (marksObtained > maxMarks && !hasError) {
            marksObtainedInput.classList.add('is-invalid');
            maxMarksInput.classList.add('is-invalid');
            hasError = true;
        }
        
        if (maxMarks > 0 && !hasError) {
            const percentage = Math.round((marksObtained / maxMarks) * 100 * 100) / 100;
            percentageDisplay.textContent = percentage + '%';
            
            // Update color based on percentage
            percentageDisplay.className = 'fs-5 fw-bold ';
            if (percentage >= 80) {
                percentageDisplay.className += 'text-success';
            } else if (percentage >= 70) {
                percentageDisplay.className += 'text-primary';
            } else if (percentage >= 60) {
                percentageDisplay.className += 'text-info';
            } else if (percentage >= 50) {
                percentageDisplay.className += 'text-warning';
            } else {
                percentageDisplay.className += 'text-danger';
            }
            
            // Auto-calculate grade if empty
            if (!gradeInput.value.trim()) {
                let grade = '';
                if (percentage >= 90) grade = 'A*';
                else if (percentage >= 80) grade = 'A';
                else if (percentage >= 70) grade = 'B';
                else if (percentage >= 60) grade = 'C';
                else if (percentage >= 50) grade = 'D';
                else if (percentage >= 40) grade = 'E';
                else if (percentage >= 30) grade = 'F';
                else grade = 'U';
                
                gradeInput.value = grade;
                updateGradeBadge(grade);
            }
        }
    }
    
    function updateGradeBadge(grade) {
        gradeBadge.textContent = grade || '-';
        gradeBadge.className = 'badge ms-2 ';
        
        switch(grade.toUpperCase()) {
            case 'A*':
            case 'A':
                gradeBadge.className += 'bg-success';
                break;
            case 'B':
                gradeBadge.className += 'bg-primary';
                break;
            case 'C':
                gradeBadge.className += 'bg-info';
                break;
            case 'D':
                gradeBadge.className += 'bg-warning';
                break;
            case 'E':
                gradeBadge.className += 'bg-secondary';
                break;
            case 'F':
                gradeBadge.className += 'bg-danger';
                break;
            case 'U':
                gradeBadge.className += 'bg-dark';
                break;
            default:
                gradeBadge.className += 'bg-secondary';
        }
    }
    
    // Event listeners
    marksObtainedInput.addEventListener('input', updatePercentage);
    maxMarksInput.addEventListener('input', updatePercentage);
    gradeInput.addEventListener('input', function() {
        updateGradeBadge(this.value);
    });
    
    // Initial calculation
    updatePercentage();
    updateGradeBadge(gradeInput.value);
    
    // Form validation
    document.getElementById('editResultForm').addEventListener('submit', function(e) {
        const marksObtained = parseFloat(marksObtainedInput.value) || 0;
        const maxMarks = parseFloat(maxMarksInput.value) || 0;
        
        if (marksObtained < 0) {
            e.preventDefault();
            alert('Marks obtained cannot be negative');
            marksObtainedInput.focus();
            return;
        }
        
        if (maxMarks <= 0) {
            e.preventDefault();
            alert('Maximum marks must be greater than 0');
            maxMarksInput.focus();
            return;
        }
        
        if (marksObtained > maxMarks) {
            e.preventDefault();
            alert('Marks obtained cannot be greater than maximum marks');
            marksObtainedInput.focus();
            return;
        }
    });
});
</script>
<?php endif; ?>

<?php render_page_footer(); ?>
