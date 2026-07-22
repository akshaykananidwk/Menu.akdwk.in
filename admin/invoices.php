<?php
/**
 * Admin › Billing / Invoices.
 * DataTable of invoices (joined with tenants). Generate an invoice for a
 * tenant + plan (computes amount/tax/total + invoice_no), toggle paid/unpaid.
 * PDF download links to standee/invoice.php (built elsewhere) — guarded.
 * Self-contained POST handler.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// GST rate (%) applied to plan price when generating an invoice.
defined('INVOICE_TAX_RATE') || define('INVOICE_TAX_RATE', 18.0);

// ---- POST handler -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $planId   = (int)($_POST['plan_id'] ?? 0);
        $tenant   = $tenantId ? db_one('SELECT id FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]) : null;
        $plan     = $planId ? db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $planId]) : null;

        if ($tenant && $plan) {
            $amount = (float)$plan['price'];
            $tax    = round($amount * INVOICE_TAX_RATE / 100, 2);
            $total  = $amount + $tax;
            // Sequential invoice number: INV-YYYYMM-####
            $seq = (int)db_val('SELECT COUNT(*) FROM ' . tbl('invoices')) + 1;
            $invoiceNo = 'INV-' . date('Ym') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

            db_insert('invoices', [
                'tenant_id'  => $tenantId,
                'invoice_no' => $invoiceNo,
                'plan_id'    => $planId,
                'amount'     => $amount,
                'tax'        => $tax,
                'total'      => $total,
                'status'     => 'unpaid',
            ]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Generated invoice $invoiceNo");
        }
    } elseif ($action === 'toggle_paid') {
        $id  = (int)($_POST['id'] ?? 0);
        $inv = $id ? db_one('SELECT * FROM ' . tbl('invoices') . ' WHERE id = :id', [':id' => $id]) : null;
        if ($inv) {
            $paid = $inv['status'] === 'paid';
            db_update('invoices', [
                'status'  => $paid ? 'unpaid' : 'paid',
                'paid_on' => $paid ? null : date('Y-m-d'),
            ], ['id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Invoice #$id marked " . ($paid ? 'unpaid' : 'paid'));
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_query('DELETE FROM ' . tbl('invoices') . ' WHERE id = :id', [':id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Deleted invoice #$id");
        }
    }
    redirect(BASE_URL . '/admin/invoices.php');
}

$rows = db_all('SELECT i.*, tn.restaurant_name, p.name AS plan_name
                FROM ' . tbl('invoices') . ' i
                LEFT JOIN ' . tbl('tenants') . ' tn ON tn.id = i.tenant_id
                LEFT JOIN ' . tbl('plans') . ' p ON p.id = i.plan_id
                ORDER BY i.id DESC');
$tenants = db_all('SELECT id, restaurant_name FROM ' . tbl('tenants') . ' ORDER BY restaurant_name');
$plans   = db_all('SELECT id, name, price FROM ' . tbl('plans') . ' WHERE status = 1 ORDER BY price');

// Does the PDF generator exist yet? (Built by another agent.)
$pdfExists = file_exists(ROOT_PATH . '/standee/invoice.php');
$cur = getSetting('currency', '₹');

$pageTitle = 'Invoices';
$activeNav = 'invoices';
require __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Billing &amp; Invoices</h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#genModal"><i class="bi bi-plus-lg"></i> Generate Invoice</button>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table id="invTable" class="table table-hover align-middle w-100">
      <thead><tr>
        <th>Invoice No</th><th>Restaurant</th><th>Plan</th><th>Amount</th><th>Tax</th><th>Total</th>
        <th>Status</th><th>Date</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= e($r['invoice_no']) ?></code></td>
          <td><?= e($r['restaurant_name'] ?? '—') ?></td>
          <td><?= e($r['plan_name'] ?? '—') ?></td>
          <td><?= e($cur . number_format((float)$r['amount'], 2)) ?></td>
          <td><?= e($cur . number_format((float)$r['tax'], 2)) ?></td>
          <td class="fw-semibold"><?= e($cur . number_format((float)$r['total'], 2)) ?></td>
          <td><?= $r['status'] === 'paid'
              ? '<span class="badge bg-success">Paid</span>'
              : '<span class="badge bg-warning text-dark">Unpaid</span>' ?></td>
          <td class="small text-muted"><?= e(date('d-m-Y', strtotime($r['created_at']))) ?></td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-<?= $r['status'] === 'paid' ? 'secondary' : 'success' ?>"
                    onclick="doAct('toggle_paid', <?= (int)$r['id'] ?>)"
                    title="<?= $r['status'] === 'paid' ? 'Mark unpaid' : 'Mark paid' ?>">
              <i class="bi bi-<?= $r['status'] === 'paid' ? 'x-circle' : 'check-circle' ?>"></i>
            </button>
            <a class="btn btn-sm btn-outline-primary" target="_blank"
               href="<?= e(BASE_URL . '/invoice.php?id=' . (int)$r['id']) ?>" title="View / print invoice"><i class="bi bi-receipt"></i></a>
            <button class="btn btn-sm btn-outline-danger" onclick="delInv(<?= (int)$r['id'] ?>)"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Generate invoice modal -->
<div class="modal fade" id="genModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="generate">
      <div class="modal-header"><h6 class="modal-title">Generate Invoice</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Restaurant</label>
          <select name="tenant_id" class="form-select" required>
            <option value="">— Select —</option>
            <?php foreach ($tenants as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= e($t['restaurant_name']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="mb-3"><label class="form-label">Plan</label>
          <select name="plan_id" class="form-select" required>
            <option value="">— Select —</option>
            <?php foreach ($plans as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($cur . number_format((float)$p['price'], 2)) ?>)</option>
            <?php endforeach; ?>
          </select></div>
        <p class="small text-muted mb-0">Tax of <?= INVOICE_TAX_RATE ?>% (GST) is applied automatically.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Generate</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden action form -->
<form method="post" id="actForm" class="d-none">
  <?= csrfField() ?>
  <input type="hidden" name="action" id="act_action">
  <input type="hidden" name="id" id="act_id">
</form>
<?php
$pageScript = <<<'HTML'
<script>
function doAct(action, id) {
  document.getElementById('act_action').value = action;
  document.getElementById('act_id').value = id;
  document.getElementById('actForm').submit();
}
function delInv(id) {
  AK.confirm('Delete this invoice?').then(ok => { if (ok) doAct('delete', id); });
}
$(function(){ $('#invTable').DataTable({order:[],pageLength:25}); });
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
