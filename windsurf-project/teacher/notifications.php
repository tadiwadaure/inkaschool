<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/notifications.php';

// Require teacher role
$auth->requireRole('teacher');

// Set page title
$pageTitle = 'Notifications - Teacher Portal';

// Get all notifications for teachers
try {
    $notifications = getNotifications($pdo, 'teacher');
} catch (PDOException $e) {
    error_log('Teacher notifications error: ' . $e->getMessage());
    $notifications = [];
    $error = 'Error loading notifications. Please try again.';
}

// Include header
render_page_header($pageTitle);
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-bullhorn"></i> Notices & Announcements
            <small class="text-muted">View all school notifications</small>
        </h1>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> All Notifications
                </h5>
                <?php if (!empty($notifications)): ?>
                    <span class="badge bg-info"><?php echo count($notifications); ?> Total</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($notifications)): ?>
                    <div class="row">
                        <?php foreach ($notifications as $notification): ?>
                            <?php 
                            $priorityColors = [
                                'low' => 'secondary',
                                'medium' => 'info', 
                                'high' => 'warning',
                                'urgent' => 'danger'
                            ];
                            $priorityClass = isset($priorityColors[$notification['priority']]) ? $priorityColors[$notification['priority']] : 'info';
                            ?>
                            <div class="col-12 mb-4">
                                <div class="card border-start border-4 border-<?php echo $priorityClass; ?>">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-<?php echo $priorityClass; ?> me-2">
                                                    <?php echo ucfirst($notification['priority']); ?>
                                                </span>
                                                <h5 class="card-title mb-0 d-inline"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> 
                                                <?php echo date('M j, Y h:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                                
                                                <?php if (!empty($notification['image_path'])): ?>
                                                    <div class="mt-3">
                                                        <img src="/<?php echo htmlspecialchars($notification['image_path']); ?>" 
                                                             style="max-width: 100%; max-height: 400px; object-fit: contain;" 
                                                             class="rounded border shadow-sm" 
                                                             alt="Notification image"
                                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                        <div style="display: none; color: #6c757d; font-style: italic; text-align: center; padding: 20px; border: 1px dashed #dee2e6; border-radius: 4px;">
                                                            <i class="fas fa-image fa-2x mb-2"></i><br>
                                                            Image unavailable
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6 class="card-title">Notification Details</h6>
                                                        <p class="mb-1"><strong>Priority:</strong> 
                                                            <span class="badge bg-<?php echo $priorityClass; ?>"><?php echo ucfirst($notification['priority']); ?></span>
                                                        </p>
                                                        <p class="mb-1"><strong>Audience:</strong> 
                                                            <span class="badge bg-outline-secondary"><?php echo ucfirst($notification['target_audience']); ?></span>
                                                        </p>
                                                        <p class="mb-1"><strong>Status:</strong> 
                                                            <span class="badge bg-success">Active</span>
                                                        </p>
                                                        <p class="mb-0"><strong>Posted:</strong><br>
                                                            <small><?php echo date('F j, Y - g:i A', strtotime($notification['created_at'])); ?></small>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No Notifications</h4>
                        <p class="text-muted">There are no new notifications at this time.</p>
                        <p class="text-muted">Check back later for updates from your school administration.</p>
                        <a href="/teacher/dashboard.php" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($notifications)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="text-center">
            <a href="/teacher/dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php render_page_footer(); ?>
