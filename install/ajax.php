<?php
/**
 * AK Menu System - Installer AJAX backend.
 * Handles: license, DB test+install, admin creation, site config, self-delete.
 *
 * This file is standalone (it must run BEFORE the app config exists), so it
 * defends itself: no PHP notice/warning/fatal is ever allowed to leak into the
 * response body — every path returns clean JSON. Shared hosts often ship with
 * display_errors=On, which would otherwise corrupt the JSON the wizard expects.
 */

// ---- Force JSON-only output, no matter what PHP wants to print --------------
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

define('ROOT_PATH', dirname(__DIR__));
define('LOCK_FILE', ROOT_PATH . '/config/installed.lock');

/** Emit a JSON envelope and stop, discarding any stray buffered output. */
function out(string $status, string $msg = '', array $data = []): void {
    if (ob_get_length() !== false) { ob_end_clean(); }
    echo json_encode(['status' => $status, 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// Convert any warning/notice into a JSON error instead of HTML.
set_error_handler(function ($no, $str, $file, $line) {
    // Respect the @-operator.
    if (!(error_reporting() & $no)) { return false; }
    out('error', 'Server error: ' . $str . ' (line ' . $line . ')');
});
// Convert uncaught exceptions into JSON.
set_exception_handler(function ($e) {
    out('error', 'Server error: ' . $e->getMessage());
});
// Catch fatals via shutdown.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length() !== false) { ob_end_clean(); }
        echo json_encode(['status' => 'error', 'message' => 'Fatal: ' . $err['message'], 'data' => []], JSON_UNESCAPED_UNICODE);
    }
});

function check_csrf(): void {
    if (empty($_SESSION['inst_csrf']) || ($_POST['csrf'] ?? '') !== $_SESSION['inst_csrf']) {
        out('error', 'Invalid session token. Reload the installer.');
    }
}

/** Open a PDO connection from stored install DB settings. */
function inst_pdo(array $db): PDO {
    return new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
        $db['user'], $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

/**
 * Split a .sql dump into individual executable statements.
 * Strips line/block comments FIRST (so a CREATE preceded by a `-- ...` banner
 * is not swallowed), normalises line endings, then splits on statement
 * terminators. The seed data contains no literal `;` inside values.
 */
function split_sql(string $sql): array {
    $sql = str_replace("\r\n", "\n", $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);      // /* block comments */
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);        // -- line comments
    $parts = preg_split('/;\s*\n/', $sql);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p, " \t\n\r\0;");
        if ($p !== '') { $out[] = $p; }
    }
    return $out;
}

if (file_exists(LOCK_FILE) && ($_GET['action'] ?? '') !== 'self_delete') {
    out('error', 'Already installed.');
}
check_csrf();
$action = $_GET['action'] ?? '';

switch ($action) {

case 'license':
    $code = trim($_POST['purchase_code'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    // Offline valid keys (or a real remote check could go here).
    $validOffline = ['AKDWK-OFFLINE', 'AK-MENU-2026'];
    $ok = in_array(strtoupper($code), $validOffline, true) || preg_match('/^AK-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $code);
    if (!$ok) { out('error', 'Invalid purchase code. Use AKDWK-OFFLINE for offline activation.'); }
    $_SESSION['inst_license'] = ['code' => $code, 'domain' => $domain];
    out('success', 'License validated.', ['next' => 3]);
    break;

case 'install_db':
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $prefix = preg_replace('/[^a-z0-9_]/i', '', $_POST['db_prefix'] ?? 'ak_');

    if ($name === '' || $user === '') { out('error', 'Database name and username are required.'); }

    try {
        $pdo = inst_pdo(['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass]);
    } catch (PDOException $e) {
        out('error', 'DB connection failed: ' . $e->getMessage());
    }

    // Load schema, substitute prefix + admin hash + secrets.
    $sqlFile = __DIR__ . '/database.sql';
    if (!is_readable($sqlFile)) { out('error', 'database.sql not found in /install.'); }
    $sql = file_get_contents($sqlFile);
    $adminHash  = password_hash('Admin@123', PASSWORD_DEFAULT); // replaced in step 4
    $cronSecret = bin2hex(random_bytes(16));
    $sql = str_replace(['{PREFIX}', '{ADMIN_HASH}', '{CRON_SECRET}'], [$prefix, $adminHash, $cronSecret], $sql);

    $statements = split_sql($sql);
    $done = 0;
    try {
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
            $done++;
        }
    } catch (PDOException $e) {
        out('error', 'SQL error after ' . $done . ' statements: ' . $e->getMessage());
    }

    if ($done < 20) {
        out('error', "Only $done statements executed — schema import looks incomplete. Check the database is empty and retry.");
    }

    // Persist DB config to session; real db.php is written at finish.
    $_SESSION['inst_db'] = compact('host', 'name', 'user', 'pass', 'prefix');
    out('success', "Installed $done statements.", ['next' => 4, 'progress' => true, 'log' => "Executed $done SQL statements successfully."]);
    break;

case 'create_admin':
    if (empty($_SESSION['inst_db'])) { out('error', 'Run database setup first.'); }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $p1 = $_POST['password'] ?? ''; $p2 = $_POST['password2'] ?? '';
    if ($name === '') { out('error', 'Name is required.'); }
    if (strlen($p1) < 6) { out('error', 'Password must be at least 6 characters.'); }
    if ($p1 !== $p2) { out('error', 'Passwords do not match.'); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { out('error', 'Invalid email.'); }

    try {
        $db = $_SESSION['inst_db'];
        $pdo = inst_pdo($db);
        $t = $db['prefix'] . 'super_admins';
        // Replace the seeded placeholder admin with the real one.
        $pdo->exec('DELETE FROM `' . $t . '`');
        $stmt = $pdo->prepare('INSERT INTO `' . $t . '` (name,email,password,mobile,status) VALUES (?,?,?,?,1)');
        $stmt->execute([$name, $email, password_hash($p1, PASSWORD_DEFAULT), $mobile]);
    } catch (PDOException $e) {
        out('error', 'Could not create admin: ' . $e->getMessage());
    }
    $_SESSION['inst_admin'] = ['name' => $name, 'email' => $email];
    out('success', 'Admin created.', ['next' => 5]);
    break;

case 'site_config':
    if (empty($_SESSION['inst_db'])) { out('error', 'Run database setup first.'); }
    try {
        $db = $_SESSION['inst_db'];
        $pdo = inst_pdo($db);

        $map = [
            'site_name' => trim($_POST['site_name'] ?? 'AK Menu System'),
            'site_url' => trim($_POST['site_url'] ?? ''),
            'timezone' => trim($_POST['timezone'] ?? 'Asia/Kolkata'),
            'currency' => trim($_POST['currency'] ?? '₹'),
            'default_language' => in_array($_POST['default_language'] ?? 'en', ['en', 'gu']) ? $_POST['default_language'] : 'en',
        ];
        $upd = $pdo->prepare('INSERT INTO `' . $db['prefix'] . 'settings` (setting_key,setting_value) VALUES (?,?)
                              ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ($map as $k => $v) { $upd->execute([$k, $v]); }

        // ---- Write /config/db.php ----
        $dbphp = "<?php defined('ROOT_PATH') or exit('Direct access denied');\n"
            . "// Auto-generated by AK Menu System installer. Do not edit manually.\n"
            . "define('DB_HOST', " . var_export($db['host'], true) . ");\n"
            . "define('DB_NAME', " . var_export($db['name'], true) . ");\n"
            . "define('DB_USER', " . var_export($db['user'], true) . ");\n"
            . "define('DB_PASS', " . var_export($db['pass'], true) . ");\n"
            . "define('DB_PREFIX', " . var_export($db['prefix'], true) . ");\n"
            . "define('DB_CHARSET', 'utf8mb4');\n";
        if (@file_put_contents(ROOT_PATH . '/config/db.php', $dbphp) === false) {
            out('error', 'Could not write /config/db.php — make the /config folder writable (0755).');
        }
        @chmod(ROOT_PATH . '/config/db.php', 0644);

        // ---- Store APP_VERSION from version.json ----
        $ver = json_decode(@file_get_contents(ROOT_PATH . '/version.json'), true)['version'] ?? '1.0.0';
        $upd->execute(['app_version', $ver]);

        // ---- Lock file ----
        if (@file_put_contents(LOCK_FILE, 'installed ' . date('c') . " v$ver\n") === false) {
            out('error', 'Could not write installed.lock — make the /config folder writable (0755).');
        }
        @chmod(LOCK_FILE, 0644);
    } catch (Throwable $e) {
        out('error', 'Finish step failed: ' . $e->getMessage());
    }

    out('success', 'Installation finished.', ['next' => 6]);
    break;

case 'self_delete':
    // Recursively delete the /install directory.
    $dir = __DIR__;
    $rrmdir = function ($path) use (&$rrmdir) {
        foreach (scandir($path) as $f) {
            if ($f === '.' || $f === '..') continue;
            $full = $path . '/' . $f;
            is_dir($full) ? $rrmdir($full) : @unlink($full);
        }
        @rmdir($path);
    };
    $rrmdir($dir);
    out('success', 'Install folder deleted. Redirecting to admin panel.');
    break;

default:
    out('error', 'Unknown action.');
}
