<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require student role
$auth->requireRole('student');

// Set page title
$pageTitle = 'My Results';

// Get student-specific data
try {
    // Get student info
    $studentStmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, u.email, c.class_name, c.section
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE u.id = ?
    ");
    $studentStmt->execute([$_SESSION['user_id']]);
    $student = $studentStmt->fetch();
    
    if (!$student) {
        throw new Exception('Student information not found');
    }
    
    // Get all results with filtering options
    $examFilter = $_GET['exam'] ?? '';
    $subjectFilter = $_GET['subject'] ?? '';
    
    $whereClause = "WHERE r.student_id = ?";
    $params = [$student['id']];
    
    if ($examFilter) {
        $whereClause .= " AND e.id = ?";
        $params[] = $examFilter;
    }
    
    if ($subjectFilter) {
        $whereClause .= " AND s.id = ?";
        $params[] = $subjectFilter;
    }
    
    $resultsStmt = $pdo->prepare("
        SELECT r.*, s.subject_name, s.subject_code, e.exam_name, e.academic_year
        FROM results r
        JOIN subjects s ON r.subject_id = s.id
        JOIN exams e ON r.exam_id = e.id
        $whereClause
        ORDER BY e.academic_year DESC, e.exam_name, s.subject_name
    ");
    $resultsStmt->execute($params);
    $allResults = $resultsStmt->fetchAll();
    
    // Get available exams for filter
    $examsStmt = $pdo->prepare("
        SELECT DISTINCT e.id, e.exam_name, e.academic_year
        FROM exams e
        JOIN results r ON e.id = r.exam_id
        WHERE r.student_id = ?
        ORDER BY e.academic_year DESC, e.exam_name
    ");
    $examsStmt->execute([$student['id']]);
    $availableExams = $examsStmt->fetchAll();
    
    // Get available subjects for filter
    $subjectsStmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.subject_name
        FROM subjects s
        JOIN results r ON s.id = r.subject_id
        WHERE r.student_id = ?
        ORDER BY s.subject_name
    ");
    $subjectsStmt->execute([$student['id']]);
    $availableSubjects = $subjectsStmt->fetchAll();
    
    // Calculate overall statistics
    $totalExams = count($allResults);
    $totalMarks = array_sum(array_column($allResults, 'marks_obtained'));
    $totalMaxMarks = array_sum(array_column($allResults, 'max_marks'));
    $averagePercentage = $totalMaxMarks > 0 ? round(($totalMarks / $totalMaxMarks) * 100, 1) : 0;
    
    // Group results by academic year and exam
    $groupedResults = [];
    foreach ($allResults as $result) {
        $year = $result['academic_year'];
        $exam = $result['exam_name'];
        if (!isset($groupedResults[$year])) {
            $groupedResults[$year] = [];
        }
        if (!isset($groupedResults[$year][$exam])) {
            $groupedResults[$year][$exam] = [];
        }
        $groupedResults[$year][$exam][] = $result;
    }
    
} catch (PDOException $e) {
    error_log('Student results error: ' . $e->getMessage());
    $student = [];
    $allResults = [];
    $availableExams = [];
    $availableSubjects = [];
    $groupedResults = [];
    $totalExams = 0;
    $averagePercentage = 0;
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-chart-bar"></i> My Results
            <small class="text-muted">Academic Performance</small>
        </h1>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stats-number"><?php echo $totalExams; ?></div>
                        <div class="stats-label">Total Exams</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-list fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo $averagePercentage; ?>%</div>
                        <div class="stats-label">Average Score</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chart-line fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo count($availableExams); ?></div>
                        <div class="stats-label">Exam Types</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-list-alt fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo count($availableSubjects); ?></div>
                        <div class="stats-label">Subjects</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter"></i> Filter Results
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="exam" class="form-label">Exam</label>
                        <select class="form-select" id="exam" name="exam">
                            <option value="">All Exams</option>
                            <?php foreach ($availableExams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo $examFilter == $exam['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo $exam['academic_year']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="subject" class="form-label">Subject</label>
                        <select class="form-select" id="subject" name="subject">
                            <option value="">All Subjects</option>
                            <?php foreach ($availableSubjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $subjectFilter == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="results.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Results Display -->
<div class="row">
    <div class="col-12">
        <?php if (!empty($groupedResults)): ?>
            <?php foreach ($groupedResults as $academicYear => $exams): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-alt"></i> Academic Year: <?php echo htmlspecialchars($academicYear); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($exams as $examName => $results): ?>
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars($examName); ?>
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Subject</th>
                                                <th>Subject Code</th>
                                                <th>Marks Obtained</th>
                                                <th>Maximum Marks</th>
                                                <th>Percentage</th>
                                                <th>Grade</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results as $result): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($result['subject_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($result['subject_code']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($result['marks_obtained'] / $result['max_marks']) >= 0.6 ? 'success' : 'danger'; ?>">
                                                            <?php echo $result['marks_obtained']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $result['max_marks']; ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <?php $percentage = round(($result['marks_obtained'] / $result['max_marks']) * 100, 1); ?>
                                                            <div class="progress-bar bg-<?php echo $percentage >= 60 ? 'success' : ($percentage >= 40 ? 'warning' : 'danger'); ?>" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $percentage; ?>%"
                                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                                <?php echo $percentage; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($result['grade']): ?>
                                                            <span class="badge bg-info"><?php echo htmlspecialchars($result['grade']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($result['remarks']): ?>
                                                            <small><?php echo htmlspecialchars($result['remarks']); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Exam Summary -->
                                <?php
                                $examTotal = array_sum(array_column($results, 'marks_obtained'));
                                $examMaxTotal = array_sum(array_column($results, 'max_marks'));
                                $examAverage = $examMaxTotal > 0 ? round(($examTotal / $examMaxTotal) * 100, 1) : 0;
                                ?>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <strong>Exam Summary:</strong>
                                            Total Marks: <?php echo $examTotal; ?>/<?php echo $examMaxTotal; ?> 
                                            (Average: <?php echo $examAverage; ?>%)
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Results Available</h5>
                    <p class="text-muted">Your examination results will appear here once they are published.</p>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
