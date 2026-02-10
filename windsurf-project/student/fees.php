<?php
// Minimal working fees page
session_start();

// Simple auth check - check what's actually available in session
if (!isset($_SESSION['user_id'])) {
    die("Please log in first. <a href='../login.php'>Click here to login</a>");
}

// Check role - try different possible session variable names
$userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? '';
if ($userRole !== 'student') {
    die("Access denied. Student role required. Your role: " . htmlspecialchars($userRole) . ". <a href='../login.php'>Click here to login</a>");
}

// Include database
try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Details - Student Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --secondary-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideDown 0.6s ease-out;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .user-info {
            color: #64748b;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .stat-icon.paid { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .stat-icon.remaining { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .stat-icon.total { background: linear-gradient(135deg, #4f46e5, #4338ca); color: white; }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .fee-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 25px 30px;
            border: none;
        }

        .card-title-custom {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-body-custom {
            padding: 30px;
        }

        .fee-table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .fee-table thead {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
        }

        .fee-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
        }

        .fee-table td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        .fee-table tbody tr {
            transition: all 0.2s ease;
        }

        .fee-table tbody tr:hover {
            background-color: #f8fafc;
            transform: scale(1.01);
        }

        .fee-type {
            font-weight: 600;
            color: #1e293b;
        }

        .fee-amount {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .badge-paid {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }

        .badge-unpaid {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }

        .btn-pay {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        }

        .alert-custom {
            border-radius: 15px;
            border: none;
            padding: 20px 25px;
            margin-bottom: 25px;
            animation: slideDown 0.5s ease-out;
        }

        .alert-success-custom {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-info-custom {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .back-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .fee-table {
                font-size: 0.9rem;
            }
            
            .btn-pay {
                padding: 6px 15px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-file-invoice-dollar"></i> Fee Details
            </h1>
            <div class="user-info">
                <i class="fas fa-user-graduate"></i> 
                Welcome back! Your role: <strong><?php echo ucfirst($userRole); ?></strong>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon paid">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value">$0.00</div>
                <div class="stat-label">Total Paid</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon remaining">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-value">$875.00</div>
                <div class="stat-label">Remaining Balance</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-value">$875.00</div>
                <div class="stat-label">Total Expected</div>
            </div>
        </div>

        <!-- Fee Structure Card -->
        <div class="fee-card">
            <div class="card-header-custom">
                <h2 class="card-title-custom">
                    <i class="fas fa-list-ul"></i>
                    Fee Structure
                </h2>
            </div>
            <div class="card-body-custom">
                <?php
                try {
                    // Get student info
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $student = $stmt->fetch();
                    
                    if ($student) {
                        // Get fees with better error handling
                        $feeStmt = $pdo->prepare("SELECT * FROM fee_structure WHERE class_id = ? ORDER BY fee_type");
                        $feeStmt->execute([$student['class_id']]);
                        $fees = $feeStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($fees)) {
                            ?>
                            <div class="table-responsive">
                                <table class="table fee-table">
                                    <thead>
                                        <tr>
                                            <th>Fee Type</th>
                                            <th>Amount</th>
                                            <th>Frequency</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fees as $index => $fee): ?>
                                        <tr style="animation-delay: <?php echo ($index + 1) * 0.1; ?>s">
                                            <td class="fee-type">
                                                <i class="fas fa-receipt me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($fee['fee_type'] ?? 'Unknown Fee'); ?>
                                            </td>
                                            <td class="fee-amount">$<?php echo number_format((float)($fee['amount'] ?? 0), 2); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars(ucfirst($fee['frequency'] ?? 'N/A')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-unpaid pulse">
                                                    <i class="fas fa-clock me-1"></i> Unpaid
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-pay btn-sm" onclick="handlePayment(<?php echo (int)($fee['id'] ?? 0); ?>)">
                                                    <i class="fas fa-credit-card me-1"></i> Pay Now
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                        } else {
                            ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No Fees Found</h4>
                                <p class="text-muted">There are currently no fees assigned to your class.</p>
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Student record not found. Please contact administration.
                        </div>
                        <?php
                    }
                } catch (PDOException $e) {
                    ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Database error: <?php echo htmlspecialchars($e->getMessage()); ?>
                    </div>
                    <?php
                } catch (Exception $e) {
                    ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading fee data: <?php echo htmlspecialchars($e->getMessage()); ?>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <!-- Back Button -->
        <div class="text-center">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add interactive animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Observe stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                observer.observe(card);
            });
        });
        
        // Handle payment button clicks
        function handlePayment(feeId) {
            // Create a beautiful notification instead of alert
            const notification = document.createElement('div');
            notification.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.innerHTML = `
                <i class="fas fa-info-circle me-2"></i>
                <strong>Payment Feature</strong><br>
                <small>Payment processing will be available soon!</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>
