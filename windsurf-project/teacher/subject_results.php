<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require teacher role
$auth->requireRole('teacher');

// Get subject_id from URL
$subjectId = $_GET['subject_id'] ?? 0;

if (!$subjectId || !is_numeric($subjectId)) {
    header('Location: /teacher/dashboard.php');
    exit;
}

// Set page title
$pageTitle = 'Subject Results';

try {
    // Get teacher info
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
    
    // Verify this subject is assigned to the teacher and get subject details
    $subjectStmt = $pdo->prepare("
        SELECT s.*, c.class_name 
        FROM subjects s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.id = ? AND s.teacher_id = ?
    ");
    $subjectStmt->execute([$subjectId, $teacher['teacher_id']]);
    $subject = $subjectStmt->fetch();
    
    if (!$subject) {
        throw new Exception('Subject not found or not assigned to you');
    }
    
    // Get all exams for this subject
    $examsStmt = $pdo->prepare("
        SELECT DISTINCT e.* 
        FROM exams e
        JOIN results r ON e.id = r.exam_id
        WHERE r.subject_id = ?
        ORDER BY e.academic_year DESC, e.start_date DESC
    ");
    $examsStmt->execute([$subjectId]);
    $exams = $examsStmt->fetchAll();
    
    // Get selected exam from URL or use the most recent
    $selectedExam = $_GET['exam_id'] ?? '';
    if (!$selectedExam && !empty($exams)) {
        $selectedExam = $exams[0]['id'];
    }
    
    // Get results for the selected exam
    $results = [];
    if ($selectedExam) {
        $resultsStmt = $pdo->prepare("
            SELECT r.*, 
                   st.roll_number, 
                   u.first_name as student_first, 
                   u.last_name as student_last,
                   c.class_name
            FROM results r
            JOIN students st ON r.student_id = st.id
            JOIN users u ON st.user_id = u.id
            LEFT JOIN classes c ON st.class_id = c.id
            WHERE r.subject_id = ? AND r.exam_id = ?
            ORDER BY st.roll_number
        ");
        $resultsStmt->execute([$subjectId, $selectedExam]);
        $results = $resultsStmt->fetchAll();
    }
    
    // Get overall statistics for this subject
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_students,
            COUNT(CASE WHEN (r.marks_obtained / r.max_marks) >= 0.4 THEN 1 END) as passed_students,
            AVG(r.marks_obtained / r.max_marks * 100) as average_percentage,
            MAX(r.marks_obtained / r.max_marks * 100) as highest_percentage,
            MIN(r.marks_obtained / r.max_marks * 100) as lowest_percentage
        FROM results r
        WHERE r.subject_id = ? AND r.max_marks > 0
    ");
    $statsStmt->execute([$subjectId]);
    $overallStats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log('Subject results page error: ' . $e->getMessage());
    $error = 'Database error occurred. Please try again.';
    $subject = null;
    $exams = [];
    $results = [];
    $overallStats = null;
} catch (Exception $e) {
    $error = $e->getMessage();
    $subject = null;
    $exams = [];
    $results = [];
    $overallStats = null;
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-chart-line"></i> Subject Results
            <small class="text-muted">
                <?php if ($subject): ?>
                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                    <?php if ($subject['class_name']): ?>
                        (<?php echo htmlspecialchars($subject['class_name']); ?>)
                    <?php endif; ?>
                <?php endif; ?>
            </small>
        </h1>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($subject): ?>
    <!-- Subject Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-book"></i> Subject Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Subject Name:</strong><br>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Subject Code:</strong><br>
                            <?php echo htmlspecialchars($subject['subject_code']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Class:</strong><br>
                            <?php echo htmlspecialchars($subject['class_name'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Total Exams:</strong><br>
                            <?php echo count($exams); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Statistics -->
    <?php if ($overallStats && $overallStats['total_students'] > 0): ?>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stats-number"><?php echo round($overallStats['average_percentage'], 1); ?>%</div>
                                <div class="stats-label">Average Score</div>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chart-bar fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stats-card text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stats-number"><?php echo round($overallStats['highest_percentage'], 1); ?>%</div>
                                <div class="stats-label">Highest Score</div>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-trophy fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stats-card text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stats-number"><?php echo round($overallStats['lowest_percentage'], 1); ?>%</div>
                                <div class="stats-label">Lowest Score</div>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-arrow-down fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stats-card text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stats-number"><?php echo round(($overallStats['passed_students'] / $overallStats['total_students']) * 100, 1); ?>%</div>
                                <div class="stats-label">Pass Rate</div>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Exam Selection -->
    <?php if (!empty($exams)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list"></i> Select Exam
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <form method="GET" action="">
                                    <input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
                                    <div class="input-group">
                                        <select class="form-select" name="exam_id" onchange="this.form.submit()">
                                            <?php foreach ($exams as $exam): ?>
                                                <option value="<?php echo $exam['id']; ?>" 
                                                        <?php echo ($selectedExam == $exam['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($exam['exam_name']); ?> 
                                                    (<?php echo htmlspecialchars($exam['academic_year']); ?>)
                                                    <?php if ($exam['start_date']): ?>
                                                        - <?php echo date('M j, Y', strtotime($exam['start_date'])); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync"></i> Load
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-6 d-flex align-items-center justify-content-end">
                                <a href="/teacher/results.php" class="btn btn-success me-2">
                                    <i class="fas fa-plus"></i> Add Results
                                </a>
                                <a href="/teacher/view_results.php" class="btn btn-info me-2">
                                    <i class="fas fa-eye"></i> View All Results
                                </a>
                                <a href="/teacher/dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Results Table -->
    <?php if (!empty($results)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-table"></i> Exam Results
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
                        <div class="table-responsive">
                            <table class="table table-striped" id="resultsTable">
                                <thead>
                                    <tr>
                                        <th>Roll No</th>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th>Marks Obtained</th>
                                        <th>Max Marks</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
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
                                        $isPassed = $percentage >= 40;
                                        if ($isPassed) $totalPassed++;
                                        $totalMarksObtained += $result['marks_obtained'];
                                        $totalMaxMarks += $result['max_marks'];
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['roll_number']); ?></td>
                                            <td><?php echo htmlspecialchars($result['student_first'] . ' ' . $result['student_last']); ?></td>
                                            <td><?php echo htmlspecialchars($result['class_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="<?php echo $isPassed ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>">
                                                    <?php echo $result['marks_obtained']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $result['max_marks']; ?></td>
                                            <td>
                                                <span class="<?php echo $isPassed ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>">
                                                    <?php echo $percentage; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getGradeColor($result['grade']); ?>">
                                                    <?php echo htmlspecialchars($result['grade'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($isPassed): ?>
                                                    <span class="badge bg-success">Pass</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Fail</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($result['remarks'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <th colspan="3">Summary</th>
                                        <th><?php echo $totalMarksObtained; ?></th>
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
                        
                        <!-- Grade Distribution Chart -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6>Grade Distribution</h6>
                                <?php
                                $gradeDistribution = [];
                                foreach ($results as $result) {
                                    $grade = $result['grade'] ?? 'N/A';
                                    $gradeDistribution[$grade] = ($gradeDistribution[$grade] ?? 0) + 1;
                                }
                                ?>
                                <div class="progress" style="height: 25px;">
                                    <?php foreach ($gradeDistribution as $grade => $count): ?>
                                        <div class="progress-bar bg-<?php echo getGradeColor($grade); ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo ($count / $totalStudents) * 100; ?>%"
                                             title="<?php echo $grade; ?>: <?php echo $count; ?> students">
                                            <?php echo $grade; ?> (<?php echo $count; ?>)
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Performance Summary</h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <div class="text-success fw-bold"><?php echo $totalPassed; ?></div>
                                            <small>Passed</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <div class="text-danger fw-bold"><?php echo $totalStudents - $totalPassed; ?></div>
                                            <small>Failed</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <div class="text-primary fw-bold"><?php echo $totalStudents; ?></div>
                                            <small>Total</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($selectedExam): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No results found for this exam.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No exams available for this subject yet.
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Subject not found or you don't have permission to view it.
        <a href="/teacher/dashboard.php" class="btn btn-primary ms-2">Back to Dashboard</a>
    </div>
<?php endif; ?>

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
    a.download = 'subject_results.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
