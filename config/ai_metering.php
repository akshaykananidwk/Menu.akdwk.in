<?php
/**
 * AI Token Usage & Cost Metering — self-contained module.
 * Loaded by config.php after functions.php. In this multi-tenant app the
 * "user" is the tenant (restaurant); admin/system calls use user_id 0.
 *
 * Design rules (from spec):
 *  - Never estimate real token counts — read exact values from the provider.
 *  - Log exactly one row per call (success/error/incomplete/blocked).
 *  - Logging is fire-and-forget: it must never break the user-facing feature.
 *  - Prices come from ai_pricing_config, never constants.
 */
defined('ROOT_PATH') or exit;

// ---- Settings (stored as rows in the existing `settings` table) -------------
function aiSetting(string $key, $default) { return getSetting('ai_' . $key, $default); }
function aiUsdToLocal(): float { return (float)aiSetting('usd_to_local_rate', 87.0); }
function aiLocalSymbol(): string { return (string)aiSetting('local_symbol', getSetting('currency', '₹')); }
function aiMarkupPct(): float { return (float)aiSetting('markup_percentage', 0); }
function aiMaxInputTokens(): int { return max(0, (int)aiSetting('max_input_tokens', 25000)); }
function aiAlertThreshold(): int { return max(0, (int)aiSetting('alert_threshold_tokens', 10000)); }
function aiRetentionDays(): int { return max(1, (int)aiSetting('log_retention_days', 180)); }

// ---- Per-request context (set by the caller before an LLM call) -------------
function aiSetContext(array $ctx): void { $GLOBALS['__ai_ctx'] = array_merge($GLOBALS['__ai_ctx'] ?? [], $ctx); }
function aiClearContext(): void { $GLOBALS['__ai_ctx'] = []; }
function aiGetContext(): array {
    $c = $GLOBALS['__ai_ctx'] ?? [];
    if (!isset($c['user_id'])) { $c['user_id'] = (int)(currentTenantId() ?? 0); }
    if (!isset($c['key_owner'])) { $c['key_owner'] = 'platform'; }
    if (!isset($c['source'])) { $c['source'] = 'ai'; }
    if (empty($c['username']) && $c['user_id'] > 0) {
        try { $c['username'] = (string)db_val('SELECT restaurant_name FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => (int)$c['user_id']]); }
        catch (Throwable $e) { $c['username'] = null; }
    }
    return $c;
}

/** Short unique id per call. */
function aiRequestId(): string { return bin2hex(random_bytes(8)); }

// ---- Pricing + cost ----------------------------------------------------------

/** Active price row for provider+model, or null (result cached per request). */
function aiPricing(string $provider, string $model): ?array {
    static $cache = [];
    $k = $provider . '|' . $model;
    if (array_key_exists($k, $cache)) return $cache[$k];
    try {
        $row = db_one('SELECT * FROM ' . tbl('ai_pricing_config') . ' WHERE provider = :p AND model_name = :m AND is_active = 1',
                      [':p' => $provider, ':m' => $model]);
    } catch (Throwable $e) { $row = null; }
    return $cache[$k] = ($row ?: null);
}

/**
 * Compute per-component USD costs.
 * @return array{input:float,cached:float,output:float,total:float,priced:bool}
 */
function aiComputeCost(string $provider, string $model, int $in, int $out, int $think, int $cached): array {
    $p = aiPricing($provider, $model);
    if (!$p) return ['input' => 0, 'cached' => 0, 'output' => 0, 'total' => 0, 'priced' => false];
    $billableInput = max(0, $in - $cached);
    $inCost     = $billableInput / 1000000 * (float)$p['input_per_million'];
    $cachedCost = $cached        / 1000000 * (float)$p['cached_per_million'];
    $outCost    = ($out + $think)/ 1000000 * (float)$p['output_per_million']; // thinking bills at output rate
    $total = $inCost + $cachedCost + $outCost;
    return [
        'input'  => round($inCost, 10), 'cached' => round($cachedCost, 10),
        'output' => round($outCost, 10), 'total' => round($total, 10), 'priced' => true,
    ];
}

/** Normalise a Gemini usageMetadata block into our canonical usage array, or null. */
function aiUsageFromGemini(?array $meta): ?array {
    if (!$meta) return null;
    $in     = (int)($meta['promptTokenCount'] ?? 0);
    $out    = (int)($meta['candidatesTokenCount'] ?? 0);
    $think  = (int)($meta['thoughtsTokenCount'] ?? 0);
    $cached = (int)($meta['cachedContentTokenCount'] ?? 0);
    $total  = (int)($meta['totalTokenCount'] ?? ($in + $out + $think));
    if ($in === 0 && $out === 0 && $total === 0) return null;
    return ['input' => $in, 'output' => $out, 'thinking' => $think, 'cached' => $cached, 'total' => $total];
}

/**
 * Insert exactly one ai_usage_logs row. Fire-and-forget: never throws.
 * @param ?array $usage canonical usage array, or null when unknown
 */
function aiLogUsage(string $provider, string $model, ?array $usage, string $status, int $ms = 0, string $error = ''): void {
    try {
        $ctx = aiGetContext();
        $in = (int)($usage['input'] ?? 0); $out = (int)($usage['output'] ?? 0);
        $think = (int)($usage['thinking'] ?? 0); $cached = (int)($usage['cached'] ?? 0);
        $total = (int)($usage['total'] ?? ($in + $out + $think));
        // A "successful" call with no usage object is a measurement gap, not zero usage.
        if ($status === 'success' && !$usage) { $status = 'incomplete'; }
        $c = aiComputeCost($provider, $model, $in, $out, $think, $cached);
        $rate  = aiUsdToLocal();
        $local = round($c['total'] * $rate, 4);
        db_query('INSERT INTO ' . tbl('ai_usage_logs') . '
            (user_id, username, request_id, created_at, provider, model_name,
             input_tokens, output_tokens, thinking_tokens, cached_tokens, total_tokens,
             input_cost_usd, output_cost_usd, cached_cost_usd, total_cost_usd, total_cost_local,
             key_owner, source, reference_id, status, error_message, response_time_ms)
            VALUES (:uid,:uname,:rid,:cat,:prov,:model,:in,:out,:think,:cache,:tot,
                    :ic,:oc,:cc,:tc,:loc,:owner,:src,:ref,:st,:err,:ms)', [
            ':uid' => (int)$ctx['user_id'], ':uname' => $ctx['username'] ?? null,
            ':rid' => $ctx['request_id'] ?? aiRequestId(), ':cat' => date('Y-m-d H:i:s'),
            ':prov' => $provider, ':model' => $model,
            ':in' => $in, ':out' => $out, ':think' => $think, ':cache' => $cached, ':tot' => $total,
            ':ic' => $c['input'], ':oc' => $c['output'], ':cc' => $c['cached'], ':tc' => $c['total'], ':loc' => $local,
            ':owner' => $ctx['key_owner'] ?? 'platform', ':src' => $ctx['source'] ?? 'ai',
            ':ref' => $ctx['reference_id'] ?? null, ':st' => $status,
            ':err' => $error !== '' ? mb_substr($error, 0, 2000) : null, ':ms' => max(0, $ms),
        ]);
    } catch (Throwable $e) {
        // Do NOT swallow silently — surface to the server log for debugging.
        error_log('aiLogUsage failed: ' . $e->getMessage());
    }
}

// ---- Runaway protection ------------------------------------------------------

/**
 * Rough pre-flight input-token estimate. We do NOT bill from this — it is only a
 * safety guard so an unbounded prompt is never sent. Images are estimated
 * conservatively; text at ~4 chars/token.
 * @return int estimated input tokens
 */
function aiEstimateInputTokens(string $text = '', int $imageCount = 0): int {
    $textTokens  = (int)ceil(mb_strlen($text) / 4);
    $imageTokens = $imageCount * 1600; // conservative per-image estimate for Gemini vision
    return $textTokens + $imageTokens;
}

/** True if the estimated input exceeds the configured cap (caller should block). */
function aiExceedsInputCap(int $estimatedInput): bool {
    $cap = aiMaxInputTokens();
    return $cap > 0 && $estimatedInput > $cap;
}

/** This month's usage summary for a user (for the widget + limits). */
function aiUserMonthUsage(int $userId): array {
    $from = date('Y-m-01 00:00:00');
    try {
        $r = db_one('SELECT COUNT(*) calls, COALESCE(SUM(total_tokens),0) tokens,
                            COALESCE(SUM(total_cost_local),0) local, COALESCE(SUM(total_cost_usd),0) usd
                     FROM ' . tbl('ai_usage_logs') . '
                     WHERE user_id = :u AND created_at >= :f', [':u' => $userId, ':f' => $from]);
    } catch (Throwable $e) { $r = null; }
    return [
        'calls'  => (int)($r['calls'] ?? 0),
        'tokens' => (int)($r['tokens'] ?? 0),
        'local'  => (float)($r['local'] ?? 0),
        'usd'    => (float)($r['usd'] ?? 0),
    ];
}
