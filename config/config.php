<?php
/**
 * AK Menu System - Central bootstrap.
 * Included by every entry point. Sets up paths, session, DB (PDO), and helpers.
 */

// ---- Error handling (production: log, don't display) ------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ---- Base paths -------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('TEMPLATE_PATH', ROOT_PATH . '/templates');
define('LANG_PATH', ROOT_PATH . '/lang');
define('LIB_PATH', ROOT_PATH . '/libs');

// ---- Install guard ----------------------------------------------------------
define('INSTALL_LOCK', CONFIG_PATH . '/installed.lock');

// Compute the app base URL (works in sub-folder installs on cPanel).
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Derive the web root: strip /config, /admin, /client ... from script dir.
$__docroot = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$__docroot = preg_replace('#/(admin|client|waiter|kitchen|api|r|install|cron|config|standee|templates).*$#', '', $__docroot);
$__docroot = rtrim($__docroot, '/');
define('BASE_URL', rtrim($__scheme . '://' . $__host . $__docroot, '/'));

// If not installed yet, force the installer (except when already inside it).
if (!file_exists(INSTALL_LOCK)) {
    $inInstaller = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/install/') !== false;
    if (!$inInstaller) {
        header('Location: ' . BASE_URL . '/install/');
        exit;
    }
} else {
    require CONFIG_PATH . '/db.php';
}

// ---- App constants ----------------------------------------------------------
define('APP_NAME', 'AK Menu System');
define('APP_VERSION', '1.0.0');
define('DEFAULT_TIMEZONE', 'Asia/Kolkata');
define('DEFAULT_CURRENCY', '₹');
define('SESSION_TIMEOUT', 3600 * 4); // 4 hours idle
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);
define('POWERED_BY', 'Powered by AK Computer, Dwarka');

date_default_timezone_set(DEFAULT_TIMEZONE);

// ---- Secure session ---------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('AKMENUSESS');
    session_start();
}

// ---- PDO connection (only after install) ------------------------------------
if (defined('DB_HOST')) {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die('Database connection failed. Please check /config/db.php.');
    }
}

require CONFIG_PATH . '/functions.php';

// Apply saved timezone / idle-timeout once DB helpers are available.
if (isset($pdo)) {
    $tz = getSetting('timezone', DEFAULT_TIMEZONE);
    if ($tz) { date_default_timezone_set($tz); }
    enforceSessionTimeout();
}
