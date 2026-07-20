<?php
/**
 * Reports API (client only, read-only, tenant-isolated).
 * Actions: sales (totals + series + payment breakdown), top_items, export (CSV download).
 */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();

$tid    = (int)currentTenantId();
$action = $_GET['action'] ?? '';

/** Normalise a date input to Y-m-d, defaulting when empty/invalid. */
function repDate(string $key, string $default): string {
    $v = trim((string)($_GET[$key] ?? ''));
    $d = $v !== '' ? date_create($v) : false;
    return $d ? $d->format('Y-m-d') : $default;
}

$from = repDate('from', date('Y-m-d', strtotime('-29 days')));
$to   = repDate('to', date('Y-m-d'));
// Sales figures exclude cancelled orders.
$dateWhere = 'tenant_id = :t AND status <> "cancelled" AND DATE(created_at) BETWEEN :f AND :to';
$dateParams = [':t' => $tid, ':f' => $from, ':to' => $to];

try {
    // -------------------------------------------------------------
    // EXPORT — stream a CSV of orders in the range (must run before JSON header).
    // -------------------------------------------------------------
    if ($action === 'export') {
        $rows = db_all('SELECT o.order_no, o.created_at, t.table_no, o.order_type, o.customer_name,
                               o.customer_mobile, o.subtotal, o.tax, o.service_charge, o.total,
                               o.payment_mode, o.status
                        FROM ' . tbl('orders') . ' o
                        LEFT JOIN ' . tbl('tables') . ' t ON t.id = o.table_id
                        WHERE o.tenant_id = :t AND DATE(o.created_at) BETWEEN :f AND :to
                        ORDER BY o.id DESC', $dateParams);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sales_' . $from . '_to_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Order No', 'Date/Time', 'Table', 'Type', 'Customer', 'Mobile',
                       'Subtotal', 'Tax', 'Service', 'Total', 'Payment', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['order_no'], $r['created_at'], $r['table_no'], $r['order_type'],
                $r['customer_name'], $r['customer_mobile'], $r['subtotal'], $r['tax'],
                $r['service_charge'], $r['total'], $r['payment_mode'], $r['status'],
            ]);
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    switch ($action) {

        // Sales summary: totals, daily series, and payment-mode breakdown.
        case 'sales': {
            $summary = db_one('SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue,
                                      COALESCE(SUM(tax),0) AS tax, COALESCE(SUM(service_charge),0) AS service,
                                      COALESCE(AVG(total),0) AS avg_order
                               FROM ' . tbl('orders') . ' WHERE ' . $dateWhere, $dateParams);

            $series = db_all('SELECT DATE(created_at) AS d, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
                              FROM ' . tbl('orders') . ' WHERE ' . $dateWhere . '
                              GROUP BY DATE(created_at) ORDER BY d ASC', $dateParams);

            $payments = db_all('SELECT payment_mode, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
                                FROM ' . tbl('orders') . ' WHERE ' . $dateWhere . '
                                GROUP BY payment_mode', $dateParams);

            jsonSuccess('', [
                'from' => $from, 'to' => $to,
                'summary' => $summary, 'series' => $series, 'payments' => $payments,
            ]);
            break;
        }

        // Best-selling items in the range (joined to orders for tenant isolation).
        case 'top_items': {
            $items = db_all('SELECT oi.item_name, SUM(oi.qty) AS qty, COALESCE(SUM(oi.total),0) AS revenue
                             FROM ' . tbl('order_items') . ' oi
                             JOIN ' . tbl('orders') . ' o ON o.id = oi.order_id
                             WHERE o.tenant_id = :t AND o.status <> "cancelled"
                               AND DATE(o.created_at) BETWEEN :f AND :to
                             GROUP BY oi.item_name ORDER BY qty DESC LIMIT 15', $dateParams);
            jsonSuccess('', ['items' => $items]);
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/reports.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
