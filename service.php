<?php
require __DIR__ . '/includes/bootstrap.php';

$s = q_one('SELECT * FROM services WHERE slug = ? AND active = 1', [input('slug')]);
if (!$s) {
    http_response_code(404);
    render_header('Service not found');
    echo '<div class="card empty"><h1>Service not found</h1><p><a href="' . e(url('services.php')) . '">Browse all services</a></p></div>';
    render_footer();
    exit;
}
$centres = centres_for_service((int)$s['id']);

render_header($s['name']);
?>
<p><a href="<?= e(url('services.php')) ?>">← All services</a></p>
<div class="kicker"><?= e($s['category']) ?> · <?= e($s['agency']) ?></div>
<h1><?= e($s['name']) ?></h1>
<p style="font-size:1.1rem"><?= e($s['summary']) ?></p>
<?php if (config('app.sample_content')): ?>
  <div class="alert alert-warning">Pilot prototype: these details are sample content. Confirm fees and requirements with <?= e($s['agency']) ?>.</div>
<?php endif; ?>

<?php if (!empty($s['apply_url'])): ?>
  <div class="card apply-box spread">
    <div>
      <h2 style="margin:0 0 4px">Apply online</h2>
      <p class="muted" style="margin:0">Start your application and pay on the official portal, <strong><?= e(link_host($s['apply_url'])) ?></strong>. This site never takes payments.</p>
    </div>
    <a class="btn btn-primary" href="<?= e($s['apply_url']) ?>" target="_blank" rel="noopener">Apply on <?= e(link_host($s['apply_url'])) ?> ↗</a>
  </div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card accent">
    <h2>What to bring</h2>
    <ul class="checklist">
      <?php foreach (lines($s['requirements']) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <div class="card">
    <h2>Steps</h2>
    <ol class="numbered">
      <?php foreach (lines($s['steps']) as $st): ?><li><?= e($st) ?></li><?php endforeach; ?>
    </ol>
    <dl class="details">
      <dt>Fee</dt><dd><?= e($s['fee']) ?></dd>
      <dt>Processing time</dt><dd><?= e($s['processing_time']) ?></dd>
      <dt>Last updated</dt><dd><?= e(fmt_date($s['updated_at'])) ?></dd>
    </dl>
  </div>
</div>

<div class="card">
  <div class="spread">
    <h2 style="margin:0">Where to get this service</h2>
    <?php if ($s['bookable'] && $centres): ?>
      <a class="btn btn-primary" href="<?= e(url('book.php', ['service' => $s['id']])) ?>">Book an appointment</a>
    <?php endif; ?>
  </div>
  <?php if (!$centres): ?>
    <p class="muted">Ask at your nearest Huduma Centre.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Centre</th><th>Address</th><th>Hours</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($centres as $c): ?>
        <tr>
          <td><strong><?= e($c['name']) ?></strong></td>
          <td><?= e($c['address']) ?></td>
          <td><?= e($c['opening_hours']) ?></td>
          <td><?php if ($s['bookable']): ?><a class="btn btn-sm" href="<?= e(url('book.php', ['service' => $s['id'], 'centre' => $c['id']])) ?>">Book here</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <?php if (!$s['bookable']): ?><p class="muted small">This service is for enquiries only. Visit a centre during opening hours.</p><?php endif; ?>
</div>

<p><a class="btn" href="<?= e(url('assistant.php', ['q' => 'Tell me about ' . $s['name']])) ?>">Have a question? Ask SmartAssist</a></p>
<?php render_footer();
