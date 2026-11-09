<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    if (input('action') === 'retry_all') {
        $r = Mailer::processQueue();
        flash('info', "Retried {$r['processed']} message(s); {$r['delivered']} delivered.");
    } elseif (input('action') === 'reminders') {
        $n = send_due_reminders();
        flash('info', "{$n} reminder(s) sent for tomorrow's appointments.");
    } elseif ($id = input_int('id')) {
        q("UPDATE notifications SET status = 'queued' WHERE id = ? AND status = 'failed'", [$id]);
        $ok = Mailer::deliver($id);
        flash($ok ? 'success' : 'error', $ok ? 'Message delivered.' : 'Delivery failed again. Check the error and mail settings.');
    }
    redirect('staff/outbox.php');
}

$view = input_int('view');
if ($view) {
    $n = q_one('SELECT * FROM notifications WHERE id = ?', [$view]);
    if ($n) {
        // Show the stored HTML email in a sandboxed frame.
        render_header('Email preview', 'staff');
        ?>
        <p><a href="<?= e(url('staff/outbox.php')) ?>">← Outbox</a></p>
        <div class="card">
          <dl class="details"><dt>To</dt><dd><?= e($n['recipient']) ?></dd><dt>Subject</dt><dd><?= e($n['subject']) ?></dd>
            <dt>Status</dt><dd><?= e($n['status']) ?><?= $n['error'] ? ' — ' . e($n['error']) : '' ?></dd><dt>Created</dt><dd><?= e(fmt_datetime($n['created_at'])) ?></dd></dl>
        </div>
        <iframe title="Email preview" sandbox="" srcdoc="<?= e($n['body_html']) ?>" style="width:100%;height:720px;border:1px solid var(--line);border-radius:12px;background:#fff"></iframe>
        <?php
        render_footer('staff');
        exit;
    }
}

$status = input('status');
$params = [];
$where = '1=1';
if (in_array($status, ['queued', 'sent', 'logged', 'failed'], true)) {
    $where = 'status = ?';
    $params[] = $status;
}
$page = max(1, input_int('page', 1));
$total = (int)q_val("SELECT COUNT(*) FROM notifications WHERE {$where}", $params);
$rows = q_all("SELECT id, recipient, subject, type, status, error, attempts, created_at FROM notifications WHERE {$where} ORDER BY id DESC LIMIT 30 OFFSET " . (($page - 1) * 30), $params);
$counts = array_column(q_all('SELECT status, COUNT(*) AS n FROM notifications GROUP BY status'), 'n', 'status');

render_header('Email outbox', 'staff');
?>
<div class="spread">
  <h1>Email outbox</h1>
  <div class="row">
    <form method="post"><?= csrf_field() ?><button class="btn" name="action" value="reminders">Send tomorrow's reminders</button></form>
    <form method="post"><?= csrf_field() ?><button class="btn btn-primary" name="action" value="retry_all">Retry queued/failed</button></form>
  </div>
</div>
<div class="alert alert-info">Mail driver: <strong><?= e(config('mail.driver')) ?></strong>.
  <?= config('mail.driver') === 'log' ? 'Emails are stored here and written to storage/mail but not delivered. Set the driver to "smtp" in config.php to send real email.' : '' ?></div>

<div class="chips" style="margin-bottom:14px">
  <a class="chip <?= $status === '' ? 'active' : '' ?>" href="?">All (<?= array_sum($counts) ?>)</a>
  <?php foreach (['sent', 'logged', 'queued', 'failed'] as $s): ?>
    <a class="chip <?= $status === $s ? 'active' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?> (<?= (int)($counts[$s] ?? 0) ?>)</a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (!$rows): ?><p class="empty">No emails yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="small"><?= e(fmt_datetime($r['created_at'])) ?></td>
        <td><?= e($r['recipient']) ?></td>
        <td><?= e($r['subject']) ?><br><span class="small muted"><?= e($r['type']) ?></span></td>
        <td><?= e($r['status']) ?><?php if ($r['error']): ?><br><span class="small" style="color:#A94B3C"><?= e(mb_strimwidth($r['error'], 0, 90, '…')) ?></span><?php endif; ?></td>
        <td><div class="actions">
          <a class="btn btn-sm" href="?view=<?= (int)$r['id'] ?>">View</a>
          <?php if ($r['status'] === 'failed'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm" type="submit">Retry</button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginate_links($page, (int)ceil($total / 30), array_filter(['status' => $status])) ?>
  <?php endif; ?>
</div>
<?php render_footer('staff');
