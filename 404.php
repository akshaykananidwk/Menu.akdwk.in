<?php
/** Custom 404 — logs the miss, offers search + popular links. Works both as an
 *  Apache ErrorDocument and when require()d from a router that found nothing. */
if (!defined('ROOT_PATH')) { require_once __DIR__ . '/config/config.php'; }

// Honour a configured 301/302 redirect before rendering the 404.
try {
    $reqPath = '/' . ltrim(strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?'), '/');
    $red = db_one('SELECT * FROM ' . tbl('redirects') . ' WHERE from_url = :f', [':f' => $reqPath]);
    if ($red) {
        db_query('UPDATE ' . tbl('redirects') . ' SET hits = hits + 1 WHERE id = :id', [':id' => (int)$red['id']]);
        $to = preg_match('~^https?://~i', $red['to_url']) ? $red['to_url'] : absUrl(ltrim($red['to_url'], '/'));
        http_response_code($red['type'] === '302' ? 302 : 301);
        header('Location: ' . $to);
        exit;
    }
} catch (Throwable $ex) { /* redirects table may not exist yet */ }

if (!headers_sent()) { http_response_code(404); }

$site = getSetting('site_name', 'AK Menu System');
$primary = getSetting('primary_color', '#e63946');
$secondary = getSetting('secondary_color', '#1d3557');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Log the 404 (dedup by URL; bump hits). Never break the page.
try {
    $url = substr(strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?'), 0, 255);
    if ($url !== '' && stripos(($_SERVER['HTTP_USER_AGENT'] ?? ''), 'bot') === false) {
        db_query('INSERT INTO ' . tbl('error_404_log') . ' (url, referrer, user_agent, ip, hits, last_seen)
                  VALUES (:u,:r,:a,:i,1,NOW())
                  ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW()', [
            ':u' => $url,
            ':r' => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
            ':a' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ':i' => substr((string)clientIp(), 0, 45),
        ]);
    }
} catch (Throwable $ex) { /* table may not exist yet */ }

$meta = ['title' => "Page not found | $site", 'description' => 'The page you are looking for could not be found.', 'robots' => 'noindex,follow', 'canonical' => absUrl('')];
render_landing_head($meta, $primary, $secondary, $site);
?>
<main class="wrap lp-main" style="text-align:center;padding-top:70px">
  <div style="font-size:5rem;font-weight:800;color:var(--p);line-height:1">404</div>
  <h1 style="margin:.4rem 0">Oops — page not found</h1>
  <p style="color:var(--muted);max-width:520px;margin:0 auto 1.4rem">The page you're looking for doesn't exist or has moved. Try one of these instead:</p>
  <div class="city-grid" style="justify-content:center">
    <a class="city-chip" href="<?= $e(absUrl('')) ?>">Home</a>
    <a class="city-chip" href="<?= $e(absUrl('signup.php')) ?>">Start free trial</a>
    <a class="city-chip" href="<?= $e(absUrl('client/login.php')) ?>">Restaurant login</a>
    <a class="city-chip" href="<?= $e(absUrl('blog')) ?>">Blog</a>
  </div>
</main>
<?php
render_landing_foot($site);
