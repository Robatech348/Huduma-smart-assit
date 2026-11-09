<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_staff();

$r = q_one(
    'SELECT r.*, sv.name AS service_name, c.name AS centre_name, a.reference AS appointment_ref
       FROM service_requests r JOIN services sv ON sv.id = r.service_id JOIN centres c ON c.id = r.centre_id
       LEFT JOIN appointments a ON a.id = r.appointment_id
      WHERE r.id = ?',
    [input_int('id')]
);
if (!$r || !can_access_centre((int)$r['centre_id'])) {
    flash('error', 'Request not found.');
    redirect('staff/requests.php');
}

if (is_post()) {
    csrf_check();
    $newStatus = input('status');
    $note = mb_substr(input('public_note'), 0, 2000);
    if (!isset(REQUEST_STATUSES[$newStatus])) {
        flash('error', 'Choose a valid status.');
    } elseif ($newStatus === $r['status'] && $note === '') {
        flash('error', 'Change the status or add a message.');
    } elseif ($newStatus === 'action_needed' && $note === '') {
        flash('error', 'Explain what the citizen needs to do when setting "Action needed".');
    } else {
        q('UPDATE service_requests SET status = ? WHERE id = ?', [$newStatus, $r['id']]);
        q('INSERT INTO request_updates (request_id, status, public_note, staff_id) VALUES (?,?,?,?)', [$r['id'], $newStatus, $note ?: null, $user['id']]);
        $notify = !empty($_POST['notify']);
        if ($notify) {
            notify_request_status((int)$r['id'], $note);
        }
        audit('request_status', "{$r['reference']} -> {$newStatus}");
        flash('success', 'Request updated' . ($notify && $r['email'] ? ' and citizen emailed.' : '.'));
    }
    redirect('staff/request.php', ['id' => $r['id']]);
}

$updates = q_all(
    'SELECT ru.*, u.name AS staff_name FROM request_updates ru LEFT JOIN users u ON u.id = ru.staff_id WHERE ru.request_id = ? ORDER BY ru.created_at DESC, ru.id DESC',
    [$r['id']]
);
$emails = q_all("SELECT subject, status, created_at FROM notifications WHERE related_ref = ? ORDER BY id DESC", [$r['reference']]);

render_header($r['reference'], 'staff');
?>
<p><a href="<?= e(url('staff/requests.php')) ?>">← Requests</a></p>
<div class="spread">
  <div><div class="kicker">Service request</div><h1><?= e($r['reference']) ?></h1></div>
  <?= status_badge($r['status'], REQUEST_STATUSES) ?>
</div>

<div class="grid grid-2" style="align-items:start">
  <div>
    <div class="card">
      <dl class="details">
        <dt>Citizen</dt><dd><?= e($r['full_name']) ?></dd>
        <dt>Email</dt><dd><?= e($r['email'] ?: '—') ?></dd>
        <dt>Phone</dt><dd><?= e($r['phone'] ?: '—') ?></dd>
        <dt>Service</dt><dd><?= e($r['service_name']) ?></dd>
        <dt>Centre</dt><dd><?= e($r['centre_name']) ?></dd>
        <dt>Appointment</dt><dd><?= e($r['appointment_ref'] ?: '—') ?></dd>
        <dt>Created</dt><dd><?= e(fmt_datetime($r['created_at'])) ?></dd>
      </dl>
    </div>
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <h2>Update status</h2>
      <label>New status
        <select name="status">
          <?php foreach (REQUEST_STATUSES as $k => [$label]): ?><option value="<?= e($k) ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Message to citizen <span class="hint">Shown on the tracking page and in the email. Never include ID numbers or sensitive details.</span>
        <textarea name="public_note" maxlength="2000"></textarea></label>
      <label class="check"><input type="checkbox" name="notify" value="1" <?= $r['email'] ? 'checked' : 'disabled' ?>> Email the citizen about this update<?= $r['email'] ? '' : ' (no email on file)' ?></label>
      <button class="btn btn-primary" type="submit">Save update</button>
    </form>
  </div>
  <div>
    <div class="card">
      <h2>History</h2>
      <ul class="timeline">
        <?php foreach ($updates as $u): ?>
          <li><strong><?= e(REQUEST_STATUSES[$u['status']][0] ?? $u['status']) ?></strong>
            <span class="muted small"> · <?= e(fmt_datetime($u['created_at'])) ?> · <?= e($u['staff_name'] ?? 'system') ?></span>
            <?php if ($u['public_note']): ?><p class="small" style="margin:4px 0 0"><?= nl2br(e($u['public_note'])) ?></p><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="card">
      <h2>Emails sent</h2>
      <?php if (!$emails): ?><p class="muted">None.</p><?php endif; ?>
      <ul class="checklist small">
        <?php foreach ($emails as $m): ?><li><?= e($m['subject']) ?> <span class="muted">· <?= e(fmt_datetime($m['created_at'])) ?> · <?= e($m['status']) ?></span></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php render_footer('staff');
