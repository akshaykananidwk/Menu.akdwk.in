<?php
/**
 * Admin › System Guide.
 *
 * The platform operator's manual: what every background program is, the exact
 * order to configure the system in, and what each setting actually controls.
 * Where a setting is already configured, the chapter shows a live green/amber
 * status so the operator can see at a glance what is still missing.
 *
 * Content is one $chapters array — adding a chapter is a data edit.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$B = BASE_URL;

// ---- Live readiness checks --------------------------------------------------
// Each is a cheap, read-only probe so the page stays fast.
$health   = function_exists('cronHealth') ? cronHealth() : [];
$secretOk = !empty($health['secret_set']);
$tickOk   = !empty($health['server_ok']);
$failing  = (int)($health['failing'] ?? 0);

$waCloud  = function_exists('waCloudEnabled') && waCloudEnabled();
$waLegacy = function_exists('getWaSetting') && trim((string)getWaSetting('api_key', '')) !== '';

$checks = [
    'db'   => ['ok' => isset($pdo), 'label' => 'Database connected'],
    'cron' => ['ok' => $secretOk && $tickOk && $failing === 0,
               'label' => !$secretOk ? 'Cron secret not generated yet — open Cron & Tasks'
                        : (!$tickOk  ? 'Master cron has not fired in the last 5 minutes — check the cPanel cron'
                        : ($failing > 0 ? $failing . ' scheduled job(s) are failing' : 'Master cron is firing, all jobs healthy'))],
    'mail' => ['ok' => mailerHasSmtp(), 'label' => 'E-mail transport: ' . mailerTransportLabel()],
    'whatsapp' => ['ok' => $waCloud || $waLegacy,
                   'label' => $waCloud ? 'Meta WhatsApp Cloud API active'
                            : ($waLegacy ? 'Legacy WhatsApp gateway configured' : 'No WhatsApp provider configured')],
    'ai'   => ['ok' => trim((string)getSetting('gemini_api_key', '')) !== '', 'label' => 'Gemini API key set'],
    'pay'  => ['ok' => trim((string)getSetting('razorpay_key_id', '')) !== ''
                        || trim((string)getSetting('upi_id', '')) !== '',
               'label' => 'Subscription payment method set (Razorpay or UPI)'],
    'seo'  => ['ok' => trim((string)getSetting('google_site_verification', '')) !== ''
                        || trim((string)getSetting('ga4_id', '')) !== '',
               'label' => 'Search Console / GA4 connected'],
];

$chapters = [

[
 'id' => 'order', 'icon' => 'bi-list-ol', 'group' => 'Set-up order',
 'title' => 'Configure in this exact order',
 'what'  => 'Each step depends on the one before it. Working top-down avoids every "why is nothing sending?" question.',
 'steps' => [
   '<b>Settings → Branding &amp; Appearance</b> — site name, logo, favicon, colours. This is what clients and e-mails are branded with.',
   '<b>Cron &amp; Tasks</b> — generate the secret and paste the one-line cron into cPanel. <u>Nothing scheduled works until this is done</u> — no WhatsApp, no e-mail, no reminders, no reports.',
   '<b>Settings → SMTP</b> — set the From address and SMTP host, then press <b>Send test</b>. Registration mail, password-reset links and e-mail OTPs all depend on this.',
   '<b>WhatsApp Cloud API</b> (or the legacy gateway under WhatsApp) — enter the Phone Number ID, WABA ID and permanent token, then Test Connection.',
   '<b>Settings → AI</b> — paste the Gemini API key and press Test. This powers AI Menu Import, AI Write, the diner chatbot and the AI Advisor.',
   '<b>Plans</b> — create your paid plans, then set <b>Trial &amp; Launch Offer</b> (which plan new signups get and for how many days).',
   '<b>Settings → Payments</b> — Razorpay keys for online subscription payment, and the UPI number/ID for offline.',
   '<b>SEO Manager</b> — Search Console and Bing verification, GA4/GTM, then review the city pages.',
   '<b>Backup</b> — take one manual backup, then enable the nightly <b>Database Backup</b> job in Cron &amp; Tasks.',
 ],
 'warn' => 'Step 2 is the one that is most often skipped. If clients report "WhatsApp is not sending", check Cron &amp; Tasks first — not the gateway.',
],

[
 'id' => 'cron', 'icon' => 'bi-alarm', 'group' => 'Background programs',
 'title' => 'The master cron — one job runs everything',
 'what'  => 'The server runs ONE cron every minute. That single tick decides which internal jobs are due and runs them. You never add another server cron again, however many features we ship.',
 'steps' => [
   'Open <b>Cron &amp; Tasks</b>. If no secret exists yet, generate one — the page then shows the exact command with the secret already in it.',
   'In cPanel → Cron Jobs, add it at <b>every minute</b> (<code>* * * * *</code>). The command is: <code>curl -s "' . e($B) . '/cron/run.php?key=YOUR_SECRET"</code> (the page shows your real key).',
   'Wait two minutes and reload Cron &amp; Tasks. The health banner should turn green and each job should show a last-run time.',
   'Every job can be switched on/off, run manually with <b>Run now</b>, and has its own execution history with duration and outcome.',
   'Jobs are locked individually while they run, so a slow job can never run twice at once, and a stuck lock is reclaimed after 15 minutes.',
   'One job failing never blocks the others — each is isolated and logged separately.',
 ],
 'tips' => [
   'The jobs that exist today: <b>WhatsApp Queue</b> and <b>E-mail Queue</b> (every minute), <b>Subscription &amp; Payment Reminders</b> (9am), <b>Auto Reports</b> (9pm), <b>AI Usage Rollup</b> (2am), <b>E-mail &amp; Token Cleanup</b> (4am), <b>Update Check</b> (every 12h) and <b>Database Backup</b> (3am, off by default).',
   'The <code>key</code> in the cron URL is the only thing protecting it. If you ever paste the command into a public place, regenerate the secret.',
 ],
],

[
 'id' => 'email', 'icon' => 'bi-envelope-at', 'group' => 'Background programs',
 'title' => 'E-mail — SMTP, templates and the log',
 'what'  => 'E-mail is what makes account recovery possible without you being involved. It sends the welcome mail, password-reset links, e-mail login OTPs and a "your password was changed" security notice.',
 'steps' => [
   '<b>Settings → SMTP</b>. Fill in Host, Port, Encryption, Username, Password and — the one people forget — <b>From E-mail</b>.',
   'Match the port to the encryption: <b>587 → STARTTLS</b>, <b>465 → SSL/TLS</b>, <b>25 → None</b>. Mismatching these is the usual cause of a failed test.',
   'Save, then press <b>Send test</b>. The result shows the transport used and the exact reply from the mail server, so a failure tells you what to fix.',
   'For Gmail or Google Workspace you must use an <b>App Password</b>, not the account password, and the From address must be that same mailbox.',
   'Open <b>E-mail Log</b> to see every message with its status (Sent / Queued / Failed) and the server\'s reply. Failed messages have a one-click Retry.',
   'Messages are queued, not sent inline, so a slow mail server never slows down a signup. The E-mail Queue job delivers them within a minute.',
 ],
 'tips' => [
   'If mail lands in Spam, add <b>SPF</b> and <b>DKIM</b> records for your sending domain at your DNS provider. That is a DNS change, not an app setting.',
   'The master switch at the top of the SMTP tab stops all outgoing e-mail without erasing your credentials — useful while testing.',
   'If no SMTP host is set at all, the system falls back to the server\'s own <code>mail()</code>. It often works on cPanel but is far more likely to be marked as spam.',
 ],
 'warn' => 'The From E-mail must be an address your SMTP account is allowed to send as, or the relay will reject every message with "MAIL FROM rejected".',
],

[
 'id' => 'whatsapp', 'icon' => 'bi-whatsapp', 'group' => 'Background programs',
 'title' => 'WhatsApp — Meta Cloud API',
 'what'  => 'Two providers are supported: the official Meta Cloud API (recommended) and the older gateway. Switching is a dropdown; nothing else changes.',
 'steps' => [
   'Open <b>WhatsApp Cloud API</b> and enter the <b>Phone Number ID</b>, <b>WABA ID</b> and <b>Permanent Access Token</b> from your Meta app.',
   'Switch the provider to <b>Cloud</b> and press <b>Test Connection</b>. It shows your verified name, number and quality rating.',
   'Build templates on the <b>Templates</b> tab — Utility, Marketing or Authentication, with a text/image/document/video header, body variables, footer and buttons — then submit them to Meta for approval.',
   'Approved / Pending / Rejected status is read live from Meta, with the rejection reason shown when there is one.',
   'The <b>Send</b> tab fires a text, an approved template, a media message or reply-buttons to any number, showing the accepted result or the exact Meta error.',
   '<b>Conversations</b> shows every message the system sent, grouped by contact, with its trigger and failure reason.',
 ],
 'warn' => 'Delivery ticks and <i>incoming</i> customer replies are only available through Meta\'s webhook, which has no polling API. Everything listed above works without a webhook; those two do not exist for us by design.',
],

[
 'id' => 'ai', 'icon' => 'bi-cpu', 'group' => 'Cost &amp; control',
 'title' => 'AI setup, metering and cost control',
 'what'  => 'All AI runs on Gemini. Every call is metered with the provider\'s own token counts, so your cost is measured, not estimated.',
 'steps' => [
   '<b>Settings → AI</b> — paste the Gemini API key, pick the model and press <b>Test</b>. Use <b>Load models</b> to list the models that key can actually use.',
   'Set the <b>monthly limit</b> and the diner chatbot\'s <b>daily question cap</b> per restaurant. This is your spending brake.',
   '<b>AI Usage &amp; Cost</b> → Overview shows platform-key vs users\'-key spend, a daily cost chart, top users and warning banners.',
   'Rates &amp; Settings holds the editable price table, the USD→local rate, your markup and the limits. Cost is computed from this table.',
   'The monthly Billing report applies your markup and prints to PDF.',
   'Oversized prompts are blocked before they are sent (logged as "blocked"), and logging is fire-and-forget so a logging failure never breaks the AI feature for a client.',
 ],
 'warn' => 'Clients never see the platform API key, your price table, your markup, or any other restaurant\'s usage. Each client sees only its own "AI Usage This Month" tile. Keep it that way.',
],

[
 'id' => 'plans', 'icon' => 'bi-gem', 'group' => 'Cost &amp; control',
 'title' => 'Plans, trial and the launch offer',
 'what'  => 'What a signup gets for free, and what they pay for afterwards.',
 'steps' => [
   '<b>Plans</b> — create each plan with its price, validity and limits (menu items, tables, staff, features).',
   '<b>Trial &amp; Launch Offer</b> — choose which plan new signups receive during the trial and how many days it lasts.',
   'The launch offer is an automatic time-limited discount (default 50% within 24h of signup). The client\'s Plans page shows a live countdown and struck-through prices, and the discount is re-applied <b>server-side</b> at payment — never trusted from the browser.',
   '<b>Plan Requests</b> — approve or reject offline (UPI) payments. Approving activates the plan, marks the invoice paid and WhatsApps the client. A badge shows how many are pending.',
   '<b>Invoices</b> — every subscription payment gets a GST invoice the client can download from Plans → Billing history.',
   'Renewing the same plan early stacks the new validity on top of remaining days rather than discarding them.',
 ],
 'tips' => [
   'Check that your trial plan is not more generous than your cheapest paid plan. If the trial out-specs what people upgrade to, nobody has a reason to convert.',
 ],
],

[
 'id' => 'seo', 'icon' => 'bi-search', 'group' => 'Growth',
 'title' => 'SEO, analytics and city pages',
 'what'  => 'Everything that makes the platform findable on Google.',
 'steps' => [
   '<b>SEO Manager</b> — add the Search Console and Bing verification codes, and the GA4 / GTM IDs.',
   'The sitemap index at <code>' . e($B) . '/sitemap.xml</code> is generated live and covers pages, restaurants (auto-split at 40k, honouring each client\'s indexing toggle), cities and blog. <code>/robots.txt</code> is dynamic too.',
   'City pages live at <code>/digital-menu/{city}</code> (45+ Indian cities seeded) with unique local content, live restaurant listings, FAQ and LocalBusiness schema. Manage them, or bulk-import by CSV, in SEO Manager.',
   'Use the keyword tracker, redirect manager and 404 log to find and fix what visitors could not reach.',
   'Publishing a new menu pings Google, Bing and IndexNow automatically.',
   '<b>Website Analytics</b> — daily and all-time visitors, a 30-day trend and the top pages. Counting is deduped per device per day; bots, super-admin visits, AJAX and internal panel/API/asset requests are excluded.',
 ],
],

[
 'id' => 'maintain', 'icon' => 'bi-wrench-adjustable', 'group' => 'Operations',
 'title' => 'Updates, backup and the activity log',
 'what'  => 'How to keep the installation current and recoverable.',
 'steps' => [
   '<b>Updates</b> — set owner/repo, branch and token once, then press <b>Check &amp; Update</b>. Database migrations apply automatically on the next panel load (for the Super Admin <i>and</i> for restaurant owners).',
   '<code>config/db.php</code> and <code>config/installed.lock</code> are never overwritten by an update, so your credentials survive every upgrade.',
   '<b>Backup</b> — take a manual dump any time, and enable the nightly <b>Database Backup</b> job in Cron &amp; Tasks (it keeps the most recent few in <code>/backups</code>).',
   '<b>Activity Log</b> — who did what and when, across admin and client panels. Start here when investigating anything unexpected.',
   '<b>Support Tickets</b> and <b>Notice Board</b> are how you talk to all clients: a ticket is one-to-one, a notice shows to everyone.',
 ],
 'warn' => 'Take a backup before every update. Restoring a dump is a minute; rebuilding a client\'s menu is not.',
],

[
 'id' => 'arch', 'icon' => 'bi-diagram-3', 'group' => 'Reference',
 'title' => 'How the code is laid out',
 'what'  => 'Useful when something needs investigating, and when briefing anyone who works on this.',
 'steps' => [
   '<code>/config/config.php</code> is the single bootstrap every page includes. It defines paths, starts the session, opens the PDO connection, then loads <code>functions.php</code>, <code>seo.php</code>, <code>ai_metering.php</code>, <code>wa_cloud.php</code>, <code>mailer.php</code> and <code>scheduler.php</code>.',
   '<code>/admin</code> super-admin panel · <code>/client</code> restaurant panel · <code>/app</code> staff app · <code>/r</code> public menu and TV board · <code>/api</code> JSON endpoints · <code>/cron/run.php</code> the single master cron · <code>/updates/*.sql</code> database migrations.',
   'Every restaurant is a <b>tenant</b>. The tenant id comes from the session only and is never accepted from a request, so one client can never read another\'s data.',
   'All database access goes through prepared statements. Every POST is CSRF-checked. Order prices are always recalculated on the server.',
   'No Composer and no build step: the whole thing is plain PHP 8 plus Bootstrap and vanilla JS from a CDN. It deploys by copying files.',
   'Database migrations are idempotent and run automatically. They use <code>CREATE TABLE IF NOT EXISTS</code> and <code>ALTER … MODIFY</code> — never <code>ADD COLUMN</code>, which MySQL cannot do conditionally.',
 ],
],

];

$groups = [];
foreach ($chapters as $ch) { $groups[$ch['group']][] = $ch; }

$pageTitle = 'System Guide';
$activeNav = 'help';
require __DIR__ . '/_header.php';
?>
<style>
.sg-toc { position:sticky; top:80px; max-height:calc(100vh - 110px); overflow-y:auto; }
.sg-toc a { display:block; padding:5px 10px; border-radius:6px; font-size:.88rem; color:#475467;
            text-decoration:none; border-left:3px solid transparent; }
.sg-toc a:hover { background:#f2f4f7; }
.sg-toc a.active { background:#f2f4f7; color:var(--primary); font-weight:600; border-left-color:var(--primary); }
.sg-toc .g { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#98a2b3;
             font-weight:700; margin:14px 0 4px 10px; }
.sg-ch { scroll-margin-top:84px; }
.sg-steps { counter-reset:s; list-style:none; padding-left:0; margin:0; }
.sg-steps > li { counter-increment:s; position:relative; padding:0 0 0 38px; margin-bottom:13px; line-height:1.6; }
.sg-steps > li::before { content:counter(s); position:absolute; left:0; top:1px; width:25px; height:25px;
  border-radius:50%; background:var(--primary); color:#fff; font-size:.78rem; font-weight:700;
  display:flex; align-items:center; justify-content:center; }
.sg-ch code { background:#f2f4f7; padding:2px 6px; border-radius:4px; font-size:.85em; word-break:break-all; }
.sg-tip  { background:#f0f9f4; border-left:4px solid #12b76a; padding:10px 14px; border-radius:0 8px 8px 0; margin-top:6px; }
.sg-warn { background:#fff6ed; border-left:4px solid #f79009; padding:10px 14px; border-radius:0 8px 8px 0; margin-top:10px; }
.sg-ch p, .sg-ch li { overflow-wrap:break-word; }
.sg-check { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid #f2f4f7; font-size:.88rem; }
.sg-check:last-child { border-bottom:0; }
</style>

<div class="row g-4">
  <div class="col-lg-3 order-lg-1">
    <div class="sg-toc">
      <div class="mb-2">
        <input id="sgSearch" class="form-control form-control-sm" placeholder="Search this guide…" oninput="sgFilter(this.value)">
      </div>
      <a href="#sg-status"><i class="bi bi-clipboard-check me-1"></i>Readiness check</a>
      <?php foreach ($groups as $gname => $items): ?>
        <div class="g"><?= $gname ?></div>
        <?php foreach ($items as $ch): ?>
          <a href="#ch-<?= e($ch['id']) ?>" data-toc="<?= e($ch['id']) ?>">
            <i class="bi <?= e($ch['icon']) ?> me-1"></i><?= $ch['title'] ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-lg-9">
    <!-- Live readiness -->
    <section class="card border-0 shadow-sm mb-3 sg-ch" id="sg-status" data-ch="sg-status">
      <div class="card-body">
        <h5 class="mb-1"><i class="bi bi-clipboard-check" style="color:var(--primary)"></i> Readiness check</h5>
        <p class="text-muted small">Read live from your settings right now. Amber means a feature below will not work yet.</p>
        <?php foreach ($checks as $c): ?>
          <div class="sg-check">
            <?php if ($c['ok']): ?>
              <i class="bi bi-check-circle-fill text-success"></i>
            <?php else: ?>
              <i class="bi bi-exclamation-triangle-fill text-warning"></i>
            <?php endif; ?>
            <span><?= e($c['label']) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <a href="<?= e($B) ?>/admin/cron.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-alarm"></i> Cron &amp; Tasks</a>
          <a href="<?= e($B) ?>/admin/settings.php#tab-smtp" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope"></i> SMTP</a>
          <a href="<?= e($B) ?>/admin/emails.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope-at"></i> E-mail Log</a>
          <a href="<?= e($B) ?>/admin/wa_cloud.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-whatsapp"></i> WhatsApp Cloud</a>
          <a href="<?= e($B) ?>/admin/ai_usage.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-cpu"></i> AI Usage</a>
        </div>
      </div>
    </section>

    <div id="sgNoMatch" class="alert alert-light border" style="display:none">
      <i class="bi bi-search"></i> Nothing in this guide matches that word.
    </div>

    <?php foreach ($chapters as $ch): ?>
      <section class="card border-0 shadow-sm mb-3 sg-ch" id="ch-<?= e($ch['id']) ?>" data-ch="<?= e($ch['id']) ?>">
        <div class="card-body">
          <h5 class="d-flex align-items-start gap-2">
            <i class="bi <?= e($ch['icon']) ?>" style="color:var(--primary)"></i>
            <span><?= $ch['title'] ?></span>
          </h5>
          <p class="text-muted"><?= $ch['what'] ?></p>
          <ol class="sg-steps mt-3">
            <?php foreach ($ch['steps'] as $st): ?><li><?= $st ?></li><?php endforeach; ?>
          </ol>
          <?php foreach ($ch['tips'] ?? [] as $tip): ?>
            <div class="sg-tip small"><strong><i class="bi bi-lightbulb"></i> Tip:</strong> <?= $tip ?></div>
          <?php endforeach; ?>
          <?php if (!empty($ch['warn'])): ?>
            <div class="sg-warn small"><strong><i class="bi bi-exclamation-triangle"></i> Important:</strong> <?= $ch['warn'] ?></div>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="alert alert-light border small">
      <i class="bi bi-info-circle"></i>
      Looking for the <strong>restaurant owner's</strong> guide (menu, QR, orders, staff app, in English and Gujarati)?
      It lives in the client panel at <code>/client/help.php</code> — every client sees it as <b>Help &amp; Guide</b> in their sidebar.
    </div>
  </div>
</div>

<script>
function sgFilter(q){
  q = (q || '').trim().toLowerCase();
  var shown = 0;
  document.querySelectorAll('.sg-ch').forEach(function(sec){
    var hit = q === '' || (sec.textContent || '').toLowerCase().indexOf(q) !== -1;
    sec.style.display = hit ? '' : 'none';
    var link = document.querySelector('[data-toc="' + sec.dataset.ch + '"]');
    if (link) { link.style.display = hit ? '' : 'none'; }
    if (hit) { shown++; }
  });
  document.getElementById('sgNoMatch').style.display = shown ? 'none' : 'block';
}

(function(){
  var links = Array.prototype.slice.call(document.querySelectorAll('[data-toc]'));
  var secs  = Array.prototype.slice.call(document.querySelectorAll('.sg-ch'));
  if (!links.length) { return; }
  var queued = false;
  function sync(){
    queued = false;
    var cur = secs[0];
    secs.forEach(function(s){
      if (s.style.display !== 'none' && s.getBoundingClientRect().top <= 140) { cur = s; }
    });
    links.forEach(function(a){ a.classList.toggle('active', cur && a.dataset.toc === cur.dataset.ch); });
  }
  window.addEventListener('scroll', function(){
    if (!queued) { queued = true; requestAnimationFrame(sync); }
  }, {passive:true});
  sync();
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
