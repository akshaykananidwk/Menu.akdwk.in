<?php
/**
 * Colorful, print-ready subscription invoice.
 * ?id=<invoice_id>  — renders one invoice from the `invoices` table.
 * Access: the owning restaurant (client session) or a super admin.
 * Designed to be opened in a new tab and printed / saved as PDF from the browser.
 */
require_once __DIR__ . '/config/config.php';

$id = (int)($_GET['id'] ?? 0);
$inv = $id ? db_one('SELECT * FROM ' . tbl('invoices') . ' WHERE id = :id', [':id' => $id]) : null;
if (!$inv) { http_response_code(404); die('Invoice not found.'); }

// ---- Access control ----------------------------------------------------------
$isAdmin  = function_exists('isSuperAdmin') && isSuperAdmin();
$myTenant = (int)(currentTenantId() ?? 0);
if (!$isAdmin && $myTenant !== (int)$inv['tenant_id']) {
    http_response_code(403);
    die('You are not allowed to view this invoice.');
}

$tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => (int)$inv['tenant_id']]);
$plan   = $inv['plan_id'] ? db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => (int)$inv['plan_id']]) : null;

// Seller (platform) details.
$siteName = getSetting('site_name', 'AK Menu System');
$logo     = getSetting('logo', '');
$sellerGst= getSetting('company_gst', '');
$sellerEml= getSetting('contact_email', '');
$sellerWa = getWaSetting('support_number', getSetting('support_whatsapp', ''));
$primary  = getSetting('primary_color', '#e63946');
$secondary= getSetting('secondary_color', '#1d3557');
$curr     = getSetting('currency', '₹');
$money    = fn($n) => $curr . number_format((float)$n, 2);

$paid     = $inv['status'] === 'paid';
$taxRate  = (float)$inv['amount'] > 0 ? round((float)$inv['tax'] / (float)$inv['amount'] * 100) : 0;
$dateOut  = fn($d) => $d ? date('d M Y', strtotime($d)) : '—';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= e($inv['invoice_no']) ?> · <?= e($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--p:<?= e($primary) ?>;--s:<?= e($secondary) ?>}
*{box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#eef1f7;color:#0f172a;margin:0;padding:24px}
.sheet{max-width:820px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 24px 60px -24px rgba(15,23,42,.35)}
.head{background:linear-gradient(135deg,var(--s),var(--p));color:#fff;padding:34px 40px;position:relative;overflow:hidden}
.head:before{content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.10)}
.head:after{content:"";position:absolute;right:40px;bottom:-70px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.08)}
.head .row{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;position:relative;z-index:2;flex-wrap:wrap}
.brand{display:flex;align-items:center;gap:12px;font-size:1.4rem;font-weight:800}
.brand img{height:42px;border-radius:9px;background:#fff;padding:3px}
.brand .dot{width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.2);display:grid;place-items:center;font-size:1.3rem}
.inv-meta{text-align:right}
.inv-meta .t{font-size:1.7rem;font-weight:800;letter-spacing:.02em}
.inv-meta .n{opacity:.9;font-size:.95rem;margin-top:2px}
.stamp{display:inline-block;margin-top:10px;padding:.3rem .9rem;border-radius:999px;font-weight:800;font-size:.85rem;letter-spacing:.06em;text-transform:uppercase}
.stamp.paid{background:#10b981;color:#fff}
.stamp.due{background:#f59e0b;color:#fff}
.parties{display:flex;flex-wrap:wrap;gap:24px;padding:28px 40px 8px}
.party{flex:1;min-width:220px}
.party h6{margin:0 0 6px;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8}
.party .big{font-weight:700;font-size:1.05rem}
.party .mut{color:#64748b;font-size:.9rem;line-height:1.5}
.dates{display:flex;gap:24px;flex-wrap:wrap;padding:8px 40px 0}
.dates .d{font-size:.9rem}
.dates .d span{display:block;color:#94a3b8;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em}
table.items{width:100%;border-collapse:collapse;margin:22px 0 0}
table.items th{background:#f8fafc;color:#475569;text-align:left;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;padding:12px 40px}
table.items th.r,table.items td.r{text-align:right}
table.items td{padding:16px 40px;border-bottom:1px solid #f1f5f9}
table.items .desc b{font-weight:700}
table.items .desc small{color:#94a3b8}
.totals{display:flex;justify-content:flex-end;padding:16px 40px 0}
.totals table{width:320px;max-width:100%}
.totals td{padding:7px 0;font-size:.95rem}
.totals td.r{text-align:right}
.totals .grand td{border-top:2px solid #e2e8f0;padding-top:12px;font-size:1.25rem;font-weight:800;color:var(--p)}
.paybox{margin:22px 40px 0;padding:14px 18px;border-radius:12px;background:#f8fafc;border:1px solid #eef1f7;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;font-size:.9rem}
.foot{padding:22px 40px 34px;color:#94a3b8;font-size:.85rem;border-top:1px dashed #e2e8f0;margin-top:24px}
.thanks{font-weight:700;color:#0f172a;font-size:1rem;margin-bottom:4px}
.actions{max-width:820px;margin:0 auto 16px;display:flex;justify-content:flex-end;gap:10px}
.btn{border:0;border-radius:10px;padding:.6rem 1.1rem;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.btn-p{background:var(--p);color:#fff}
.btn-o{background:#fff;color:#334155;border:1px solid #cbd5e1}
@media print{
  body{background:#fff;padding:0}
  .sheet{box-shadow:none;border-radius:0;max-width:100%}
  .actions{display:none}
  @page{margin:12mm}
}
@media(max-width:560px){.head,.parties,.dates,table.items th,table.items td,.totals,.paybox,.foot{padding-left:22px;padding-right:22px}}
</style>
</head>
<body>

<div class="actions">
  <button class="btn btn-p" onclick="window.print()">⬇ Download / Print</button>
  <?php if ($isAdmin): ?><a class="btn btn-o" href="<?= e(BASE_URL) ?>/admin/invoices.php">Back</a>
  <?php else: ?><a class="btn btn-o" href="<?= e(BASE_URL) ?>/client/plans.php">Back</a><?php endif; ?>
</div>

<div class="sheet">
  <div class="head">
    <div class="row">
      <div class="brand">
        <?php if ($logo): ?><img src="<?= e(mediaUrl($logo)) ?>" alt=""><?php else: ?><span class="dot">🍽️</span><?php endif; ?>
        <?= e($siteName) ?>
      </div>
      <div class="inv-meta">
        <div class="t">INVOICE</div>
        <div class="n">#<?= e($inv['invoice_no']) ?></div>
        <div class="stamp <?= $paid ? 'paid' : 'due' ?>"><?= $paid ? '✓ Paid' : 'Payment Due' ?></div>
      </div>
    </div>
  </div>

  <div class="parties">
    <div class="party">
      <h6>From</h6>
      <div class="big"><?= e($siteName) ?></div>
      <div class="mut">
        <?= $sellerEml ? e($sellerEml) . '<br>' : '' ?>
        <?= $sellerWa ? 'WhatsApp: ' . e($sellerWa) . '<br>' : '' ?>
        <?= $sellerGst ? 'GSTIN: ' . e($sellerGst) : '' ?>
      </div>
    </div>
    <div class="party">
      <h6>Billed To</h6>
      <div class="big"><?= e($tenant['restaurant_name'] ?? '—') ?></div>
      <div class="mut">
        <?= $tenant && $tenant['owner_name'] ? e($tenant['owner_name']) . '<br>' : '' ?>
        <?php
          $addr = array_filter([$tenant['address'] ?? '', $tenant['city'] ?? '']);
          echo $addr ? e(implode(', ', $addr)) . '<br>' : '';
        ?>
        <?= $tenant && $tenant['mobile'] ? e($tenant['mobile']) . '<br>' : '' ?>
        <?= $tenant && !empty($tenant['gst_no']) ? 'GSTIN: ' . e($tenant['gst_no']) : '' ?>
      </div>
    </div>
  </div>

  <div class="dates">
    <div class="d"><span>Invoice Date</span><?= e($dateOut($inv['created_at'])) ?></div>
    <?php if ($paid): ?><div class="d"><span>Paid On</span><?= e($dateOut($inv['paid_on'])) ?></div><?php endif; ?>
    <?php if ($plan): ?><div class="d"><span>Billing Period</span><?= (int)$plan['validity_days'] ?> days</div><?php endif; ?>
  </div>

  <table class="items">
    <thead><tr><th>Description</th><th class="r">Amount</th></tr></thead>
    <tbody>
      <tr>
        <td class="desc">
          <b><?= e($plan['name'] ?? 'Subscription') ?> Plan</b><br>
          <small><?= $siteName ?> subscription<?= $plan ? ' · ' . (int)$plan['validity_days'] . ' days validity' : '' ?></small>
        </td>
        <td class="r"><?= e($money($inv['amount'])) ?></td>
      </tr>
    </tbody>
  </table>

  <div class="totals">
    <table>
      <tr><td>Subtotal</td><td class="r"><?= e($money($inv['amount'])) ?></td></tr>
      <?php if ((float)$inv['tax'] > 0): ?>
      <tr><td>GST (<?= (int)$taxRate ?>%)</td><td class="r"><?= e($money($inv['tax'])) ?></td></tr>
      <?php endif; ?>
      <tr class="grand"><td>Total</td><td class="r"><?= e($money($inv['total'])) ?></td></tr>
    </table>
  </div>

  <div class="paybox">
    <div><strong>Status:</strong>
      <?= $paid
        ? '<span style="color:#059669;font-weight:700">Paid in full</span>'
        : '<span style="color:#d97706;font-weight:700">Awaiting payment</span>' ?>
    </div>
    <div><strong>Amount <?= $paid ? 'Paid' : 'Due' ?>:</strong> <?= e($money($inv['total'])) ?></div>
  </div>

  <div class="foot">
    <div class="thanks">Thank you<?= $tenant ? ', ' . e($tenant['restaurant_name']) : '' ?>! 🎉</div>
    <?= $paid
        ? 'Your subscription is active. This is a computer-generated invoice and needs no signature.'
        : 'Please complete the payment to activate your plan. This is a computer-generated invoice.' ?>
    <br><?= e(POWERED_BY) ?>
  </div>
</div>

<script>
<?php if (!empty($_GET['print'])): ?>window.addEventListener('load', ()=>setTimeout(()=>window.print(), 400));<?php endif; ?>
</script>
</body>
</html>
