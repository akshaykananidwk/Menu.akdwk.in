# AK Menu System — Developer Contract (READ FIRST)

This file is the single source of truth for conventions. Every file you write MUST follow it.
The foundation (config, DB, auth, layouts) is already built and TESTED. Do not modify foundation files.

## Environment
- PHP 8.x, MySQL/MariaDB, PDO everywhere. No Composer. Frontend: Bootstrap 5 + jQuery + vanilla JS via CDN.
- Local test DB is live: host `localhost`, db `ak_menu`, user `akuser`, pass `akpass`, prefix `ak_`.
- Admin login: `admin@akdwk.in` / `Admin@123`.
- A PHP dev server runs at http://127.0.0.1:8090 (document root = repo root).

## Bootstrap (every entry-point file starts with this)
```php
require_once dirname(__DIR__) . '/config/config.php';  // sets up $pdo, session, helpers, BASE_URL
```
`$pdo` is a global PDO. Use the helper functions instead of raw PDO where possible.

## Key helper functions (defined in config/functions.php — DO NOT redefine)
- `tbl('items')` → prefixed table name `ak_items`. ALWAYS wrap table names with `tbl()`.
- `db_one($sql,$params)` → assoc row or null. `db_all()` → rows. `db_val()` → scalar. `db_exec()` → lastInsertId.
- `db_insert('table',[col=>val])` → id. `db_update('table',[col=>val],['id'=>5])`.
- `e($str)` → htmlspecialchars. USE ON ALL OUTPUT.
- `getSetting($k,$default)` / `setSetting($k,$v)` — global white-label settings.
- `getWaSetting` / `setWaSetting` — WhatsApp settings.
- Auth guards: `requireAdmin()`, `requireClient()`, `requireStaff('waiter'|'kitchen')`.
- `isSuperAdmin()`, `isClient()`, `currentTenantId()`, `currentTenant()` (returns tenant row).
- `tenantPlan($tid)`, `planHasFeature($tid,$feature)`, `checkPlanLimit($tid,$type)` returns `['allowed'=>bool,'used','max','message']`. Types: items|categories|tables|waiters|ai.
- `csrfToken()`, `csrfField()` (hidden input), `csrfCheck()` (validates POST; call at top of every POST handler).
- `jsonSuccess($msg,$data)`, `jsonError($msg,$code)` — API responses. Contract: `{"status":"success|error","message":"","data":{}}`.
- `uploadImage($_FILES['x'],'items')` → relative path like `uploads/items/abc.webp` or null.
- `logActivity($userType,$userId,$action)`, `logAi($tid,$type,$tokens,$status)`.
- `sendWhatsApp($number,$msg,$mediaUrl,$tid,$triggerKey,$immediate)` → log id (non-blocking queue by default).
- `sendWaTemplate($triggerKey,$number,$vars,$mediaUrl,$tid,$immediate)` — renders template + queues.
- `generateOtp($mobile,$purpose)`, `verifyOtp($mobile,$otp,$purpose)`.
- `geminiExtractMenu([$absPath,...])` → `['ok'=>bool,'data'=>array|null,'error'=>string]`.
- `generateQr($data,$filename)` → relative path `uploads/qr/x.png` or null.
- `publicMenuUrl($slug)`, `makeSlug($text)`, `randomToken()`, `money($amt)`, `nextOrderNo($tid)`, `isRestaurantOpen($tenant)`.
- i18n: `__('key')`, `currentLang()`. Lang files: lang/en.php, lang/gu.php.
- `BASE_URL` constant = app root URL (no trailing slash). Always build URLs as `BASE_URL . '/client/...'`.
- `POWERED_BY`, `APP_VERSION` constants.

## Session shape
- Super admin: `$_SESSION['admin_id']`, `admin_name`.
- Client: `$_SESSION['tenant_id']`, `lang`. Impersonation sets `impersonated_by`.
- Staff: `$_SESSION['staff_id']`, `staff_role` (waiter|kitchen), `staff_name`, `staff_tenant_id`.

## Layout partials (reuse — do not rebuild chrome)
- Admin page:
```php
<?php $pageTitle='Clients'; $activeNav='clients'; require __DIR__.'/_header.php'; ?>
   ... page HTML ...
<?php $pageScript='<script>...</script>'; require __DIR__.'/_footer.php'; ?>
```
  `_header.php` already calls `requireAdmin()`. activeNav keys: dashboard,clients,plans,templates,standees,whatsapp,broadcast,notices,tickets,invoices,ai_report,settings,updates,activity,backup.
- Client page: same pattern with `client/_header.php` / `_footer.php`. `_header.php` calls `requireClient()`. `$tenant = currentTenant()` is available. activeNav keys: dashboard,menu,ai,design,qr,orders,tables,staff,feedback,reports,whatsapp,settings.
- Both headers render `<meta name="csrf-token">` and `<meta name="base-url">` and load `assets/js/app.js` (window.AK helpers: `AK.post(url,data)`, `AK.get`, `AK.toast(type,msg)`, `AK.confirm`, `AK.handle(res,cb)`).

## Multi-tenant isolation (CRITICAL SECURITY)
EVERY client-scoped query MUST filter by `tenant_id = currentTenantId()`. Never trust a tenant_id from the request. In API endpoints for clients, resolve tenant from session only. A client must NEVER read/write another tenant's data.

## API endpoint convention (files in /api/*.php)
```php
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD']==='POST') csrfCheck();
requireClient(); // or requireAdmin() etc. — pick correct guard
$tid = currentTenantId();
$action = $_GET['action'] ?? '';
// ... switch, use jsonSuccess/jsonError
```
Public endpoints (customer ordering, no login) must NOT call requireClient; resolve tenant by slug/qr_token and still validate.

## Style
- Comment in English. Match existing code density. Use Bootstrap 5 classes. Use DataTables for list pages, SweetAlert2 for confirms, Toastr for notifications, Chart.js for graphs.
- CSS variables `--primary/--secondary/--accent` are injected by layouts. Use `btn-primary` etc.
- Currency symbol via `money()` or `getSetting('currency','₹')`.

## DB tables (all prefixed via tbl()): super_admins, plans, templates, standee_templates, tenants, staff, categories, items, item_variants, item_addons, tables, orders, order_items, feedback, invoices, tickets, ticket_replies, notices, settings, ai_logs, activity_logs, login_attempts, migrations, update_logs, backups, whatsapp_settings, whatsapp_templates, whatsapp_logs, whatsapp_inbox, otp_verifications.
See install/database.sql for exact columns.

## API endpoint spec (these exact names/actions are relied on by already-built front-ends)
- `api/auth.php?action=send_otp` (POST JSON or form {mobile}) — public; generates+sends OTP. Returns jsonSuccess.
- `api/order.php?action=place` (POST JSON, PUBLIC no login) — body: `{slug, table_token, customer_name, customer_mobile, order_type, items:[{id,name,variant,addons:[{name,price}],qty,price,notes}]}`. Resolve tenant by slug (or table_token→table→tenant). Compute subtotal/tax(cgst+sgst)/service_charge/total from tenant settings. Insert order + order_items. Fire `new_order` WhatsApp to owner. Return `{order_id, order_no}`.
- `api/order.php?action=feedback` (POST JSON, PUBLIC) — `{order_id, slug, rating, comment?}`. Insert feedback. (Smart routing handled client-side.)
- `api/order.php?action=status` (POST, client/staff) — `{order_id, status}` update; tenant-isolated. Fires `order_ready`/`order_completed` WhatsApp on those transitions.
- `api/order.php?action=list` (GET, client/staff) — live orders for currentTenantId(), optional `?status=`.
- The public menu template posts to `place`/`feedback` WITHOUT csrf (public). All client/admin actions REQUIRE csrfCheck().

## Reference template contract (already built: templates/modern/)
A menu template is `templates/{folder}/index.php`, rendered by `r/index.php` which provides:
`$menu` (=['tenant'=>..,'categories'=>..]), `$tenant`, `$categories`, `$orderingMode`, `$tableToken`, `$tableNo`, `$currency`, `$lang`, `$showBranding`. Category rows have `items`; each item has `variants` and `addons` arrays. Templates do NO DB queries. Copy templates/modern as the basis for new templates. Ordering JS (openItem/cart/placeOrder) can be copied verbatim.

## Testing your work
Run `php -l yourfile.php` on every file. Prefer to curl the dev server to smoke-test pages return 200.
