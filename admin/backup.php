<?php
/**
 * Admin › Database Backup.
 * One-click export of all prefixed tables to /backups/db_{timestamp}.sql using
 * PDO (CREATE TABLE + INSERTs). Records a row in the `backups` table. Lists
 * existing backups with download + delete. All file access is confined to
 * /backups and filenames are sanitized. Self-contained handlers.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

define('BACKUP_DIR', ROOT_PATH . '/backups');

/** Return an absolute path inside BACKUP_DIR for a user-supplied name, or null. */
function safeBackupPath(string $name): ?string {
    $base = basename($name); // strip any directory components
    if ($base === '' || !preg_match('/^[A-Za-z0-9._-]+\.sql$/', $base)) return null;
    $path = BACKUP_DIR . '/' . $base;
    // Ensure the resolved path is really inside BACKUP_DIR.
    $real = realpath($path);
    if ($real !== false && strpos($real, realpath(BACKUP_DIR)) !== 0) return null;
    return $path;
}

/** Dump all prefixed tables to $file. Returns bytes written or 0 on failure. */
function runDbBackup(string $file): int {
    global $pdo;
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    $fh = @fopen($file, 'w');
    if (!$fh) return 0;

    fwrite($fh, "-- AK Menu System DB backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

    // List all tables matching the prefix.
    $tables = [];
    foreach (db_all("SHOW TABLES LIKE '" . str_replace('_', '\\_', $prefix) . "%'") as $row) {
        $tables[] = array_values($row)[0];
    }

    foreach ($tables as $table) {
        // Structure.
        $create = db_one('SHOW CREATE TABLE `' . $table . '`');
        $createSql = $create['Create Table'] ?? ($create['Create View'] ?? '');
        fwrite($fh, "\n-- ----------------------------\n-- Table: $table\n-- ----------------------------\n");
        fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($fh, $createSql . ";\n\n");

        // Data (streamed row by row to keep memory low).
        $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = '`' . implode('`,`', array_keys($r)) . '`';
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string)$v);
            }, array_values($r));
            fwrite($fh, "INSERT INTO `$table` ($cols) VALUES (" . implode(',', $vals) . ");\n");
        }
    }

    fwrite($fh, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($fh);
    return (int)@filesize($file);
}

// ---- Download handler (must run before any output) --------------------------
if (($_GET['action'] ?? '') === 'download') {
    $path = safeBackupPath($_GET['file'] ?? '');
    if (!$path || !is_file($path)) { http_response_code(404); die('File not found.'); }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

// ---- POST handler -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!is_dir(BACKUP_DIR)) { @mkdir(BACKUP_DIR, 0755, true); }
        $fileName = 'db_' . date('Ymd_His') . '.sql';
        $size = runDbBackup(BACKUP_DIR . '/' . $fileName);
        if ($size > 0) {
            db_insert('backups', [
                'file_name' => $fileName, 'type' => 'db', 'size' => $size, 'version' => APP_VERSION,
            ]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Created DB backup $fileName");
        }
        redirect(BASE_URL . '/admin/backup.php?done=' . ($size > 0 ? '1' : '0'));
    } elseif ($action === 'delete') {
        $path = safeBackupPath($_POST['file'] ?? '');
        if ($path && is_file($path)) {
            @unlink($path);
            db_query('DELETE FROM ' . tbl('backups') . ' WHERE file_name = :f', [':f' => basename($path)]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'Deleted backup ' . basename($path));
        }
        redirect(BASE_URL . '/admin/backup.php');
    }
    redirect(BASE_URL . '/admin/backup.php');
}

// ---- Build list from disk (authoritative) + DB metadata ---------------------
$meta = [];
foreach (db_all('SELECT * FROM ' . tbl('backups') . ' ORDER BY id DESC') as $b) {
    $meta[$b['file_name']] = $b;
}
$files = [];
if (is_dir(BACKUP_DIR)) {
    foreach (glob(BACKUP_DIR . '/*.sql') as $f) {
        $name = basename($f);
        $files[] = [
            'name'    => $name,
            'size'    => filesize($f),
            'mtime'   => filemtime($f),
            'version' => $meta[$name]['version'] ?? '—',
        ];
    }
    usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
}
$done = $_GET['done'] ?? null;

$pageTitle = 'Backup';
$activeNav = 'backup';
require __DIR__ . '/_header.php';
?>
<?php if ($done === '1'): ?>
  <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> Backup created successfully.
    <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif ($done === '0'): ?>
  <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-x-circle"></i> Backup failed. Check folder permissions on <code>/backups</code>.
    <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Database Backup</h5>
  <form method="post" onsubmit="return confirmBackup(this)">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="create">
    <button class="btn btn-primary"><i class="bi bi-database-down"></i> Create Backup Now</button>
  </form>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table class="table table-hover align-middle w-100">
      <thead><tr><th>File</th><th>Size</th><th>Version</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php if (!$files): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No backups yet. Click “Create Backup Now”.</td></tr>
      <?php else: foreach ($files as $f): ?>
        <tr>
          <td><i class="bi bi-file-earmark-code text-secondary"></i> <?= e($f['name']) ?></td>
          <td><?= e(number_format($f['size'] / 1024, 1)) ?> KB</td>
          <td><?= e($f['version']) ?></td>
          <td class="small text-muted"><?= e(date('d-m-Y H:i', $f['mtime'])) ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL . '/admin/backup.php?action=download&file=' . urlencode($f['name'])) ?>"><i class="bi bi-download"></i></a>
            <form method="post" class="d-inline" onsubmit="return delBackup(this)">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="file" value="<?= e($f['name']) ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$pageScript = <<<'HTML'
<script>
function confirmBackup(form){
  event.preventDefault();
  AK.confirm('Create a full database backup now?','Backup').then(ok=>{ if(ok) form.submit(); });
  return false;
}
function delBackup(form){
  event.preventDefault();
  AK.confirm('Delete this backup file permanently?').then(ok=>{ if(ok) form.submit(); });
  return false;
}
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
