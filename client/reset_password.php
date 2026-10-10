<?php
/**
 * Forgot password — step 2 (e-mail channel).
 *
 * Reached only from the single-use link in the reset e-mail:
 *   /client/reset_password.php?token=<64 hex chars>
 *
 * The token is validated on GET (so a dead link says so immediately) and again
 * on POST, then burned — so the same link can never set a password twice.
 * Self-POST, no JSON API, so it works even with JavaScript disabled.
 */
require_once dirname(__DIR__) . '/config/config.php';

$siteName = getSetting('site_name', 'AK Menu System');
$token    = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error    = '';
$done     = false;

$row    = passwordResetLookup($token);
$tenant = null;
if ($row) {
    $tenant = db_one('SELECT id, owner_name, email, restaurant_name FROM ' . tbl('tenants') . ' WHERE id = :id',
        [':id' => (int)$row['user_id']]);
    if (!$tenant) { $row = null; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');

    if (!$row || !$tenant) {
        $error = 'This reset link is invalid, already used, or has expired.';
    } elseif (strlen($p1) < 6) {
        $error = 'The new password must be at least 6 characters.';
    } elseif ($p1 !== $p2) {
        $error = 'The two passwords do not match.';
    } else {
        db_update('tenants', ['password' => password_hash($p1, PASSWORD_DEFAULT)], ['id' => (int)$tenant['id']]);
        passwordResetConsume((int)$row['id']);
        logActivity('tenant', (int)$tenant['id'], 'Password reset via e-mail link');

        // Tell the owner their password changed — the first signal of a hijack.
        try {
            $html = mailLayout('Your password was changed',
                '<p style="margin:0 0 12px;">Hi ' . e((string)($tenant['owner_name'] ?? 'there')) . ',</p>'
                . '<p style="margin:0 0 12px;">The password for your <strong>' . e($siteName)
                . '</strong> account was just changed from IP ' . e((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'))
                . ' on ' . e(date('d M Y, h:i A')) . '.</p>'
                . '<p style="margin:0;">If this was you, nothing more to do. If it was not, reset your password '
                . 'again immediately and contact support.</p>',
                ['cta_url' => BASE_URL . '/client/login.php', 'cta_label' => 'Sign in',
                 'preheader' => 'Your password was changed just now.']
            );
            mailQueue((string)$tenant['email'], 'Your ' . $siteName . ' password was changed', $html, [
                'tenant_id' => (int)$tenant['id'], 'trigger' => 'password_changed',
                'name' => (string)($tenant['owner_name'] ?? ''),
            ]);
        } catch (Throwable $e) {
            error_log('password-changed notice failed: ' . $e->getMessage());
        }

        $done = true;
    }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Set a New Password · <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:linear-gradient(135deg,#e63946,#f1a208);min-height:100vh;display:flex;align-items:center;font-family:system-ui}
.login-card{max-width:420px;margin:auto;border-radius:16px;border:0;box-shadow:0 20px 60px rgba(0,0,0,.3)}</style>
</head><body>
<div class="container"><div class="card login-card p-4">

<?php if ($done): ?>
  <div class="text-center">
    <i class="bi bi-check-circle-fill text-success" style="font-size:2.8rem"></i>
    <h5 class="mt-3">Password changed</h5>
    <p class="text-muted small mb-4">You can now sign in with your new password.</p>
    <a href="<?= e(BASE_URL) ?>/client/login.php" class="btn btn-danger w-100">Go to login</a>
  </div>

<?php elseif (!$row || !$tenant): ?>
  <div class="text-center">
    <i class="bi bi-x-circle-fill text-danger" style="font-size:2.8rem"></i>
    <h5 class="mt-3">This link no longer works</h5>
    <p class="text-muted small">
      Reset links can be used once and expire after 60 minutes.
      Please request a fresh one.
    </p>
    <a href="<?= e(BASE_URL) ?>/client/forgot_password.php" class="btn btn-danger w-100 mt-2">Request a new link</a>
    <a href="<?= e(BASE_URL) ?>/client/login.php" class="btn btn-link w-100 mt-1 small">Back to login</a>
  </div>

<?php else: ?>
  <div class="text-center mb-3">
    <i class="bi bi-shield-lock text-danger" style="font-size:2.3rem"></i>
    <h5 class="mt-2 mb-0">Set a new password</h5>
    <small class="text-muted">
      For <strong><?= e((string)$tenant['email']) ?></strong>
      <?php if (!empty($tenant['restaurant_name'])): ?><br><?= e((string)$tenant['restaurant_name']) ?><?php endif; ?>
    </small>
  </div>

  <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <?= csrfField() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
      <label class="form-label">New password</label>
      <input name="password" type="password" class="form-control" minlength="6" required
             placeholder="At least 6 characters" autocomplete="new-password" autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Confirm new password</label>
      <input name="password2" type="password" class="form-control" minlength="6" required autocomplete="new-password">
    </div>
    <button class="btn btn-danger w-100">Save new password</button>
  </form>
  <a href="<?= e(BASE_URL) ?>/client/login.php" class="btn btn-link w-100 mt-2 small">Cancel and go back to login</a>
<?php endif; ?>

</div></div>
</body></html>
