<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_staff();

$centres = centres_in_scope();
$centreId = input_int('centre') ?: (int)($centres[0]['id'] ?? 0);
if (!can_access_centre($centreId)) {
    $centreId = (int)($centres[0]['id'] ?? 0);
}

if (is_post()) {
    csrf_check();
    $action = input('action');
    if ($action === 'generate') {
        $from = input('from');
        $to = input('to');
        $weekdays = array_map('intval', (array)($_POST['weekdays'] ?? []));
        $start = input('start_time');
        $end = input('end_time');
        $interval = input_int('interval');
        $capacity = input_int('capacity');
        $err = '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
            $err = 'Choose a valid date range.';
        } elseif ($from < date('Y-m-d')) {
            $err = 'Start date cannot be in the past.';
        } elseif ((strtotime($to) - strtotime($from)) / 86400 > 92) {
            $err = 'Generate at most 3 months at a time.';
        } elseif (!$weekdays) {
            $err = 'Choose at least one weekday.';
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
            $err = 'Choose valid start and end times.';
        } elseif ($interval < 10 || $interval > 240) {
            $err = 'Slot length must be between 10 and 240 minutes.';
        } elseif ($capacity < 1 || $capacity > 500) {
            $err = 'Capacity per slot must be between 1 and 500.';
        }
        if ($err) {
            flash('error', $err);
        } else {
            $res = generate_slots($centreId, $from, $to, $weekdays, $start, $end, $interval, $capacity);
            audit('slots_generated', "centre {$centreId}: {$res['created']} created");
            flash('success', "{$res['created']} slot(s) created" . ($res['skipped'] ? ", {$res['skipped']} already existed and were left unchanged" : '') . '.');
        }
        redirect('staff/slots.php', ['centre' => $centreId, 'date' => $from ?: null]);
    }

    $slot = q_one('SELECT * FROM slots WHERE id = ?', [input_int('slot_id')]);
    if (!$slot || !can_access_centre((int)$slot['centre_id'])) {
        flash('error', 'Slot not found.');
        redirect('staff/slots.php', ['centre' => $centreId]);
    }
    if ($action === 'capacity') {
        $cap = input_int('capacity');
        if ($cap < (int)$slot['booked']) {
            flash('error', "Capacity cannot be lower than the {$slot['booked']} existing booking(s).");
        } elseif ($cap < 1 || $cap > 500) {
            flash('error', 'Capacity must be between 1 and 500.');
        } else {
            q('UPDATE slots SET capacity = ? WHERE id = ?', [$cap, $slot['id']]);
            flash('success', 'Capacity updated.');
        }
    } elseif ($action === 'toggle') {
        $new = $slot['status'] === 'open' ? 'closed' : 'open';
        q('UPDATE slots SET status = ? WHERE id = ?', [$new, $slot['id']]);
        flash('success', 'Slot ' . ($new === 'open' ? 'opened' : 'closed to new bookings') . '.' . ($new === 'closed' && $slot['booked'] > 0 ? " The {$slot['booked']} existing booking(s) remain valid." : ''));
    } elseif ($action === 'delete') {
        if ((int)q_val('SELECT COUNT(*) FROM appointments WHERE slot_id = ?', [$slot['id']]) > 0) {
            flash('error', 'This slot has appointment history and cannot be deleted. Close it instead.');
        } else {
            q('DELETE FROM slots WHERE id = ?', [$slot['id']]);
            flash('success', 'Slot deleted.');
        }
    }
    audit('slot_' . $action, "slot {$slot['id']}");
    redirect('staff/slots.php', ['centre' => $slot['centre_id'], 'date' => $slot['slot_date']]);
}

$date = input('date', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$slots = $centreId ? q_all('SELECT * FROM slots WHERE centre_id = ? AND slot_date = ? ORDER BY start_time', [$centreId, $date]) : [];
$overview = $centreId ? q_all(
    "SELECT slot_date, COUNT(*) AS n, SUM(capacity) AS capacity, SUM(booked) AS booked, SUM(status='closed') AS closed
       FROM slots WHERE centre_id = ? AND slot_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 27 DAY
      GROUP BY slot_date ORDER BY slot_date",
    [$centreId]
) : [];

render_header('Capacity', 'staff');
?>
<h1>Capacity & time slots</h1>
<p class="muted">Bookings follow the capacity your centre can actually serve. Set how many people each slot can take, and close slots when staff are unavailable.</p>

<?php if (count($centres) > 1): ?>
<form class="card form-inline" method="get">
  <label>Centre
    <select name="centre" data-autosubmit>
      <?php foreach ($centres as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $centreId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <input type="hidden" name="date" value="<?= e($date) ?>">
</form>
<?php endif; ?>

<?php if (!$centreId): ?>
  <div class="alert alert-warning">Your account is not assigned to a centre.</div>
<?php else: ?>
<div class="grid grid-2" style="align-items:start">
  <div>
    <div class="card">
      <h2>Next 4 weeks</h2>
      <?php if (!$overview): ?><p class="muted">No slots published. Generate some below.</p><?php endif; ?>
      <div class="bars">
        <?php foreach ($overview as $d): $pct = $d['capacity'] ? round($d['booked'] / $d['capacity'] * 100) : 0; ?>
          <a class="bar-row" style="text-decoration:none;color:inherit" href="<?= e(url('staff/slots.php', ['centre' => $centreId, 'date' => $d['slot_date']])) ?>">
            <span <?= $d['slot_date'] === $date ? 'style="font-weight:700"' : '' ?>><?= e(date('D j M', strtotime($d['slot_date']))) ?></span>
            <span class="bar"><i class="<?= $pct >= 90 ? 'high' : ($pct >= 70 ? 'mid' : '') ?>" style="width:<?= min(100, $pct) ?>%"></i></span>
            <span class="small"><?= (int)$d['booked'] ?>/<?= (int)$d['capacity'] ?><?= $d['closed'] ? ' · ' . (int)$d['closed'] . ' closed' : '' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <form method="post" class="card form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="generate"><input type="hidden" name="centre" value="<?= $centreId ?>">
      <h2>Publish new slots</h2>
      <div class="grid grid-2">
        <label>From<input type="date" name="from" value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" min="<?= e(date('Y-m-d')) ?>" required></label>
        <label>To<input type="date" name="to" value="<?= e(date('Y-m-d', strtotime('+28 days'))) ?>" required></label>
        <label>Day starts<input type="time" name="start_time" value="08:30" required></label>
        <label>Day ends<input type="time" name="end_time" value="16:30" required></label>
        <label>Slot length (minutes)<input type="number" name="interval" value="60" min="10" max="240" required></label>
        <label>People per slot<input type="number" name="capacity" value="6" min="1" max="500" required></label>
      </div>
      <fieldset><legend>Days</legend>
        <div class="row">
          <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $d): ?>
            <label class="check"><input type="checkbox" name="weekdays[]" value="<?= $n ?>" <?= $n <= 5 ? 'checked' : '' ?>> <?= $d ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <p class="muted small">Existing slots at the same time are left unchanged. Keep some capacity free for walk-ins.</p>
      <button class="btn btn-primary" type="submit">Publish slots</button>
    </form>
  </div>

  <div class="card">
    <form class="spread" method="get">
      <h2 style="margin:0"><?= e(fmt_date($date)) ?></h2>
      <input type="hidden" name="centre" value="<?= $centreId ?>">
      <input type="date" name="date" value="<?= e($date) ?>" data-autosubmit style="max-width:180px">
    </form>
    <?php if (!$slots): ?><p class="empty">No slots on this day.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Time</th><th>Booked</th><th>Capacity</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($slots as $s): ?>
        <tr>
          <td><strong><?= e(fmt_time($s['start_time'])) ?></strong>–<?= e(fmt_time($s['end_time'])) ?></td>
          <td><?= (int)$s['booked'] ?></td>
          <td>
            <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="capacity"><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
              <input type="number" name="capacity" value="<?= (int)$s['capacity'] ?>" min="<?= max(1, (int)$s['booked']) ?>" max="500" style="width:80px;min-height:34px;padding:4px 8px">
              <button class="btn btn-sm" type="submit">Set</button></form>
          </td>
          <td><?= $s['status'] === 'open' ? '<span class="badge badge-green">Open</span>' : '<span class="badge badge-grey">Closed</span>' ?></td>
          <td><div class="actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-sm" type="submit"><?= $s['status'] === 'open' ? 'Close' : 'Open' ?></button></form>
            <?php if ((int)$s['booked'] === 0): ?>
            <form method="post" data-confirm="Delete this slot?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>
<?php endif;
render_footer('staff');
