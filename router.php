<?php
// Simple router for Render's PHP built-in server.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/v1/chat' || $path === '/v1/chat/') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('ok' => false, 'error' => 'not_found'));
