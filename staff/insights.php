<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/assistant.php';
require_admin();

$stats = q_one(
    "SELECT COUNT(*) AS n, SUM(source='ai') AS ai, SUM(source='catalogue') AS catalogue, SUM(source='fallback') AS fallback,
            SUM(helpful=1) AS helpful, SUM(helpful=0) AS unhelpful
       FROM assistant_logs WHERE created_at > NOW() - INTERVAL 30 DAY"
);
$topServices = q_all(
    "SELECT s.name, COUNT(*) AS n FROM assistant_logs l JOIN services s ON s.id = l.matched_service_id
      WHERE l.created_at > NOW() - INTERVAL 30 DAY GROUP BY s.id, s.name ORDER BY n DESC LIMIT 10"
);
$gaps = q_all(
    "SELECT question, source, helpful, created_at FROM assistant_logs
      WHERE (source = 'fallback' OR helpful = 0) ORDER BY id DESC LIMIT 50"
);
$recent = q_all('SELECT question, source, helpful, created_at FROM assistant_logs ORDER BY id DESC LIMIT 30');

render_header('Assistant insights', 'staff');
?>
<h1>SmartAssist insights</h1>
<div class="alert alert-info">Assistant mode: <strong><?= Assistant::aiAvailable() ? 'AI-assisted (Claude, ' . e(config('assistant.model')) . ')' : 'Catalogue only' ?></strong>.
  <?php if (!Assistant::aiAvailable()): ?>To enable AI answers, install the Anthropic PHP SDK with Composer and set the API key in config.php (see README).<?php endif; ?></div>

<div class="grid grid-4" style="margin-bottom:18px">
  <div class="stat green"><div class="label-sm">Questions (30 days)</div><div class="value"><?= (int)$stats['n'] ?></div></div>
  <div class="stat blue"><div class="label-sm">Answered</div><div class="value"><?= (int)$stats['ai'] + (int)$stats['catalogue'] ?></div><div class="sub"><?= (int)$stats['ai'] ?> AI · <?= (int)$stats['catalogue'] ?> catalogue</div></div>
  <div class="stat coral"><div class="label-sm">No match</div><div class="value"><?= (int)$stats['fallback'] ?></div><div class="sub">content gaps to fill</div></div>
  <div class="stat yellow"><div class="label-sm">Helpful votes</div><div class="value"><?= (int)$stats['helpful'] ?> / <?= (int)$stats['helpful'] + (int)$stats['unhelpful'] ?></div></div>
</div>

<div class="grid grid-2" style="align-items:start">
  <div class="card">
    <h2>Content gaps</h2>
    <p class="muted small">Questions with no catalogue match or marked unhelpful. Add FAQs, keywords or services to cover them.</p>
    <?php if (!$gaps): ?><p class="muted">None yet.</p><?php endif; ?>
    <ul class="checklist small"><?php foreach ($gaps as $g): ?><li><?= e($g['question']) ?> <span class="muted">· <?= e($g['source']) ?><?= $g['helpful'] === 0 ? ' · unhelpful' : '' ?> · <?= e(fmt_datetime($g['created_at'])) ?></span></li><?php endforeach; ?></ul>
  </div>
  <div>
    <div class="card"><h2>Most asked-about services</h2>
      <?php if (!$topServices): ?><p class="muted">No data yet.</p><?php endif; ?>
      <ul class="checklist small"><?php foreach ($topServices as $t): ?><li><?= e($t['name']) ?> <span class="muted">· <?= (int)$t['n'] ?></span></li><?php endforeach; ?></ul></div>
    <div class="card"><h2>Recent questions</h2>
      <ul class="checklist small"><?php foreach ($recent as $r): ?><li><?= e($r['question']) ?> <span class="muted">· <?= e($r['source']) ?></span></li><?php endforeach; ?></ul></div>
  </div>
</div>
<?php render_footer('staff');
