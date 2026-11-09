<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_staff();

[$scope, $params] = scope_sql('a.centre_id');
$days = in_array(input_int('days', 30), [7, 30, 90, 365], true) ? input_int('days', 30) : 30;
$params[] = $days;

$summary = q_one(
    "SELECT COUNT(*) AS n, ROUND(AVG(f.rating),2) AS avg_rating, ROUND(AVG(f.prepared)*100) AS prepared_pct
       FROM feedback f JOIN appointments a ON a.id = f.appointment_id
      WHERE {$scope} AND f.created_at > NOW() - INTERVAL ? DAY",
    $params
);
$dist = q_all(
    "SELECT f.rating, COUNT(*) AS n FROM feedback f JOIN appointments a ON a.id = f.appointment_id
      WHERE {$scope} AND f.created_at > NOW() - INTERVAL ? DAY GROUP BY f.rating ORDER BY f.rating DESC",
    $params
);
$byService = q_all(
    "SELECT sv.name, COUNT(*) AS n, ROUND(AVG(f.rating),1) AS avg_rating, ROUND(AVG(f.prepared)*100) AS prepared_pct
       FROM feedback f JOIN appointments a ON a.id = f.appointment_id JOIN services sv ON sv.id = a.service_id
      WHERE {$scope} AND f.created_at > NOW() - INTERVAL ? DAY GROUP BY sv.id, sv.name ORDER BY n DESC",
    $params
);
$comments = q_all(
    "SELECT f.*, a.reference, sv.name AS service_name, c.name AS centre_name
       FROM feedback f JOIN appointments a ON a.id = f.appointment_id
       JOIN services sv ON sv.id = a.service_id JOIN centres c ON c.id = a.centre_id
      WHERE {$scope} AND f.created_at > NOW() - INTERVAL ? DAY ORDER BY f.created_at DESC LIMIT 100",
    $params
);
$distMap = array_column($dist, 'n', 'rating');

render_header('Feedback', 'staff');
?>
<div class="spread">
  <h1>Citizen feedback</h1>
  <form method="get"><select name="days" data-autosubmit aria-label="Period">
    <?php foreach ([7, 30, 90, 365] as $d): ?><option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>>Last <?= $d ?> days</option><?php endforeach; ?>
  </select></form>
</div>

<div class="grid grid-3" style="margin:16px 0">
  <div class="stat green"><div class="label-sm">Average rating</div><div class="value"><?= $summary['avg_rating'] !== null ? e($summary['avg_rating']) : '—' ?></div><div class="sub">out of 5 · <?= (int)$summary['n'] ?> responses</div></div>
  <div class="stat blue"><div class="label-sm">Arrived prepared</div><div class="value"><?= $summary['prepared_pct'] !== null ? (int)$summary['prepared_pct'] . '%' : '—' ?></div><div class="sub">had everything they needed</div></div>
  <div class="stat yellow"><div class="label-sm">Distribution</div>
    <div class="bars" style="margin-top:6px">
      <?php for ($i = 5; $i >= 1; $i--): $n = (int)($distMap[$i] ?? 0); $pct = $summary['n'] ? round($n / $summary['n'] * 100) : 0; ?>
        <div class="bar-row" style="grid-template-columns:30px 1fr 30px"><span><?= $i ?>★</span><span class="bar"><i style="width:<?= $pct ?>%"></i></span><span class="small"><?= $n ?></span></div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<div class="grid grid-2" style="align-items:start">
  <div class="card">
    <h2>By service</h2>
    <?php if (!$byService): ?><p class="muted">No feedback yet.</p><?php else: ?>
    <table class="table"><thead><tr><th>Service</th><th>Responses</th><th>Rating</th><th>Prepared</th></tr></thead><tbody>
      <?php foreach ($byService as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= (int)$s['n'] ?></td><td><?= e($s['avg_rating']) ?></td><td><?= $s['prepared_pct'] !== null ? (int)$s['prepared_pct'] . '%' : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <p class="muted small">Low "prepared" scores suggest the requirements shown for that service need to be clearer.</p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Recent comments</h2>
    <?php if (!$comments): ?><p class="muted">No feedback yet.</p><?php endif; ?>
    <?php foreach ($comments as $c): ?>
      <div style="border-bottom:1px solid var(--line);padding:10px 0">
        <span class="stars" aria-label="<?= (int)$c['rating'] ?> stars"><?= str_repeat('★', (int)$c['rating']) ?><span style="color:#ddd"><?= str_repeat('★', 5 - (int)$c['rating']) ?></span></span>
        <span class="muted small"> · <?= e($c['service_name']) ?> · <?= e($c['centre_name']) ?> · <?= e(fmt_datetime($c['created_at'])) ?></span>
        <?php if ($c['comment']): ?><p style="margin:4px 0 0"><?= nl2br(e($c['comment'])) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php render_footer('staff');
