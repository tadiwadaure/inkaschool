<?php
declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require student role
$auth->requireRole('student');

// Set page title
$pageTitle = 'Timetable';

// Get student-specific data
try {
    // Get student info with class details
    $studentStmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, c.class_name, c.section
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
    
    // Get current week timetable for student's class
    $currentWeek = date('W');
    $currentYear = date('Y');
    
    // Get subjects for the student's class
    $subjectsStmt = $pdo->prepare("
        SELECT s.*, u.first_name as teacher_first_name, u.last_name as teacher_last_name
        FROM subjects s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE s.class_id = ?
        ORDER BY s.subject_name
    ");
    $subjectsStmt->execute([$student['class_id']]);
    $subjects = $subjectsStmt->fetchAll();
    
    // Create sample timetable data (in a real application, this would come from a timetable table)
    // For now, we'll generate a structured timetable based on the subjects
    $timetable = [];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $timeSlots = [
        '08:00 - 09:00' => 'Period 1',
        '09:00 - 10:00' => 'Period 2',
        '10:00 - 10:30' => 'Break',
        '10:30 - 11:30' => 'Period 3',
        '11:30 - 12:30' => 'Period 4',
        '12:30 - 13:30' => 'Lunch',
        '13:30 - 14:30' => 'Period 5',
        '14:30 - 15:30' => 'Period 6'
    ];
    
    // Generate timetable (this would normally come from database)
    foreach ($days as $day) {
        foreach ($timeSlots as $time => $period) {
            $timetable[$day][$time] = [
                'period' => $period,
                'subject' => null,
                'teacher' => null,
                'room' => null,
                'type' => 'empty'
            ];
        }
    }
    
    // Fill with sample data (in real app, this would be from database)
    if (!empty($subjects)) {
        $periodCount = 0;
        foreach ($subjects as $subject) {
            if ($periodCount >= 6) break; // Max 6 periods per day
            
            $timeSlot = array_keys($timeSlots)[$periodCount < 2 ? $periodCount : $periodCount + 1]; // Skip break
            if ($timeSlot && isset($timeSlots[$timeSlot])) {
                foreach ($days as $dayIndex => $day) {
                    if ($periodCount < 6) {
                        $dayOffset = ($periodCount + $dayIndex) % 5;
                        $timetable[$days[$dayOffset]][$timeSlot] = [
                            'period' => $timeSlots[$timeSlot],
                            'subject' => $subject['subject_name'],
                            'teacher' => trim(($subject['teacher_first_name'] ?? '') . ' ' . ($subject['teacher_last_name'] ?? 'Staff')),
                            'room' => 'Room ' . chr(65 + ($periodCount % 5)) . ($dayIndex + 1), // Sample room assignment
                            'type' => 'lesson'
                        ];
                    }
                }
            }
            $periodCount++;
        }
    }
    
    // Get upcoming exams (if any)
    $examsStmt = $pdo->prepare("
        SELECT e.*, COUNT(r.id) as result_count
        FROM exams e
        LEFT JOIN results r ON e.id = r.exam_id AND r.student_id = ?
        WHERE e.class_id = ? AND e.start_date >= CURDATE()
        ORDER BY e.start_date ASC
        LIMIT 5
    ");
    $examsStmt->execute([$student['id'], $student['class_id']]);
    $upcomingExams = $examsStmt->fetchAll();
    
    // Get today's schedule
    $today = date('l');
    $todaySchedule = $timetable[$today] ?? [];
    
} catch (PDOException $e) {
    error_log('Student timetable error: ' . $e->getMessage());
    $student = [];
    $subjects = [];
    $timetable = [];
    $upcomingExams = [];
    $todaySchedule = [];
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-calendar-alt"></i> Timetable
            <small class="text-muted">Class Schedule</small>
        </h1>
    </div>
</div>

<!-- Quick Info Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stats-number"><?php echo count($subjects); ?></div>
                        <div class="stats-label">Subjects</div>
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
                        <div class="stats-number">6</div>
                        <div class="stats-label">Daily Periods</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo date('l'); ?></div>
                        <div class="stats-label">Today</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-day fa-2x opacity-75"></i>
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
                        <div class="stats-number"><?php echo count($upcomingExams); ?></div>
                        <div class="stats-label">Upcoming Exams</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-list fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Schedule -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-day"></i> Today's Schedule - <?php echo date('l'); ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($todaySchedule)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($todaySchedule as $time => $slot): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo $time; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $slot['period']; ?></small>
                                        <?php if ($slot['type'] === 'lesson' && $slot['subject']): ?>
                                            <br>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($slot['subject'] ?? ''); ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($slot['teacher'] ?? 'Staff'); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($slot['room'] ?? ''); ?>
                                            </small>
                                        <?php elseif ($slot['period'] === 'Break' || $slot['period'] === 'Lunch'): ?>
                                            <br>
                                            <span class="badge bg-success"><?php echo $slot['period']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                    $currentTime = date('H:i');
                                    $slotStart = explode(' - ', $time)[0];
                                    $slotEnd = explode(' - ', $time)[1];
                                    if ($currentTime >= $slotStart && $currentTime <= $slotEnd): 
                                    ?>
                                        <span class="badge bg-warning">Now</span>
                                    <?php elseif ($currentTime < $slotStart): ?>
                                        <span class="badge bg-info">Upcoming</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Completed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No classes scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Exams -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-clipboard-list"></i> Upcoming Exams
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($upcomingExams)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingExams as $exam): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($exam['exam_name'] ?? ''); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo $exam['start_date'] ? date('M j, Y', strtotime($exam['start_date'])) : 'Date not set'; ?>
                                        </small>
                                        <?php if ($exam['end_date'] && $exam['end_date'] != $exam['start_date']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-check"></i> to <?php echo $exam['end_date'] ? date('M j, Y', strtotime($exam['end_date'])) : 'Date not set'; ?>
                                            </small>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted">
                                            Academic Year: <?php echo htmlspecialchars($exam['academic_year'] ?? ''); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php 
                                        $daysUntil = $exam['start_date'] ? ceil((strtotime($exam['start_date']) - time()) / 86400) : 0;
                                        if ($daysUntil <= 3 && $daysUntil > 0): ?>
                                            <span class="badge bg-danger"><?php echo $daysUntil; ?> days</span>
                                        <?php elseif ($daysUntil <= 7 && $daysUntil > 0): ?>
                                            <span class="badge bg-warning"><?php echo $daysUntil; ?> days</span>
                                        <?php else: ?>
                                            <span class="badge bg-info"><?php echo $daysUntil > 0 ? $daysUntil . ' days' : 'Date not set'; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Upcoming Exams</h5>
                        <p class="text-muted">Check back later for exam schedules.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Full Week Timetable -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-week"></i> Weekly Timetable
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" style="width: 120px;">Time</th>
                                <th class="text-center">Monday</th>
                                <th class="text-center">Tuesday</th>
                                <th class="text-center">Wednesday</th>
                                <th class="text-center">Thursday</th>
                                <th class="text-center">Friday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timeSlots as $time => $period): ?>
                                <tr>
                                    <td class="text-center align-middle">
                                        <strong><?php echo $time; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $period; ?></small>
                                    </td>
                                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day): ?>
                                        <td class="text-center align-middle">
                                            <?php if (isset($timetable[$day][$time])): ?>
                                                <?php $slot = $timetable[$day][$time]; ?>
                                                <?php if ($slot['type'] === 'lesson' && $slot['subject']): ?>
                                                    <div class="p-2">
                                                        <strong><?php echo htmlspecialchars($slot['subject'] ?? ''); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($slot['teacher'] ?? 'Staff'); ?></small>
                                                        <br>
                                                        <small class="text-info">
                                                            <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($slot['room'] ?? ''); ?>
                                                        </small>
                                                    </div>
                                                <?php elseif ($slot['period'] === 'Break'): ?>
                                                    <span class="badge bg-success p-2">
                                                        <i class="fas fa-coffee"></i> Break
                                                    </span>
                                                <?php elseif ($slot['period'] === 'Lunch'): ?>
                                                    <span class="badge bg-warning p-2">
                                                        <i class="fas fa-utensils"></i> Lunch
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subjects Overview -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-book"></i> Subjects Overview
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($subjects)): ?>
                    <div class="row">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-book-open fa-2x text-primary mb-2"></i>
                                        <h6 class="card-title"><?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Code: <?php echo htmlspecialchars($subject['subject_code'] ?? 'N/A'); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                Teacher: <?php echo htmlspecialchars(trim(($subject['teacher_first_name'] ?? '') . ' ' . ($subject['teacher_last_name'] ?? 'Not Assigned'))); ?>
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No subjects assigned to your class yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
