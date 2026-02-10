<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

// Require teacher role
$auth->requireRole('teacher');

// Set page title
$pageTitle = 'Teacher Dashboard';

// Get teacher-specific data
try {
    // Get notifications for teacher
    $notifications = getNotifications($pdo, 'teachers');
    
    // Get teacher info
    $teacherStmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name, u.email 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.id = ?
    ");
    $teacherStmt->execute([$_SESSION['user_id']]);
    $teacher = $teacherStmt->fetch();
    
    // Get assigned subjects
    $subjectsStmt = $pdo->prepare("
        SELECT s.*, c.class_name 
        FROM subjects s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.teacher_id = ?
    ");
    $subjectsStmt->execute([$teacher['id']]);
    $assignedSubjects = $subjectsStmt->fetchAll();
    
    // Get class IDs for this teacher
    $classIds = array_unique(array_filter(array_column($assignedSubjects, 'class_id')));
    
    // Get exams for teacher's classes
    $teacherExams = [];
    if (!empty($classIds)) {
        $placeholders = str_repeat('?,', count($classIds) - 1) . '?';
        $examsStmt = $pdo->prepare("
            SELECT COUNT(*) as exam_count 
            FROM exams 
            WHERE class_id IS NULL OR class_id IN ($placeholders)
        ");
        $examsStmt->execute($classIds);
        $teacherExams = $examsStmt->fetch();
    }
    
    // Get recent results submitted by this teacher
    $resultsStmt = $pdo->prepare("
        SELECT r.*, st.roll_number, u.first_name as student_first, u.last_name as student_last,
               s.subject_name, e.exam_name
        FROM results r
        JOIN students st ON r.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN subjects s ON r.subject_id = s.id
        JOIN exams e ON r.exam_id = e.id
        WHERE s.teacher_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $resultsStmt->execute([$teacher['id']]);
    $recentResults = $resultsStmt->fetchAll();
    
    // Get notifications for teachers
    $notifications = getNotifications($pdo, 'teacher');
    
} catch (PDOException $e) {
    error_log('Teacher dashboard error: ' . $e->getMessage());
    $teacher = [];
    $assignedSubjects = [];
    $recentResults = [];
    $notifications = [];
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Display Notifications -->
<?php echo displayNotifications($notifications); ?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-chalkboard-teacher"></i> Teacher Dashboard
            <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</small>
        </h1>
    </div>
</div>

<!-- Teacher Info Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user-tie"></i> My Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($teacher['email']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Qualification:</strong><br>
                        <?php echo htmlspecialchars($teacher['qualification'] ?? 'N/A'); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Specialization:</strong><br>
                        <?php echo htmlspecialchars($teacher['specialization'] ?? 'N/A'); ?>
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
                        <div class="stats-number"><?php echo count($assignedSubjects); ?></div>
                        <div class="stats-label">Assigned Subjects</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo $teacherExams['exam_count'] ?? 0; ?></div>
                        <div class="stats-label">Available Exams</div>
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
                        <div class="stats-number"><?php echo count($recentResults); ?></div>
                        <div class="stats-label">Results Submitted</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-check fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo date('Y'); ?></div>
                        <div class="stats-label">Academic Year</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-alt fa-2x opacity-75"></i>
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
                        <a href="/teacher/results.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle"></i> Add Results
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/teacher/exams.php" class="btn btn-success w-100">
                            <i class="fas fa-clipboard-list"></i> Add Exams
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/teacher/view_results.php" class="btn btn-info w-100">
                            <i class="fas fa-eye"></i> View Results
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/teacher/profile.php" class="btn btn-secondary w-100">
                            <i class="fas fa-user-edit"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Assigned Subjects -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-book-open"></i> Assigned Subjects
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($assignedSubjects)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($assignedSubjects as $subject): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Code: <?php echo htmlspecialchars($subject['subject_code']); ?>
                                        </small>
                                        <?php if ($subject['class_name']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-school"></i> <?php echo htmlspecialchars($subject['class_name']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <a href="/teacher/subject_results.php?subject_id=<?php echo $subject['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-chart-line"></i> Results
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No subjects assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Results -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Recent Results Submitted
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentResults)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentResults as $result): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($result['student_first'] . ' ' . $result['student_last']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($result['subject_name']); ?> - 
                                            <?php echo htmlspecialchars($result['exam_name']); ?>
                                        </small>
                                        <br>
                                        <small class="text-success">
                                            Marks: <?php echo $result['marks_obtained']; ?>/<?php echo $result['max_marks']; ?>
                                            <?php if ($result['grade']): ?>
                                                - Grade: <?php echo htmlspecialchars($result['grade']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No results submitted yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
