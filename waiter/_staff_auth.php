<?php
/** Shared staff (waiter/kitchen) login logic — defines functions only, no execution. */

/** Handle a POSTed staff login for the given role; returns error string ('' on none). */
function staffLoginHandler(string $role): string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return '';
    csrfCheck();
    $code = trim($_POST['rcode'] ?? '');
    $pin  = trim($_POST['pin'] ?? '');
    $ident = "staff:$role:$code";
    if (isLoginLocked($ident)) return __('account_locked');

    $tenant = tenantByLoginCode($code);
    if (!$tenant) { recordFailedLogin($ident); return 'Invalid property code.'; }

    $staff = db_all('SELECT * FROM ' . tbl('staff') . ' WHERE tenant_id = :t AND role = :r AND status = 1',
        [':t' => $tenant['id'], ':r' => $role]);
    foreach ($staff as $s) {
        // PINs may be stored hashed (preferred) or plain for legacy imports.
        if (password_verify($pin, $s['pin']) || hash_equals((string)$s['pin'], $pin)) {
            clearLoginAttempts($ident);
            regenerateSession();
            $_SESSION['staff_id'] = (int)$s['id'];
            $_SESSION['staff_role'] = $role;
            $_SESSION['staff_name'] = $s['name'];
            $_SESSION['staff_tenant_id'] = (int)$tenant['id'];
            $_SESSION['lang'] = $tenant['language'] ?: 'en';
            logActivity('staff', (int)$s['id'], ucfirst($role) . ' logged in');
            redirect(BASE_URL . "/$role/index.php");
        }
    }
    recordFailedLogin($ident);
    return 'Invalid PIN.';
}

/** Render the mobile-first staff login screen. */
function render_staff_login(string $role, string $title, string $icon, string $error): void {
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:#1d3557;min-height:100vh;display:flex;align-items:center;font-family:system-ui}
.card{max-width:380px;margin:auto;border-radius:18px;border:0}
.pin-input{font-size:1.8rem;letter-spacing:.6rem;text-align:center}</style>
</head><body>
<div class="container"><div class="card p-4">
  <div class="text-center mb-3"><i class="bi <?= e($icon) ?> text-danger" style="font-size:2.6rem"></i>
    <h5 class="mt-2 mb-0"><?= e($title) ?></h5></div>
  <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrfField() ?>
    <div class="mb-3"><label class="form-label">Restaurant Code</label>
      <input name="rcode" class="form-control form-control-lg" placeholder="slug or code" required autofocus></div>
    <div class="mb-3"><label class="form-label">4-digit PIN</label>
      <input name="pin" class="form-control form-control-lg pin-input" inputmode="numeric" maxlength="6" required></div>
    <button class="btn btn-danger btn-lg w-100">Login</button>
  </form>
</div></div>
</body></html>
<?php
}
