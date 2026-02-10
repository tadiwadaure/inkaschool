<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require teacher role
$auth->requireRole('teacher');

// Set page title
$pageTitle = 'Update Profile';

// Get teacher info
try {
    $teacherStmt = $pdo->prepare("
        SELECT t.*, u.username, u.email, u.first_name, u.last_name, u.phone, u.address, u.last_login
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.id = ?
    ");
    $teacherStmt->execute([$_SESSION['user_id']]);
    $teacher = $teacherStmt->fetch();
    
    if (!$teacher) {
        throw new Exception('Teacher not found');
    }
    
} catch (PDOException $e) {
    error_log('Profile page error: ' . $e->getMessage());
    $error = 'Database error occurred. Please try again.';
    $teacher = null;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        
        // Validate required fields
        if (!$firstName || !$lastName || !$email) {
            throw new Exception('First name, last name, and email are required');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Check if email is already taken by another user
        $emailCheck = $pdo->prepare("
            SELECT id FROM users 
            WHERE email = ? AND id != ?
        ");
        $emailCheck->execute([$email, $_SESSION['user_id']]);
        if ($emailCheck->fetch()) {
            throw new Exception('Email is already taken by another user');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update users table
        $updateUserStmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
            WHERE id = ?
        ");
        $updateUserStmt->execute([$firstName, $lastName, $email, $phone, $address, $_SESSION['user_id']]);
        
        // Update teachers table
        $updateTeacherStmt = $pdo->prepare("
            UPDATE teachers 
            SET qualification = ?, specialization = ?
            WHERE user_id = ?
        ");
        $updateTeacherStmt->execute([$qualification, $specialization, $_SESSION['user_id']]);
        
        // Update session variables
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['email'] = $email;
        
        $pdo->commit();
        $success = 'Profile updated successfully!';
        
        // Refresh teacher data
        $teacherStmt->execute([$_SESSION['user_id']]);
        $teacher = $teacherStmt->fetch();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Profile update error: ' . $e->getMessage());
        $error = 'Database error occurred. Please try again.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            throw new Exception('All password fields are required');
        }
        
        if ($newPassword !== $confirmPassword) {
            throw new Exception('New passwords do not match');
        }
        
        if (strlen($newPassword) < 6) {
            throw new Exception('New password must be at least 6 characters long');
        }
        
        // Verify current password
        $passwordCheck = $pdo->prepare("
            SELECT password FROM users WHERE id = ?
        ");
        $passwordCheck->execute([$_SESSION['user_id']]);
        $user = $passwordCheck->fetch();
        
        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception('Current password is incorrect');
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePasswordStmt = $pdo->prepare("
            UPDATE users SET password = ? WHERE id = ?
        ");
        $updatePasswordStmt->execute([$hashedPassword, $_SESSION['user_id']]);
        
        $passwordSuccess = 'Password changed successfully!';
        
    } catch (PDOException $e) {
        error_log('Password change error: ' . $e->getMessage());
        $passwordError = 'Database error occurred. Please try again.';
    } catch (Exception $e) {
        $passwordError = $e->getMessage();
    }
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-user-edit"></i> Update Profile
            <small class="text-muted">Manage your personal information</small>
        </h1>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user"></i> Personal Information
                </h5>
            </div>
            <div class="card-body">
                <?php if ($teacher): ?>
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($teacher['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($teacher['last_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($teacher['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <hr>
                        
                        <h6 class="mb-3">Professional Information</h6>
                        
                        <div class="mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   value="<?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?>" 
                                   placeholder="e.g., M.Sc. Mathematics, B.Ed.">
                        </div>
                        
                        <div class="mb-3">
                            <label for="specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" 
                                   value="<?php echo htmlspecialchars($teacher['specialization'] ?? ''); ?>" 
                                   placeholder="e.g., Mathematics, Physics, Chemistry">
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                            <a href="/teacher/dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Unable to load profile information.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Account Information & Password Change -->
    <div class="col-md-4">
        <!-- Account Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Account Information
                </h5>
            </div>
            <div class="card-body">
                <?php if ($teacher): ?>
                    <div class="mb-2">
                        <strong>Username:</strong><br>
                        <span class="text-muted"><?php echo htmlspecialchars($teacher['username']); ?></span>
                    </div>
                    <div class="mb-2">
                        <strong>Role:</strong><br>
                        <span class="badge bg-primary">Teacher</span>
                    </div>
                    <div class="mb-2">
                        <strong>Joining Date:</strong><br>
                        <span class="text-muted"><?php echo date('M j, Y', strtotime($teacher['joining_date'])); ?></span>
                    </div>
                    <div class="mb-2">
                        <strong>Last Login:</strong><br>
                        <span class="text-muted">
                            <?php echo $teacher['last_login'] ? 
                                date('M j, Y H:i', strtotime($teacher['last_login'])) : 
                                'Never'; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Password Change -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-lock"></i> Change Password
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($passwordError)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($passwordError); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($passwordSuccess)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($passwordSuccess); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               minlength="6" required>
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Clear custom validity when typing new password
document.getElementById('new_password').addEventListener('input', function() {
    document.getElementById('confirm_password').setCustomValidity('');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
