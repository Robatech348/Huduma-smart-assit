<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_staff();

// ---- Actions ----
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    $a = q_one('SELECT * FROM appointments WHERE id = ?', [$id]);
    if (!$a || !can_access_centre((int)$a['centre_id'])) {
        flash('error', 'Appointment not found.');
        redirect('staff/appointments.php');
    }
    $action = input('action');
    $back = ['date' => input('date'), 'status' => input('status_filter'), 'centre' => input('centre_filter'), 'search' => input('search')];

    switch ($action) {
        case 'check_in':
            if ($a['status'] === 'booked') {
                q("UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = ?", [$id]);
                flash('success', "{$a['reference']} checked in.");
            }
            break;
        case 'served':
            if (in_array($a['status'], ['booked', 'checked_in'], true)) {
                q("UPDATE appointments SET status = 'served', served_at = NOW(), checked_in_at = COALESCE(checked_in_at, NOW()) WHERE id = ?", [$id]);
                notify_feedback_request($id);
                flash('success', "{$a['reference']} marked as served." . ($a['email'] ? ' A feedback request was emailed.' : ''));
            }
            break;
        case 'no_show':
            if ($a['status'] === 'booked') {
                q("UPDATE appointments SET status = 'no_show' WHERE id = ?", [$id]);
                flash('info', "{$a['reference']} marked as no-show.");
            }
            break;
        case 'cancel':
            if (cancel_appointment($id)) {
                flash('info', "{$a['reference']} cancelled and slot released.");
            }
            break;
        case 'note':
            q('UPDATE appointments SET staff_notes = ? WHERE id = ?', [mb_substr(input('staff_notes'), 0, 500) ?: null, $id]);
            flash('success', 'Note saved.');
            break;
        default:
            flash('error', 'Unknown action.');
    }
    audit('appointment_' . $action, $a['reference']);
    redirect('staff/appointments.php', array_filter($back));
}

// ---- Filters ----
$date = input('date', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$status = input('status');
$centreFilter = input_int('centre');
$search = input('search');

[$scope, $params] = scope_sql('a.centre_id');
$where = ["{$scope}"];
if ($search !== '') {
    $where[] = '(a.reference LIKE ? OR a.full_name LIKE ? OR a.email LIKE ? OR a.phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
} else {
    $where[] = 's.slot_date = ?';
    $params[] = $date;
}
if ($status !== '' && isset(APPOINTMENT_STATUSES[$status])) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if ($centreFilter) {
    $where[] = 'a.centre_id = ?';
    $params[] = $centreFilter;
}

$rows = q_all(
    'SELECT a.*, s.slot_date, s.start_time, sv.name AS service_name, c.name AS centre_name,
            (SELECT r.id FROM service_requests r WHERE r.appointment_id = a.id LIMIT 1) AS request_id
       FROM appointments a
       JOIN slots s ON s.id = a.slot_id
       JOIN services sv ON sv.id = a.service_id
       JOIN centres c ON c.id = a.centre_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY s.slot_date, s.start_time, a.created_at
      LIMIT 300',
    $params
);

$hidden = function () use ($date, $status, $centreFilter, $search): string {
    return csrf_field()
        . '<input type="hidden" name="date" value="' . e($date) . '">'
        . '<input type="hidden" name="status_filter" value="' . e($status) . '">'
        . '<input type="hidden" name="centre_filter" value="' . e($centreFilter ?: '') . '">'
        . '<input type="hidden" name="search" value="' . e($search) . '">';
};

render_header('Appointments', 'staff');
?>
<div class="spread">
  <h1>Appointments</h1>
  <a class="btn btn-primary" href="<?= e(url('staff/assisted_booking.php')) ?>">+ Assisted booking</a>
</div>

<form class="card form-inline" method="get">
  <label>Date<input type="date" name="date" value="<?= e($date) ?>" data-autosubmit></label>
  <label>Status
    <select name="status" data-autosubmit>
      <option value="">All statuses</option>
      <?php foreach (APPOINTMENT_STATUSES as $k => [$label]): ?>
        <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if (staff_centre_scope() === null): ?>
    <label>Centre
      <select name="centre" data-autosubmit>
        <option value="">All centres</option>
        <?php foreach (centres_in_scope() as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $centreFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <label>Search any date<input type="search" name="search" value="<?= e($search) ?>" placeholder="Reference, name, email, phone"></label>
  <button class="btn" type="submit">Filter</button>
  <a class="btn" href="<?= e(url('staff/appointments.php')) ?>">Today</a>
</form>

<div class="card">
  <p class="muted small"><?= count($rows) ?> appointment(s) <?= $search ? 'matching “' . e($search) . '”' : 'on ' . e(fmt_date($date)) ?></p>
  <?php if (!$rows): ?>
    <p class="empty">No appointments found.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Time</th><th>Reference / citizen</th><th>Service</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e(fmt_time($r['start_time'])) ?></strong><?php if ($search): ?><br><span class="small muted"><?= e(fmt_date($r['slot_date'])) ?></span><?php endif; ?></td>
        <td>
          <strong><?= e($r['reference']) ?></strong> · <?= e($r['full_name']) ?>
          <?php if ($r['needs_assistance']): ?> <span class="badge badge-yellow" title="Asked for extra assistance">Assistance</span><?php endif; ?>
          <?php if ($r['channel'] === 'assisted'): ?> <span class="badge badge-grey">Assisted</span><?php endif; ?>
          <br><span class="small muted"><?= e($r['email'] ?: 'no email') ?><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></span>
          <details class="small"><summary class="muted">Note<?= $r['staff_notes'] ? ': ' . e(mb_strimwidth($r['staff_notes'], 0, 40, '…')) : '' ?></summary>
            <form method="post" class="row" style="margin-top:6px"><?= $hidden() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="note">
              <input type="text" name="staff_notes" value="<?= e($r['staff_notes']) ?>" maxlength="500" style="min-width:200px">
              <button class="btn btn-sm" type="submit">Save</button>
            </form>
          </details>
        </td>
        <td><?= e($r['service_name']) ?><?php if (staff_centre_scope() === null): ?><br><span class="small muted"><?= e($r['centre_name']) ?></span><?php endif; ?></td>
        <td><?= status_badge($r['status'], APPOINTMENT_STATUSES) ?></td>
        <td>
          <div class="actions">
            <?php if ($r['status'] === 'booked'): ?>
              <form method="post"><?= $hidden() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-primary" name="action" value="check_in">Check in</button></form>
            <?php endif; ?>
            <?php if (in_array($r['status'], ['booked', 'checked_in'], true)): ?>
              <form method="post"><?= $hidden() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm" name="action" value="served">Served</button></form>
            <?php endif; ?>
            <?php if ($r['status'] === 'booked'): ?>
              <form method="post"><?= $hidden() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm" name="action" value="no_show">No-show</button></form>
              <form method="post" data-confirm="Cancel <?= e($r['reference']) ?>? The citizen will be emailed."><?= $hidden() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger" name="action" value="cancel">Cancel</button></form>
            <?php endif; ?>
            <?php if ($r['request_id']): ?>
              <a class="btn btn-sm" href="<?= e(url('staff/request.php', ['id' => $r['request_id']])) ?>">View request</a>
            <?php elseif (in_array($r['status'], ['checked_in', 'served'], true)): ?>
              <a class="btn btn-sm" href="<?= e(url('staff/requests.php', ['from_appointment' => $r['id']])) ?>">+ Request</a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php render_footer('staff');
