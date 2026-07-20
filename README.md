# AK Menu System

A complete **multi-tenant SaaS Digital Restaurant Menu & Ordering System** — QR menus, AI menu import (Google Gemini), direct & waiter ordering, Kitchen Display (KOT), WhatsApp automation, QR/standee generator, billing, and a one-click GitHub auto-updater. Built for **shared cPanel hosting** with **PHP 8 + MySQL/MariaDB** and a vanilla **Bootstrap 5 / jQuery** front-end. Full **English + ગુજરાતી** support.

> Powered by AK Computer, Dwarka

---

## 1. Requirements

- PHP **8.0+** with extensions: `pdo_mysql`, `curl`, `gd`, `mbstring`, `fileinfo`, `zip`, `openssl`
- MySQL **8.x** or MariaDB **10.4+**
- `allow_url_fopen` enabled (for QR fallback + update checks)
- Apache with `mod_rewrite` (clean URLs) — the bundled `.htaccess` handles the rest
- No Node.js, no Composer, no shell access required

---

## 2. Installation (cPanel — web installer)

1. **Upload** all files to your domain's document root (e.g. `public_html/`) — via cPanel File Manager or FTP. On `menu.akdwk.in` that means the account's web root.
2. Make sure these folders are **writable (0755)**: `/config`, `/uploads`, `/assets/cache`, `/backups`, `/temp`.
3. Create a **MySQL database** + user in cPanel and grant the user all privileges on it.
4. Visit **`https://menu.akdwk.in/install/`** in your browser. The wizard walks you through:
   1. **Requirements check** — every row must be green.
   2. **License** — enter `AKDWK-OFFLINE` for offline activation (or your purchase code).
   3. **Database** — host, name, user, password, table prefix (default `ak_`). It tests the connection and imports all tables + seed data automatically.
   4. **Super Admin** — your name, email, password.
   5. **Site config** — site name, URL (auto-detected), timezone (`Asia/Kolkata`), currency (`₹`), default language.
   6. **Finish** — writes `/config/db.php`, creates `/config/installed.lock`, and offers a **"Delete Install Folder"** button. Click it (or delete `/install/` manually) for security.

That's it — no manual SQL import, no config editing.

> If `installed.lock` exists, `/install/` refuses to run again. If it's missing, the app forces you back to the installer.

---

## 3. Default Credentials

| Panel | URL | Login |
|---|---|---|
| Super Admin | `/admin/` | the email + password you set in the installer |
| Restaurant Owner | `/client/` | mobile/email + password (created per client) |
| Waiter | `/waiter/` | restaurant code (slug) + 4-digit PIN |
| Kitchen (KOT) | `/kitchen/` | restaurant code (slug) + 4-digit PIN |
| Public menu | `/r/{slug}` | no login |

The seed data ships one super admin, 3 plans (Starter/Professional/Enterprise), 5 menu templates, 3 standee templates, and WhatsApp templates. **Change the admin password after first login.**

---

## 4. Post-install configuration (Super Admin → Settings)

- **Branding / Appearance:** site name, logo, colors (injected as CSS variables), theme mode, fonts.
- **AI (Gemini):** paste your **Gemini API key** and model (default `gemini-2.0-flash`) to enable AI menu import.
- **WhatsApp:** the gateway (`bulk.akdwk.in`) demo credentials are pre-seeded in the DB — update them under **WhatsApp → Settings** (never hardcoded in source). Use **Send Test Message** to verify.
- **Payments:** Razorpay key id + secret (for online ordering payments).
- **SMTP:** for email (PHPMailer-ready).
- **Updates:** GitHub owner/repo/branch/token for one-click updates.

---

## 5. Cron Jobs (cPanel → Cron Jobs)

Add these (replace `SECRET` with the value of the `cron_secret` setting, shown in Super Admin):

```
* * * * *  php /home/USER/public_html/cron/whatsapp_queue.php
0 9 * * *  php /home/USER/public_html/cron/expiry_reminder.php?key=SECRET
0 * * * *  php /home/USER/public_html/cron/check_update.php?key=SECRET
5 23 * * * php /home/USER/public_html/cron/daily_summary.php?key=SECRET
```

- **whatsapp_queue.php** — every minute; sends queued WhatsApp messages respecting the configured delay/limit.
- **expiry_reminder.php** — daily; plan-expiry reminders (7/3/1 days) and auto-suspend.
- **check_update.php** — hourly/daily; *notifies* of new GitHub releases (never auto-installs).
- **daily_summary.php** — nightly; sends each restaurant its daily sales summary.

You can also hit them over HTTPS with `?key=SECRET` if CLI cron isn't available.

---

## 6. Folder Structure

```
/                     landing page + entry router
config/               db.php (generated), config.php, functions.php
admin/                super admin panel
client/               restaurant owner panel
waiter/               waiter ordering panel
kitchen/              KOT display
r/                    public menu (/r/{slug}) + PWA manifest & service worker
api/                  AJAX JSON endpoints
templates/            menu design templates (modern, classic, cafe, finedine, fastfood)
standee/              standee & invoice PDF generators
assets/               css, js, img, cache
uploads/              logos, items, menus, qr, standee (script execution blocked)
lang/                 en.php, gu.php
libs/                 fpdf, phpqrcode (bundled, no Composer)
install/              web installer + database.sql
updates/              versioned migration files ({version}.sql / .php)
backups/              auto DB/file backups (protected)
temp/                 update workspace
cron/                 queue worker + scheduled tasks
version.json          current app version + changelog
update_exclude.php    files never overwritten during updates
```

---

## 7. AI Menu Import

Client panel → **AI Menu Import**: upload menu photos (JPG/PNG/PDF). The server base64-encodes them and calls the **Gemini Vision API** via cURL, prompting for strict JSON. The parsed categories/items are shown in an **editable review grid** (fix OCR errors) before saving. Each successful extraction deducts one **AI credit** from the plan; the limit is enforced server-side. Failures fall back to manual entry + retry.

---

## 8. Ordering Modes (client chooses in Settings)

- **Direct** — customer scans a table QR, browses, adds to cart, places an order (Razorpay or pay-at-counter). Orders appear live on the owner dashboard and KOT with sound alerts.
- **Waiter** — waiter logs in with a PIN, picks a table, punches items → goes to KOT.
- **View only** — digital menu with no ordering.

Prices are **always re-computed server-side** from the database on order placement — the client-submitted price is never trusted.

---

## 9. Security

- PDO prepared statements everywhere; `password_hash()`/`password_verify()`.
- CSRF token on every POST; `htmlspecialchars()` on all output.
- Upload validation (MIME + extension + size + random rename) and script execution blocked in `/uploads` via `.htaccess`.
- Session regeneration on login, idle timeout, HttpOnly cookies.
- Login rate limiting (5 attempts → 15-min lock).
- **Tenant isolation:** every restaurant-scoped query filters by the session `tenant_id`; one client can never read another's data.
- Plan limits enforced in code, not just hidden in the UI.
- `/config`, `/backups`, `/temp`, `/libs` protected by `.htaccess`; `db.php` guarded against direct access.

---

## 10. GitHub Auto-Update

Super Admin → **Updates**: configure the GitHub repo + (optional) token. "Check for Updates" compares `version.json`'s tag with the latest GitHub release. **Update Now** runs a guarded pipeline: pre-check → maintenance mode → backup (keeps last 5) → download & verify ZIP → extract → copy (never overwriting `db.php`, `installed.lock`, `/uploads`, `/backups`, custom templates) → run `/updates/{version}.sql|.php` migrations (recorded so they never re-run) → clear cache → bump version → maintenance off. Any failure aborts and restores from backup. **Rollback** and **manual ZIP upload** are also supported.

---

## 11. Adding Templates & Standees (no code changes to the core)

- **Menu template:** drop a folder in `/templates/{name}/index.php` (copy `templates/modern/` as a base — it receives the `$menu`/`$tenant` data and does no DB queries) and add a row in the `templates` table via Super Admin → Menu Templates.
- **Standee:** drop a `/standee/{name}.php` render function and add a `standee_templates` row.

---

## 12. Deliverables checklist

- ✅ Web installer with auto DB import + self-delete
- ✅ `install/database.sql` schema + seed data
- ✅ 5 menu templates + 3 standee templates
- ✅ AI menu extraction (Gemini)
- ✅ QR + standee PDF generator (bundled FPDF + QR)
- ✅ Direct/waiter ordering, KOT, bill/KOT printing
- ✅ WhatsApp automation (signup, orders, reminders) + queue worker + inbound webhook
- ✅ GitHub auto-update with backup/rollback/migrations
- ✅ Postman collection (`postman_collection.json`)
- ✅ `version.json` + sample `updates/1.0.1.sql`

See `DEV_CONTRACT.md` for the internal architecture / helper reference.
