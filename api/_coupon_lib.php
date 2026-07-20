<?php
/**
 * Shared coupon evaluation logic — used by BOTH api/coupon.php (public "apply")
 * and api/order.php ("place", server-side re-validation). Single source of truth
 * so the discount can never be tampered with from the client.
 */

/**
 * Evaluate a coupon for a tenant against a given subtotal.
 * Does NOT modify used_count — callers decide when to consume it.
 *
 * @return array{
 *   valid:bool, discount:float, type:?string,
 *   coupon:?array, message:string, code:?string
 * }
 */
function couponEvaluate(int $tenantId, string $code, float $subtotal, string $currency = '₹'): array {
    $fail = fn(string $msg) => ['valid' => false, 'discount' => 0.0, 'type' => null, 'coupon' => null, 'message' => $msg, 'code' => null];

    $code = strtoupper(trim($code));
    if ($code === '') { return $fail('Please enter a coupon code.'); }

    $c = db_one('SELECT * FROM ' . tbl('coupons') . '
                 WHERE tenant_id = :t AND code = :c AND status = 1',
        [':t' => $tenantId, ':c' => $code]);
    if (!$c) { return $fail('Invalid coupon code.'); }

    // Expiry (date only; valid through the whole expiry day).
    if (!empty($c['expiry_date']) && $c['expiry_date'] < date('Y-m-d')) {
        return $fail('This coupon has expired.');
    }
    // Usage limit (0 = unlimited).
    if ((int)$c['usage_limit'] > 0 && (int)$c['used_count'] >= (int)$c['usage_limit']) {
        return $fail('This coupon has reached its usage limit.');
    }
    // Minimum order value.
    if ($subtotal < (float)$c['min_order']) {
        return $fail('Minimum order of ' . $currency . number_format((float)$c['min_order'], 0) . ' required for this coupon.');
    }

    // Compute the discount, never exceeding the subtotal.
    if ($c['type'] === 'percent') {
        $discount = $subtotal * (float)$c['value'] / 100;
        if ((float)$c['max_discount'] > 0 && $discount > (float)$c['max_discount']) {
            $discount = (float)$c['max_discount']; // cap percent discounts
        }
    } else { // flat
        $discount = (float)$c['value'];
    }
    if ($discount > $subtotal) { $discount = $subtotal; }
    $discount = round($discount, 2);

    return [
        'valid'    => true,
        'discount' => $discount,
        'type'     => $c['type'],
        'coupon'   => $c,
        'code'     => $code,
        'message'  => 'Coupon applied — you saved ' . $currency . number_format($discount, 0) . '!',
    ];
}
