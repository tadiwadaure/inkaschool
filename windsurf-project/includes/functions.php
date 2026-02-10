<?php
declare(strict_types=1);

/**
 * Default function for custom head content
 * Can be overridden in individual pages
 */
function custom_head_content(): void {
    // Default is empty, can be overridden in page controllers
}

/**
 * Generate a secure random token
 * @param int $length Length of the token
 * @return string Random token
 */
function generate_token(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Redirect to a URL
 * @param string $url URL to redirect to
 * @param int $statusCode HTTP status code
 */
function redirect(string $url, int $statusCode = 302): void {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

/**
 * Get the current URL
 * @return string Current URL
 */
function current_url(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Check if the request is an AJAX request
 * @return bool True if AJAX request
 */
function is_ajax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP address
 * @return string IP address
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Check for shared internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } 
    // Check for IPs passing through proxies
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // Sometimes the IP is an array
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ?: '127.0.0.1';
}

/**
 * Log an error message
 * @param string $message Error message
 * @param array $context Additional context data
 */
function log_error(string $message, array $context = []): void {
    $logEntry = sprintf(
        "[%s] %s %s" . PHP_EOL,
        date('Y-m-d H:i:s'),
        $message,
        !empty($context) ? json_encode($context, JSON_PRETTY_PRINT) : ''
    );
    
    $logFile = __DIR__ . '/../logs/error-' . date('Y-m-d') . '.log';
    
    // Create logs directory if it doesn't exist
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
