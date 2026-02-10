<?php
declare(strict_types=1);

/**
 * Layout utilities and rendering functions
 */

// Include functions for custom_head_content
require_once __DIR__ . '/functions.php';

/**
 * Render page header
 * @param string $pageTitle Page title
 */
function render_page_header(string $pageTitle = 'School Management System'): void {
    global $pageTitle;
    if (!isset($pageTitle)) {
        $pageTitle = $pageTitle;
    }
    require_once __DIR__ . '/header.php';
}

/**
 * Render page footer
 */
function render_page_footer(): void {
    require_once __DIR__ . '/footer.php';
}

/**
 * Render a complete page layout
 * @param string $pageTitle Page title
 * @param callable $content Function that renders page content
 */
function render_page(string $pageTitle, callable $content): void {
    global $pageTitle;
    $pageTitle = $pageTitle;
    
    render_header($pageTitle);
    $content();
    render_footer();
}

/**
 * Get navigation menu items based on user role
 * @return array Navigation items
 */
function get_navigation_items(): array {
    if (!isset($_SESSION['role'])) {
        return [];
    }
    
    $role = $_SESSION['role'];
    
    $navItems = [
        'admin' => [
            ['url' => '/admin/dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
            ['url' => '/admin/classes.php', 'icon' => 'fas fa-chalkboard', 'label' => 'Classes'],
            ['url' => '/admin/exams.php', 'icon' => 'fas fa-clipboard-list', 'label' => 'Exams'],
            ['url' => '/admin/exam_timetable.php', 'icon' => 'fas fa-calendar-alt', 'label' => 'Exam Timetable'],
            ['url' => '/admin/students.php', 'icon' => 'fas fa-user-graduate', 'label' => 'Students'],
            ['url' => '/admin/teachers.php', 'icon' => 'fas fa-chalkboard-teacher', 'label' => 'Teachers'],
            ['url' => '/admin/subjects.php', 'icon' => 'fas fa-book', 'label' => 'Subjects'],
            ['url' => '/admin/timetable.php', 'icon' => 'fas fa-calendar', 'label' => 'Timetable'],
            ['url' => '/admin/reports.php', 'icon' => 'fas fa-chart-bar', 'label' => 'Reports'],
        ],
        'teacher' => [
            ['url' => '/teacher/dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
            ['url' => '/teacher/exams.php', 'icon' => 'fas fa-clipboard-list', 'label' => 'Exams'],
            ['url' => '/teacher/results.php', 'icon' => 'fas fa-chart-line', 'label' => 'Results'],
            ['url' => '/teacher/edit_result.php', 'icon' => 'fas fa-edit', 'label' => 'Edit Results'],
            ['url' => '/teacher/timetable.php', 'icon' => 'fas fa-calendar', 'label' => 'Timetable'],
            ['url' => '/teacher/subject_results.php', 'icon' => 'fas fa-book-open', 'label' => 'Subject Results'],
        ],
        'student' => [
            ['url' => '/student/dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
            ['url' => '/student/fees.php', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Fees'],
            ['url' => '/student/results.php', 'icon' => 'fas fa-chart-line', 'label' => 'Results'],
            ['url' => '/student/timetable.php', 'icon' => 'fas fa-calendar', 'label' => 'Timetable'],
            ['url' => '/student/profile.php', 'icon' => 'fas fa-user', 'label' => 'Profile'],
        ],
        'accountant' => [
            ['url' => '/accountant/dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
            ['url' => '/accountant/fees.php', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Fees'],
            ['url' => '/accountant/fee_structure.php', 'icon' => 'fas fa-list-alt', 'label' => 'Fee Structure'],
            ['url' => '/accountant/dues.php', 'icon' => 'fas fa-exclamation-triangle', 'label' => 'Dues'],
            ['url' => '/accountant/reports.php', 'icon' => 'fas fa-chart-bar', 'label' => 'Reports'],
        ],
    ];
    
    return $navItems[$role] ?? [];
}

/**
 * Check if a navigation item is active
 * @param string $url URL to check
 * @return bool True if active
 */
function is_nav_active(string $url): bool {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return $currentPath === $url;
}

/**
 * Generate breadcrumb navigation
 * @param array $breadcrumbs Breadcrumb items
 * @return string HTML breadcrumb navigation
 */
function generate_breadcrumb(array $breadcrumbs): string {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    foreach ($breadcrumbs as $index => $breadcrumb) {
        $isActive = $index === count($breadcrumbs) - 1;
        
        if ($isActive) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($breadcrumb['label']) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($breadcrumb['url']) . '">' . htmlspecialchars($breadcrumb['label']) . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Generate page title with breadcrumb
 * @param string $title Page title
 * @param array $breadcrumbs Optional breadcrumbs
 * @return string HTML title section
 */
function page_header(string $title, array $breadcrumbs = []): string {
    $html = '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">';
    $html .= '<h1 class="h2">' . htmlspecialchars($title) . '</h1>';
    
    if (!empty($breadcrumbs)) {
        $html .= generate_breadcrumb($breadcrumbs);
    }
    
    $html .= '</div>';
    return $html;
}
