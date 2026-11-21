<?php
/**
 * Public diner bill — the {invoice_url} / {bill_url} link sent on WhatsApp when
 * an order is completed. No login: access needs the signed key from
 * orderBillUrl(), so bills can't be found by guessing order numbers.
 *   /r/bill.php?o=<order id>&k=<signature>
 */
require_once dirname(__DIR__) . '/config/config.php';

$id    = (int)($_GET['o'] ?? 0);
$key   = (string)($_GET['k'] ?? '');
$order = $id ? db_one('SELECT * FROM ' . tbl('orders') . ' WHERE id = :i', [':i' => $id]) : null;
if (!$order || $key === '' || !hash_equals(orderBillToken($order), $key)) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bill not found</title>'
       . '<body style="font-family:system-ui;text-align:center;padding:60px 20px"><h2>Bill not found</h2><p>This link is invalid or has expired.</p></body>';
    exit;
}
header('X-Robots-Tag: noindex, nofollow');

$tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => (int)$order['tenant_id']]) ?: [];
$items  = db_all('SELECT item_name, variant_label, addons_json, qty, price, total FROM ' . tbl('order_items') . ' WHERE order_id = :o ORDER BY id', [':o' => $id]);
$table  = $order['table_id'] ? db_val('SELECT table_no FROM ' . tbl('tables') . ' WHERE id = :i', [':i' => (int)$order['table_id']]) : null;
$cur    = ($tenant['currency'] ?? '') ?: getSetting('currency', '₹');
$m      = fn($n) => $cur . number_format((float)$n, 2);
$primary = ($tenant['primary_color'] ?? '') ?: '#e63946';
$cgst   = (float)($tenant['cgst'] ?? 0);
$sgst   = (float)($tenant['sgst'] ?? 0);
$svcPct = (float)($tenant['service_charge'] ?? 0);
$paid   = $order['payment_status'] === 'paid';
$modes  = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank', 'online' => 'Online'];
$types  = ['dinein' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'];
$menuUrl = !empty($tenant['slug']) ? publicMenuUrl($tenant['slug']) : '';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Bill #<?= e($order['order_no']) ?> · <?= e($tenant['restaurant_name'] ?? '') ?></title>
<style>
:root{--p:<?= e($primary) ?>}
*{box-sizing:border-box}
body{margin:0;background:#f1f3f8;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#111;padding:16px}
.bill{max-width:420px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 10px 30px -12px rgba(0,0,0,.25);overflow:hidden}
.top{background:var(--p);color:#fff;text-align:center;padding:20px 16px}
.top img{height:54px;border-radius:10px;background:#fff;padding:3px;margin-bottom:6px}
.top h1{font-size:1.25rem;margin:0}
.top .s{font-size:.82rem;opacity:.92;margin-top:4px;line-height:1.4}
.meta{display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px;padding:12px 16px;font-size:.85rem;color:#555;border-bottom:1px dashed #ddd}
table{width:100%;border-collapse:collapse}
.items td{padding:8px 16px;font-size:.92rem;vertical-align:top;border-bottom:1px solid #f2f2f2}
.items .q{color:#666;white-space:nowrap}
.items .r,.tot .r{text-align:right;white-space:nowrap}
.items small{color:#888}
.tot td{padding:5px 16px;font-size:.9rem}
.tot .g td{font-size:1.15rem;font-weight:800;padding-top:10px;border-top:2px solid #eee;color:var(--p)}
.disc{color:#059669}
.pay{margin:12px 16px;padding:10px 12px;border-radius:10px;font-weight:600;text-align:center;font-size:.92rem}
.pay.ok{background:#ecfdf5;color:#047857}.pay.due{background:#fff7ed;color:#c2410c}
.foot{text-align:center;padding:4px 16px 18px;color:#777;font-size:.85rem}
.btns{display:flex;gap:8px;justify-content:center;max-width:420px;margin:14px auto 0}
.btn{border:0;border-radius:10px;padding:.6rem 1rem;font-weight:600;font-size:.9rem;cursor:pointer;text-decoration:none;background:#fff;color:#333;border:1px solid #ddd}
.btn.p{background:var(--p);color:#fff;border-color:var(--p)}
@media print{body{background:#fff;padding:0}.bill{box-shadow:none}.btns{display:none}}
</style>
</head><body>
<div class="bill">
  <div class="top">
    <?php if (!empty($tenant['logo'])): ?><img src="<?= e(mediaUrl($tenant['logo'])) ?>" alt=""><br><?php endif; ?>
    <h1><?= e($tenant['restaurant_name'] ?? 'Bill') ?></h1>
    <div class="s">
      <?= !empty($tenant['address']) ? e($tenant['address']) . '<br>' : '' ?>
      <?= !empty($tenant['mobile']) ? 'Ph: ' . e($tenant['mobile']) : '' ?>
      <?= !empty($tenant['gst_no']) ? '<br>GSTIN: ' . e($tenant['gst_no']) : '' ?>
    </div>
  </div>
  <div class="meta">
    <span><b>Bill #<?= e($order['order_no']) ?></b></span>
    <span><?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></span>
    <span><?= $table ? 'Table ' . e($table) : e($types[$order['order_type']] ?? $order['order_type']) ?></span>
    <?php if (!empty($order['customer_name'])): ?><span><?= e($order['customer_name']) ?></span><?php endif; ?>
  </div>
  <table class="items">
    <?php foreach ($items as $i):
      $ad = [];
      foreach ((json_decode((string)$i['addons_json'], true) ?: []) as $a) { if (!empty($a['name'])) $ad[] = $a['name']; }
    ?>
    <tr>
      <td><?= e($i['item_name']) ?><?= $i['variant_label'] ? ' (' . e($i['variant_label']) . ')' : '' ?>
        <?= $ad ? '<br><small>+ ' . e(implode(', ', $ad)) . '</small>' : '' ?></td>
      <td class="q"><?= (int)$i['qty'] ?> × <?= e($m($i['price'])) ?></td>
      <td class="r"><?= e($m($i['total'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <table class="tot">
    <tr><td>Subtotal</td><td class="r"><?= e($m($order['subtotal'])) ?></td></tr>
    <?php if ((float)$order['tax'] > 0): ?>
      <?php if ($cgst > 0 && $sgst > 0): ?>
        <tr><td>CGST (<?= e(rtrim(rtrim((string)$cgst, '0'), '.')) ?>%)</td><td class="r"><?= e($m((float)$order['tax'] * $cgst / ($cgst + $sgst))) ?></td></tr>
        <tr><td>SGST (<?= e(rtrim(rtrim((string)$sgst, '0'), '.')) ?>%)</td><td class="r"><?= e($m((float)$order['tax'] * $sgst / ($cgst + $sgst))) ?></td></tr>
      <?php else: ?>
        <tr><td>GST</td><td class="r"><?= e($m($order['tax'])) ?></td></tr>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ((float)$order['service_charge'] > 0): ?>
      <tr><td>Service charge<?= $svcPct > 0 ? ' (' . e(rtrim(rtrim((string)$svcPct, '0'), '.')) . '%)' : '' ?></td><td class="r"><?= e($m($order['service_charge'])) ?></td></tr>
    <?php endif; ?>
    <?php if ((float)$order['discount'] > 0): ?>
      <tr class="disc"><td>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></td><td class="r">−<?= e($m($order['discount'])) ?></td></tr>
    <?php endif; ?>
    <tr class="g"><td>Total</td><td class="r"><?= e($m($order['total'])) ?></td></tr>
  </table>
  <div class="pay <?= $paid ? 'ok' : 'due' ?>">
    <?= $paid ? '✓ Paid' . (isset($modes[$order['payment_mode']]) ? ' by ' . e($modes[$order['payment_mode']]) : '') : 'Payment pending' ?>
  </div>
  <div class="foot">Thank you for visiting! 🙏<br>Please visit again.</div>
</div>
<div class="btns">
  <button class="btn p" onclick="window.print()">⬇ Download / Print</button>
  <?php if ($menuUrl): ?><a class="btn" href="<?= e($menuUrl) ?>">View Menu</a><?php endif; ?>
</div>
</body></html>
