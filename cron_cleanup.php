<?php
// Run from CLI or Windows Task Scheduler, e.g.:
// C:\xampp\php\php.exe C:\xampp\htdocs\quizzy\cron_cleanup.php
require_once __DIR__ . '/includes/cleanup.php';
$removed = run_cleanup();
echo "QUIZZY cleanup complete. Sessions removed: {$removed}\n";
