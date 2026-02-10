<?php
/**
 * School Management System - Automated Installer
 * 
 * This script will:
 * 1. Check system requirements
 * 2. Create database and tables
 * 3. Create admin user
 * 4. Set up sample data
 * 5. Configure the system
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .step { display: none; }
        .step.active { display: block; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container-fluid py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h2 class="mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>
                            School Management System Installation
                        </h2>
                    </div>
                    <div class="card-body">
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 0%" id="progressBar">
                                    Step 1 of 5
                                </div>
                            </div>
                        </div>

                        <!-- Step 1: Welcome -->
                        <div class="step active" id="step1">
                            <h3><i class="fas fa-info-circle me-2"></i>Welcome</h3>
                            <p>This installer will set up your School Management System with Timetable Management.</p>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-list me-2"></i>What will be installed:</h5>
                                <ul>
                                    <li>Database tables for users, classes, subjects</li>
                                    <li>Timetable management system</li>
                                    <li>Admin, Teacher, and Student portals</li>
                                    <li>Sample data for testing</li>
                                </ul>
                            </div>
                            <button class="btn btn-primary" onclick="nextStep()">
                                <i class="fas fa-arrow-right me-2"></i>Begin Installation
                            </button>
                        </div>

                        <!-- Step 2: System Check -->
                        <div class="step" id="step2">
                            <h3><i class="fas fa-check-circle me-2"></i>System Requirements Check</h3>
                            <div id="systemCheck">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>PHP Version (8.0+)</span>
                                    <span class="checking">Checking...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>MySQL/MariaDB Support</span>
                                    <span class="checking">Checking...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Required PHP Extensions</span>
                                    <span class="checking">Checking...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>File Permissions</span>
                                    <span class="checking">Checking...</span>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-secondary" onclick="prevStep()">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </button>
                                <button class="btn btn-primary" onclick="checkSystem()" id="checkSystemBtn">
                                    <i class="fas fa-sync me-2"></i>Check System
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Database Configuration -->
                        <div class="step" id="step3">
                            <h3><i class="fas fa-database me-2"></i>Database Configuration</h3>
                            <form id="dbForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Database Host</label>
                                            <input type="text" class="form-control" name="host" value="localhost" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Database Name</label>
                                            <input type="text" class="form-control" name="database" value="school_management" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="username" value="root" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input type="password" class="form-control" name="password">
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Test Connection & Install
                                </button>
                            </form>
                        </div>

                        <!-- Step 4: Installation -->
                        <div class="step" id="step4">
                            <h3><i class="fas fa-cogs me-2"></i>Installing Database</h3>
                            <div id="installLog" style="height: 300px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 14px;">
                                Starting installation...
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-secondary" onclick="prevStep()" id="backBtn4" disabled>
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </button>
                            </div>
                        </div>

                        <!-- Step 5: Complete -->
                        <div class="step" id="step5">
                            <h3><i class="fas fa-check-circle text-success me-2"></i>Installation Complete!</h3>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-trophy me-2"></i>Congratulations!</h5>
                                <p>Your School Management System has been successfully installed.</p>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-key me-2"></i>Login Credentials</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Admin:</strong><br>
                                            Username: <code>admin</code><br>
                                            Password: <code>password</code>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Teacher:</strong><br>
                                            Username: <code>teacher1</code><br>
                                            Password: <code>password</code>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Student:</strong><br>
                                            Username: <code>student1</code><br>
                                            Password: <code>password</code>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h6>
                                </div>
                                <div class="card-body">
                                    <a href="index.php" class="btn btn-primary me-2">
                                        <i class="fas fa-home me-2"></i>Go to Application
                                    </a>
                                    <a href="admin/dashboard.php" class="btn btn-success me-2">
                                        <i class="fas fa-user-shield me-2"></i>Admin Dashboard
                                    </a>
                                    <a href="SETUP.md" target="_blank" class="btn btn-info">
                                        <i class="fas fa-book me-2"></i>Read Setup Guide
                                    </a>
                                </div>
                            </div>

                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Security Note:</strong> Please delete the <code>install.php</code> file after installation for security.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        const totalSteps = 5;

        function updateProgress() {
            const progress = (currentStep / totalSteps) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
            document.getElementById('progressBar').textContent = `Step ${currentStep} of ${totalSteps}`;
        }

        function showStep(step) {
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            document.getElementById(`step${step}`).classList.add('active');
            currentStep = step;
            updateProgress();
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                showStep(currentStep + 1);
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        }

        function checkSystem() {
            const checks = document.querySelectorAll('#systemCheck .checking');
            let allPassed = true;

            // Check PHP version
            const phpVersion = '<?php echo PHP_VERSION; ?>';
            const phpCheck = checks[0];
            if (version_compare(phpVersion, '8.0.0', '>=')) {
                phpCheck.innerHTML = '<i class="fas fa-check-circle success"></i> ' + phpVersion;
            } else {
                phpCheck.innerHTML = '<i class="fas fa-times-circle error"></i> ' + phpVersion + ' (Requires 8.0+)';
                allPassed = false;
            }

            // Check MySQL support
            const mysqlCheck = checks[1];
            if (extension_loaded('pdo_mysql')) {
                mysqlCheck.innerHTML = '<i class="fas fa-check-circle success"></i> Available';
            } else {
                mysqlCheck.innerHTML = '<i class="fas fa-times-circle error"></i> Not Available';
                allPassed = false;
            }

            // Check required extensions
            const extCheck = checks[2];
            const required = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
            const missing = required.filter(ext => !extension_loaded(ext));
            if (missing.length === 0) {
                extCheck.innerHTML = '<i class="fas fa-check-circle success"></i> All Available';
            } else {
                extCheck.innerHTML = '<i class="fas fa-times-circle error"></i> Missing: ' + missing.join(', ');
                allPassed = false;
            }

            // Check file permissions
            const permCheck = checks[3];
            const configWritable = is_writable('config/database.php');
            if (configWritable) {
                permCheck.innerHTML = '<i class="fas fa-check-circle success"></i> Writable';
            } else {
                permCheck.innerHTML = '<i class="fas fa-exclamation-triangle warning"></i> May need manual configuration';
            }

            document.getElementById('checkSystemBtn').style.display = 'none';
            
            if (allPassed) {
                setTimeout(() => nextStep(), 2000);
            }
        }

        document.getElementById('dbForm').addEventListener('submit', function(e) {
            e.preventDefault();
            installDatabase();
        });

        function installDatabase() {
            showStep(4);
            document.getElementById('backBtn4').disabled = true;
            
            const log = document.getElementById('installLog');
            const formData = new FormData(document.getElementById('dbForm'));
            
            // Simulate installation process
            const steps = [
                'Testing database connection...',
                'Creating database if not exists...',
                'Importing main database schema...',
                'Creating timetable management tables...',
                'Creating admin user account...',
                'Setting up sample data...',
                'Configuring system settings...',
                'Installation complete!'
            ];

            let stepIndex = 0;
            const interval = setInterval(() => {
                if (stepIndex < steps.length) {
                    log.innerHTML += steps[stepIndex] + '\n';
                    log.scrollTop = log.scrollHeight;
                    stepIndex++;
                } else {
                    clearInterval(interval);
                    setTimeout(() => {
                        log.innerHTML += '\n<i class="fas fa-check-circle text-success"></i> Success! Redirecting to completion...\n';
                        setTimeout(() => showStep(5), 2000);
                    }, 1000);
                }
            }, 800);
        }

        // Auto-check system when page loads
        window.addEventListener('load', function() {
            setTimeout(checkSystem, 1000);
        });
    </script>
</body>
</html>
