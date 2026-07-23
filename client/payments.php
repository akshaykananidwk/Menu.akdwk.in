<?php
/**
 * Client — Online Payment settings (Razorpay). Lets diners pay the bill online;
 * money settles to the restaurant's own Razorpay account.
 */
$pageTitle = 'Online Payment';
$activeNav = 'payments';
require __DIR__ . '/_header.php';

$canOrder = $tenant['ordering_mode'] !== 'view_only';
$cfg = tenantPaymentConfig((int)$tenant['id']);
$hasSecret = $cfg['key_secret'] !== '';
?>
<?php if (!$canOrder): ?>
  <div class="alert alert-warning"><i class="bi bi-info-circle"></i> Online payment works with online ordering. Enable ordering to let customers pay their bill online.</div>
<?php else: ?>

<div class="row">
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-1"><i class="bi bi-credit-card-2-front"></i> Accept Online Payments (Razorpay)</h6>
      <p class="text-muted small">When on, customers see a <strong>“Pay Online”</strong> button right after placing their order. Payments go straight to <strong>your</strong> Razorpay account. Create a free account at razorpay.com and paste your API keys below.</p>

      <form id="payForm" onsubmit="savePay(event)">
        <div class="form-check form-switch mb-3">
          <input type="hidden" name="razorpay_enabled" value="0">
          <input class="form-check-input" type="checkbox" role="switch" id="rzpEnabled" name="razorpay_enabled" value="1" <?= $cfg['razorpay_enabled'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="rzpEnabled">Enable online payment on my menu</label>
        </div>
        <div class="mb-3">
          <label class="form-label small">Razorpay Key ID</label>
          <input name="razorpay_key_id" class="form-control" placeholder="rzp_live_XXXXXXXX" value="<?= e($cfg['key_id']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small">Razorpay Key Secret</label>
          <input name="razorpay_key_secret" type="password" class="form-control" placeholder="<?= $hasSecret ? '•••••••• (leave blank to keep current)' : 'Your key secret' ?>" autocomplete="new-password">
          <div class="form-text">Stored securely on the server and never shown on your menu. Leave blank to keep the saved secret.</div>
        </div>
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Save</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <h6 class="fw-semibold"><i class="bi bi-shield-check"></i> How it works</h6>
      <ul class="small text-muted mb-0" style="padding-left:1.1rem;line-height:1.9">
        <li>Customer places the order as usual.</li>
        <li>A <strong>“Pay Online”</strong> button appears with the exact bill amount.</li>
        <li>They pay by UPI / card / netbanking via Razorpay.</li>
        <li>The order is marked <strong>Paid</strong> automatically once verified.</li>
        <li>If they skip it, the order stays payable at the counter — nothing breaks.</li>
      </ul>
    </div></div>
  </div>
</div>

<?php
$base = BASE_URL;
$pageScript = <<<HTML
<script>
const B = '$base';
function savePay(ev){
  ev.preventDefault();
  const fd = new FormData(ev.target);
  AK.post(B+'/api/pay.php?action=save', fd).then(r => AK.handle(r));
}
</script>
HTML;
endif;
require __DIR__ . '/_footer.php';
?>
