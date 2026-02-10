<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Reports';

// Handle report generation
$reportData = [];
$reportType = $_GET['report_type'] ?? '';

if ($reportType && isset($_GET['generate'])) {
    try {
        switch ($reportType) {
            case 'student_results':
                $examId = $_GET['exam_id'] ?? '';
                $classId = $_GET['class_id'] ?? '';
                
                $sql = "
                    SELECT 
                        u.first_name, u.last_name, s.roll_number, c.class_name,
                        e.exam_name, e.academic_year,
                        sub.subject_name, sub.subject_code,
                        r.marks_obtained, r.max_marks, r.grade,
                        ROUND((r.marks_obtained / r.max_marks) * 100, 1) as percentage
                    FROM results r
                    JOIN students s ON r.student_id = s.id
                    JOIN users u ON s.user_id = u.id
                    JOIN exams e ON r.exam_id = e.id
                    JOIN subjects sub ON r.subject_id = sub.id
                    LEFT JOIN classes c ON s.class_id = c.id
                    WHERE 1=1
                ";
                
                $params = [];
                if ($examId) {
                    $sql .= " AND e.id = ?";
                    $params[] = $examId;
                }
                if ($classId) {
                    $sql .= " AND c.id = ?";
                    $params[] = $classId;
                }
                
                $sql .= " ORDER BY c.class_name, u.last_name, u.first_name, sub.subject_name";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'class_performance':
                $classId = $_GET['class_id'] ?? '';
                $examId = $_GET['exam_id'] ?? '';
                
                $sql = "
                    SELECT 
                        c.class_name,
                        sub.subject_name,
                        COUNT(r.id) as total_students,
                        AVG(r.marks_obtained) as avg_marks,
                        AVG((r.marks_obtained / r.max_marks) * 100) as avg_percentage,
                        MAX(r.marks_obtained) as highest_marks,
                        MIN(r.marks_obtained) as lowest_marks
                    FROM results r
                    JOIN students s ON r.student_id = s.id
                    JOIN subjects sub ON r.subject_id = sub.id
                    JOIN classes c ON s.class_id = c.id
                    JOIN exams e ON r.exam_id = e.id
                    WHERE 1=1
                ";
                
                $params = [];
                if ($classId) {
                    $sql .= " AND c.id = ?";
                    $params[] = $classId;
                }
                if ($examId) {
                    $sql .= " AND e.id = ?";
                    $params[] = $examId;
                }
                
                $sql .= " GROUP BY c.class_name, sub.subject_name ORDER BY c.class_name, sub.subject_name";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'exam_summary':
                $examId = $_GET['exam_id'] ?? '';
                
                $sql = "
                    SELECT 
                        e.exam_name, e.academic_year,
                        c.class_name,
                        COUNT(DISTINCT s.id) as total_students,
                        COUNT(r.id) as total_results,
                        AVG((r.marks_obtained / r.max_marks) * 100) as avg_percentage,
                        COUNT(CASE WHEN r.grade IN ('A+', 'A') THEN 1 END) as excellent_count,
                        COUNT(CASE WHEN r.grade IN ('B+', 'B') THEN 1 END) as good_count,
                        COUNT(CASE WHEN r.grade = 'C' THEN 1 END) as average_count,
                        COUNT(CASE WHEN r.grade IN ('D', 'F') THEN 1 END) as poor_count
                    FROM exams e
                    LEFT JOIN results r ON e.id = r.exam_id
                    LEFT JOIN students s ON r.student_id = s.id
                    LEFT JOIN classes c ON s.class_id = c.id
                ";
                
                $params = [];
                if ($examId) {
                    $sql .= " WHERE e.id = ?";
                    $params[] = $examId;
                }
                
                $sql .= " GROUP BY e.exam_name, e.academic_year, c.class_name ORDER BY e.academic_year DESC, e.exam_name, c.class_name";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll();
                break;
        }
    } catch (PDOException $e) {
        error_log('Report generation error: ' . $e->getMessage());
        $error = 'Error generating report: ' . $e->getMessage();
    }
}

// Fetch data for dropdowns
try {
    $examsStmt = $pdo->query("SELECT id, exam_name, academic_year FROM exams ORDER BY academic_year DESC, exam_name");
    $exams = $examsStmt->fetchAll();
    
    $classesStmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
    $classes = $classesStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Reports data loading error: ' . $e->getMessage());
    $exams = $classes = [];
    $error = 'Error loading data: ' . $e->getMessage();
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-file-alt"></i> Reports
            <small class="text-muted">Generate and view various reports</small>
        </h1>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Report Generation Form -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Generate Report
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type" required>
                                <option value="">Select Report Type</option>
                                <option value="student_results" <?php echo $reportType === 'student_results' ? 'selected' : ''; ?>>
                                    Student Results Report
                                </option>
                                <option value="class_performance" <?php echo $reportType === 'class_performance' ? 'selected' : ''; ?>>
                                    Class Performance Report
                                </option>
                                <option value="exam_summary" <?php echo $reportType === 'exam_summary' ? 'selected' : ''; ?>>
                                    Exam Summary Report
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="exam_id" class="form-label">Exam (Optional)</label>
                            <select class="form-select" id="exam_id" name="exam_id">
                                <option value="">All Exams</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>" 
                                        <?php echo (($_GET['exam_id'] ?? '') == $exam['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($exam['exam_name'] . ' - ' . $exam['academic_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="class_id" class="form-label">Class (Optional)</label>
                            <select class="form-select" id="class_id" name="class_id">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"
                                        <?php echo (($_GET['class_id'] ?? '') == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" name="generate" value="1" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Generate
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Report Results -->
<?php if ($reportType && !empty($reportData)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-table"></i> 
                        <?php 
                        switch ($reportType) {
                            case 'student_results': echo 'Student Results Report'; break;
                            case 'class_performance': echo 'Class Performance Report'; break;
                            case 'exam_summary': echo 'Exam Summary Report'; break;
                        }
                        ?>
                    </h5>
                    <button class="btn btn-sm btn-outline-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <?php if ($reportType === 'student_results'): ?>
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student</th>
                                        <th>Roll No</th>
                                        <th>Class</th>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Marks</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['roll_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['class_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['exam_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                            <td><?php echo $row['marks_obtained']; ?> / <?php echo $row['max_marks']; ?></td>
                                            <td><?php echo $row['percentage']; ?>%</td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($row['grade']) {
                                                        'A+', 'A' => 'success',
                                                        'B+', 'B' => 'info',
                                                        'C' => 'warning',
                                                        'D' => 'secondary',
                                                        default => 'danger'
                                                    }; ?>">
                                                    <?php echo htmlspecialchars($row['grade']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                        <?php elseif ($reportType === 'class_performance'): ?>
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Total Students</th>
                                        <th>Average Marks</th>
                                        <th>Average %</th>
                                        <th>Highest</th>
                                        <th>Lowest</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                            <td><?php echo $row['total_students']; ?></td>
                                            <td><?php echo number_format($row['avg_marks'], 1); ?></td>
                                            <td><?php echo number_format($row['avg_percentage'], 1); ?>%</td>
                                            <td><?php echo $row['highest_marks']; ?></td>
                                            <td><?php echo $row['lowest_marks']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                        <?php elseif ($reportType === 'exam_summary'): ?>
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Exam</th>
                                        <th>Class</th>
                                        <th>Total Students</th>
                                        <th>Results</th>
                                        <th>Average %</th>
                                        <th>Excellent</th>
                                        <th>Good</th>
                                        <th>Average</th>
                                        <th>Poor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($row['exam_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($row['academic_year']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['class_name'] ?? 'All Classes'); ?></td>
                                            <td><?php echo $row['total_students']; ?></td>
                                            <td><?php echo $row['total_results']; ?></td>
                                            <td><?php echo number_format($row['avg_percentage'], 1); ?>%</td>
                                            <td><span class="badge bg-success"><?php echo $row['excellent_count']; ?></span></td>
                                            <td><span class="badge bg-info"><?php echo $row['good_count']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $row['average_count']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $row['poor_count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Summary Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Report Summary</h6>
                                <p class="mb-0">
                                    Total Records: <strong><?php echo count($reportData); ?></strong> |
                                    Generated on: <strong><?php echo date('M j, Y h:i A'); ?></strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<?php elseif ($reportType && empty($reportData)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No data found for the selected criteria.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="row">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h5>Total Students</h5>
                <?php 
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
                $count = $stmt->fetch()['count'];
                echo "<h3 class='text-primary'>$count</h3>";
                ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-list fa-2x text-success mb-2"></i>
                <h5>Total Exams</h5>
                <?php 
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM exams");
                $count = $stmt->fetch()['count'];
                echo "<h3 class='text-success'>$count</h3>";
                ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="fas fa-chart-bar fa-2x text-info mb-2"></i>
                <h5>Total Results</h5>
                <?php 
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM results");
                $count = $stmt->fetch()['count'];
                echo "<h3 class='text-info'>$count</h3>";
                ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
