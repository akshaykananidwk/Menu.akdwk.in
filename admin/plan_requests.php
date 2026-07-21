<?php
/**
 * Admin › Plan Requests.
 * Lists client subscription upgrade/renewal requests. Super admin approves an
 * offline request (activates the plan on the tenant, marks the matching invoice
 * paid, notifies the client) or rejects it. Online (Razorpay) requests arrive
 * already approved.
 */
$pageTitle = 'Plan Requests';
$activeNav = 'plan_requests';
require __DIR__ . '/_header.php';

// ---- POST handler (approve / reject) ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $req    = $id ? db_one('SELECT * FROM ' . tbl('plan_requests') . ' WHERE id = :id', [':id' => $id]) : null;

    if ($req && $req['status'] === 'pending') {
        $tenantId = (int)$req['tenant_id'];
        $planId   = (int)$req['plan_id'];
        $tenant   = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]);
        $plan     = db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $planId]);

        if ($action === 'approve' && $tenant && $plan) {
            activatePlanForTenant($tenantId, $planId);
            // Mark the newest matching unpaid invoice as paid.
            $inv = db_one('SELECT id FROM ' . tbl('invoices') . " WHERE tenant_id = :t AND plan_id = :p AND status = 'unpaid' ORDER BY id DESC LIMIT 1",
                          [':t' => $tenantId, ':p' => $planId]);
            if ($inv) {
                db_update('invoices', ['status' => 'paid', 'paid_on' => date('Y-m-d')], ['id' => (int)$inv['id']]);
            }
            db_update('plan_requests', ['status' => 'approved', 'processed_at' => date('Y-m-d H:i:s')], ['id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Approved {$plan['name']} for {$tenant['restaurant_name']}");

            $to = preg_replace('/\D/', '', (string)($tenant['whatsapp_no'] ?: $tenant['mobile']));
            if ($to) {
                $exp = db_val('SELECT expiry_date FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]);
                sendWhatsApp($to, "Your {$plan['name']} plan is now active" . ($exp ? " till $exp" : '') . '. Thank you!', null, $tenantId, 'manual');
            }
        } elseif ($action === 'reject') {
            db_update('plan_requests', ['status' => 'rejected', 'processed_at' => date('Y-m-d H:i:s')], ['id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Rejected plan request #$id");
        }
    }
    redirect(BASE_URL . '/admin/plan_requests.php');
}

$rows = db_all('SELECT pr.*, t.restaurant_name, t.mobile, p.name AS plan_name
                FROM ' . tbl('plan_requests') . ' pr
                LEFT JOIN ' . tbl('tenants') . ' t ON t.id = pr.tenant_id
                LEFT JOIN ' . tbl('plans') . ' p ON p.id = pr.plan_id
                ORDER BY (pr.status = "pending") DESC, pr.id DESC');
$curr = getSetting('currency', '₹');

function prBadge(string $s): string {
    $m = [
        'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
        'approved' => '<span class="badge bg-success">Approved</span>',
        'rejected' => '<span class="badge bg-secondary">Rejected</span>',
    ];
    return $m[$s] ?? e($s);
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Plan Requests</h5>
  <a href="<?= e(BASE_URL) ?>/admin/invoices.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt"></i> Invoices</a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="prTable">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Restaurant</th><th>Plan</th><th>Amount</th><th>Method</th>
          <th>Ref / Proof</th><th>Requested</th><th>Status</th><th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td>
            <div class="fw-semibold"><?= e($r['restaurant_name'] ?? '—') ?></div>
            <div class="text-muted small"><?= e($r['mobile'] ?? '') ?></div>
          </td>
          <td><?= e($r['plan_name'] ?? '—') ?></td>
          <td class="fw-semibold"><?= e($curr) ?><?= number_format((float)$r['amount'], 2) ?></td>
          <td><span class="badge bg-<?= $r['method'] === 'online' ? 'info' : 'light text-dark border' ?>"><?= e(ucfirst($r['method'])) ?></span></td>
          <td class="small">
            <?= $r['txn_ref'] ? e($r['txn_ref']) : '<span class="text-muted">—</span>' ?>
            <?php if ($r['screenshot']): ?>
              <a href="<?= e(mediaUrl($r['screenshot'])) ?>" target="_blank" class="d-inline-block ms-1"><i class="bi bi-image"></i> View</a>
            <?php endif; ?>
          </td>
          <td class="small text-nowrap"><?= e(date('d M, H:i', strtotime($r['created_at']))) ?></td>
          <td><?= prBadge($r['status']) ?></td>
          <td class="text-end text-nowrap">
            <?php if ($r['status'] === 'pending'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Activate this plan for the restaurant?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Approve</button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Reject this request?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
              </form>
            <?php else: ?>
              <span class="text-muted small"><?= $r['processed_at'] ? e(date('d M', strtotime($r['processed_at']))) : '' ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No plan requests yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$pageScript = $rows ? '<script>$(function(){$("#prTable").DataTable({order:[],pageLength:25});});</script>' : '';
require __DIR__ . '/_footer.php';
?>
