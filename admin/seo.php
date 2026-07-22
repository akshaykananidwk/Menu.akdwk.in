<?php
/**
 * Admin › SEO Manager.
 * Tabs: Settings (GSC/Bing/GA4/GTM/IndexNow), City Pages (seed/add/toggle),
 * Keywords, Redirects, 404 Log. Self-POST handlers.
 */
$pageTitle = 'SEO Manager';
$activeNav = 'seo';
require __DIR__ . '/_header.php';
require_once CONFIG_PATH . '/seo.php'; // ensure SEO helpers exist even if a cached config.php didn't load them

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $a = $_POST['do'] ?? '';
    try {
        if ($a === 'settings') {
            foreach (['google_site_verification','bing_site_verification','ga4_id','gtm_id','seo_description','seo_keywords'] as $k) {
                if (array_key_exists($k, $_POST)) { setSetting($k, trim((string)$_POST[$k])); }
            }
        } elseif ($a === 'seed_cities') {
            seedSeoCitiesIfEmpty();
        } elseif ($a === 'city_toggle') {
            db_query('UPDATE ' . tbl('seo_cities') . ' SET is_active = 1 - is_active WHERE id = :id', [':id' => (int)$_POST['id']]);
        } elseif ($a === 'city_add') {
            $name = trim((string)$_POST['city_name']);
            $slug = makeSlug($name);
            if ($name && $slug) {
                db_insert('seo_cities', [
                    'city_name' => $name, 'state' => trim((string)($_POST['state'] ?? '')), 'slug' => $slug,
                    'population_tier' => (int)($_POST['population_tier'] ?? 3),
                    'meta_title' => "Digital Menu & QR Code Ordering in $name | " . getSetting('site_name', 'AK Menu System'),
                    'h1' => "Digital Menu & QR Code Ordering System in $name",
                    'is_active' => 1, 'priority' => (int)($_POST['priority'] ?? 40),
                ]);
            }
        } elseif ($a === 'city_import') {
            // CSV: city_name,state,tier per line
            $csv = trim((string)($_POST['csv'] ?? ''));
            foreach (preg_split('/\r\n|\n|\r/', $csv) as $line) {
                $cols = array_map('trim', explode(',', $line));
                if (($cols[0] ?? '') === '') continue;
                $slug = makeSlug($cols[0]);
                $exists = db_val('SELECT COUNT(*) FROM ' . tbl('seo_cities') . ' WHERE slug = :s', [':s' => $slug]);
                if ($exists) continue;
                db_insert('seo_cities', [
                    'city_name' => $cols[0], 'state' => $cols[1] ?? '', 'slug' => $slug,
                    'population_tier' => (int)($cols[2] ?? 3),
                    'meta_title' => "Digital Menu & QR Code Ordering in {$cols[0]} | " . getSetting('site_name', 'AK Menu System'),
                    'h1' => "Digital Menu & QR Code Ordering System in {$cols[0]}",
                    'is_active' => 1, 'priority' => 40,
                ]);
            }
        } elseif ($a === 'kw_add') {
            $kw = trim((string)$_POST['keyword']);
            if ($kw) { db_insert('seo_keywords', ['keyword' => $kw, 'type' => trim((string)($_POST['type'] ?? 'primary')), 'target_url' => trim((string)($_POST['target_url'] ?? '')) ?: null]); }
        } elseif ($a === 'kw_delete') {
            db_query('DELETE FROM ' . tbl('seo_keywords') . ' WHERE id = :id', [':id' => (int)$_POST['id']]);
        } elseif ($a === 'redir_add') {
            $from = '/' . ltrim(trim((string)$_POST['from_url']), '/');
            $to   = trim((string)$_POST['to_url']);
            if ($from !== '/' && $to) {
                db_query('INSERT INTO ' . tbl('redirects') . ' (from_url,to_url,type) VALUES (:f,:t,:ty)
                          ON DUPLICATE KEY UPDATE to_url=:t2, type=:ty2',
                          [':f' => $from, ':t' => $to, ':ty' => $_POST['type'] ?? '301', ':t2' => $to, ':ty2' => $_POST['type'] ?? '301']);
            }
        } elseif ($a === 'redir_delete') {
            db_query('DELETE FROM ' . tbl('redirects') . ' WHERE id = :id', [':id' => (int)$_POST['id']]);
        } elseif ($a === 'clear_404') {
            db_query('DELETE FROM ' . tbl('error_404_log'));
        } elseif ($a === 'rebuild') {
            seoCacheClear();
            pingSearchEngines(absUrl(''));
        }
        logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'SEO manager: ' . $a);
    } catch (Throwable $e) { error_log('seo admin: ' . $e->getMessage()); }
    redirect(BASE_URL . '/admin/seo.php?saved=1');
}

$v = fn($k, $d = '') => e(getSetting($k, $d));
$get = function (string $sql) { try { return db_all($sql); } catch (Throwable $e) { return []; } };
$cities   = $get('SELECT * FROM ' . tbl('seo_cities') . ' ORDER BY priority DESC, city_name LIMIT 400');
$keywords = $get('SELECT * FROM ' . tbl('seo_keywords') . ' ORDER BY id DESC');
$redirs   = $get('SELECT * FROM ' . tbl('redirects') . ' ORDER BY id DESC');
$notfound = $get('SELECT * FROM ' . tbl('error_404_log') . ' ORDER BY hits DESC, last_seen DESC LIMIT 100');
$cityCount = count($cities);
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2 small">Saved.</div><?php endif; ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-set" type="button">Settings</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-city" type="button">City Pages <span class="badge bg-secondary"><?= $cityCount ?></span></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-kw" type="button">Keywords</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-redir" type="button">Redirects</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-404" type="button">404 Log</button></li>
</ul>
<div class="tab-content">

  <!-- SETTINGS -->
  <div class="tab-pane fade show active" id="t-set">
    <form method="post" class="card"><div class="card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="settings">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Google Search Console verification</label>
          <input name="google_site_verification" class="form-control" value="<?= $v('google_site_verification') ?>" placeholder="content of google-site-verification meta"></div>
        <div class="col-md-6"><label class="form-label">Bing Webmaster verification</label>
          <input name="bing_site_verification" class="form-control" value="<?= $v('bing_site_verification') ?>" placeholder="msvalidate.01 content"></div>
        <div class="col-md-6"><label class="form-label">Google Analytics 4 ID</label>
          <input name="ga4_id" class="form-control" value="<?= $v('ga4_id') ?>" placeholder="G-XXXXXXXXXX"></div>
        <div class="col-md-6"><label class="form-label">Google Tag Manager ID</label>
          <input name="gtm_id" class="form-control" value="<?= $v('gtm_id') ?>" placeholder="GTM-XXXXXX"></div>
        <div class="col-12"><label class="form-label">Default site meta description</label>
          <input name="seo_description" class="form-control" maxlength="160" value="<?= $v('seo_description') ?>"></div>
        <div class="col-12"><label class="form-label">Default keywords</label>
          <input name="seo_keywords" class="form-control" value="<?= $v('seo_keywords') ?>"></div>
      </div>
      <button class="btn btn-primary mt-3">Save Settings</button>
    </div></form>
    <div class="card mt-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
      <div class="me-auto small text-muted">IndexNow key: <code><?= e(indexNowKey()) ?></code> · sitemap: <a href="<?= e(absUrl('sitemap.xml')) ?>" target="_blank">/sitemap.xml</a> · robots: <a href="<?= e(absUrl('robots.txt')) ?>" target="_blank">/robots.txt</a></div>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="rebuild">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Rebuild sitemaps &amp; ping Google</button></form>
    </div></div>
  </div>

  <!-- CITY PAGES -->
  <div class="tab-pane fade" id="t-city">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php if ($cityCount === 0): ?>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="seed_cities">
        <button class="btn btn-primary btn-sm"><i class="bi bi-magic"></i> Seed default cities</button></form>
      <?php endif; ?>
      <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#addCity">+ Add city</button>
      <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#importCity"><i class="bi bi-upload"></i> Bulk import (CSV)</button>
    </div>
    <div class="collapse mb-3" id="addCity"><form method="post" class="card card-body"><div class="row g-2 align-items-end">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="city_add">
      <div class="col-md-4"><label class="form-label small">City name</label><input name="city_name" class="form-control form-control-sm" required></div>
      <div class="col-md-3"><label class="form-label small">State</label><input name="state" class="form-control form-control-sm"></div>
      <div class="col-md-2"><label class="form-label small">Tier</label><input name="population_tier" type="number" min="1" max="3" value="2" class="form-control form-control-sm"></div>
      <div class="col-md-3"><button class="btn btn-primary btn-sm w-100">Add city</button></div>
    </div></form></div>
    <div class="collapse mb-3" id="importCity"><form method="post" class="card card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="city_import">
      <label class="form-label small">One per line: <code>City,State,Tier</code></label>
      <textarea name="csv" class="form-control form-control-sm" rows="5" placeholder="Rajkot,Gujarat,2&#10;Surat,Gujarat,1"></textarea>
      <button class="btn btn-primary btn-sm mt-2">Import</button>
    </form></div>
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>City</th><th>State</th><th>URL</th><th>Priority</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($cities as $c): ?>
        <tr>
          <td class="fw-semibold"><?= e($c['city_name']) ?></td>
          <td class="small"><?= e($c['state']) ?></td>
          <td class="small"><a href="<?= e(absUrl('digital-menu/' . $c['slug'])) ?>" target="_blank">/digital-menu/<?= e($c['slug']) ?></a></td>
          <td><?= (int)$c['priority'] ?></td>
          <td><?= $c['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Off</span>' ?></td>
          <td><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="city_toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $c['is_active'] ? 'Disable' : 'Enable' ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$cities): ?><tr><td colspan="6" class="text-center text-muted py-3">No cities yet — click “Seed default cities”.</td></tr><?php endif; ?>
      </tbody>
    </table></div></div>
  </div>

  <!-- KEYWORDS -->
  <div class="tab-pane fade" id="t-kw">
    <form method="post" class="card card-body mb-3"><div class="row g-2 align-items-end">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="kw_add">
      <div class="col-md-5"><label class="form-label small">Keyword</label><input name="keyword" class="form-control form-control-sm" required></div>
      <div class="col-md-3"><label class="form-label small">Type</label>
        <select name="type" class="form-select form-select-sm"><option>primary</option><option>commercial</option><option>long-tail</option><option>local</option><option>gujarati</option></select></div>
      <div class="col-md-3"><label class="form-label small">Target URL</label><input name="target_url" class="form-control form-control-sm"></div>
      <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Add</button></div>
    </div></form>
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>Keyword</th><th>Type</th><th>Target</th><th></th></tr></thead><tbody>
      <?php foreach ($keywords as $k): ?>
        <tr><td><?= e($k['keyword']) ?></td><td><span class="badge bg-light text-dark border"><?= e($k['type']) ?></span></td>
          <td class="small"><?= e($k['target_url']) ?></td>
          <td><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="kw_delete"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$keywords): ?><tr><td colspan="4" class="text-center text-muted py-3">No keywords tracked yet.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>

  <!-- REDIRECTS -->
  <div class="tab-pane fade" id="t-redir">
    <form method="post" class="card card-body mb-3"><div class="row g-2 align-items-end">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="redir_add">
      <div class="col-md-4"><label class="form-label small">From (path)</label><input name="from_url" class="form-control form-control-sm" placeholder="/old-page" required></div>
      <div class="col-md-4"><label class="form-label small">To (URL or path)</label><input name="to_url" class="form-control form-control-sm" placeholder="/new-page" required></div>
      <div class="col-md-2"><label class="form-label small">Type</label><select name="type" class="form-select form-select-sm"><option>301</option><option>302</option></select></div>
      <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Add</button></div>
    </div></form>
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th></th></tr></thead><tbody>
      <?php foreach ($redirs as $r): ?>
        <tr><td class="small"><?= e($r['from_url']) ?></td><td class="small"><?= e($r['to_url']) ?></td><td><?= e($r['type']) ?></td><td><?= (int)$r['hits'] ?></td>
          <td><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="redir_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$redirs): ?><tr><td colspan="5" class="text-center text-muted py-3">No redirects.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>

  <!-- 404 LOG -->
  <div class="tab-pane fade" id="t-404">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="text-muted small">Broken URLs people hit — add a redirect to fix the top ones.</span>
      <?php if ($notfound): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="clear_404"><button class="btn btn-sm btn-outline-secondary">Clear log</button></form><?php endif; ?>
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>URL</th><th>Hits</th><th>Last seen</th></tr></thead><tbody>
      <?php foreach ($notfound as $n): ?>
        <tr><td class="small"><?= e($n['url']) ?></td><td><?= (int)$n['hits'] ?></td><td class="small text-muted"><?= e(date('d M, H:i', strtotime($n['last_seen']))) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$notfound): ?><tr><td colspan="3" class="text-center text-muted py-3">No 404s logged. 🎉</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
