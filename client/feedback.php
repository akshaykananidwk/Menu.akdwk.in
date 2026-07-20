<?php
/**
 * Client — Customer feedback list + rating summary (tenant-isolated).
 */
$pageTitle = 'Feedback';
$activeNav = 'feedback';
require __DIR__ . '/_header.php';

$tid = (int)currentTenantId();
$rows = db_all('SELECT f.*, o.order_no FROM ' . tbl('feedback') . ' f
                LEFT JOIN ' . tbl('orders') . ' o ON o.id = f.order_id
                WHERE f.tenant_id = :t ORDER BY f.id DESC', [':t' => $tid]);

$total = count($rows);
$sum   = 0;
$byStar = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($rows as $r) {
    $rt = max(1, min(5, (int)$r['rating']));
    $byStar[$rt]++;
    $sum += $rt;
}
$avg = $total ? round($sum / $total, 1) : 0;

/** Render N filled + (5-N) empty stars. */
function stars(int $n): string {
    $n = max(0, min(5, $n));
    return str_repeat('<i class="bi bi-star-fill text-warning"></i>', $n)
         . str_repeat('<i class="bi bi-star text-muted"></i>', 5 - $n);
}
?>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card h-100"><div class="card-body text-center">
      <div class="display-5 fw-bold"><?= e($avg) ?> <small class="text-warning fs-4"><i class="bi bi-star-fill"></i></small></div>
      <div class="text-muted">Average rating</div>
      <div class="small text-muted"><?= (int)$total ?> review<?= $total === 1 ? '' : 's' ?></div>
    </div></div>
  </div>
  <div class="col-md-8">
    <div class="card h-100"><div class="card-body">
      <?php for ($s = 5; $s >= 1; $s--):
        $pct = $total ? round($byStar[$s] / $total * 100) : 0; ?>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="small" style="width:60px"><?= $s ?> star</span>
          <div class="progress flex-grow-1" style="height:10px">
            <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="small text-muted" style="width:40px"><?= (int)$byStar[$s] ?></span>
        </div>
      <?php endfor; ?>
    </div></div>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle" id="fbTable" style="width:100%">
    <thead><tr><th>Rating</th><th>Comment</th><th>Customer</th><th>Order</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td data-order="<?= (int)$r['rating'] ?>"><?= stars((int)$r['rating']) ?></td>
          <td><?= e($r['comment'] ?: '-') ?></td>
          <td><?= e($r['customer_name'] ?: 'Anonymous') ?><?= $r['mobile'] ? '<br><small class="text-muted">' . e($r['mobile']) . '</small>' : '' ?></td>
          <td><?= e($r['order_no'] ?: '-') ?></td>
          <td><?= e($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$pageScript = <<<HTML
<script>
new DataTable('#fbTable', {order:[[4,'desc']], pageLength:25});
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
