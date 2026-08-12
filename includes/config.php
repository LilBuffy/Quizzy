<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'quizzy_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'QUIZZY');
define('SESSION_DATA_LIFETIME_HOURS', 24);
define('CLEANUP_TRIGGER_PROBABILITY', 0.03);
// Change this before deploying if you expose api/cleanup.php on the internet.
define('CLEANUP_SECRET', 'quizzy_cleanup_secret_change_me');

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
