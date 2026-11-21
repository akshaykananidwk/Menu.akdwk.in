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
ak_run_pending_migrations(); // creates plan_coupons / invoice_details on first use

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
    } elseif ($action === 'record_payment') {
        // Payment received outside Razorpay (cash / GPay / bank): raise a PAID
        // invoice with the discount given, optionally activate the plan, and
        // tell the client. The client sees it in Plans & Billing.
        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $planId   = (int)($_POST['plan_id'] ?? 0);
        $tenant   = $tenantId ? db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]) : null;
        $plan     = $planId ? db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $planId]) : null;
        if (!$tenant || !$plan) { flash('error', 'Select a restaurant and a plan.'); redirect(BASE_URL . '/admin/invoices.php'); }

        $gross = planGross($plan);
        $dType = $_POST['discount_type'] ?? 'none';
        $dVal  = max(0, (float)($_POST['discount_value'] ?? 0));
        $code  = null; $label = '';
        if ($dType === 'percent') {
            $dVal = min($dVal, 100);
            $disc = round($gross * $dVal / 100, 2);
            $label = 'Special discount ' . rtrim(rtrim(number_format($dVal, 2), '0'), '.') . '%';
        } elseif ($dType === 'flat') {
            $disc = round(min($dVal, $gross), 2);
            $label = 'Special discount';
        } elseif ($dType === 'coupon') {
            $ev = planCouponEvaluate((string)($_POST['coupon_code'] ?? ''), $tenantId, $plan, $gross);
            if (!$ev['valid']) { flash('error', 'Coupon: ' . $ev['message']); redirect(BASE_URL . '/admin/invoices.php'); }
            $disc = $ev['discount']; $code = $ev['code']; $label = 'Coupon ' . $code;
        } else {
            $disc = 0.0;
        }
        $total = round(max(0, $gross - $disc), 2);
        $modes = planPaymentModes();
        $mode  = isset($modes[$_POST['payment_mode'] ?? '']) ? $_POST['payment_mode'] : 'other';
        $paidOn = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['paid_on'] ?? '')) ? $_POST['paid_on'] : date('Y-m-d');

        [$invId, $invNo] = planInvoice($tenantId, $plan, 'paid', [
            'total' => $total, 'list_total' => $gross, 'discount_label' => $label, 'coupon_code' => $code,
            'payment_mode' => $mode, 'payment_ref' => trim((string)($_POST['payment_ref'] ?? '')),
            'note' => trim((string)($_POST['note'] ?? '')), 'paid_on' => $paidOn,
        ]);
        // Keep the payments log complete (shows in Plan Requests as approved).
        try {
            db_insert('plan_requests', [
                'tenant_id' => $tenantId, 'plan_id' => $planId, 'amount' => $total, 'method' => 'offline',
                'txn_ref' => trim((string)($_POST['payment_ref'] ?? '')) ?: null, 'status' => 'approved',
                'note' => 'Recorded by admin (' . $modes[$mode] . ') ' . $invNo, 'processed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) { /* log only */ }

        $activate = ($_POST['activate'] ?? '1') === '1';
        if ($activate) { activatePlanForTenant($tenantId, $planId); }
        if (($_POST['notify'] ?? '1') === '1') { notifyPlanPaid($tenantId, (int)$invId, $plan, $total); }

        logActivity('super_admin', $_SESSION['admin_id'] ?? null,
            "Recorded {$modes[$mode]} payment {$invNo} for {$tenant['restaurant_name']} ({$plan['name']}, ₹$total)" . ($activate ? ', plan activated' : ''));
        flash('success', "Payment recorded. Invoice $invNo is now in the client's Plans & Billing" . ($activate ? ' and the plan is active.' : '.'));
    } elseif ($action === 'toggle_paid') {
        $id  = (int)($_POST['id'] ?? 0);
        $inv = $id ? db_one('SELECT * FROM ' . tbl('invoices') . ' WHERE id = :id', [':id' => $id]) : null;
        if ($inv) {
            $paid = $inv['status'] === 'paid';
            if ($paid) {
                db_update('invoices', ['status' => 'unpaid', 'paid_on' => null], ['id' => $id]);
            } else {
                markPlanInvoicePaid($id);
            }
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

try {
    $rows = db_all('SELECT i.*, tn.restaurant_name, p.name AS plan_name, d.payment_mode, d.discount, d.payment_ref
                    FROM ' . tbl('invoices') . ' i
                    LEFT JOIN ' . tbl('tenants') . ' tn ON tn.id = i.tenant_id
                    LEFT JOIN ' . tbl('plans') . ' p ON p.id = i.plan_id
                    LEFT JOIN ' . tbl('invoice_details') . ' d ON d.invoice_id = i.id
                    ORDER BY i.id DESC');
} catch (Throwable $e) {
    $rows = db_all('SELECT i.*, tn.restaurant_name, p.name AS plan_name
                    FROM ' . tbl('invoices') . ' i
                    LEFT JOIN ' . tbl('tenants') . ' tn ON tn.id = i.tenant_id
                    LEFT JOIN ' . tbl('plans') . ' p ON p.id = i.plan_id
                    ORDER BY i.id DESC');
}
$modes = planPaymentModes();
$tenants = db_all('SELECT id, restaurant_name, mobile FROM ' . tbl('tenants') . ' ORDER BY restaurant_name');
$plans   = db_all('SELECT id, name, price, validity_days FROM ' . tbl('plans') . ' WHERE status = 1 ORDER BY price');

// Does the PDF generator exist yet? (Built by another agent.)
$pdfExists = file_exists(ROOT_PATH . '/standee/invoice.php');
$cur = getSetting('currency', '₹');

$pageTitle = 'Invoices';
$activeNav = 'invoices';
require __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Billing &amp; Invoices</h5>
  <div class="d-flex gap-2">
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payModal"><i class="bi bi-cash-coin"></i> Record Payment</button>
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#genModal"><i class="bi bi-plus-lg"></i> Unpaid Invoice</button>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table id="invTable" class="table table-hover align-middle w-100">
      <thead><tr>
        <th>Invoice No</th><th>Restaurant</th><th>Plan</th><th>Amount</th><th>Tax</th><th>Total</th>
        <th>Status</th><th>Paid by</th><th>Date</th><th class="text-end">Actions</th>
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
          <td class="small"><?= !empty($r['payment_mode']) ? e($modes[$r['payment_mode']] ?? $r['payment_mode']) : '—' ?>
            <?= (float)($r['discount'] ?? 0) > 0 ? '<br><span class="text-success">−' . e($cur . number_format((float)$r['discount'], 2)) . ' off</span>' : '' ?></td>
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

<!-- Record payment modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="post" id="payForm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="record_payment">
      <div class="modal-header"><h6 class="modal-title"><i class="bi bi-cash-coin"></i> Record Payment (Cash / GPay / Bank)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Restaurant</label>
          <select name="tenant_id" class="form-select" required>
            <option value="">— Select —</option>
            <?php foreach ($tenants as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= e($t['restaurant_name']) ?><?= $t['mobile'] ? ' · ' . e($t['mobile']) : '' ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="mb-3"><label class="form-label">Plan</label>
          <select name="plan_id" id="rpPlan" class="form-select" required>
            <option value="">— Select —</option>
            <?php foreach ($plans as $p): ?>
              <option value="<?= (int)$p['id'] ?>" data-price="<?= (float)$p['price'] ?>"><?= e($p['name']) ?> (<?= e($cur . number_format((float)$p['price'], 2)) ?> + GST · <?= (int)$p['validity_days'] ?> days)</option>
            <?php endforeach; ?>
          </select></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Discount</label>
            <select name="discount_type" id="rpDType" class="form-select">
              <option value="none">No discount</option>
              <option value="percent">% off</option>
              <option value="flat">₹ off</option>
              <option value="coupon">Coupon code</option>
            </select></div>
          <div class="col-6" id="rpValWrap" style="display:none"><label class="form-label" id="rpValLabel">Value</label>
            <input name="discount_value" id="rpDVal" type="number" step="0.01" min="0" class="form-control" value="50"></div>
          <div class="col-6" id="rpCodeWrap" style="display:none"><label class="form-label">Coupon code</label>
            <input name="coupon_code" class="form-control text-uppercase" placeholder="e.g. WELCOME50"></div>
        </div>
        <div class="alert alert-light border py-2 small" id="rpSummary">Select a plan to see the amount.</div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Paid by</label>
            <select name="payment_mode" class="form-select">
              <?php foreach ($modes as $k => $lbl): if ($k === 'online') continue; ?>
                <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-6"><label class="form-label">Paid on</label>
            <input type="date" name="paid_on" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
        </div>
        <div class="mb-3"><label class="form-label">UTR / Transaction ID / Receipt no. <span class="text-muted small">(optional)</span></label>
          <input name="payment_ref" class="form-control" maxlength="120"></div>
        <div class="mb-3"><label class="form-label">Note <span class="text-muted small">(optional, shows on invoice)</span></label>
          <input name="note" class="form-control" maxlength="255"></div>
        <div class="form-check mb-1">
          <input class="form-check-input" type="radio" name="activate" value="1" id="rpAct1" checked>
          <label class="form-check-label" for="rpAct1"><b>Activate / extend the plan now</b></label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="radio" name="activate" value="0" id="rpAct0">
          <label class="form-check-label" for="rpAct0">Plan is already active — only create the bill</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="notify" value="1" id="rpNotify" checked>
          <label class="form-check-label" for="rpNotify">Send the client a WhatsApp with the invoice link</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success"><i class="bi bi-check2-circle"></i> Save Payment</button>
      </div>
    </form>
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
$pageScript = str_replace(['__TAX__', '__CUR__'], [json_encode((float)INVOICE_TAX_RATE), json_encode($cur)], <<<'HTML'
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

// Record Payment: live amount preview (the server recalculates on save).
(function(){
  const TAX = __TAX__, CUR = __CUR__;
  const plan = document.getElementById('rpPlan'), dt = document.getElementById('rpDType'), dv = document.getElementById('rpDVal');
  const fmt = n => CUR + Number(n).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  function upd(){
    const t = dt.value;
    document.getElementById('rpValWrap').style.display  = (t === 'percent' || t === 'flat') ? '' : 'none';
    document.getElementById('rpCodeWrap').style.display = t === 'coupon' ? '' : 'none';
    document.getElementById('rpValLabel').textContent = t === 'percent' ? '% off' : '₹ off';
    const o = plan.selectedOptions[0];
    const box = document.getElementById('rpSummary');
    if (!o || !o.dataset.price) { box.textContent = 'Select a plan to see the amount.'; return; }
    const price = parseFloat(o.dataset.price), gross = Math.round((price + price * TAX / 100) * 100) / 100;
    let disc = 0, v = parseFloat(dv.value) || 0;
    if (t === 'percent') disc = gross * Math.min(v, 100) / 100;
    if (t === 'flat') disc = Math.min(v, gross);
    disc = Math.round(disc * 100) / 100;
    box.innerHTML = 'Plan price incl. GST: <b>' + fmt(gross) + '</b>' +
      (t === 'coupon' ? '<br>Coupon discount is applied on save.' : '') +
      (disc > 0 ? '<br>Discount: <b class="text-success">−' + fmt(disc) + '</b>' : '') +
      (t !== 'coupon' ? '<br>Amount received: <b class="fs-6">' + fmt(gross - disc) + '</b>' : '');
  }
  [plan, dt, dv].forEach(el => el.addEventListener('input', upd));
  dt.addEventListener('change', upd); plan.addEventListener('change', upd);
  upd();
})();
</script>
HTML);
require __DIR__ . '/_footer.php';
?>
