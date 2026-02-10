<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// Require teacher role
$auth->requireRole('teacher');

// Set page title
$pageTitle = 'My Timetable';

// Get current teacher
$userId = $_SESSION['user_id'];
$teacherId = null;

// Get teacher ID from user ID
try {
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $teacher = $stmt->fetch();
    $teacherId = $teacher ? $teacher['id'] : null;
} catch (PDOException $e) {
    error_log('Error getting teacher ID: ' . $e->getMessage());
    $teacherId = null;
}

// If no teacher ID found, redirect to login or show error
if (!$teacherId) {
    $_SESSION['error'] = 'Teacher profile not found. Please contact administrator.';
    header('Location: /login.php');
    exit();
}

// Get current academic year and term
$currentAcademicYear = date('Y') . '-' . (date('Y') + 1);
$currentTerm = 'Term 1';

// Get teacher's timetable
try {
    $stmt = $pdo->prepare("
        SELECT tt.*, c.class_name, s.subject_name, s.subject_code, s.class_id as subject_class_id
        FROM teaching_timetable tt
        JOIN classes c ON tt.class_id = c.id
        JOIN subjects s ON tt.subject_id = s.id
        WHERE tt.teacher_id = ? AND tt.academic_year = ? AND tt.term = ? AND tt.status = 'active'
        ORDER BY FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), tt.start_time
    ");
    $stmt->execute([$teacherId, $currentAcademicYear, $currentTerm]);
    $timetableEntries = $stmt->fetchAll();
    
    // Group by day for better display
    $timetableByDay = [];
    foreach ($timetableEntries as $entry) {
        $timetableByDay[$entry['day_of_week']][] = $entry;
    }
    
    // Get teacher info
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, t.specialization
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$teacherId]);
    $teacherInfo = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log('Teacher timetable error: ' . $e->getMessage());
    $timetableEntries = [];
    $timetableByDay = [];
    $teacherInfo = null;
}

// Days of week in order
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .timetable-grid {
            display: grid;
            grid-template-columns: 120px repeat(7, 1fr);
            gap: 1px;
            background: #dee2e6;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .time-slot {
            background: #f8f9fa;
            padding: 10px;
            font-weight: 600;
            text-align: center;
            border-right: 1px solid #dee2e6;
        }
        
        .day-header {
            background: #e9ecef;
            padding: 15px 10px;
            font-weight: 600;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }
        
        .timetable-cell {
            background: white;
            min-height: 80px;
            padding: 8px;
            position: relative;
        }
        
        .timetable-entry {
            background: #007bff;
            color: white;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 4px;
            font-size: 0.85rem;
        }
        
        .timetable-entry:last-child {
            margin-bottom: 0;
        }
        
        .subject-name {
            font-weight: 600;
            display: block;
        }
        
        .class-name {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .room-info {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        
        .empty-day {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-style: italic;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-calendar-alt me-2"></i>My Teaching Timetable</h2>
                        <p class="text-muted mb-0">
                            <?php echo $teacherInfo ? $teacherInfo['first_name'] . ' ' . $teacherInfo['last_name'] : 'Teacher'; ?>
                            <?php echo $teacherInfo['specialization'] ? ' - ' . $teacherInfo['specialization'] : ''; ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <h5 class="text-muted"><?php echo $currentAcademicYear; ?></h5>
                        <h6 class="text-muted"><?php echo $currentTerm; ?></h6>
                    </div>
                </div>
                
                <!-- Weekly View -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Weekly View</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="timetable-grid">
                            <!-- Headers -->
                            <div class="time-slot">Time / Day</div>
                            <?php foreach ($daysOfWeek as $day): ?>
                                <div class="day-header"><?php echo $day; ?></div>
                            <?php endforeach; ?>
                            
                            <!-- Time slots -->
                            <?php
                            $timeSlots = [
                                '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00',
                                '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00',
                                '16:00-17:00'
                            ];
                            
                            foreach ($timeSlots as $slot): ?>
                                <div class="time-slot"><?php echo $slot; ?></div>
                                <?php foreach ($daysOfWeek as $day): ?>
                                    <div class="timetable-cell">
                                        <?php
                                        $hasEntry = false;
                                        if (isset($timetableByDay[$day])) {
                                            foreach ($timetableByDay[$day] as $entry) {
                                                $entrySlot = $entry['start_time'] . '-' . $entry['end_time'];
                                                if ($entrySlot === $slot) {
                                                    $hasEntry = true;
                                                    ?>
                                                    <div class="timetable-entry">
                                                        <span class="subject-name"><?php echo $entry['subject_name']; ?></span>
                                                        <span class="class-name"><?php echo $entry['class_name']; ?></span>
                                                        <?php if ($entry['room']): ?>
                                                            <span class="room-info">Room: <?php echo $entry['room']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                        }
                                        
                                        if (!$hasEntry): ?>
                                            <div class="empty-day">Free</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- List View -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Detailed Schedule</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($timetableEntries)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No classes scheduled for this term</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($daysOfWeek as $day): ?>
                                <?php if (isset($timetableByDay[$day]) && !empty($timetableByDay[$day])): ?>
                                    <div class="mb-4">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-calendar-day me-2"></i><?php echo $day; ?>
                                        </h6>
                                        <div class="row">
                                            <?php foreach ($timetableByDay[$day] as $entry): ?>
                                                <div class="col-md-6 col-lg-4 mb-3">
                                                    <div class="card border-primary">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <h6 class="card-title mb-0"><?php echo $entry['subject_name']; ?></h6>
                                                                <span class="badge bg-primary"><?php echo $entry['start_time']; ?></span>
                                                            </div>
                                                            <p class="card-text">
                                                                <i class="fas fa-users me-2 text-muted"></i><?php echo $entry['class_name']; ?><br>
                                                                <i class="fas fa-clock me-2 text-muted"></i><?php echo $entry['start_time']; ?> - <?php echo $entry['end_time']; ?>
                                                                <?php if ($entry['room']): ?>
                                                                    <br><i class="fas fa-door-open me-2 text-muted"></i>Room: <?php echo $entry['room']; ?>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Summary Statistics -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count($timetableEntries); ?></h4>
                                        <p class="mb-0">Total Classes</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chalkboard fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count(array_filter($timetableByDay)); ?></h4>
                                        <p class="mb-0">Teaching Days</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count(array_unique(array_column($timetableEntries, 'class_id'))); ?></h4>
                                        <p class="mb-0">Classes</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-school fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count(array_unique(array_column($timetableEntries, 'subject_id'))); ?></h4>
                                        <p class="mb-0">Subjects</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-book fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
