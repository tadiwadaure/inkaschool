<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Auth {
    private PDO $pdo;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 15 * 60; // 15 minutes in seconds

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Authenticate user with username and password
     * @param string $username Username
     * @param string $password Password
     * @return bool True if authentication is successful
     * @throws RuntimeException On authentication failure
     */
    public function login(string $username, string $password): bool {
        // Check for brute force attempts
        if ($this->isBruteForce($username)) {
            throw new RuntimeException('Too many failed login attempts. Please try again later.');
        }

        $sql = "SELECT * FROM users WHERE username = :username AND status = 'active' LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if password needs rehashing
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $this->updatePasswordHash($user['id'], $password);
            }
            
            // Reset failed login attempts
            $this->resetLoginAttempts($username);
            
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set user session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['last_activity'] = time();
            
            // Set secure session cookie
            $this->setSecureSessionCookie();
            
            return true;
        }
        
        // Record failed login attempt
        $this->recordFailedLogin($username);
        return false;
    }

    /**
     * Log out the current user
     */
    public function logout(): void {
        // Unset all session variables
        $_SESSION = [];
        
        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        // Redirect to login page
        header("Location: /login.php");
        exit();
    }

    /**
     * Check if user is logged in and session is still valid
     * @return bool True if user is logged in and session is valid
     */
    public function isLoggedIn(): bool {
        // Check if session exists and not expired
        if (empty($_SESSION['user_id']) || empty($_SESSION['last_activity'])) {
            return false;
        }
        
        // Session timeout after 30 minutes of inactivity
        $timeout = 30 * 60; // 30 minutes in seconds
        if (time() - $_SESSION['last_activity'] > $timeout) {
            $this->logout();
            return false;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        
        return true;
    }

    /**
     * Redirect user based on their role
     * @param string $role User role
     */
    public function redirectBasedOnRole(string $role): void {
        $redirects = [
            'admin' => '/admin/dashboard.php',
            'teacher' => '/teacher/dashboard.php',
            'student' => '/student/dashboard.php',
            'accountant' => '/accountant/dashboard.php',
            'default' => '/index.php'
        ];
        
        $location = $redirects[strtolower($role)] ?? $redirects['default'];
        header("Location: $location");
        exit();
    }

    /**
     * Check if user has the required role
     * @param string|array $requiredRole Single role or array of allowed roles
     * @return bool True if user has the required role
     */
    public function hasRole($requiredRole): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_array($requiredRole)) {
            return in_array($_SESSION['role'], $requiredRole, true);
        }
        
        return $_SESSION['role'] === $requiredRole;
    }
    
    /**
     * Require user to have specific role
     * @param string|array $requiredRole Single role or array of allowed roles
     * @throws RuntimeException If user doesn't have required role
     */
    public function requireRole($requiredRole): void {
        if (!$this->isLoggedIn()) {
            $this->redirectToLogin();
        }
        
        if (!$this->hasRole($requiredRole)) {
            $this->handleUnauthorized();
        }
    }
    
    /**
     * Handle unauthorized access
     */
    private function handleUnauthorized(): void {
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            // For regular requests, redirect to unauthorized page
            header('HTTP/1.0 403 Forbidden');
            header('Location: /error/403.php');
        } else {
            // For AJAX requests, return JSON error
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access']);
        }
        exit();
    }

    /**
     * Check if login attempts exceed the limit for a username
     * @param string $username Username to check
     * @return bool True if too many failed attempts
     */
    private function isBruteForce(string $username): bool {
        $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                WHERE username = :username AND attempt_time > DATE_SUB(NOW(), INTERVAL :lockout_time SECOND)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':lockout_time' => self::LOCKOUT_TIME
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['attempts'] >= self::MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Record a failed login attempt
     * @param string $username Username that failed to log in
     */
    private function recordFailedLogin(string $username): void {
        $sql = "INSERT INTO login_attempts (username, ip_address, user_agent) 
                VALUES (:username, :ip, :user_agent)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    /**
     * Reset login attempts for a user
     * @param string $username Username to reset attempts for
     */
    private function resetLoginAttempts(string $username): void {
        $sql = "DELETE FROM login_attempts WHERE username = :username";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
    }
    
    /**
     * Update user's password hash
     * @param int $userId User ID
     * @param string $password New password (plaintext)
     */
    private function updatePasswordHash(int $userId, string $password): void {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :hash, updated_at = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':hash' => $hash, ':id' => $userId]);
    }
    
    /**
     * Set secure session cookie parameters
     */
    private function setSecureSessionCookie(): void {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $params = session_get_cookie_params();
        
        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => 0,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    /**
     * Redirect to login page with return URL
     */
    private function redirectToLogin(): void {
        $returnUrl = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /login.php?return_to=$returnUrl");
        exit();
    }
    
    /**
     * Get current user data
     * @return array|null User data or null if not logged in
     */
    public function getCurrentUser(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $sql = "SELECT id, username, email, first_name, last_name, role, created_at, last_login 
                    FROM users 
                    WHERE id = :id AND status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching user data: ' . $e->getMessage());
            return null;
        }
    }
}

// Initialize Auth
try {
    $auth = new Auth($pdo);
} catch (RuntimeException $e) {
    error_log('Auth initialization failed: ' . $e->getMessage());
    die('System error. Please try again later.');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        header('Location: /login.php');
        exit();
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        if ($auth->login($username, $password)) {
            // Update last login time
            $updateSql = "UPDATE users SET last_login = NOW() WHERE username = :username";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([':username' => $username]);
            
            // Redirect to dashboard
            $auth->redirectBasedOnRole($_SESSION['role']);
        } else {
            $_SESSION['error'] = 'Invalid username or password';
            header('Location: /login.php');
            exit();
        }
    } catch (RuntimeException $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: /login.php');
        exit();
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
}
?>
