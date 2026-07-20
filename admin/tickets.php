<?php
/**
 * Admin › Support Tickets.
 * List tickets (joined with tenants). View a thread + replies, and reply as admin.
 * Replying inserts a ticket_replies row (sender='admin'), marks the ticket
 * 'answered', and optionally fires the 'ticket_reply' WhatsApp template.
 * Self-contained POST handler.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// ---- POST handler -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message  = trim($_POST['message'] ?? '');
        $ticket   = $ticketId ? db_one('SELECT * FROM ' . tbl('tickets') . ' WHERE id = :id', [':id' => $ticketId]) : null;

        if ($ticket && $message !== '') {
            db_insert('ticket_replies', ['ticket_id' => $ticketId, 'sender' => 'admin', 'message' => $message]);
            db_update('tickets', ['status' => 'answered'], ['id' => $ticketId]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Replied to ticket #$ticketId");

            // Optionally notify the restaurant owner via WhatsApp.
            if (!empty($_POST['send_wa'])) {
                $tenant = db_one('SELECT mobile, whatsapp_no FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $ticket['tenant_id']]);
                $number = $tenant['whatsapp_no'] ?: ($tenant['mobile'] ?? '');
                if ($number) {
                    sendWaTemplate('ticket_reply', $number, [], null, (int)$ticket['tenant_id']);
                }
            }
        }
        redirect(BASE_URL . '/admin/tickets.php?view=' . $ticketId);
    } elseif ($action === 'status') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $status   = in_array($_POST['status'] ?? '', ['open', 'answered', 'closed'], true) ? $_POST['status'] : 'open';
        if ($ticketId > 0) {
            db_update('tickets', ['status' => $status], ['id' => $ticketId]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Ticket #$ticketId status → $status");
        }
        redirect(BASE_URL . '/admin/tickets.php?view=' . $ticketId);
    }
    redirect(BASE_URL . '/admin/tickets.php');
}

$viewId = (int)($_GET['view'] ?? 0);

$pageTitle = 'Support Tickets';
$activeNav = 'tickets';
require __DIR__ . '/_header.php';

$badgeMap = [
    'open'     => 'bg-warning text-dark',
    'answered' => 'bg-success',
    'closed'   => 'bg-secondary',
];

if ($viewId > 0):
    // ---- Ticket detail view ---------------------------------------------------
    $ticket = db_one('SELECT t.*, tn.restaurant_name FROM ' . tbl('tickets') . ' t
                      LEFT JOIN ' . tbl('tenants') . ' tn ON tn.id = t.tenant_id
                      WHERE t.id = :id', [':id' => $viewId]);
    if (!$ticket): ?>
      <div class="alert alert-danger">Ticket not found.</div>
      <a href="<?= e(BASE_URL) ?>/admin/tickets.php" class="btn btn-light btn-sm">Back</a>
    <?php else:
      $replies = db_all('SELECT * FROM ' . tbl('ticket_replies') . ' WHERE ticket_id = :id ORDER BY id ASC', [':id' => $viewId]);
    ?>
      <a href="<?= e(BASE_URL) ?>/admin/tickets.php" class="btn btn-light btn-sm mb-3"><i class="bi bi-arrow-left"></i> Back to tickets</a>
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h5 class="mb-1"><?= e($ticket['subject']) ?></h5>
              <div class="text-muted small"><?= e($ticket['restaurant_name'] ?? 'Unknown') ?> · <?= e(date('d-m-Y H:i', strtotime($ticket['created_at']))) ?></div>
            </div>
            <span class="badge <?= $badgeMap[$ticket['status']] ?? 'bg-secondary' ?>"><?= e(ucfirst($ticket['status'])) ?></span>
          </div>
          <hr>
          <div class="mb-2"><span class="badge bg-light text-dark">Client</span></div>
          <p class="mb-0" style="white-space:pre-wrap"><?= e($ticket['message']) ?></p>
        </div>
      </div>

      <?php foreach ($replies as $rep): ?>
        <div class="card border-0 shadow-sm mb-2 <?= $rep['sender'] === 'admin' ? 'ms-md-5' : 'me-md-5' ?>">
          <div class="card-body py-2">
            <div class="d-flex justify-content-between">
              <span class="badge <?= $rep['sender'] === 'admin' ? 'bg-primary' : 'bg-light text-dark' ?>"><?= e(ucfirst($rep['sender'])) ?></span>
              <small class="text-muted"><?= e(date('d-m-Y H:i', strtotime($rep['created_at']))) ?></small>
            </div>
            <p class="mb-0 mt-2" style="white-space:pre-wrap"><?= e($rep['message']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
            <div class="mb-2"><label class="form-label">Reply</label>
              <textarea name="message" class="form-control" rows="3" required></textarea></div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="send_wa" id="send_wa" value="1">
              <label class="form-check-label" for="send_wa">Notify owner via WhatsApp</label></div>
            <button class="btn btn-primary"><i class="bi bi-send"></i> Send Reply</button>
          </form>
          <hr>
          <form method="post" class="d-flex align-items-center gap-2">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
            <label class="form-label mb-0 small">Set status:</label>
            <select name="status" class="form-select form-select-sm" style="width:auto">
              <?php foreach (['open', 'answered', 'closed'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary">Update</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

<?php else:
    // ---- Ticket list ----------------------------------------------------------
    $rows = db_all('SELECT t.*, tn.restaurant_name,
                    (SELECT COUNT(*) FROM ' . tbl('ticket_replies') . ' r WHERE r.ticket_id = t.id) AS reply_count
                    FROM ' . tbl('tickets') . ' t
                    LEFT JOIN ' . tbl('tenants') . ' tn ON tn.id = t.tenant_id
                    ORDER BY t.id DESC');
?>
  <h5 class="mb-3">Support Tickets</h5>
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <table id="ticketTable" class="table table-hover align-middle w-100">
        <thead><tr>
          <th>#</th><th>Subject</th><th>Restaurant</th><th>Replies</th><th>Status</th><th>Created</th><th class="text-end">Action</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td class="fw-semibold"><?= e($r['subject']) ?></td>
            <td><?= e($r['restaurant_name'] ?? '—') ?></td>
            <td><?= (int)$r['reply_count'] ?></td>
            <td><span class="badge <?= $badgeMap[$r['status']] ?? 'bg-secondary' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            <td class="small text-muted"><?= e(date('d-m-Y', strtotime($r['created_at']))) ?></td>
            <td class="text-end">
              <a href="<?= e(BASE_URL . '/admin/tickets.php?view=' . (int)$r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php $pageScript = '<script>$(function(){ $("#ticketTable").DataTable({order:[],pageLength:25}); });</script>'; ?>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
