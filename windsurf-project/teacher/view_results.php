<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require teacher role
$auth->requireRole('teacher');

// Set page title
$pageTitle = 'View Student Results';

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
    
    // Get filter parameters
    $selectedSubject = $_GET['subject_id'] ?? '';
    $selectedExam = $_GET['exam_id'] ?? '';
    
    // Get results if filters are applied
    $results = [];
    if ($selectedSubject && $selectedExam) {
        // Verify subject belongs to teacher
        $subjectCheck = $pdo->prepare("
            SELECT id FROM subjects 
            WHERE id = ? AND teacher_id = ?
        ");
        $subjectCheck->execute([$selectedSubject, $teacher['teacher_id']]);
        
        if ($subjectCheck->fetch()) {
            $resultsStmt = $pdo->prepare("
                SELECT r.*, 
                       st.roll_number, 
                       u.first_name as student_first, 
                       u.last_name as student_last,
                       s.subject_name,
                       s.subject_code,
                       e.exam_name,
                       e.academic_year,
                       c.class_name
                FROM results r
                JOIN students st ON r.student_id = st.id
                JOIN users u ON st.user_id = u.id
                JOIN subjects s ON r.subject_id = s.id
                JOIN exams e ON r.exam_id = e.id
                LEFT JOIN classes c ON st.class_id = c.id
                WHERE r.subject_id = ? AND r.exam_id = ?
                ORDER BY st.roll_number
            ");
            $resultsStmt->execute([$selectedSubject, $selectedExam]);
            $results = $resultsStmt->fetchAll();
        }
    }
    
    // Get all exams for dropdown
    $examsStmt = $pdo->prepare("
        SELECT DISTINCT e.* 
        FROM exams e
        JOIN results r ON e.id = r.exam_id
        JOIN subjects s ON r.subject_id = s.id
        WHERE s.teacher_id = ?
        ORDER BY e.academic_year DESC, e.exam_name
    ");
    $examsStmt->execute([$teacher['teacher_id']]);
    $exams = $examsStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('View results page error: ' . $e->getMessage());
    $assignedSubjects = [];
    $exams = [];
    $results = [];
    $error = 'Database error occurred. Please try again.';
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-eye"></i> View Student Results
            <small class="text-muted">Browse and manage submitted results</small>
        </h1>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter"></i> Filter Results
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="subject_id" class="form-label">Subject</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select a subject</option>
                                <?php foreach ($assignedSubjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" 
                                            <?php echo ($selectedSubject == $subject['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        <?php echo $subject['class_name'] ? ' (' . htmlspecialchars($subject['class_name']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="exam_id" class="form-label">Exam</label>
                            <select class="form-select" id="exam_id" name="exam_id" required>
                                <option value="">Select an exam</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>"
                                            <?php echo ($selectedExam == $exam['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($exam['exam_name']); ?> 
                                        (<?php echo htmlspecialchars($exam['academic_year']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> View Results
                            </button>
                            <a href="/teacher/dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($results)): ?>
<!-- Results Display -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-table"></i> Results
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
                <?php if (!empty($results)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>Roll No</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Exam</th>
                                    <th>Marks Obtained</th>
                                    <th>Max Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalStudents = count($results);
                                $totalPassed = 0;
                                $totalMarksObtained = 0;
                                $totalMaxMarks = 0;
                                ?>
                                
                                <?php foreach ($results as $result): ?>
                                    <?php 
                                    $percentage = $result['max_marks'] > 0 ? 
                                        round(($result['marks_obtained'] / $result['max_marks']) * 100, 2) : 0;
                                    $isPassed = $percentage >= 40; // Assuming 40% is passing
                                    if ($isPassed) $totalPassed++;
                                    $totalMarksObtained += $result['marks_obtained'];
                                    $totalMaxMarks += $result['max_marks'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['roll_number']); ?></td>
                                        <td><?php echo htmlspecialchars($result['student_first'] . ' ' . $result['student_last']); ?></td>
                                        <td><?php echo htmlspecialchars($result['class_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($result['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($result['exam_name']); ?></td>
                                        <td>
                                            <span class="<?php echo $isPassed ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $result['marks_obtained']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $result['max_marks']; ?></td>
                                        <td>
                                            <span class="<?php echo $isPassed ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $percentage; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getGradeColor($result['grade']); ?>">
                                                <?php echo htmlspecialchars($result['grade'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['remarks'] ?? '-'); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" 
                                                    onclick="editResult(<?php echo $result['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="deleteResult(<?php echo $result['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <th colspan="6">Summary</th>
                                    <th><?php echo $totalMaxMarks; ?></th>
                                    <th>
                                        <?php 
                                        $overallPercentage = $totalMaxMarks > 0 ? 
                                            round(($totalMarksObtained / $totalMaxMarks) * 100, 2) : 0;
                                        echo $overallPercentage . '%';
                                        ?>
                                    </th>
                                    <th colspan="3">
                                        Pass Rate: <?php echo $totalStudents > 0 ? 
                                            round(($totalPassed / $totalStudents) * 100, 2) : 0; ?>%
                                        (<?php echo $totalPassed; ?>/<?php echo $totalStudents; ?>)
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No results found for the selected criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php elseif ($selectedSubject && $selectedExam): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No results found for the selected subject and exam.
    </div>
<?php endif; ?>

<!-- Edit Result Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="edit_result_id">
                    <div class="mb-3">
                        <label for="edit_marks_obtained" class="form-label">Marks Obtained</label>
                        <input type="number" class="form-control" id="edit_marks_obtained" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_max_marks" class="form-label">Max Marks</label>
                        <input type="number" class="form-control" id="edit_max_marks" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_grade" class="form-label">Grade</label>
                        <input type="text" class="form-control" id="edit_grade" maxlength="5">
                    </div>
                    <div class="mb-3">
                        <label for="edit_remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="edit_remarks" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function for grade colors
function getGradeColor($grade) {
    if (!$grade) return 'secondary';
    
    $grade = strtoupper($grade);
    switch ($grade) {
        case 'A+':
        case 'A':
            return 'success';
        case 'B+':
        case 'B':
            return 'info';
        case 'C+':
        case 'C':
            return 'warning';
        case 'D':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>

<script>
function editResult(resultId) {
    // Fetch result data via AJAX and populate modal
    fetch(`/teacher/get_result.php?id=${resultId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_result_id').value = data.id;
            document.getElementById('edit_marks_obtained').value = data.marks_obtained;
            document.getElementById('edit_max_marks').value = data.max_marks;
            document.getElementById('edit_grade').value = data.grade || '';
            document.getElementById('edit_remarks').value = data.remarks || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching result data');
        });
}

function saveEdit() {
    const formData = new FormData(document.getElementById('editForm'));
    
    fetch('/teacher/update_result.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error updating result: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating result');
    });
}

function deleteResult(resultId) {
    if (confirm('Are you sure you want to delete this result?')) {
        fetch(`/teacher/delete_result.php?id=${resultId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting result: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting result');
        });
    }
}

function exportToCSV() {
    const table = document.getElementById('resultsTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Add headers
    const headers = [];
    rows[0].querySelectorAll('th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Add data rows (skip footer)
    for (let i = 1; i < rows.length - 1; i++) {
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
    a.download = 'results.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
