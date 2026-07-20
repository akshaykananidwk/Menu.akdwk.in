<?php
/** Super Admin login. */
require_once dirname(__DIR__) . '/config/config.php';
if (isSuperAdmin()) { redirect(BASE_URL . '/admin/index.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $ident = 'admin:' . $email;

    if (isLoginLocked($ident)) {
        $error = __('account_locked');
    } else {
        $admin = db_one('SELECT * FROM ' . tbl('super_admins') . ' WHERE email = :e AND status = 1', [':e' => $email]);
        if ($admin && password_verify($pass, $admin['password'])) {
            clearLoginAttempts($ident);
            regenerateSession();
            $_SESSION['admin_id']   = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            logActivity('super_admin', (int)$admin['id'], 'Logged in');
            redirect(BASE_URL . '/admin/index.php');
        } else {
            recordFailedLogin($ident);
            $error = __('invalid_login');
        }
    }
}
$siteName = getSetting('site_name', 'AK Menu System');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login · <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:linear-gradient(135deg,#1d3557,#e63946);min-height:100vh;display:flex;align-items:center;font-family:system-ui}
.login-card{max-width:400px;margin:auto;border-radius:16px;border:0;box-shadow:0 20px 60px rgba(0,0,0,.3)}</style>
</head>
<body>
<div class="container">
  <div class="card login-card p-4">
    <div class="text-center mb-3">
      <i class="bi bi-shield-lock-fill text-danger" style="font-size:2.5rem"></i>
      <h5 class="mt-2 mb-0"><?= e($siteName) ?></h5>
      <small class="text-muted">Super Admin Panel</small>
    </div>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrfField() ?>
      <div class="mb-3"><label class="form-label">Email</label>
        <input name="email" type="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label>
        <input name="password" type="password" class="form-control" required></div>
      <button class="btn btn-danger w-100">Login</button>
    </form>
  </div>
</div>
</body>
</html>
