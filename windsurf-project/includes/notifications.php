<?php
declare(strict_types=1);

/**
 * Get notifications for a specific user role
 * @param PDO $pdo Database connection
 * @param string $userRole User role (student, teacher, admin)
 * @return array Array of notifications
 */
function getNotifications(PDO $pdo, string $userRole): array {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE is_active = TRUE 
            AND (expires_at IS NULL OR expires_at > NOW())
            AND (target_audience = 'all' OR target_audience = ?)
            ORDER BY priority DESC, created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userRole]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error fetching notifications: ' . $e->getMessage());
        return [];
    }
}

/**
 * Display notifications HTML
 * @param array $notifications Array of notifications
 * @return string HTML output
 */
function displayNotifications(array $notifications): string {
    if (empty($notifications)) {
        return '';
    }
    
    $html = '<div class="row mb-4">';
    $html .= '<div class="col-12">';
    $html .= '<div class="alert alert-info alert-dismissible fade show" role="alert">';
    $html .= '<h5 class="alert-heading"><i class="fas fa-bullhorn"></i> Notices & Announcements</h5>';
    
    foreach ($notifications as $notification) {
        $priorityColors = [
            'low' => 'secondary',
            'medium' => 'info', 
            'high' => 'warning',
            'urgent' => 'danger'
        ];
        
        $html .= '<div class="mb-2">';
        $html .= '<span class="badge bg-' . $priorityColors[$notification['priority']] . ' me-2">';
        $html .= ucfirst($notification['priority']) . '</span>';
        $html .= '<strong>' . htmlspecialchars($notification['title']) . '</strong>';
        $html .= '<p class="mb-1 mt-1">' . nl2br(htmlspecialchars($notification['message'])) . '</p>';
        
        // Display image if exists
        if (!empty($notification['image_path'])) {
            $html .= '<div class="mb-2">';
            $html .= '<img src="/' . htmlspecialchars($notification['image_path']) . '" ';
            $html .= 'style="max-width: 100%; max-height: 300px; object-fit: contain;" ';
            $html .= 'class="rounded border" alt="Notification image">';
            $html .= '</div>';
        }
        
        $html .= '<small class="text-muted">Posted on ' . date('M j, Y h:i A', strtotime($notification['created_at'])) . '</small>';
        $html .= '</div>';
        
        if ($notification !== end($notifications)) {
            $html .= '<hr>';
        }
    }
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>
