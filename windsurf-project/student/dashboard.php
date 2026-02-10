<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

// Require student role
$auth->requireRole('student');

// Set page title
$pageTitle = 'Student Dashboard';

// Get dashboard statistics
try {
    // Get notifications for student
    $notifications = getNotifications($pdo, 'student');
    
    // Get student info first
    $studentStmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, u.email, c.class_name, c.section
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE u.id = ?
    ");
    $studentStmt->execute([$_SESSION['user_id']]);
    $student = $studentStmt->fetch();
    
    // Get student's fee information
    $feeInfo = ['pending_fees' => 0, 'total_paid' => 0];
    if ($student && $student['class_id']) {
        $feeStmt = $pdo->prepare("
            SELECT 
                SUM(fs.amount) as total_fees,
                COALESCE(SUM(fp.amount_paid), 0) as paid_amount
            FROM fee_structure fs
            LEFT JOIN fee_payments fp ON fs.id = fp.fee_structure_id AND fp.status = 'completed'
            WHERE fs.class_id = ? AND fs.academic_year = ?
        ");
        $currentYear = date('Y') . '-' . (date('Y') + 1);
        $feeStmt->execute([$student['class_id'], $currentYear]);
        $feeData = $feeStmt->fetch();
        
        if ($feeData) {
            $feeInfo['pending_fees'] = $feeData['total_fees'] - $feeData['paid_amount'];
            $feeInfo['total_paid'] = $feeData['paid_amount'];
        }
    }
    
    // Get recent results
    $recentResults = [];
    if ($student) {
        $resultsStmt = $pdo->prepare("
            SELECT r.*, s.subject_name, e.exam_name
            FROM results r
            JOIN subjects s ON r.subject_id = s.id
            JOIN exams e ON r.exam_id = e.id
            WHERE r.student_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $resultsStmt->execute([$student['id']]);
        $recentResults = $resultsStmt->fetchAll();
    }
    
    // Get overall performance
    $performance = ['total_exams' => 0, 'average_percentage' => 0];
    if ($student) {
        $performanceStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_exams,
                COALESCE(AVG(r.marks_obtained / r.max_marks * 100), 0) as average_percentage
            FROM results r
            WHERE r.student_id = ? AND r.max_marks > 0
        ");
        $performanceStmt->execute([$student['id']]);
        $perfData = $performanceStmt->fetch();
        
        if ($perfData) {
            $performance['total_exams'] = (int)$perfData['total_exams'];
            $performance['average_percentage'] = round((float)$perfData['average_percentage'], 1);
        }
    }
    
    // Get notifications for students
    $notifications = getNotifications($pdo, 'student');
    
} catch (PDOException $e) {
    error_log('Student dashboard error: ' . $e->getMessage());
    $student = [];
    $recentResults = [];
    $feeInfo = ['pending_fees' => 0, 'total_paid' => 0];
    $performance = ['total_exams' => 0, 'average_percentage' => 0];
    $notifications = [];
}

// Check if student data was found
if (empty($student)) {
    $message = 'Warning: Student profile not found. Please contact the administrator.';
    $messageType = 'warning';
}

// Include header
require_once __DIR__ . '/../includes/header.php';

// Display notifications
echo displayNotifications($notifications);
?>

<?php if (isset($message) && isset($messageType)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-user-graduate"></i> Student Dashboard
            <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</small>
        </h1>
    </div>
</div>

<!-- Student Info Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-id-card"></i> My Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Roll Number:</strong><br>
                        <?php echo htmlspecialchars($student['roll_number'] ?? 'Not Assigned'); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Class:</strong><br>
                        <?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?>
                        <?php if (!empty($student['section'])): ?>
                            - <?php echo htmlspecialchars($student['section']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($student['email'] ?? 'Not Available'); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Admission Date:</strong><br>
                        <?php echo isset($student['admission_date']) ? date('M j, Y', strtotime($student['admission_date'])) : 'Not Available'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stats-number"><?php echo $performance['total_exams']; ?></div>
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
                        <div class="stats-number"><?php echo round((float)($performance['average_percentage'] ?? 0), 1); ?>%</div>
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
                        <div class="stats-number"><?php echo $feeInfo['pending_fees']; ?></div>
                        <div class="stats-label">Pending Fees</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo count($recentResults); ?></div>
                        <div class="stats-label">Recent Results</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-history fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="/student/results.php" class="btn btn-primary w-100">
                            <i class="fas fa-chart-bar"></i> View Results
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/student/fees.php" class="btn btn-success w-100">
                            <i class="fas fa-receipt"></i> Fee Details
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/student/profile.php" class="btn btn-info w-100">
                            <i class="fas fa-user-edit"></i> Update Profile
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/student/timetable.php" class="btn btn-warning w-100">
                            <i class="fas fa-calendar-alt"></i> Timetable
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Results -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Recent Results
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentResults)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentResults as $result): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($result['subject_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($result['exam_name']); ?>
                                        </small>
                                        <br>
                                        <small class="text-<?php echo ($result['marks_obtained'] / $result['max_marks']) >= 0.6 ? 'success' : 'danger'; ?>">
                                            Score: <?php echo $result['marks_obtained']; ?>/<?php echo $result['max_marks']; ?>
                                            (<?php echo round(($result['marks_obtained'] / $result['max_marks']) * 100, 1); ?>%)
                                        </small>
                                        <?php if ($result['grade']): ?>
                                            <br>
                                            <small class="badge bg-info"><?php echo htmlspecialchars($result['grade']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="/student/results.php" class="btn btn-sm btn-outline-primary">View All Results</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No results available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Academic Overview -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-graduation-cap"></i> Academic Overview
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-trophy fa-2x text-warning mb-2"></i>
                            <h6>Performance</h6>
                            <?php if (($performance['average_percentage'] ?? 0) >= 80): ?>
                                <small class="text-success">Excellent</small>
                            <?php elseif (($performance['average_percentage'] ?? 0) >= 60): ?>
                                <small class="text-info">Good</small>
                            <?php elseif (($performance['average_percentage'] ?? 0) >= 40): ?>
                                <small class="text-warning">Average</small>
                            <?php else: ?>
                                <small class="text-danger">Needs Improvement</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-book-reader fa-2x text-info mb-2"></i>
                            <h6>Subjects</h6>
                            <small>Active Learning</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                            <h6>Attendance</h6>
                            <small class="text-success">Good</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <i class="fas fa-award fa-2x text-primary mb-2"></i>
                            <h6>Grade Level</h6>
                            <small><?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Auto-refresh dashboard every 30 seconds to show latest results
setInterval(function() {
    // Only refresh if the page is visible to avoid unnecessary requests
    if (!document.hidden) {
        location.reload();
    }
}, 30000);

// Add refresh button functionality
document.addEventListener('DOMContentLoaded', function() {
    // Create refresh button if it doesn't exist
    const header = document.querySelector('.h3');
    if (header && !document.getElementById('refresh-btn')) {
        const refreshBtn = document.createElement('button');
        refreshBtn.id = 'refresh-btn';
        refreshBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
        refreshBtn.onclick = function() {
            this.querySelector('i').classList.add('fa-spin');
            location.reload();
        };
        header.appendChild(refreshBtn);
    }
});

// Listen for visibility changes to refresh when tab becomes active
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Refresh when user comes back to the tab after being away for more than 30 seconds
        const lastHidden = localStorage.getItem('dashboardHidden') || Date.now();
        if (Date.now() - lastHidden > 30000) {
            location.reload();
        }
    } else {
        localStorage.setItem('dashboardHidden', Date.now());
    }
});
</script>
