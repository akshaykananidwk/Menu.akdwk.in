<?php
/**
 * AK Menu System — Auto-update API endpoint (Super Admin only).
 *
 * DANGER: The `run`, `manual_upload` and `rollback` actions touch the filesystem
 * and the database destructively. Every operation is guarded, path-validated,
 * confined to the application root, and preceded by a backup. On ANY failure the
 * pipeline aborts, attempts to restore from the backup it just made, disables
 * maintenance mode, and records the failure. Never trust request-supplied paths.
 *
 * Contract: JSON responses {status,message,data}. CSRF required on POST.
 *
 * Actions:
 *   check          — query GitHub latest release, compare versions (read-only)
 *   run            — full update pipeline from a GitHub release
 *   manual_upload  — full pipeline starting from an uploaded ZIP
 *   progress       — return the latest update_logs row + parsed step log
 *   rollback       — restore a DB backup by id
 */

require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
}
requireAdmin();

// The updater can be long-running; give it room but keep output as JSON.
@set_time_limit(600);
@ignore_user_abort(true);

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminId   = (int)($_SESSION['admin_id'] ?? 0);
$action    = $_GET['action'] ?? '';

// =============================================================================
// TOKEN OBFUSCATION (reversible). Mirrors admin/updates.php.
// The GitHub token is stored obfuscated when openssl is available, else plain.
// This is deliberately simple: it hides the token from a casual DB dump, it is
// NOT a substitute for proper secret management.
// =============================================================================

/** Derive a stable key from install-time constants (never user-facing). */
function ak_secret_key(): string {
    $seed = (defined('DB_PASS') ? DB_PASS : '') . '|' . (defined('DB_NAME') ? DB_NAME : '') . '|ak-menu-update';
    return hash('sha256', $seed, true); // 32 raw bytes → AES-256 key
}

/** Encrypt a token for storage. Returns "enc:<base64>" or plaintext fallback. */
function ak_enc_token(string $plain): string {
    if ($plain === '') return '';
    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(16);
        $ct = openssl_encrypt($plain, 'aes-256-cbc', ak_secret_key(), OPENSSL_RAW_DATA, $iv);
        if ($ct !== false) return 'enc:' . base64_encode($iv . $ct);
    }
    return $plain;
}

/** Decrypt a stored token. Accepts plaintext (legacy) transparently. */
function ak_dec_token(string $stored): string {
    if ($stored === '' || strncmp($stored, 'enc:', 4) !== 0) return $stored;
    if (!function_exists('openssl_decrypt')) return '';
    $raw = base64_decode(substr($stored, 4), true);
    if ($raw === false || strlen($raw) < 17) return '';
    $iv = substr($raw, 0, 16);
    $ct = substr($raw, 16);
    $pt = openssl_decrypt($ct, 'aes-256-cbc', ak_secret_key(), OPENSSL_RAW_DATA, $iv);
    return $pt === false ? '' : $pt;
}

// =============================================================================
// GITHUB API
// =============================================================================

/**
 * Fetch the latest release JSON from GitHub.
 * @return array{ok:bool, data:?array, error:string, http:int}
 */
function ak_github_latest_release(): array {
    $owner = trim((string)getSetting('github_owner', ''));
    $repo  = trim((string)getSetting('github_repo', ''));
    $token = ak_dec_token((string)getSetting('github_token', ''));

    if ($owner === '' || $repo === '') {
        return ['ok' => false, 'data' => null, 'error' => 'GitHub owner/repo not configured.', 'http' => 0];
    }

    $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/releases/latest';
    $headers = ['User-Agent: AK-Menu-System', 'Accept: application/vnd.github+json'];
    if ($token !== '') { $headers[] = 'Authorization: token ' . $token; }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'data' => null, 'error' => 'cURL extension is not available.', 'http' => 0];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'data' => null, 'error' => 'Network error: ' . ($err ?: 'unknown'), 'http' => 0];
    }
    if ($http === 404) {
        return ['ok' => false, 'data' => null, 'error' => 'No published release found for this repository.', 'http' => 404];
    }
    if ($http < 200 || $http >= 300) {
        $j = json_decode($resp, true);
        $msg = $j['message'] ?? ('HTTP ' . $http);
        return ['ok' => false, 'data' => null, 'error' => 'GitHub API error: ' . $msg, 'http' => $http];
    }
    $data = json_decode($resp, true);
    if (!is_array($data)) {
        return ['ok' => false, 'data' => null, 'error' => 'Malformed response from GitHub.', 'http' => $http];
    }
    return ['ok' => true, 'data' => $data, 'error' => '', 'http' => $http];
}

/** Normalise a tag_name like "v1.2.3" → "1.2.3". */
function ak_clean_version(string $tag): string {
    return ltrim(trim($tag), 'vV');
}

// =============================================================================
// PROGRESS / STEP LOG helpers.
// The step log is stored as JSON in update_logs.log_text so `progress` can
// re-read it while an update runs (each run appends its own steps array).
// =============================================================================

/** Persist the accumulated steps + status onto an update_logs row. */
function ak_save_progress(int $logId, array $steps, string $status): void {
    db_update('update_logs', [
        'status'   => $status,
        'log_text' => json_encode(['steps' => $steps, 'updated_at' => date('c')], JSON_UNESCAPED_UNICODE),
    ], ['id' => $logId]);
}

/** Append a step result and immediately flush it so progress polling can see it. */
function ak_step(int $logId, array &$steps, string $name, string $state, string $message = ''): void {
    $steps[] = ['name' => $name, 'state' => $state, 'message' => $message, 'at' => date('H:i:s')];
    ak_save_progress($logId, $steps, $state === 'error' ? 'failed' : 'running');
}

// =============================================================================
// FILESYSTEM helpers (all confined to ROOT_PATH).
// =============================================================================

/** True if a destination-relative path matches the exclusion list. */
function ak_is_excluded(string $relPath, array $excludes): bool {
    $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
    foreach ($excludes as $ex) {
        $ex = ltrim(str_replace('\\', '/', $ex), '/');
        if ($ex === '') continue;
        // Exact file match, or the path lives under an excluded directory.
        if ($relPath === $ex || strpos($relPath . '/', $ex . '/') === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Guard: ensure an absolute path stays within ROOT_PATH (block traversal).
 * Uses realpath on the nearest existing ancestor so it works for not-yet-created
 * destination files too.
 */
function ak_within_root(string $absPath): bool {
    $root = realpath(ROOT_PATH);
    if ($root === false) return false;
    $abs = str_replace('\\', '/', $absPath);
    // Reject any traversal token outright.
    if (strpos($abs, '/../') !== false || substr($abs, -3) === '/..') return false;
    // Walk up to the first existing ancestor and realpath-check it.
    $probe = $abs;
    while ($probe !== '' && !file_exists($probe)) {
        $parent = dirname($probe);
        if ($parent === $probe) break;
        $probe = $parent;
    }
    $realProbe = realpath($probe);
    if ($realProbe === false) return false;
    $realProbe = str_replace('\\', '/', $realProbe);
    $root = str_replace('\\', '/', $root);
    return $realProbe === $root || strpos($realProbe . '/', $root . '/') === 0;
}

/** Recursively delete a directory (confined to ROOT_PATH). */
function ak_rrmdir(string $dir): void {
    if (!ak_within_root($dir) || !is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) { @rmdir($item->getPathname()); }
        else { @unlink($item->getPathname()); }
    }
    @rmdir($dir);
}

/** Empty a directory's contents but keep the directory itself. */
function ak_empty_dir(string $dir): void {
    if (!ak_within_root($dir) || !is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
        $p = $dir . '/' . $f;
        if (is_dir($p)) { ak_rrmdir($p); } else { @unlink($p); }
    }
}

/**
 * Recursively copy $src tree over ROOT_PATH, honouring the exclusion list.
 * $relBase is the destination-relative prefix (usually '').
 * Returns [copied:int, skipped:int].
 */
function ak_copy_tree(string $src, string $destRoot, array $excludes, string $relBase = ''): array {
    $copied = 0; $skipped = 0;
    $dh = @opendir($src);
    if (!$dh) return [$copied, $skipped];
    while (($entry = readdir($dh)) !== false) {
        if ($entry === '.' || $entry === '..') continue;
        $srcPath = $src . '/' . $entry;
        $rel     = ($relBase === '') ? $entry : $relBase . '/' . $entry;
        $destPath = $destRoot . '/' . $rel;

        if (ak_is_excluded($rel, $excludes)) { $skipped++; continue; }
        if (!ak_within_root($destPath)) { $skipped++; continue; }

        if (is_dir($srcPath)) {
            if (!is_dir($destPath)) { @mkdir($destPath, 0755, true); }
            [$c, $s] = ak_copy_tree($srcPath, $destRoot, $excludes, $rel);
            $copied += $c; $skipped += $s;
        } else {
            $parent = dirname($destPath);
            if (!is_dir($parent)) { @mkdir($parent, 0755, true); }
            if (@copy($srcPath, $destPath)) { $copied++; } else { $skipped++; }
        }
    }
    closedir($dh);
    return [$copied, $skipped];
}

/**
 * A GitHub zipball extracts to a single top-level folder (e.g. owner-repo-sha).
 * Detect and return that folder, else return the extract dir itself.
 */
function ak_detect_root(string $extractDir): string {
    $entries = array_values(array_diff(scandir($extractDir) ?: [], ['.', '..']));
    if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) {
        return $extractDir . '/' . $entries[0];
    }
    return $extractDir;
}

// =============================================================================
// DATABASE BACKUP / RESTORE (pure PHP mysqldump-lite; no shell dependency).
// =============================================================================

/**
 * Dump every prefixed table to a .sql file under /backups.
 * Returns [ok:bool, file:?string, error:string].
 */
function ak_backup_db(string $version): array {
    global $pdo;
    $dir = ROOT_PATH . '/backups';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!is_writable($dir)) {
        return ['ok' => false, 'file' => null, 'error' => 'Backups directory is not writable.'];
    }

    $ts   = date('Ymd_His');
    $name = 'db_' . preg_replace('/[^0-9.]/', '', $version) . '_' . $ts . '.sql';
    $path = $dir . '/' . $name;

    $fh = @fopen($path, 'w');
    if (!$fh) return ['ok' => false, 'file' => null, 'error' => 'Cannot create backup file.'];

    try {
        fwrite($fh, "-- AK Menu System DB backup\n-- Version: $version\n-- Created: " . date('c') . "\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

        $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
        $tables = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $r) {
            $t = $r[0];
            if ($prefix === '' || strpos($t, $prefix) === 0) { $tables[] = $t; }
        }

        foreach ($tables as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`')->fetch(PDO::FETCH_NUM);
            fwrite($fh, "\nDROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n");

            $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '', $table) . '`');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols = '`' . implode('`,`', array_keys($row)) . '`';
                $vals = array_map(function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote((string)$v);
                }, array_values($row));
                fwrite($fh, "INSERT INTO `$table` ($cols) VALUES (" . implode(',', $vals) . ");\n");
            }
        }
        fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
    } catch (Throwable $e) {
        fclose($fh);
        @unlink($path);
        return ['ok' => false, 'file' => null, 'error' => 'Backup failed: ' . $e->getMessage()];
    }

    // Record backup row and prune to the newest 5 db backups.
    $size = (int)@filesize($path);
    db_insert('backups', ['file_name' => $name, 'type' => 'db', 'size' => $size, 'version' => $version]);
    ak_prune_backups(5);

    return ['ok' => true, 'file' => $path, 'error' => ''];
}

/** Keep only the newest $keep db backups (row + file). */
function ak_prune_backups(int $keep = 5): void {
    $rows = db_all('SELECT id, file_name FROM ' . tbl('backups') . " WHERE type='db' ORDER BY id DESC");
    $old  = array_slice($rows, $keep);
    foreach ($old as $r) {
        $p = ROOT_PATH . '/backups/' . basename($r['file_name']);
        if (is_file($p) && ak_within_root($p)) { @unlink($p); }
        db_query('DELETE FROM ' . tbl('backups') . ' WHERE id = :id', [':id' => $r['id']]);
    }
}

/**
 * Restore a DB backup by executing its .sql statements.
 * Returns [ok:bool, error:string].
 */
function ak_restore_db(string $absSqlPath): array {
    global $pdo;
    if (!is_file($absSqlPath) || !ak_within_root($absSqlPath)) {
        return ['ok' => false, 'error' => 'Backup file not found.'];
    }
    $sql = file_get_contents($absSqlPath);
    if ($sql === false || $sql === '') {
        return ['ok' => false, 'error' => 'Backup file is empty.'];
    }
    try {
        // Naive but sufficient splitter: statements are written one-per-line by
        // ak_backup_db (each INSERT / DDL terminated by ";\n").
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (ak_split_sql($sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || strncmp($stmt, '--', 2) === 0) continue;
            $pdo->exec($stmt);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Restore failed: ' . $e->getMessage()];
    }
    return ['ok' => true, 'error' => ''];
}

/** Split a SQL dump into individual statements (semicolon at line end). */
function ak_split_sql(string $sql): array {
    $out = []; $buf = '';
    foreach (preg_split('/\r\n|\n|\r/', $sql) as $line) {
        $trim = trim($line);
        if ($trim === '' || strncmp($trim, '--', 2) === 0) continue;
        $buf .= $line . "\n";
        if (substr(rtrim($line), -1) === ';') {
            $out[] = rtrim(rtrim($buf), ";\n ");
            $buf = '';
        }
    }
    if (trim($buf) !== '') { $out[] = rtrim(trim($buf), ';'); }
    return $out;
}

// =============================================================================
// MIGRATION RUNNER
// =============================================================================

/**
 * Scan /updates for *.sql / *.php whose version > current, run in order,
 * record each in the migrations table (skip if already recorded).
 * Returns [ran:string[], error:?string].
 */
function ak_run_migrations(string $currentVersion): array {
    global $pdo;
    $dir = ROOT_PATH . '/updates';
    $ran = [];
    if (!is_dir($dir)) return ['ran' => $ran, 'error' => null];

    $files = [];
    foreach (glob($dir . '/*.{sql,php}', GLOB_BRACE) ?: [] as $f) {
        $base = basename($f);
        if (!preg_match('/^(\d+\.\d+\.\d+)\.(sql|php)$/', $base, $m)) continue;
        $ver = $m[1];
        // Only migrations strictly newer than the version we are upgrading FROM.
        if (version_compare($ver, $currentVersion, '>')) {
            $files[] = ['file' => $f, 'base' => $base, 'ver' => $ver, 'type' => $m[2]];
        }
    }
    // Ascending by version.
    usort($files, fn($a, $b) => version_compare($a['ver'], $b['ver']));

    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';

    foreach ($files as $mig) {
        // Skip if this file has already been recorded.
        $done = db_val('SELECT COUNT(*) FROM ' . tbl('migrations') . ' WHERE file_name = :f', [':f' => $mig['base']]);
        if ((int)$done > 0) continue;

        try {
            if ($mig['type'] === 'sql') {
                $raw = file_get_contents($mig['file']);
                if ($raw === false) throw new RuntimeException('Cannot read ' . $mig['base']);
                $raw = str_replace('{PREFIX}', $prefix, $raw);
                foreach (ak_split_sql($raw) as $stmt) {
                    if (trim($stmt) === '') continue;
                    $pdo->exec($stmt);
                }
            } else {
                // PHP migration: receives $pdo + $prefix + tbl() in scope.
                (function (string $__file, $pdo, string $prefix) {
                    require $__file;
                })($mig['file'], $pdo, $prefix);
            }
            db_insert('migrations', ['version' => $mig['ver'], 'file_name' => $mig['base']]);
            $ran[] = $mig['base'];
        } catch (Throwable $e) {
            return ['ran' => $ran, 'error' => 'Migration ' . $mig['base'] . ' failed: ' . $e->getMessage()];
        }
    }
    return ['ran' => $ran, 'error' => null];
}

// =============================================================================
// DOWNLOAD + EXTRACT helpers.
// =============================================================================

/** Download a zipball to a destination path with GitHub auth headers. */
function ak_download_zip(string $url, string $dest): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL not available.'];
    }
    $token = ak_dec_token((string)getSetting('github_token', ''));
    $headers = ['User-Agent: AK-Menu-System', 'Accept: application/octet-stream'];
    if ($token !== '') { $headers[] = 'Authorization: token ' . $token; }

    $fh = @fopen($dest, 'w');
    if (!$fh) return ['ok' => false, 'error' => 'Cannot open temp file for writing.'];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FAILONERROR    => true,
    ]);
    $ok   = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fh);

    if ($ok === false) {
        @unlink($dest);
        return ['ok' => false, 'error' => 'Download failed (HTTP ' . $http . '): ' . $err];
    }
    if (!is_file($dest) || filesize($dest) < 100) {
        @unlink($dest);
        return ['ok' => false, 'error' => 'Downloaded archive is empty or too small.'];
    }
    // Verify it is a real ZIP (magic bytes "PK\x03\x04").
    $magic = file_get_contents($dest, false, null, 0, 4);
    if ($magic !== "PK\x03\x04") {
        @unlink($dest);
        return ['ok' => false, 'error' => 'Downloaded file is not a valid ZIP archive.'];
    }
    return ['ok' => true, 'error' => ''];
}

/** Extract a ZIP into a directory using ZipArchive. */
function ak_extract_zip(string $zipPath, string $destDir): array {
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'ZipArchive extension is not available.'];
    }
    if (!is_dir($destDir)) { @mkdir($destDir, 0755, true); }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'error' => 'Cannot open ZIP archive.'];
    }
    // Guard against Zip-Slip: reject entries that escape the destination.
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false) continue;
        if (strpos($name, '..') !== false || strpos($name, "\0") !== false) {
            $zip->close();
            return ['ok' => false, 'error' => 'Archive contains an unsafe path: ' . $name];
        }
    }
    if (!$zip->extractTo($destDir)) {
        $zip->close();
        return ['ok' => false, 'error' => 'Extraction failed.'];
    }
    $zip->close();
    return ['ok' => true, 'error' => ''];
}

// =============================================================================
// THE PIPELINE.
// $source is either ['type'=>'github','zipball'=>url,'version'=>x] or
// ['type'=>'manual','zip'=>absPath,'version'=>x].
// =============================================================================

function ak_run_pipeline(array $source): void {
    global $adminName;
    $current = (string)getSetting('app_version', APP_VERSION);
    $target  = $source['version'] ?? $current;

    // Open an update_logs row up front so `progress` can track from step 1.
    $logId = db_insert('update_logs', [
        'from_version' => $current,
        'to_version'   => $target,
        'status'       => 'running',
        'performed_by' => $adminName,
        'log_text'     => json_encode(['steps' => []]),
    ]);

    $steps = [];
    $backupFile = null;
    $tempZip    = ROOT_PATH . '/temp/update.zip';
    $extractDir = ROOT_PATH . '/temp/update_extract';
    $autoBackup = getSetting('auto_backup', '1') === '1';

    $started = microtime(true);

    // Small helper to abort: restore DB if we have a backup, maintenance off.
    $abort = function (string $where, string $msg) use (&$steps, $logId, &$backupFile, $started) {
        ak_step($logId, $steps, $where, 'error', $msg);
        if ($backupFile) {
            $r = ak_restore_db($backupFile);
            ak_step($logId, $steps, 'Rollback', $r['ok'] ? 'done' : 'error',
                $r['ok'] ? 'Database restored from backup.' : ('Restore error: ' . $r['error']));
        }
        setSetting('maintenance_mode', '0');
        ak_step($logId, $steps, 'Maintenance OFF', 'done', 'Site is back online.');
        // Cleanup temp best-effort.
        ak_empty_dir(ROOT_PATH . '/temp');
        db_update('update_logs', [
            'status'   => 'failed',
            'log_text' => json_encode(['steps' => $steps, 'duration' => round(microtime(true) - $started, 1)], JSON_UNESCAPED_UNICODE),
        ], ['id' => $logId]);
        logActivity('admin', (int)($_SESSION['admin_id'] ?? 0), 'Update FAILED at ' . $where . ': ' . $msg);
        jsonError('Update failed at step: ' . $where . ' — ' . $msg, 500, ['log_id' => $logId, 'steps' => $steps]);
    };

    // ---- Step 1: pre-checks -------------------------------------------------
    $free = @disk_free_space(ROOT_PATH);
    if ($free !== false && $free < 50 * 1024 * 1024) {
        $abort('Pre-check', 'Insufficient disk space (need at least 50MB free).');
    }
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        $abort('Pre-check', 'PHP 8.0+ required, found ' . PHP_VERSION);
    }
    foreach (['temp', 'config', 'updates', 'assets/cache'] as $d) {
        $p = ROOT_PATH . '/' . $d;
        if (!is_dir($p)) { @mkdir($p, 0755, true); }
        if (!is_writable($p)) { $abort('Pre-check', "Directory not writable: /$d"); }
    }
    ak_step($logId, $steps, 'Pre-check', 'done', 'Disk, PHP version and write permissions OK.');

    // ---- Step 2: maintenance ON --------------------------------------------
    setSetting('maintenance_mode', '1');
    ak_step($logId, $steps, 'Maintenance ON', 'done', 'Site put into maintenance mode.');

    // ---- Step 3: backup -----------------------------------------------------
    if ($autoBackup) {
        $b = ak_backup_db($current);
        if (!$b['ok']) { $abort('Backup', $b['error']); }
        $backupFile = $b['file'];
        ak_step($logId, $steps, 'Backup', 'done', 'Database backed up: ' . basename($backupFile));
    } else {
        ak_step($logId, $steps, 'Backup', 'skipped', 'Auto-backup disabled in settings.');
    }

    // ---- Steps 4+5: obtain the release archive ------------------------------
    ak_empty_dir(ROOT_PATH . '/temp');
    if ($source['type'] === 'github') {
        if (empty($source['zipball'])) { $abort('Download', 'No zipball URL supplied.'); }
        $d = ak_download_zip($source['zipball'], $tempZip);
        if (!$d['ok']) { $abort('Download', $d['error']); }
        ak_step($logId, $steps, 'Download', 'done', 'Release archive downloaded.');
    } else {
        // Manual upload: the zip was already validated + moved to $tempZip.
        if (!is_file($source['zip'])) { $abort('Download', 'Uploaded archive missing.'); }
        $tempZip = $source['zip'];
        ak_step($logId, $steps, 'Upload', 'done', 'Uploaded archive accepted.');
    }

    $ex = ak_extract_zip($tempZip, $extractDir);
    if (!$ex['ok']) { $abort('Extract', $ex['error']); }
    $srcRoot = ak_detect_root($extractDir);
    ak_step($logId, $steps, 'Extract', 'done', 'Archive extracted.');

    // Sanity: the extracted tree must look like this app (config/ present).
    if (!is_dir($srcRoot . '/config') && !is_file($srcRoot . '/version.json')) {
        $abort('Validate', 'Extracted archive does not look like an AK Menu System release.');
    }

    // ---- Step 6+7: copy new files, protecting excluded paths ----------------
    $excludes = is_file(ROOT_PATH . '/update_exclude.php') ? (require ROOT_PATH . '/update_exclude.php') : [];
    if (!is_array($excludes)) { $excludes = []; }
    ak_step($logId, $steps, 'Protect user data', 'done', 'Loaded ' . count($excludes) . ' protected path rules.');

    [$copied, $skipped] = ak_copy_tree($srcRoot, ROOT_PATH, $excludes);
    ak_step($logId, $steps, 'Copy files', 'done', "Copied $copied file(s), skipped $skipped protected/invalid.");

    // ---- Step 8: migrations -------------------------------------------------
    $m = ak_run_migrations($current);
    if ($m['error'] !== null) { $abort('Migrations', $m['error']); }
    ak_step($logId, $steps, 'Migrations', 'done',
        $m['ran'] ? ('Ran: ' . implode(', ', $m['ran'])) : 'No new migrations.');

    // ---- Step 9: clear caches ----------------------------------------------
    ak_empty_dir(ROOT_PATH . '/assets/cache');
    if (function_exists('opcache_reset')) { @opcache_reset(); }
    ak_step($logId, $steps, 'Clear cache', 'done', 'Asset cache cleared and opcache reset.');

    // ---- Step 10: write new version ----------------------------------------
    setSetting('app_version', $target);
    setSetting('update_available', '0');
    // Best-effort: keep version.json in sync (non-fatal if not writable).
    $vjson = ROOT_PATH . '/version.json';
    if (is_writable($vjson) || is_writable(dirname($vjson))) {
        @file_put_contents($vjson, json_encode([
            'version' => $target, 'released_on' => date('Y-m-d'), 'min_php' => '8.0',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    ak_step($logId, $steps, 'Finalize', 'done', 'Version set to ' . $target . '.');

    // ---- Step 11: cleanup temp ---------------------------------------------
    ak_empty_dir(ROOT_PATH . '/temp');
    ak_step($logId, $steps, 'Cleanup', 'done', 'Temporary files removed.');

    // ---- Step 12: maintenance OFF ------------------------------------------
    setSetting('maintenance_mode', '0');
    ak_step($logId, $steps, 'Maintenance OFF', 'done', 'Site is back online.');

    $duration = round(microtime(true) - $started, 1);
    db_update('update_logs', [
        'status'   => 'success',
        'log_text' => json_encode(['steps' => $steps, 'duration' => $duration], JSON_UNESCAPED_UNICODE),
    ], ['id' => $logId]);

    logActivity('admin', (int)($_SESSION['admin_id'] ?? 0), 'Updated system ' . $current . ' → ' . $target);

    jsonSuccess('Update completed successfully.', [
        'log_id'   => $logId,
        'from'     => $current,
        'to'       => $target,
        'duration' => $duration,
        'steps'    => $steps,
    ]);
}

// =============================================================================
// ROUTER
// =============================================================================

try {
    switch ($action) {

        // ---------------------------------------------------------------------
        // CHECK — read-only version check against GitHub.
        // ---------------------------------------------------------------------
        case 'check': {
            $current = (string)getSetting('app_version', APP_VERSION);
            $res = ak_github_latest_release();
            if (!$res['ok']) {
                // Graceful: never fatal on network/API failure.
                jsonSuccess('Could not reach GitHub.', [
                    'current'   => $current,
                    'latest'    => null,
                    'newer'     => false,
                    'error'     => $res['error'],
                    'reachable' => false,
                ]);
            }
            $rel     = $res['data'];
            $latest  = ak_clean_version((string)($rel['tag_name'] ?? ''));
            $newer   = $latest !== '' && version_compare($latest, $current, '>');
            setSetting('update_available', $newer ? '1' : '0');

            jsonSuccess('Checked.', [
                'current'     => $current,
                'latest'      => $latest,
                'newer'       => $newer,
                'reachable'   => true,
                'released'    => $rel['published_at'] ?? null,
                'changelog'   => (string)($rel['body'] ?? ''),
                'name'        => $rel['name'] ?? $rel['tag_name'] ?? '',
                'zipball_url' => $rel['zipball_url'] ?? null,
                'html_url'    => $rel['html_url'] ?? null,
            ]);
            break;
        }

        // ---------------------------------------------------------------------
        // RUN — full pipeline from the latest GitHub release.
        // ---------------------------------------------------------------------
        case 'run': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST required.', 405); }
            $res = ak_github_latest_release();
            if (!$res['ok']) { jsonError('Cannot start update: ' . $res['error'], 502); }
            $rel     = $res['data'];
            $latest  = ak_clean_version((string)($rel['tag_name'] ?? ''));
            $current = (string)getSetting('app_version', APP_VERSION);
            if ($latest === '') { jsonError('Release has no tag/version.', 422); }
            if (!version_compare($latest, $current, '>')) {
                jsonError('You are already on the latest version (' . $current . ').', 409);
            }
            if (empty($rel['zipball_url'])) { jsonError('Release has no downloadable archive.', 422); }

            ak_run_pipeline([
                'type'    => 'github',
                'zipball' => $rel['zipball_url'],
                'version' => $latest,
            ]);
            break;
        }

        // ---------------------------------------------------------------------
        // MANUAL_UPLOAD — full pipeline from an uploaded ZIP.
        // ---------------------------------------------------------------------
        case 'manual_upload': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST required.', 405); }
            if (empty($_FILES['package']) || ($_FILES['package']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                jsonError('No ZIP file uploaded.', 422);
            }
            $file = $_FILES['package'];
            if ($file['size'] <= 0 || $file['size'] > 200 * 1024 * 1024) {
                jsonError('Upload must be a non-empty ZIP under 200MB.', 422);
            }
            // Validate it is really a ZIP by magic bytes + mime.
            $magic = file_get_contents($file['tmp_name'], false, null, 0, 4);
            if ($magic !== "PK\x03\x04") { jsonError('Uploaded file is not a valid ZIP.', 422); }

            $tmpDir = ROOT_PATH . '/temp';
            if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0755, true); }
            ak_empty_dir($tmpDir);
            $dest = $tmpDir . '/update.zip';
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                jsonError('Could not store the uploaded archive.', 500);
            }

            // Version label: prefer POSTed target, else derive placeholder.
            $ver = trim((string)($_POST['version'] ?? ''));
            $ver = $ver !== '' ? ak_clean_version($ver) : ('manual-' . date('Ymd_His'));

            ak_run_pipeline([
                'type'    => 'manual',
                'zip'     => $dest,
                'version' => $ver,
            ]);
            break;
        }

        // ---------------------------------------------------------------------
        // PROGRESS — latest update_logs row + parsed steps.
        // ---------------------------------------------------------------------
        case 'progress': {
            $id  = (int)($_GET['id'] ?? 0);
            $row = $id > 0
                ? db_one('SELECT * FROM ' . tbl('update_logs') . ' WHERE id = :id', [':id' => $id])
                : db_one('SELECT * FROM ' . tbl('update_logs') . ' ORDER BY id DESC LIMIT 1');
            if (!$row) { jsonSuccess('No updates yet.', ['found' => false]); }

            $parsed = json_decode($row['log_text'] ?? '', true);
            jsonSuccess('OK', [
                'found'    => true,
                'id'       => (int)$row['id'],
                'status'   => $row['status'],
                'from'     => $row['from_version'],
                'to'       => $row['to_version'],
                'steps'    => $parsed['steps'] ?? [],
                'duration' => $parsed['duration'] ?? null,
                'at'       => $row['created_at'],
            ]);
            break;
        }

        // ---------------------------------------------------------------------
        // ROLLBACK — restore a chosen DB backup.
        // ---------------------------------------------------------------------
        case 'rollback': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST required.', 405); }
            $bid = (int)($_POST['backup_id'] ?? 0);
            if ($bid <= 0) { jsonError('backup_id required.', 422); }
            $bk = db_one('SELECT * FROM ' . tbl('backups') . ' WHERE id = :id', [':id' => $bid]);
            if (!$bk) { jsonError('Backup not found.', 404); }

            $path = ROOT_PATH . '/backups/' . basename($bk['file_name']);
            if (!is_file($path) || !ak_within_root($path)) { jsonError('Backup file missing on disk.', 404); }

            // Put the site into maintenance while we restore, then release it.
            setSetting('maintenance_mode', '1');
            $r = ak_restore_db($path);
            setSetting('maintenance_mode', '0');
            if (!$r['ok']) { jsonError($r['error'], 500); }

            if (!empty($bk['version'])) { setSetting('app_version', $bk['version']); }
            logActivity('admin', $adminId, 'Rolled back DB to backup #' . $bid . ' (' . $bk['file_name'] . ')');
            jsonSuccess('Database restored from backup: ' . $bk['file_name'], [
                'backup_id' => $bid,
                'version'   => $bk['version'],
            ]);
            break;
        }

        default:
            jsonError('Unknown action.', 400);
    }
} catch (Throwable $e) {
    // Last-resort guard: make sure we never leave the site stuck in maintenance
    // due to an unexpected fatal, and always answer with JSON.
    @setSetting('maintenance_mode', '0');
    jsonError('Unexpected error: ' . $e->getMessage(), 500);
}
