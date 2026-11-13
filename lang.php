<?php
require_once __DIR__ . '/includes/functions.php';

$lang = (string)($_GET['lang'] ?? 'en');
if (!in_array($lang, ['en', 'fil'], true)) $lang = 'en';
$_SESSION['lang'] = $lang;

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === $host) {
    redirect($referer);
}
redirect('index.php');
