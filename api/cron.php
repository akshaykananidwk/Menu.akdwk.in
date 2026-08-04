<?php
/**
 * Cron scheduler — admin JSON API (Super Admin only).
 * Actions: jobs, run, run_all, toggle, logs, retry.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isSuperAdmin()) { jsonError('Unauthorized.', 401); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }

$action = $_GET['action'] ?? '';

try {
    cronEnsureJobs();

    switch ($action) {

        case 'jobs': {
            $rows = db_all('SELECT * FROM ' . tbl('cron_jobs') . ' ORDER BY (at_hour IS NULL) DESC, interval_minutes ASC, job_key ASC');
            $defs = [];
            foreach (cronRegistry() as $d) { $defs[$d['key']] = $d['desc']; }
            foreach ($rows as &$r) { $r['desc'] = $defs[$r['job_key']] ?? ''; }
            unset($r);
            jsonSuccess('', ['jobs' => $rows, 'health' => cronHealth()]);
        }

        case 'run': {
            $key = trim((string)($_POST['key'] ?? ''));
            if ($key === '') { jsonError('Missing job.'); }
            $r = cronRunJob($key, true);
            if (!empty($r['skipped']) && empty($r['ok'])) { jsonError($r['message'] ?: 'Could not run.'); }
            jsonSuccess($r['message'] ?? 'Done.', ['result' => $r]);
        }

        case 'run_all': {
            $summary = cronRunDue();
            jsonSuccess('Ran ' . count($summary) . ' due job(s).', ['summary' => $summary]);
        }

        case 'toggle': {
            $key = trim((string)($_POST['key'] ?? ''));
            $enabled = ($_POST['enabled'] ?? '0') === '1' ? 1 : 0;
            if (!cronDef($key)) { jsonError('Unknown job.'); }
            db_update('cron_jobs', ['enabled' => $enabled], ['job_key' => $key]);
            jsonSuccess($enabled ? 'Job enabled.' : 'Job disabled.');
        }

        case 'logs': {
            $key = trim((string)($_GET['key'] ?? ''));
            $params = []; $where = '';
            if ($key !== '') { $where = 'WHERE job_key = :k'; $params[':k'] = $key; }
            $rows = db_all('SELECT * FROM ' . tbl('cron_logs') . " $where ORDER BY id DESC LIMIT 100", $params);
            jsonSuccess('', ['logs' => $rows]);
        }

        case 'retry': {
            // Re-run any enabled job whose last run failed.
            $failed = db_all('SELECT job_key FROM ' . tbl('cron_jobs') . " WHERE enabled = 1 AND last_status = 'failed'");
            $ran = [];
            foreach ($failed as $f) { $r = cronRunJob($f['job_key'], true); $ran[] = $f['job_key'] . ': ' . ($r['ok'] ? 'ok' : 'FAIL'); }
            jsonSuccess('Retried ' . count($ran) . ' failed job(s).', ['ran' => $ran]);
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/cron.php: ' . $e->getMessage());
    jsonError('Server error: ' . $e->getMessage(), 500);
}
