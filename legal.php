<?php
/**
 * Public legal / policy pages — required by Razorpay (and most payment
 * gateways) before a merchant account is activated.
 *
 *   /terms     Terms & Conditions
 *   /privacy   Privacy Policy
 *   /refund    Cancellation & Refund Policy
 *   /shipping  Shipping & Delivery Policy (digital service — no physical goods)
 *   /contact   Contact Us
 *
 * Text written by the super admin in Admin → Settings → Legal overrides the
 * defaults below. Plain text is shown with line breaks; text containing HTML
 * tags is treated as trusted admin HTML (same as blog posts).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/seo.php';
checkMaintenance();

$e         = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$site      = getSetting('site_name', 'AK Menu System');
$primary   = getSetting('primary_color', '#e63946');
$secondary = getSetting('secondary_color', '#1d3557');
$legal     = trim((string)getSetting('legal_business_name', '')) ?: $site;
$email     = trim((string)getSetting('contact_email', ''));
$phone     = trim((string)getSetting('support_whatsapp', ''));
$address   = trim((string)getSetting('business_address', ''));
$updated   = date('d M Y', strtotime((string)getSetting('legal_updated_on', '')) ?: time());

$pages = [
    'terms'    => ['Terms & Conditions',            'terms_content'],
    'privacy'  => ['Privacy Policy',                'privacy_content'],
    'refund'   => ['Cancellation & Refund Policy',  'refund_content'],
    'shipping' => ['Shipping & Delivery Policy',    'shipping_content'],
    'contact'  => ['Contact Us',                    'contact_content'],
];
$page = strtolower(trim((string)($_GET['page'] ?? '')));
if (!isset($pages[$page])) { require __DIR__ . '/404.php'; exit; }
[$title, $settingKey] = $pages[$page];

// Contact line reused inside several policies.
$contactHtml = '<ul>'
    . '<li><b>Business name:</b> ' . $e($legal) . '</li>'
    . ($email   !== '' ? '<li><b>E-mail:</b> <a href="mailto:' . $e($email) . '">' . $e($email) . '</a></li>' : '')
    . ($phone   !== '' ? '<li><b>Phone / WhatsApp:</b> ' . $e($phone) . '</li>' : '')
    . ($address !== '' ? '<li><b>Address:</b> ' . nl2br($e($address)) . '</li>' : '')
    . '</ul>';

$S = $e($site);
$L = $e($legal);
$defaults = [
'terms' => <<<HTML
<p>These Terms &amp; Conditions govern your use of {$S} (the "Service"), operated by {$L}. By creating an account or using the Service you agree to these terms.</p>
<h2>1. The Service</h2>
<p>{$S} is an online software service (SaaS) for restaurants, cafés and food businesses. It provides a digital QR menu, online ordering, kitchen order tickets (KOT), billing, reports, WhatsApp notifications and related tools. The Service is delivered online; no physical product is sold.</p>
<h2>2. Account</h2>
<ul>
<li>You must give correct business and contact details when signing up.</li>
<li>You are responsible for keeping your login, PINs and passwords safe, and for everything done from your account.</li>
<li>You are responsible for the menu, prices, taxes and images you publish, and for following food-safety, GST and other laws that apply to your business.</li>
</ul>
<h2>3. Plans &amp; payment</h2>
<ul>
<li>Paid plans are prepaid for the validity period shown on the Plans page (for example 30, 180 or 365 days). Prices are in Indian Rupees (INR).</li>
<li>Online payments are processed securely by Razorpay. We do not store your card, UPI or bank details.</li>
<li>Your plan is activated as soon as the payment is confirmed. Offline (UPI/GPay) payments are activated after our team verifies them.</li>
<li>If a plan is not renewed after it expires, paid features stop working until you renew.</li>
</ul>
<h2>4. Payments from your customers</h2>
<p>If you turn on online payments for your diners, that money goes directly to your own Razorpay account. {$L} is not a party to the sale between you and your customer and is not responsible for refunds or disputes on those orders.</p>
<h2>5. Acceptable use</h2>
<p>You must not use the Service for anything illegal, misleading or harmful, or try to break, overload or gain unauthorised access to it. We may suspend accounts that break these terms.</p>
<h2>6. Availability</h2>
<p>We work to keep the Service available at all times, but we do not guarantee uninterrupted service. Planned maintenance and events outside our control may cause downtime.</p>
<h2>7. Limitation of liability</h2>
<p>To the extent allowed by law, our total liability for any claim is limited to the amount you paid us for the Service in the three months before the claim.</p>
<h2>8. Changes</h2>
<p>We may update these terms. The latest version is always on this page.</p>
<h2>9. Governing law</h2>
<p>These terms are governed by the laws of India. Courts in Gujarat, India have jurisdiction.</p>
<h2>10. Contact</h2>
{$contactHtml}
HTML,

'privacy' => <<<HTML
<p>This Privacy Policy explains how {$L} ("we") collects and uses information when you use {$S}.</p>
<h2>Information we collect</h2>
<ul>
<li><b>Restaurant owners:</b> name, business name, mobile number, e-mail, address, GST number, menu content and images you upload.</li>
<li><b>Diners:</b> name, mobile number, table number and order details entered while ordering from a restaurant's menu.</li>
<li><b>Technical data:</b> IP address, browser type and usage logs, used for security and to improve the Service.</li>
</ul>
<h2>Payments</h2>
<p>Payments are processed by Razorpay. Card, UPI and bank details are entered on Razorpay's secure checkout and are never stored on our servers. We only keep the payment reference and status.</p>
<h2>How we use information</h2>
<ul>
<li>To run the Service: show menus, deliver orders to the kitchen, print bills and send order updates.</li>
<li>To send account, billing and service messages by WhatsApp, SMS or e-mail.</li>
<li>To provide support, prevent fraud and meet legal requirements.</li>
</ul>
<h2>Sharing</h2>
<p>We do not sell personal data. Diner order details are shared only with the restaurant they ordered from. We use trusted service providers (hosting, payment gateway, messaging) only to run the Service.</p>
<h2>Data retention &amp; your rights</h2>
<p>We keep data while your account is active and as required by law. You can ask us to correct or delete your data by contacting us.</p>
<h2>Cookies</h2>
<p>We use essential cookies to keep you logged in and to remember your cart and language.</p>
<h2>Contact</h2>
{$contactHtml}
HTML,

'refund' => <<<HTML
<p>This policy applies to subscription plans purchased for {$S} from {$L}.</p>
<h2>Free trial</h2>
<p>New accounts can try the Service before paying, so you can check that it suits your business before you buy a plan.</p>
<h2>Cancellation</h2>
<ul>
<li>Plans are prepaid and do not renew automatically. You can stop using the Service at any time by not renewing.</li>
<li>To close your account, contact us using the details below.</li>
</ul>
<h2>Refunds</h2>
<ul>
<li>If you were charged twice, or charged but your plan was not activated, we will refund the extra or failed amount in full.</li>
<li>If you request a refund within <b>7 days</b> of your first paid purchase and the Service did not work for you, contact us and we will review it fairly.</li>
<li>Apart from the cases above, payments for a plan period that has already started are not refundable.</li>
<li>Approved refunds are sent back to the original payment method within <b>5–7 working days</b>.</li>
</ul>
<h2>Orders placed by diners at a restaurant</h2>
<p>Refunds for food orders paid to a restaurant are handled by that restaurant, as the money goes to the restaurant's own account.</p>
<h2>How to request a refund</h2>
<p>Send your registered mobile number, payment date and payment reference to:</p>
{$contactHtml}
HTML,

'shipping' => <<<HTML
<p>{$S} is a digital software service. <b>No physical product is shipped.</b></p>
<h2>Delivery of the Service</h2>
<ul>
<li>After an online payment is confirmed, your plan is activated instantly and you can use it right away from your account.</li>
<li>For offline (UPI/GPay) payments, the plan is activated after our team verifies the payment, usually within 24 hours.</li>
<li>Plan details and the invoice are available in your account under Plans / Invoices.</li>
</ul>
<h2>Printed QR standees</h2>
<p>QR codes and standee designs are delivered as downloadable files. If you separately order printed material from us, the delivery time and charges will be told to you before payment.</p>
<h2>Food orders from restaurants</h2>
<p>Delivery of food ordered from a restaurant's menu is handled by that restaurant.</p>
<h2>Contact</h2>
{$contactHtml}
HTML,

'contact' => <<<HTML
<p>We are happy to help with setup, billing or any question about {$S}.</p>
{$contactHtml}
<p>Support hours: Monday to Saturday, 10:00 AM to 7:00 PM (IST). We usually reply within one working day.</p>
HTML,
];

// Admin-written text wins over the default.
$custom = trim((string)getSetting($settingKey, ''));
if ($custom !== '') {
    $body = preg_match('/<[a-z][\s\S]*>/i', $custom) ? $custom : nl2br($e($custom));
    if ($page === 'contact') { $body .= $contactHtml; }
} else {
    $body = $defaults[$page];
}

$canon = absUrl($page);
render_landing_head([
    'title'       => "$title | $site",
    'description' => "$title for $site.",
    'canonical'   => $canon,
], $primary, $secondary, $site);
?>
<main class="wrap lp-main" style="max-width:820px">
  <h1 style="font-size:2rem;margin:.2rem 0"><?= $e($title) ?></h1>
  <div style="color:var(--muted);font-size:.9rem;margin-bottom:1.2rem">Last updated: <?= $e($updated) ?></div>
  <article class="lp-content"><?= $body /* defaults are escaped above; custom HTML is trusted admin input */ ?></article>
  <p style="margin-top:2rem;font-size:.9rem">
    <?php $i = 0; foreach ($pages as $slug => $p): ?><?= $i++ ? ' · ' : '' ?><a href="<?= $e(absUrl($slug)) ?>" style="color:var(--p)"><?= $e($p[0]) ?></a><?php endforeach; ?>
  </p>
</main>
<?php
render_landing_foot($site);
