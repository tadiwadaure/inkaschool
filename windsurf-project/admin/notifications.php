<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin role
$auth->requireRole('admin');

// Set page title
$pageTitle = 'Manage Notifications';

// Handle form submissions
$message = '';
$messageType = '';

// Add new notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $imagePath = null;
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            
            if (in_array($_FILES['image']['type'], $allowedTypes) && $_FILES['image']['size'] <= $maxFileSize) {
                $uploadDir = __DIR__ . '/../assets/uploads/notifications/';
                $fileName = uniqid('notification_', true) . '_' . basename($_FILES['image']['name']);
                $imagePath = 'assets/uploads/notifications/' . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                    // Image uploaded successfully
                } else {
                    throw new Exception('Failed to upload image');
                }
            } else {
                throw new Exception('Invalid file type or size. Allowed: JPG, PNG, GIF, WebP (max 5MB)');
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, image_path, target_audience, priority, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['title'],
            $_POST['message'],
            $imagePath,
            $_POST['target_audience'],
            $_POST['priority'],
            $expiresAt,
            $_SESSION['user_id']
        ]);
        $message = 'Notification posted successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error posting notification: ' . $e->getMessage();
        $messageType = 'danger';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Edit notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        
        // Handle image upload
        $imagePath = $_POST['existing_image'] ?? null;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            
            if (in_array($_FILES['image']['type'], $allowedTypes) && $_FILES['image']['size'] <= $maxFileSize) {
                $uploadDir = __DIR__ . '/../assets/uploads/notifications/';
                $fileName = uniqid('notification_', true) . '_' . basename($_FILES['image']['name']);
                $imagePath = 'assets/uploads/notifications/' . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                    // Delete old image if exists
                    if (!empty($_POST['existing_image']) && file_exists(__DIR__ . '/../' . $_POST['existing_image'])) {
                        unlink(__DIR__ . '/../' . $_POST['existing_image']);
                    }
                } else {
                    throw new Exception('Failed to upload image');
                }
            } else {
                throw new Exception('Invalid file type or size. Allowed: JPG, PNG, GIF, WebP (max 5MB)');
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET title = ?, message = ?, image_path = ?, target_audience = ?, priority = ?, expires_at = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['title'],
            $_POST['message'],
            $imagePath,
            $_POST['target_audience'],
            $_POST['priority'],
            $expiresAt,
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['id']
        ]);
        $message = 'Notification updated successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error updating notification: ' . $e->getMessage();
        $messageType = 'danger';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Delete notification
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        // Get notification to delete associated image
        $stmt = $pdo->prepare("SELECT image_path FROM notifications WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $notification = $stmt->fetch();
        
        // Delete image if exists
        if ($notification && !empty($notification['image_path']) && file_exists(__DIR__ . '/../' . $notification['image_path'])) {
            unlink(__DIR__ . '/../' . $notification['image_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $message = 'Notification deleted successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error deleting notification: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Toggle notification status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $message = 'Notification status updated!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error updating notification: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch notifications
try {
    $notificationsStmt = $pdo->query("
        SELECT n.*, u.first_name, u.last_name, u.username
        FROM notifications n
        JOIN users u ON n.created_by = u.id
        ORDER BY n.created_at DESC
    ");
    $notifications = $notificationsStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Notifications error: ' . $e->getMessage());
    $notifications = [];
    $message = 'Error loading notifications: ' . $e->getMessage();
    $messageType = 'danger';
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-bullhorn"></i> Manage Notifications
            <small class="text-muted">Post notices and announcements</small>
        </h1>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Add New Notification -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus-circle"></i> Post New Notification
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                placeholder="e.g., Holiday Announcement, Exam Schedule" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="target_audience" class="form-label">Target Audience</label>
                            <select class="form-select" id="target_audience" name="target_audience" required>
                                <option value="all">All Users</option>
                                <option value="students">Students Only</option>
                                <option value="teachers">Teachers Only</option>
                                <option value="admin">Admin Only</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="4" 
                                placeholder="Enter your notification message here..." required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="image" class="form-label">Image (Optional)</label>
                            <input type="file" class="form-control" id="image" name="image" 
                                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            <small class="form-text text-muted">Allowed: JPG, PNG, GIF, WebP (max 5MB)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="expires_at" class="form-label">Expires At (Optional)</label>
                            <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            <small class="form-text text-muted">Leave empty if notification doesn't expire</small>
                        </div>
                        <div class="col-12 mb-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Post Notification
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Notifications List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Posted Notifications
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($notifications)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Audience</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <?php 
                                    $isExpired = $notification['expires_at'] && strtotime($notification['expires_at']) < time();
                                    $priorityColors = [
                                        'low' => 'secondary',
                                        'medium' => 'info', 
                                        'high' => 'warning',
                                        'urgent' => 'danger'
                                    ];
                                    ?>
                                    <tr class="<?php echo $isExpired ? 'table-secondary' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                            <?php if ($isExpired): ?>
                                                <br><small class="text-muted"><i class="fas fa-clock"></i> Expired</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="text-truncate d-inline-block" style="max-width: 200px;">
                                                    <?php echo htmlspecialchars(substr($notification['message'], 0, 100)); ?>...
                                                </span>
                                                <?php if (!empty($notification['image_path'])): ?>
                                                    <br><small class="text-primary"><i class="fas fa-image"></i> Has image</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-outline-secondary">
                                                <?php echo ucfirst($notification['target_audience']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $priorityColors[$notification['priority']]; ?>">
                                                <?php echo ucfirst($notification['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($notification['is_active'] && !$isExpired): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                            <br><small class="text-muted">@<?php echo htmlspecialchars($notification['username']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($notification['created_at'])); ?>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($notification['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewNotification(<?php echo $notification['id']; ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editNotification(<?php echo $notification['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?action=toggle&id=<?php echo $notification['id']; ?>" 
                                               class="btn btn-sm btn-outline-warning" 
                                               title="<?php echo $notification['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $notification['is_active'] ? 'pause' : 'play'; ?>"></i>
                                            </a>
                                            <a href="?action=delete&id=<?php echo $notification['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this notification?')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No notifications posted yet. Create your first notification above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="existing_image" id="edit_existing_image">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="edit_target_audience" class="form-label">Target Audience</label>
                            <select class="form-select" id="edit_target_audience" name="target_audience" required>
                                <option value="all">All Users</option>
                                <option value="students">Students Only</option>
                                <option value="teachers">Teachers Only</option>
                                <option value="admin">Admin Only</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="edit_priority" class="form-label">Priority</label>
                            <select class="form-select" id="edit_priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="edit_message" class="form-label">Message</label>
                            <textarea class="form-control" id="edit_message" name="message" rows="4" required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_image" class="form-label">Image (Optional)</label>
                            <input type="file" class="form-control" id="edit_image" name="image" 
                                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            <small class="form-text text-muted">Allowed: JPG, PNG, GIF, WebP (max 5MB)</small>
                            <div id="edit_current_image" class="mt-2"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_expires_at" class="form-label">Expires At (Optional)</label>
                            <input type="datetime-local" class="form-control" id="edit_expires_at" name="expires_at">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Notification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">View Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="view_content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewNotification(id) {
    fetch('notifications.php?action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            const priorityColors = {
                'low': 'secondary',
                'medium': 'info', 
                'high': 'warning',
                'urgent': 'danger'
            };
            
            const isExpired = data.expires_at && new Date(data.expires_at) < new Date();
            const statusText = (data.is_active && !isExpired) ? 'Active' : 'Inactive';
            const statusClass = (data.is_active && !isExpired) ? 'success' : 'secondary';
            
            let imageHtml = '';
            if (data.image_path) {
                imageHtml = `
                    <div class="mb-3">
                        <strong>Image:</strong><br>
                        <img src="/${data.image_path}" style="max-width: 100%; max-height: 400px; object-fit: contain;" class="mt-2 rounded border">
                    </div>
                `;
            }
            
            document.getElementById('view_content').innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <h4>${data.title}</h4>
                        <p class="text-muted">${data.message.replace(/\n/g, '<br>')}</p>
                        ${imageHtml}
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Notification Details</h6>
                                <p><strong>Audience:</strong> <span class="badge bg-outline-secondary">${data.target_audience}</span></p>
                                <p><strong>Priority:</strong> <span class="badge bg-${priorityColors[data.priority]}">${data.priority}</span></p>
                                <p><strong>Status:</strong> <span class="badge bg-${statusClass}">${statusText}</span></p>
                                <p><strong>Created By:</strong><br>${data.first_name} ${data.last_name}<br><small class="text-muted">@${data.username}</small></p>
                                <p><strong>Created:</strong><br>${new Date(data.created_at).toLocaleString()}</p>
                                ${data.expires_at ? `<p><strong>Expires:</strong><br>${new Date(data.expires_at).toLocaleString()}</p>` : ''}
                                ${isExpired ? '<p class="text-warning"><i class="fas fa-clock"></i> This notification has expired</p>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching notification data');
        });
}

function editNotification(id) {
    fetch('notifications.php?action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_title').value = data.title;
            document.getElementById('edit_target_audience').value = data.target_audience;
            document.getElementById('edit_priority').value = data.priority;
            document.getElementById('edit_message').value = data.message;
            document.getElementById('edit_expires_at').value = data.expires_at ? data.expires_at.slice(0, 16) : '';
            document.getElementById('edit_is_active').checked = data.is_active == 1;
            document.getElementById('edit_existing_image').value = data.image_path || '';
            
            // Show current image if exists
            const currentImageDiv = document.getElementById('edit_current_image');
            if (data.image_path) {
                currentImageDiv.innerHTML = `
                    <small class="text-muted">Current image:</small><br>
                    <img src="/${data.image_path}" style="max-width: 100px; max-height: 60px; object-fit: cover;" class="mt-1 rounded">
                `;
            } else {
                currentImageDiv.innerHTML = '';
            }
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching notification data');
        });
}

<?php
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($notification) {
            header('Content-Type: application/json');
            echo json_encode($notification);
            exit;
        }
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
