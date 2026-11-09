<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_staff();

[$scopeA, $pA] = scope_sql('a.centre_id');
[$scopeS, $pS] = scope_sql('s.centre_id');
[$scopeR, $pR] = scope_sql('r.centre_id');

$today = q_one(
    "SELECT COUNT(*) AS total,
            SUM(a.status = 'booked') AS waiting,
            SUM(a.status = 'checked_in') AS checked_in,
            SUM(a.status = 'served') AS served,
            SUM(a.status = 'no_show') AS no_show,
            SUM(a.needs_assistance = 1 AND a.status IN ('booked','checked_in')) AS assistance
       FROM appointments a JOIN slots s ON s.id = a.slot_id
      WHERE s.slot_date = CURDATE() AND a.status <> 'cancelled' AND {$scopeA}",
    $pA
);
$cap = q_one("SELECT COALESCE(SUM(capacity),0) AS capacity, COALESCE(SUM(booked),0) AS booked FROM slots s WHERE s.slot_date = CURDATE() AND s.status = 'open' AND {$scopeS}", $pS);
$utilisation = $cap['capacity'] > 0 ? round($cap['booked'] / $cap['capacity'] * 100) : 0;
$openRequests = (int)q_val("SELECT COUNT(*) FROM service_requests r WHERE r.status NOT IN ('completed','rejected') AND {$scopeR}", $pR);
$actionNeeded = (int)q_val("SELECT COUNT(*) FROM service_requests r WHERE r.status = 'action_needed' AND {$scopeR}", $pR);
$rating = q_one(
    "SELECT ROUND(AVG(f.rating),1) AS avg_rating, COUNT(*) AS n, ROUND(AVG(f.prepared)*100) AS prepared_pct
       FROM feedback f JOIN appointments a ON a.id = f.appointment_id
      WHERE f.created_at > NOW() - INTERVAL 30 DAY AND {$scopeA}",
    $pA
);
$past30 = q_one(
    "SELECT SUM(a.status = 'no_show') AS no_show, SUM(a.status IN ('served','no_show')) AS concluded
       FROM appointments a JOIN slots s ON s.id = a.slot_id
      WHERE s.slot_date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() - INTERVAL 1 DAY AND {$scopeA}",
    $pA
);
$noShowRate = $past30['concluded'] > 0 ? round($past30['no_show'] / $past30['concluded'] * 100) : null;

$week = q_all(
    "SELECT s.slot_date, SUM(s.capacity) AS capacity, SUM(s.booked) AS booked
       FROM slots s
      WHERE s.slot_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 6 DAY AND s.status = 'open' AND {$scopeS}
      GROUP BY s.slot_date ORDER BY s.slot_date",
    $pS
);

$schedule = q_all(
    "SELECT s.start_time, s.capacity, s.booked, s.status, c.name AS centre_name,
            (SELECT COUNT(*) FROM appointments a WHERE a.slot_id = s.id AND a.status IN ('checked_in','served')) AS arrived
       FROM slots s JOIN centres c ON c.id = s.centre_id
      WHERE s.slot_date = CURDATE() AND {$scopeS}
      ORDER BY s.start_time, c.name",
    $pS
);

$recentRequests = q_all(
    "SELECT r.id, r.reference, r.full_name, r.status, r.updated_at, sv.name AS service_name
       FROM service_requests r JOIN services sv ON sv.id = r.service_id
      WHERE r.status NOT IN ('completed','rejected') AND {$scopeR}
      ORDER BY (r.status = 'action_needed') DESC, r.updated_at ASC LIMIT 6",
    $pR
);

render_header('Dashboard', 'staff');
?>
<div class="spread">
  <div>
    <div class="kicker"><?= e($user['centre_name'] ?? 'All centres') ?> · <?= e(fmt_date(date('Y-m-d'))) ?></div>
    <h1>Good <?= (int)date('G') < 12 ? 'morning' : ((int)date('G') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['name'])[0]) ?></h1>
  </div>
  <div class="row">
    <a class="btn btn-primary" href="<?= e(url('staff/appointments.php')) ?>">Today's appointments</a>
    <a class="btn" href="<?= e(url('staff/assisted_booking.php')) ?>">Assisted booking</a>
  </div>
</div>

<div class="grid grid-4" style="margin:18px 0">
  <div class="stat green"><div class="label-sm">Today</div><div class="value"><?= (int)$today['total'] ?></div><div class="sub">bookings · <?= (int)$today['served'] ?> served · <?= (int)$today['checked_in'] ?> waiting in centre</div></div>
  <div class="stat yellow"><div class="label-sm">Capacity used</div><div class="value"><?= $utilisation ?>%</div><div class="sub"><?= (int)$cap['booked'] ?> of <?= (int)$cap['capacity'] ?> planned places</div></div>
  <div class="stat blue"><div class="label-sm">Open requests</div><div class="value"><?= $openRequests ?></div><div class="sub"><?= $actionNeeded ?> need citizen action</div></div>
  <div class="stat coral"><div class="label-sm">Satisfaction (30 days)</div><div class="value"><?= $rating['avg_rating'] !== null ? e($rating['avg_rating']) . '/5' : '—' ?></div>
    <div class="sub"><?= (int)$rating['n'] ?> ratings<?= $rating['prepared_pct'] !== null ? ' · ' . (int)$rating['prepared_pct'] . '% arrived prepared' : '' ?><?= $noShowRate !== null ? ' · no-show ' . $noShowRate . '%' : '' ?></div></div>
</div>

<?php if ((int)$today['assistance'] > 0): ?>
  <div class="alert alert-info"><?= (int)$today['assistance'] ?> visitor(s) today asked for extra assistance. They are flagged in the appointment list.</div>
<?php endif; ?>

<div class="grid grid-2" style="align-items:start">
  <div class="card">
    <h2>Today's slots</h2>
    <?php if (!$schedule): ?>
      <p class="empty">No slots today. <a href="<?= e(url('staff/slots.php')) ?>">Set up capacity</a>.</p>
    <?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Time</th><?php if (staff_centre_scope() === null): ?><th>Centre</th><?php endif; ?><th>Booked</th><th>Arrived</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($schedule as $s): ?>
          <tr>
            <td><strong><?= e(fmt_time($s['start_time'])) ?></strong></td>
            <?php if (staff_centre_scope() === null): ?><td class="small"><?= e($s['centre_name']) ?></td><?php endif; ?>
            <td><?= (int)$s['booked'] ?>/<?= (int)$s['capacity'] ?></td>
            <td><?= (int)$s['arrived'] ?></td>
            <td><?= $s['status'] === 'closed' ? '<span class="badge badge-grey">Closed</span>' : ($s['booked'] >= $s['capacity'] ? '<span class="badge badge-coral">Full</span>' : '<span class="badge badge-green">Open</span>') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <h2>Next 7 days — demand vs capacity</h2>
      <div class="bars">
        <?php foreach ($week as $d): $pct = $d['capacity'] > 0 ? round($d['booked'] / $d['capacity'] * 100) : 0; ?>
          <div class="bar-row">
            <span><?= e(date('D j M', strtotime($d['slot_date']))) ?></span>
            <span class="bar" role="img" aria-label="<?= $pct ?>% booked"><i class="<?= $pct >= 90 ? 'high' : ($pct >= 70 ? 'mid' : '') ?>" style="width:<?= min(100, $pct) ?>%"></i></span>
            <span class="small"><?= (int)$d['booked'] ?>/<?= (int)$d['capacity'] ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$week): ?><p class="muted">No slots published for the coming week.</p><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="spread"><h2 style="margin:0">Requests needing follow-up</h2><a href="<?= e(url('staff/requests.php')) ?>">All →</a></div>
      <?php if (!$recentRequests): ?><p class="muted">Nothing outstanding.</p><?php endif; ?>
      <ul class="checklist">
        <?php foreach ($recentRequests as $r): ?>
          <li><a href="<?= e(url('staff/request.php', ['id' => $r['id']])) ?>"><?= e($r['reference']) ?></a> · <?= e($r['full_name']) ?>
            <span class="muted small">· <?= e($r['service_name']) ?></span> <?= status_badge($r['status'], REQUEST_STATUSES) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<p class="muted small">Operational measures support staff decisions; they do not replace local judgement or service standards.</p>
<?php render_footer('staff');
