<?php
/**
 * Cobalt Strike C2 Server Endpoint Simulator
 * 
 * This PHP script simulates a Cobalt Strike C2 server endpoint that receives
 * beacon check-ins and returns appropriate responses. Use this for testing
 * NDR solutions in a controlled environment.
 * 
 * Usage: Deploy on a web server (Apache/Nginx with PHP) or use PHP's built-in server:
 *   php -S 0.0.0.0:8080 submit.php
 */

// Log request details for debugging (optional - comment out in production testing)
error_log("=== Cobalt Strike Beacon Check-in ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("URI: " . $_SERVER['REQUEST_URI']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    exit('Not Found');
}

// Read the POST body (beacon data)
$body = file_get_contents('php://input');
$body_length = strlen($body);

// Log beacon metadata
if (isset($_SERVER['HTTP_COOKIE'])) {
    error_log("Cookie: " . substr($_SERVER['HTTP_COOKIE'], 0, 50) . "...");
}
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    error_log("User-Agent: " . $_SERVER['HTTP_USER_AGENT']);
}
error_log("Payload length: " . $body_length . " bytes");

// Check for Cobalt Strike-like patterns
if ($body_length > 0) {
    $first_bytes = substr($body, 0, 4);
    $hex_preview = bin2hex($first_bytes);
    error_log("First 4 bytes (hex): " . $hex_preview);
}

// Simulate C2 server response
// Real Cobalt Strike servers may return:
// - Empty response (no tasks)
// - Encrypted task data
// - Binary response with commands

// For realistic NDR testing, return a small binary response
// that mimics an encrypted "no tasks" response
http_response_code(200);
header('Content-Type: application/octet-stream');
header('Connection: close');

// Generate a realistic C2 response (4-16 bytes of "encrypted" data)
// In real Cobalt Strike, this would be encrypted commands or acknowledgment
$response_size = rand(4, 16);
$response = random_bytes($response_size);

echo $response;

// Log the response
error_log("Response sent: " . $response_size . " bytes");
error_log("=====================================");
?>
