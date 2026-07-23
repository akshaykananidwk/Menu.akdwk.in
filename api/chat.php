<?php
/**
 * Public menu chatbot API (no login).
 * action=ask : answer a diner's question grounded ONLY in that restaurant's menu.
 *
 * Cost control (this is an unauthenticated, public endpoint):
 *   - Globally gated by admin setting `ai_chatbot_enabled` + a configured Gemini key.
 *   - Hard daily cap per restaurant (setting `ai_chatbot_daily_cap`, default 300),
 *     counted from the metering log so it needs no extra table.
 *   - Soft per-session cap to blunt a single abusive browser.
 *   - Menu context is trimmed to keep token cost bounded.
 * Every call is metered under the tenant (source=chatbot, key_owner=platform).
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
if ($action !== 'ask') { jsonError('Unknown action.', 404); }

try {
    // ---- Resolve tenant by slug or table token -----------------------------
    $slug  = trim((string)($_REQUEST['slug'] ?? ''));
    $token = trim((string)($_REQUEST['table'] ?? ''));
    $tenant = null;
    if ($token !== '') {
        $row = db_one('SELECT tenant_id FROM ' . tbl('tables') . ' WHERE qr_token = :t', [':t' => $token]);
        if ($row) { $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $row['tenant_id']]); }
    } elseif ($slug !== '') {
        $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
    }
    if (!$tenant) { jsonError('Restaurant not found.', 404); }
    $tid = (int)$tenant['id'];

    // ---- Global gate -------------------------------------------------------
    $enabled = getSetting('ai_chatbot_enabled', '0') === '1'
            && trim((string)getSetting('gemini_api_key', '')) !== '';
    if (!$enabled || $tenant['status'] !== 'active') {
        jsonError('The assistant is not available right now.', 503, ['disabled' => true]);
    }

    // ---- Validate the question --------------------------------------------
    $q = trim((string)($_REQUEST['q'] ?? ''));
    if ($q === '') { jsonError('Please type a question.'); }
    if (mb_strlen($q) > 300) { $q = mb_substr($q, 0, 300); }

    // ---- Rate limits -------------------------------------------------------
    // Soft per-session cap.
    $_SESSION['chat_count'] = ($_SESSION['chat_count'] ?? 0) + 1;
    if ($_SESSION['chat_count'] > 40) {
        jsonSuccess('', ['answer' => "You've asked quite a few questions! Please ask our staff for more help. 🙏"]);
    }
    // Hard daily cap per restaurant (from the metering log).
    $cap = max(10, (int)getSetting('ai_chatbot_daily_cap', 300));
    $usedToday = (int)db_val("SELECT COUNT(*) FROM " . tbl('ai_usage_logs') . "
                              WHERE user_id = :t AND source = 'chatbot'
                                AND DATE(created_at) = CURDATE()", [':t' => $tid]);
    if ($usedToday >= $cap) {
        jsonSuccess('', ['answer' => "I'm a bit busy right now — please ask our staff, or try again later. 🙏"]);
    }

    // ---- Build a compact menu context -------------------------------------
    $menu = getTenantMenu($tid, true);
    $lines = [];
    $itemCount = 0;
    foreach ($menu['categories'] as $c) {
        $catName = $c['name'] ?? '';
        foreach ($c['items'] as $it) {
            if ($itemCount >= 150) { break 2; } // bound token cost
            $price = ($it['discount_price'] > 0 ? $it['discount_price'] : $it['price']);
            $flags = [];
            if (!empty($it['is_jain'])) { $flags[] = 'Jain'; }
            elseif (!empty($it['is_veg'])) { $flags[] = 'Veg'; }
            else { $flags[] = 'Non-veg'; }
            if ((int)($it['spice_level'] ?? 0) >= 2) { $flags[] = 'Spicy'; }
            if (!empty($it['is_bestseller'])) { $flags[] = 'Bestseller'; }
            $desc = trim((string)($it['description'] ?? ''));
            $lines[] = '- ' . $it['name'] . ' (' . $catName . ') ₹' . (float)$price
                     . ' [' . implode(', ', $flags) . ']'
                     . ($desc !== '' ? ' — ' . mb_substr($desc, 0, 80) : '');
            $itemCount++;
        }
    }
    if (!$lines) { jsonSuccess('', ['answer' => "The menu isn't ready yet — please ask our staff. 🙏"]); }

    $rname = $tenant['restaurant_name'];
    $menuText = implode("\n", $lines);
    $prompt =
        "You are a friendly menu assistant for the restaurant \"$rname\". "
      . "Answer the customer's question using ONLY the menu below. "
      . "If they ask about something not on the menu, politely say it's not available and suggest a close item from the menu. "
      . "Reply in the SAME language as the question (English, Hindi or Gujarati). "
      . "Be concise (1–3 short sentences). Never invent items or prices. Do not mention that you are an AI.\n\n"
      . "MENU:\n$menuText\n\n"
      . "Customer question: \"$q\"";

    if (function_exists('aiSetContext')) {
        aiSetContext(['user_id' => $tid, 'source' => 'chatbot', 'key_owner' => 'platform']);
    }
    $parts = [['text' => $prompt]];
    $answer = ''; $ok = false;
    foreach (geminiModelCandidates() as $model) {
        $r = geminiGenerate($parts, $model, ['temperature' => 0.4]);
        if ($r['ok']) { $answer = trim($r['text']); $ok = true; break; }
        if (geminiIsModelGone($r['http'], $r['apiMsg'])) { continue; }
        if (in_array($r['http'], [400, 403], true)) { break; }
    }
    if (!$ok || $answer === '') {
        jsonSuccess('', ['answer' => "Sorry, I couldn't answer that just now. Please ask our staff. 🙏"]);
    }
    // Strip any accidental markdown fences.
    $answer = trim(preg_replace('/^```[a-z]*|```$/m', '', $answer));
    jsonSuccess('', ['answer' => mb_substr($answer, 0, 800)]);

} catch (Throwable $e) {
    error_log('api/chat.php: ' . $e->getMessage());
    jsonError('Server error.', 500);
}
