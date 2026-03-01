<?php

/**
 * @file
 * Health check endpoint for container orchestration.
 */

// Simple health check - verify basic functionality
$health_checks = [];

// Check if we can load Drupal bootstrap
try {
    $autoloader = require_once 'autoload.php';
    \Drupal\Core\DrupalKernel::createFromRequest(
        \Symfony\Component\HttpFoundation\Request::createFromGlobals(),
        $autoloader,
        'prod',
        FALSE
    );
    $health_checks['bootstrap'] = 'OK';
} catch (Exception $e) {
    $health_checks['bootstrap'] = 'FAILED: ' . $e->getMessage();
    http_response_code(503);
}

// Check database connectivity
try {
    $database = \Drupal::database();
    $database->query('SELECT 1')->fetchField();
    $health_checks['database'] = 'OK';
} catch (Exception $e) {
    $health_checks['database'] = 'FAILED: ' . $e->getMessage();
    http_response_code(503);
}

// Check cache system
try {
    $cache = \Drupal::cache();
    $cache->get('health_check_test');
    $health_checks['cache'] = 'OK';
} catch (Exception $e) {
    $health_checks['cache'] = 'FAILED: ' . $e->getMessage();
    http_response_code(503);
}

// Return results
header('Content-Type: application/json');
echo json_encode([
    'status' => http_response_code() === 200 ? 'healthy' : 'unhealthy',
    'timestamp' => date('c'),
    'checks' => $health_checks,
], JSON_PRETTY_PRINT);

exit();