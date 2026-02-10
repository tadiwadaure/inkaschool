<?php
require_once 'config/database.php';

// Simulate student session (assuming student ID 1)
$_SESSION['user_id'] = 3; // This should be a user_id from the users table with student role

echo "=== STUDENT FEES PAGE SIMULATION ===\n\n";

try {
    // Get student info (same as in student/fees.php)
    $studentStmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, u.email, c.class_name, c.section
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE u.id = ?
    ");
    $studentStmt->execute([$_SESSION['user_id']]);
    $student = $studentStmt->fetch();
    
    echo "Student Info:\n";
    if ($student) {
        echo "Name: {$student['first_name']} {$student['last_name']}\n";
        echo "Class: {$student['class_name']} (ID: {$student['class_id']})\n";
        echo "Student ID: {$student['id']}\n";
    } else {
        echo "Student not found for user_id: {$_SESSION['user_id']}\n";
        exit;
    }
    
    // Get fee information (same as in student/fees.php)
    $feeInfo = ['pending_fees' => 0, 'total_paid' => 0, 'total_expected' => 0, 'fee_details' => []];
    if ($student && $student['class_id']) {
        // Get fee structure for student's class
        $feeStmt = $pdo->prepare("
            SELECT fs.*, 
                   COALESCE(SUM(fp.amount_paid), 0) as paid_amount,
                   COUNT(fp.id) as payment_count
            FROM fee_structure fs
            LEFT JOIN fee_payments fp ON fs.id = fp.fee_structure_id AND fp.student_id = ? AND fp.status = 'completed'
            WHERE fs.class_id = ? AND fs.academic_year = ?
            GROUP BY fs.id
            ORDER BY fs.fee_type
        ");
        $currentYear = date('Y');
        $feeStmt->execute([$student['id'], $student['class_id'], $currentYear]);
        $feeDetails = $feeStmt->fetchAll();
        
        echo "\nFee Details Found: " . count($feeDetails) . " items\n";
        
        $totalExpected = 0;
        $totalPaid = 0;
        
        foreach ($feeDetails as $fee) {
            echo "- {$fee['fee_type']}: {$fee['amount']} (Paid: {$fee['paid_amount']})\n";
            $totalExpected += $fee['amount'];
            $totalPaid += $fee['paid_amount'];
        }
        
        $feeInfo = [
            'pending_fees' => $totalExpected - $totalPaid,
            'total_paid' => $totalPaid,
            'total_expected' => $totalExpected,
            'fee_details' => $feeDetails
        ];
        
        echo "\nSummary:\n";
        echo "Total Expected: {$feeInfo['total_expected']}\n";
        echo "Total Paid: {$feeInfo['total_paid']}\n";
        echo "Pending Fees: {$feeInfo['pending_fees']}\n";
    } else {
        echo "No class assigned to student or student not found.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== CHECKING USER ROLES ===\n";

// Check user roles
$stmt = $pdo->query("SELECT id, first_name, last_name, role FROM users WHERE role = 'student' LIMIT 5");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as $user) {
    echo "User ID: {$user['id']}, Name: {$user['first_name']} {$user['last_name']}, Role: {$user['role']}\n";
}
?>
