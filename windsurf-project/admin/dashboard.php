<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Admin Dashboard';

// Get dashboard statistics
try {
    // Get notifications for admin
    $notifications = getNotifications($pdo, 'admin');
    
    // Total students
    $studentsStmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $totalStudents = $studentsStmt->fetch()['count'];
    
    // Total teachers
    $teachersStmt = $pdo->query("SELECT COUNT(*) as count FROM teachers");
    $totalTeachers = $teachersStmt->fetch()['count'];
    
    // Total classes
    $classesStmt = $pdo->query("SELECT COUNT(*) as count FROM classes");
    $totalClasses = $classesStmt->fetch()['count'];
    
    // Total subjects
    $subjectsStmt = $pdo->query("SELECT COUNT(*) as count FROM subjects");
    $totalSubjects = $subjectsStmt->fetch()['count'];
    
    // Recent users
    $recentUsersStmt = $pdo->query("
        SELECT username, role, created_at 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentUsers = $recentUsersStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $totalStudents = $totalTeachers = $totalClasses = $totalSubjects = 0;
    $recentUsers = [];
    $notifications = [];
}

// Include header
require_once __DIR__ . '/../includes/header.php';

// Display notifications
echo displayNotifications($notifications);
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-tachometer-alt"></i> Admin Dashboard
            <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</small>
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
                        <div class="stats-number"><?php echo $totalStudents; ?></div>
                        <div class="stats-label">Total Students</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-graduate fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo $totalTeachers; ?></div>
                        <div class="stats-label">Total Teachers</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chalkboard-teacher fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo $totalClasses; ?></div>
                        <div class="stats-label">Total Classes</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo $totalSubjects; ?></div>
                        <div class="stats-label">Total Subjects</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book fa-2x opacity-75"></i>
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
                        <a href="/admin/students.php" class="btn btn-primary w-100">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/teachers.php" class="btn btn-success w-100">
                            <i class="fas fa-user-tie"></i> Add Teacher
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/classes.php" class="btn btn-info w-100">
                            <i class="fas fa-plus-circle"></i> Add Class
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/subjects.php" class="btn btn-warning w-100">
                            <i class="fas fa-book-open"></i> Add Subject
                        </a>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3 mb-2">
                        <a href="/admin/exam_timetable.php" class="btn btn-secondary w-100">
                            <i class="fas fa-calendar-alt"></i> Exam Timetable
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/exams.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-clipboard-list"></i> Manage Exams
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/notifications.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-bullhorn"></i> Notifications
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/reports.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-file-alt"></i> Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users"></i> Recent Users
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentUsers)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentUsers as $user): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-user-tag"></i> <?php echo ucfirst($user['role']); ?>
                                    </small>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No recent users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bullhorn"></i> Recent Notifications
                </h5>
                <a href="/admin/notifications.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus"></i> Manage
                </a>
            </div>
            <div class="card-body">
                <?php
                // Get recent notifications for admin
                try {
                    $recentNotificationsStmt = $pdo->query("
                        SELECT n.*, u.first_name, u.last_name 
                        FROM notifications n
                        JOIN users u ON n.created_by = u.id
                        ORDER BY n.created_at DESC
                        LIMIT 5
                    ");
                    $recentNotifications = $recentNotificationsStmt->fetchAll();
                } catch (PDOException $e) {
                    $recentNotifications = [];
                }
                ?>
                
                <?php if (!empty($recentNotifications)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentNotifications as $notification): ?>
                            <?php 
                            $priorityColors = [
                                'low' => 'secondary',
                                'medium' => 'info', 
                                'high' => 'warning',
                                'urgent' => 'danger'
                            ];
                            $isExpired = $notification['expires_at'] && strtotime($notification['expires_at']) < time();
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge bg-<?php echo $priorityColors[$notification['priority']]; ?> me-2">
                                                <?php echo ucfirst($notification['priority']); ?>
                                            </span>
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                            <?php if (!empty($notification['image_path'])): ?>
                                                <i class="fas fa-image text-primary ms-2" title="Has image"></i>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-1 text-muted small">
                                            <?php echo htmlspecialchars(substr($notification['message'], 0, 80)); ?>...
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-users"></i> <?php echo ucfirst($notification['target_audience']); ?>
                                            • <i class="fas fa-clock"></i> <?php echo date('M j, h:i A', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="ms-2">
                                        <?php if ($notification['is_active'] && !$isExpired): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-bullhorn fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No notifications posted yet.</p>
                        <a href="/admin/notifications.php" class="btn btn-sm btn-primary mt-2">
                            <i class="fas fa-plus"></i> Create First Notification
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
