<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require student role
$auth->requireRole('student');

// Set page title
$pageTitle = 'Update Profile';

// Initialize variables
$successMessage = '';
$errorMessage = '';

// Get student-specific data
try {
    // Get student info with user details
    $studentStmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.address, 
               c.class_name, c.section
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
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate and update user information
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $errorMessage = 'First name, last name, and email are required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } else {
            // Check if email is already taken by another user
            $emailCheckStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $emailCheckStmt->execute([$email, $_SESSION['user_id']]);
            if ($emailCheckStmt->fetch()) {
                $errorMessage = 'This email address is already in use by another user.';
            } else {
                // Update user information
                $updateUserStmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updateResult = $updateUserStmt->execute([$firstName, $lastName, $email, $phone, $address, $_SESSION['user_id']]);
                
                if ($updateResult) {
                    // Update session variables
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    $_SESSION['email'] = $email;
                    
                    $successMessage = 'Profile updated successfully!';
                    
                    // Refresh student data
                    $studentStmt->execute([$_SESSION['user_id']]);
                    $student = $studentStmt->fetch();
                } else {
                    $errorMessage = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password']) && $_POST['change_password'] === 'yes') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = 'All password fields are required.';
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = 'New password must be at least 6 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New password and confirm password do not match.';
        } else {
            // Verify current password
            $passwordCheckStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $passwordCheckStmt->execute([$_SESSION['user_id']]);
            $user = $passwordCheckStmt->fetch();
            
            if ($user && password_verify($currentPassword, $user['password'])) {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePasswordStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $passwordResult = $updatePasswordStmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                if ($passwordResult) {
                    $successMessage = 'Password changed successfully!';
                } else {
                    $errorMessage = 'Failed to change password. Please try again.';
                }
            } else {
                $errorMessage = 'Current password is incorrect.';
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('Student profile error: ' . $e->getMessage());
    $errorMessage = 'An error occurred. Please try again.';
    $student = [];
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-user-edit"></i> Update Profile
            <small class="text-muted">Manage Your Information</small>
        </h1>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information Form -->
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user"></i> Personal Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"
                                   placeholder="+1 (555) 123-4567">
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Academic Information & Password Change -->
    <div class="col-md-4">
        <!-- Academic Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-graduation-cap"></i> Academic Information
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">Roll Number</label>
                    <div class="fw-bold"><?php echo htmlspecialchars($student['roll_number'] ?? ''); ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Class</label>
                    <div class="fw-bold">
                        <?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?>
                        <?php if ($student['section']): ?>
                            - <?php echo htmlspecialchars($student['section']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Admission Date</label>
                    <div class="fw-bold"><?php echo date('M j, Y', strtotime($student['admission_date'])); ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Username</label>
                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Account Status</label>
                    <div>
                        <span class="badge bg-success">Active</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Password Change -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-key"></i> Change Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="yes">
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
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Account Statistics -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Account Activity
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                            <h6>Member Since</h6>
                            <small><?php echo date('M j, Y', strtotime($student['admission_date'])); ?></small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-user-shield fa-2x text-success mb-2"></i>
                            <h6>Account Type</h6>
                            <small>Student</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-clock fa-2x text-info mb-2"></i>
                            <h6>Last Login</h6>
                            <small><?php echo $_SESSION['last_login'] ? date('M j, Y H:i', strtotime($_SESSION['last_login'])) : 'First time login'; ?></small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <i class="fas fa-envelope fa-2x text-warning mb-2"></i>
                            <h6>Email Verified</h6>
                            <small class="text-success">Verified</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
