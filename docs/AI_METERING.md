# AI Token Usage & Cost Metering

Self-contained module that logs **exact** token counts and cost for every LLM
call, per user (restaurant), so you can price your service and prevent runaway
costs. Nothing here changes existing menu/ordering behaviour.

## What gets logged

Every Gemini call (menu import, AI test) routes through `geminiGenerate()` in
`config/functions.php`, which reads the provider's own `usageMetadata` and calls
`aiLogUsage()` (in `config/ai_metering.php`). One row per call in
`ai_usage_logs`:

- `success` – real token counts logged
- `incomplete` – the API replied but sent no usage object (never logged as 0)
- `error` – the call failed (0 tokens)
- `blocked` – a pre-flight guard stopped an oversized prompt

Costs are computed from `ai_pricing_config` (never hard-coded):

```
billable_input = input_tokens - cached_tokens
input_cost  = billable_input/1e6 * input_per_million
cached_cost = cached_tokens /1e6 * cached_per_million
output_cost = (output_tokens + thinking_tokens)/1e6 * output_per_million   # reasoning bills at OUTPUT rate
total_usd   = input_cost + cached_cost + output_cost
total_local = total_usd * ai_usd_to_local_rate
```

## Admin panel

**Admin → AI Usage & Cost** (`/admin/ai_usage.php`) with a date filter
(Today / 7 Days / This Month / Last Month):

- **Overview** – totals, "on MY key (platform)" vs "on users' own keys", daily
  cost chart, top-10 users, and warning banners (no platform key / unpriced
  model / calls over the alert threshold).
- **User-wise** – sortable, searchable table + CSV export. "Est. Monthly" is
  extrapolated from days-active.
- **User detail** – per-user cards, 30-day chart, model & feature breakdown,
  recent 100 calls.
- **Rates & Settings** – edit model prices, USD→local rate, currency symbol,
  markup %, max input tokens, alert threshold, retention days, timezone.
- **Billing** – monthly per-user report (platform key only) with markup;
  "Print / PDF" via the browser. Shows the rate + markup used so it stays
  reproducible.

Each restaurant sees only its own summary on its dashboard
("AI Usage This Month"). Clients can never see other users' data, prices, or
the platform key.

## How to …

- **Add a model / change a price** → Admin → AI Usage & Cost → *Rates &
  Settings*. Price changes affect **new** calls only; historical rows keep the
  cost that was logged.
- **Change the exchange rate / currency symbol / markup** → same tab. Changing
  the rate re-displays all local figures (they're computed at display time from
  the stored USD).
- **Set the platform key** → Settings → AI → *Gemini API Key*. If it's empty a
  red banner warns that all usage is billable to you.
- **Per-request limits** → *Max input tokens / request* blocks oversized prompts
  (logged as `blocked`). Per-plan call limits already exist via plan AI credits.

## Cron (required for scale)

`ai_usage_logs` grows one row per call. A nightly job rolls the data into
`ai_usage_daily` and purges raw rows older than the retention window:

```
# once a day, e.g. 2 AM
0 2 * * *  php /path/to/cron/ai_rollup.php
# or over HTTP:
0 2 * * *  curl -s "https://menu.akdwk.in/cron/ai_rollup.php?key=YOUR_CRON_SECRET"
```

The rollup is idempotent (rebuilds a trailing 35-day window each run).

## Tables (migration `updates/1.4.0.sql`, idempotent)

- `ai_usage_logs` – one row per call (indexed for `user_id,created_at`,
  `key_owner`, `status`).
- `ai_pricing_config` – editable per-model USD prices (seeded for Gemini).
- `ai_usage_daily` – per-user/day rollup for fast dashboards.
- AI settings are stored as `ai_*` keys in the existing `settings` table.

`created_at` is written in the app timezone (`date()`), so "today"/"this month"
agree between the log and the dashboard.
