<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/assistant.php';

$category = input('category');
$agency = input('agency');
$search = input('search');
$all = active_services();

$categories = array_values(array_unique(array_column($all, 'category')));
sort($categories);
$agencyCounts = array_count_values(array_column($all, 'agency'));
arsort($agencyCounts);

$services = $all;
if ($agency !== '') {
    $services = array_filter($services, fn($s) => $s['agency'] === $agency);
}
if ($category !== '') {
    $services = array_filter($services, fn($s) => $s['category'] === $category);
}
if ($search !== '') {
    $tokens = Assistant::tokens($search);
    $services = array_filter($services, function ($s) use ($tokens, $search) {
        $hay = mb_strtolower($s['name'] . ' ' . $s['summary'] . ' ' . $s['keywords'] . ' ' . $s['agency']);
        if (mb_stripos($hay, $search) !== false) {
            return true;
        }
        return (bool)array_intersect($tokens, Assistant::tokens($hay));
    });
}

// Group results by agency, biggest agencies first.
$grouped = [];
foreach ($services as $s) {
    $grouped[$s['agency']][] = $s;
}
uksort($grouped, fn($a, $b) => ($agencyCounts[$b] <=> $agencyCounts[$a]) ?: strcmp($a, $b));
$filtered = $search !== '' || $category !== '' || $agency !== '';

render_header($agency !== '' ? $agency : 'Services');
?>
<div class="spread">
  <div>
    <div class="kicker">Government services · Huduma za Serikali</div>
    <h1 style="margin-bottom:4px"><?= $agency !== '' ? e($agency) : 'All government services' ?></h1>
    <p class="muted" style="margin-top:0"><?= count($all) ?> services from <?= count($agencyCounts) ?> agencies. Apply online on the official portal, or book an assisted visit at a Huduma Centre.</p>
  </div>
</div>
<?php if (config('app.sample_content')): ?>
  <div class="alert alert-warning">Pilot prototype: requirements and steps are general guidance. Always confirm fees and documents on the official portal before you apply.</div>
<?php endif; ?>

<form class="form-inline card" method="get" role="search">
  <label>Search<input type="search" name="search" value="<?= e($search) ?>" placeholder="e.g. passport, KRA, lost ID, logbook"></label>
  <label>Agency
    <select name="agency" data-autosubmit>
      <option value="">All agencies</option>
      <?php foreach ($agencyCounts as $a => $n): ?>
        <option value="<?= e($a) ?>" <?= $a === $agency ? 'selected' : '' ?>><?= e($a) ?> (<?= (int)$n ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Category
    <select name="category" data-autosubmit>
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-primary" type="submit">Search</button>
  <?php if ($filtered): ?><a class="btn" href="<?= e(url('services.php')) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$filtered): ?>
  <h2>Browse by agency</h2>
  <div class="agency-grid">
    <?php foreach ($agencyCounts as $a => $n): ?>
      <a class="agency-tile" href="<?= e(url('services.php', ['agency' => $a])) ?>">
        <span class="agency-badge" aria-hidden="true"><?= e(agency_initials($a)) ?></span>
        <span><strong><?= e($a) ?></strong><small><?= (int)$n ?> service<?= $n === 1 ? '' : 's' ?></small></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$services): ?>
  <div class="card empty">
    <p>No services matched<?= $search !== '' ? ' “' . e($search) . '”' : ' these filters' ?>.</p>
    <a class="btn btn-primary" href="<?= e(url('assistant.php', ['q' => $search])) ?>">Ask SmartAssist instead</a>
  </div>
<?php else: ?>
  <?php foreach ($grouped as $agencyName => $list): ?>
    <section class="agency-section">
      <h2><span class="agency-badge" aria-hidden="true"><?= e(agency_initials($agencyName)) ?></span> <?= e($agencyName) ?></h2>
      <div class="grid grid-3">
        <?php foreach ($list as $s): ?>
          <a class="card service-card" href="<?= e(url('service.php', ['slug' => $s['slug']])) ?>">
            <span class="kicker"><?= e($s['category']) ?></span>
            <strong><?= e($s['name']) ?></strong>
            <span class="muted small"><?= e($s['summary']) ?></span>
            <span class="pills">
              <?php if ($s['apply_url']): ?><span class="pill pill-online">Apply online</span><?php endif; ?>
              <?php if ($s['bookable']): ?><span class="pill">Book assisted visit</span><?php else: ?><span class="pill" style="background:#ECECE7;color:#545B57">Online / enquiries</span><?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif;
render_footer();
