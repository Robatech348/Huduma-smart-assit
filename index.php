<?php
require __DIR__ . '/includes/bootstrap.php';

$services = active_services();
$popularSlugs = ['passport', 'national-id-replacement', 'good-conduct', 'kra-pin', 'driving-licence', 'business-name'];
$popular = array_values(array_filter($services, fn($s) => in_array($s['slug'], $popularSlugs, true))) ?: array_slice($services, 0, 6);
$centres = q_all('SELECT * FROM centres WHERE active = 1 ORDER BY name');
$agencies = q_all('SELECT agency, COUNT(*) AS n FROM services WHERE active = 1 GROUP BY agency ORDER BY n DESC, agency');

render_header('Home');
?>
<section class="hero">
  <div>
    <div class="kicker">Huduma Kenya · Smart service companion</div>
    <h1>A clearer route to public services.</h1>
    <p>Find out what you need, book a time at a Huduma Centre, and get updates on your request by email.</p>
    <form class="hero-search" action="<?= e(url('assistant.php')) ?>" method="get" role="search">
      <label class="skip" for="q">Ask a question</label>
      <input id="q" type="search" name="q" placeholder="e.g. How do I replace a lost ID?" required maxlength="500">
      <button class="btn btn-primary" type="submit">Ask SmartAssist</button>
    </form>
    <div class="row" style="margin-top:14px">
      <a class="btn" href="<?= e(url('services.php')) ?>">Browse all services</a>
      <a class="btn" href="<?= e(url('book.php')) ?>">Book a visit</a>
      <a class="btn" href="<?= e(url('track.php')) ?>">Track a request</a>
    </div>
  </div>
  <div class="hero-art" aria-hidden="true"><div class="hero-circle"></div></div>
</section>

<section class="steps" aria-label="How it works" style="margin-bottom:28px">
  <div class="step"><strong>Find</strong><p class="muted small">Search a service or ask a question.</p></div>
  <div class="step"><strong>Prepare</strong><p class="muted small">See exactly what documents to bring.</p></div>
  <div class="step"><strong>Book</strong><p class="muted small">Pick a centre and an open time slot.</p></div>
  <div class="step"><strong>Arrive</strong><p class="muted small">Show your reference at the service desk.</p></div>
  <div class="step"><strong>Track</strong><p class="muted small">Get email updates and share feedback.</p></div>
</section>

<div class="spread"><h2>Services by agency</h2><a href="<?= e(url('services.php')) ?>">All <?= count($services) ?> services →</a></div>
<div class="agency-grid">
  <?php foreach (array_slice($agencies, 0, 12) as $ag): ?>
    <a class="agency-tile" href="<?= e(url('services.php', ['agency' => $ag['agency']])) ?>">
      <span class="agency-badge" aria-hidden="true"><?= e(agency_initials($ag['agency'])) ?></span>
      <span><strong><?= e($ag['agency']) ?></strong><small><?= (int)$ag['n'] ?> service<?= (int)$ag['n'] === 1 ? '' : 's' ?></small></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="spread"><h2>Popular services</h2><a href="<?= e(url('services.php')) ?>">All services →</a></div>
<div class="grid grid-3" style="margin-bottom:28px">
  <?php foreach ($popular as $s): ?>
    <a class="card service-card" href="<?= e(url('service.php', ['slug' => $s['slug']])) ?>">
      <span class="kicker"><?= e($s['category']) ?></span>
      <strong><?= e($s['name']) ?></strong>
      <span class="muted small"><?= e($s['summary']) ?></span>
      <span class="meta"><?= e($s['agency']) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid grid-2">
  <div class="card accent">
    <h2>No email or internet?</h2>
    <p>You can still be served. Walk into any Huduma Centre and staff will book or serve you directly. Call <?= e(config('app.support_phone')) ?> for guidance.</p>
  </div>
  <div class="card">
    <h2>Participating centres</h2>
    <ul class="checklist">
      <?php foreach ($centres as $c): ?>
        <li><strong><?= e($c['name']) ?></strong> <span class="muted small">— <?= e($c['opening_hours']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php render_footer();
