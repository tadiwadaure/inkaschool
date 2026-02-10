<?php
require_once 'config/database.php';

try {
    echo "=== FEE STRUCTURE DEBUG ===\n\n";

    // Check if fee structures exist
    $stmt = $pdo->query('SELECT * FROM fee_structure ORDER BY created_at DESC LIMIT 10');
    $feeStructures = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Fee Structures in database:\n";
    if (empty($feeStructures)) {
        echo "No fee structures found in database.\n";
    } else {
        foreach ($feeStructures as $fs) {
            echo "ID: {$fs['id']}, Class: {$fs['class_id']}, Type: {$fs['fee_type']}, Amount: {$fs['amount']}, Year: {$fs['academic_year']}\n";
        }
    }

    echo "\n=== STUDENTS DEBUG ===\n\n";

    // Check students and their classes
    $stmt = $pdo->query('SELECT s.id, u.first_name, u.last_name, s.class_id, c.class_name FROM students s JOIN users u ON s.user_id = u.id LEFT JOIN classes c ON s.class_id = c.id LIMIT 5');
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Students and their classes:\n";
    foreach ($students as $student) {
        echo "Student: {$student['first_name']} {$student['last_name']}, Class ID: {$student['class_id']}, Class Name: " . ($student['class_name'] ?? 'None') . "\n";
    }

    echo "\n=== CURRENT YEAR DEBUG ===\n\n";
    echo "Current year (date('Y')): " . date('Y') . "\n";
    echo "Current year format (date('Y') . '-' . (date('Y') + 1)): " . date('Y') . '-' . (date('Y') + 1) . "\n";

    // Test student fee query with a specific student if available
    if (!empty($students) && $students[0]['class_id']) {
        echo "\n=== STUDENT FEE QUERY TEST ===\n\n";
        $testStudent = $students[0];
        $currentYear = date('Y');
        
        $stmt = $pdo->prepare("
            SELECT fs.*, 
                   COALESCE(SUM(fp.amount_paid), 0) as paid_amount,
                   COUNT(fp.id) as payment_count
            FROM fee_structure fs
            LEFT JOIN fee_payments fp ON fs.id = fp.fee_structure_id AND fp.student_id = ? AND fp.status = 'completed'
            WHERE fs.class_id = ? AND fs.academic_year = ?
            GROUP BY fs.id
            ORDER BY fs.fee_type
        ");
        $stmt->execute([$testStudent['id'], $testStudent['class_id'], $currentYear]);
        $feeDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Test query for student {$testStudent['first_name']} {$testStudent['last_name']} (Class ID: {$testStudent['class_id']}, Year: $currentYear):\n";
        if (empty($feeDetails)) {
            echo "No fee details found.\n";
            
            // Try fallback query
            echo "\nTrying fallback query (any year):\n";
            $fallbackStmt = $pdo->prepare("
                SELECT fs.*, 
                       COALESCE(SUM(fp.amount_paid), 0) as paid_amount,
                       COUNT(fp.id) as payment_count
                FROM fee_structure fs
                LEFT JOIN fee_payments fp ON fs.id = fp.fee_structure_id AND fp.student_id = ? AND fp.status = 'completed'
                WHERE fs.class_id = ?
                GROUP BY fs.id
                ORDER BY fs.academic_year DESC, fs.fee_type
                LIMIT 10
            ");
            $fallbackStmt->execute([$testStudent['id'], $testStudent['class_id']]);
            $fallbackDetails = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($fallbackDetails)) {
                echo "Fallback query also returned no results.\n";
            } else {
                foreach ($fallbackDetails as $fd) {
                    echo "Found: {$fd['fee_type']}, Amount: {$fd['amount']}, Year: {$fd['academic_year']}\n";
                }
            }
        } else {
            foreach ($feeDetails as $fd) {
                echo "Found: {$fd['fee_type']}, Amount: {$fd['amount']}, Paid: {$fd['paid_amount']}\n";
            }
        }
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
