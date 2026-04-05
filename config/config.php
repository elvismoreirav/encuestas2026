<?php

if (!defined('ENCUESTAS2026')) {
    exit('Acceso no permitido');
}

define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV !== 'production');
define('APP_NAME', 'Shalom Encuestas');
define('APP_VERSION', '1.0.0');

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CORE_PATH', ROOT_PATH . '/core');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('DATABASE_PATH', ROOT_PATH . '/database');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'encuestas2026');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '12345678');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_NAME', 'shalom_encuestas_session');
define('SESSION_LIFETIME', 7200);
define('SESSION_SECURE', APP_ENV === 'production');
define('SESSION_HTTPONLY', true);
define('CSRF_TOKEN_NAME', '_token');
define('TIMEZONE', 'America/Guayaquil');

date_default_timezone_set(TIMEZONE);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$rootPath = str_replace('\\', '/', realpath(ROOT_PATH) ?: ROOT_PATH);

if ($documentRoot !== '' && str_starts_with($rootPath, $documentRoot)) {
    $basePath = substr($rootPath, strlen($documentRoot));
} else {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $projectDir = basename(ROOT_PATH);
    if (preg_match('#^(.*/' . preg_quote($projectDir, '#') . ')#', $scriptName, $matches)) {
        $basePath = $matches[1];
    } else {
        $basePath = dirname($scriptName);
        if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
            $basePath = '';
        }
    }
}

$basePath = rtrim($basePath, '/');

define('BASE_URL', $protocol . '://' . $host . $basePath);
define('ASSETS_URL', BASE_URL . '/assets');

define('COLOR_PRIMARY', '#1e4d39');
define('COLOR_PRIMARY_DARK', '#163a2b');
define('COLOR_PRIMARY_LIGHT', '#2a6b4f');
define('COLOR_SECONDARY', '#f9f8f4');
define('COLOR_ACCENT', '#A3B7A5');
define('COLOR_MUTED', '#73796F');
define('COLOR_GOLD', '#D6C29A');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

ini_set('log_errors', '1');
ini_set('error_log', LOGS_PATH . '/php_errors.log');
