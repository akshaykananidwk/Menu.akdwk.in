<?php
/**
 * AK Menu System - Installer AJAX backend.
 * Handles: license, DB test+install, admin creation, site config, self-delete.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
define('ROOT_PATH', dirname(__DIR__));
define('LOCK_FILE', ROOT_PATH . '/config/installed.lock');

function out(string $status, string $msg = '', array $data = []): void {
    echo json_encode(['status' => $status, 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function check_csrf(): void {
    if (empty($_SESSION['inst_csrf']) || ($_POST['csrf'] ?? '') !== $_SESSION['inst_csrf']) {
        out('error', 'Invalid session token. Reload the installer.');
    }
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

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        out('error', 'DB connection failed: ' . $e->getMessage());
    }

    // Load schema, substitute prefix + admin hash + secrets.
    $sql = file_get_contents(__DIR__ . '/database.sql');
    $adminHash = password_hash('Admin@123', PASSWORD_DEFAULT); // placeholder; real admin set in step 4
    $cronSecret = bin2hex(random_bytes(16));
    $sql = str_replace(['{PREFIX}', '{ADMIN_HASH}', '{CRON_SECRET}'], [$prefix, $adminHash, $cronSecret], $sql);

    // Execute statement-by-statement for a progress feel.
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql)));
    $done = 0; $total = count($statements); $log = '';
    try {
        foreach ($statements as $stmt) {
            if ($stmt === '' || str_starts_with($stmt, '--')) continue;
            $pdo->exec($stmt);
            $done++;
        }
    } catch (PDOException $e) {
        out('error', 'SQL error: ' . $e->getMessage());
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
    if (strlen($p1) < 6) { out('error', 'Password must be at least 6 characters.'); }
    if ($p1 !== $p2) { out('error', 'Passwords do not match.'); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { out('error', 'Invalid email.'); }

    $db = $_SESSION['inst_db'];
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // Replace the seeded placeholder admin with the real one.
    $pdo->exec('DELETE FROM `' . $db['prefix'] . 'super_admins`');
    $stmt = $pdo->prepare('INSERT INTO `' . $db['prefix'] . 'super_admins` (name,email,password,mobile,status) VALUES (?,?,?,?,1)');
    $stmt->execute([$name, $email, password_hash($p1, PASSWORD_DEFAULT), $mobile]);
    $_SESSION['inst_admin'] = ['name' => $name, 'email' => $email];
    out('success', 'Admin created.', ['next' => 5]);
    break;

case 'site_config':
    if (empty($_SESSION['inst_db'])) { out('error', 'Run database setup first.'); }
    $db = $_SESSION['inst_db'];
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

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
    file_put_contents(ROOT_PATH . '/config/db.php', $dbphp);
    @chmod(ROOT_PATH . '/config/db.php', 0644);

    // ---- Store APP_VERSION from version.json ----
    $ver = json_decode(@file_get_contents(ROOT_PATH . '/version.json'), true)['version'] ?? '1.0.0';
    $upd->execute(['app_version', $ver]);

    // ---- Lock file ----
    file_put_contents(LOCK_FILE, 'installed ' . date('c') . " v$ver\n");
    @chmod(LOCK_FILE, 0644);

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
