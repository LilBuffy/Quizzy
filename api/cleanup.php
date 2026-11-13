<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cleanup.php';

header('Content-Type: application/json; charset=utf-8');

$secret = $_GET['secret'] ?? $_POST['secret'] ?? '';
if (!hash_equals(CLEANUP_SECRET, (string)$secret)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$removed = run_cleanup();
echo json_encode(['ok' => true, 'sessions_removed' => $removed]);
