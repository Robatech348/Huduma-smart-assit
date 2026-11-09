<?php
declare(strict_types=1);

function nav_link(string $path, string $label, string $class = ''): string
{
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $root = realpath(APP_ROOT) ?: APP_ROOT;
    $rel = str_replace('\\', '/', ltrim(substr($script, strlen($root)), '\\/'));
    $active = strcasecmp($rel, $path) === 0;
    $classes = trim($class . ($active ? ' active' : ''));
    return '<a href="' . e(url($path)) . '"' . ($classes ? ' class="' . e($classes) . '"' : '') . ($active ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
}

/** Original SmartAssist emblem: a rounded shield with an "H" bridge and a guidance dot. */
function brand_logo(int $size = 44): string
{
    return '<svg class="brand-logo" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="hsaG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0A8F5B"/><stop offset="1" stop-color="#056B44"/></linearGradient></defs>'
        . '<path d="M24 2 43 9v14c0 11.5-8 19.6-19 23C13 42.6 5 34.5 5 23V9Z" fill="url(#hsaG)"/>'
        . '<path d="M24 2 43 9v3L24 5 5 12V9Z" fill="#111"/>'
        . '<path d="M24 5 43 12v1.6L24 6.6 5 13.6V12Z" fill="#fff"/>'
        . '<path d="M24 6.6 43 13.6v1.8L24 8.4 5 15.4v-1.8Z" fill="#C8102E"/>'
        . '<rect x="14" y="17" width="5" height="18" rx="2.5" fill="#fff"/>'
        . '<rect x="29" y="17" width="5" height="18" rx="2.5" fill="#fff"/>'
        . '<path d="M16 26.5h16" stroke="#fff" stroke-width="4.5" stroke-linecap="round"/>'
        . '<circle cx="24" cy="38.5" r="2.6" fill="#F4C430"/>'
        . '</svg>';
}

function render_header(string $title, string $area = 'public'): void
{
    $app = config('app.name');
    $user = current_user();
    $citizen = $area === 'public' ? current_citizen() : null;
    $phone = (string)config('app.support_phone');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($app) ?></title>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
<link rel="icon" href="<?= e(url('assets/img/logo.svg')) ?>" type="image/svg+xml">
</head>
<body class="area-<?= e($area) ?>">
<a class="skip" href="#main">Skip to content</a>
<div class="flag-stripe" aria-hidden="true"><span></span><span></span><span></span></div>
<?php if ($area === 'public'): ?>
<div class="utility">
  <div class="wrap utility-inner">
    <span>Government services, explained simply · <span lang="sw">Huduma kwa urahisi</span></span>
    <span class="utility-links">
      <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>">☎ <?= e($phone) ?></a>
      <a href="https://www.ecitizen.go.ke" target="_blank" rel="noopener">Official eCitizen portal ↗</a>
    </span>
  </div>
</div>
<?php endif; ?>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="<?= e(url($area === 'staff' ? 'staff/index.php' : 'index.php')) ?>">
      <?= brand_logo() ?>
      <span class="brand-text">
        <span class="brand-name"><?= e($app) ?><?php if ($area === 'staff'): ?> <small>Staff</small><?php endif; ?></span>
        <span class="brand-tag"><?= $area === 'staff' ? 'Huduma Centre operations' : 'Your guide to Huduma Centre services' ?></span>
      </span>
    </a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="mainnav"><span aria-hidden="true">☰</span> Menu</button>
    <nav id="mainnav" class="nav" aria-label="Main">
      <?php if ($area === 'staff' && $user): ?>
        <?= nav_link('staff/index.php', 'Dashboard') ?>
        <?= nav_link('staff/appointments.php', 'Appointments') ?>
        <?= nav_link('staff/assisted_booking.php', 'Assisted booking') ?>
        <?= nav_link('staff/requests.php', 'Requests') ?>
        <?= nav_link('staff/slots.php', 'Capacity') ?>
        <?= nav_link('staff/feedback.php', 'Feedback') ?>
        <?php if ($user['role'] === 'admin'): ?>
          <details class="nav-more"><summary>Admin</summary>
            <div>
              <?= nav_link('staff/services.php', 'Services') ?>
              <?= nav_link('staff/faqs.php', 'FAQs') ?>
              <?= nav_link('staff/centres.php', 'Centres') ?>
              <?= nav_link('staff/users.php', 'Users') ?>
              <?= nav_link('staff/outbox.php', 'Email outbox') ?>
              <?= nav_link('staff/insights.php', 'Assistant insights') ?>
            </div>
          </details>
        <?php endif; ?>
        <form method="post" action="<?= e(url('staff/logout.php')) ?>" class="inline"><?= csrf_field() ?><button class="link-btn" type="submit">Sign out</button></form>
      <?php elseif ($area === 'staff'): ?>
        <?= nav_link('index.php', 'Public site') ?>
      <?php else: ?>
        <?= nav_link('index.php', 'Home') ?>
        <?= nav_link('services.php', 'Services') ?>
        <?= nav_link('assistant.php', 'Ask SmartAssist') ?>
        <?= nav_link('book.php', 'Book a visit') ?>
        <?= nav_link('booking.php', 'Find appointment') ?>
        <?= nav_link('track.php', 'Track request') ?>
        <span class="nav-auth">
          <?php if ($citizen): ?>
            <?= nav_link('account/index.php', '👤 ' . explode(' ', $citizen['full_name'])[0], 'nav-account') ?>
            <form method="post" action="<?= e(url('account/logout.php')) ?>" class="inline"><?= csrf_field() ?><button class="link-btn" type="submit">Sign out</button></form>
          <?php else: ?>
            <?= nav_link('account/login.php', 'Sign in', 'nav-outline') ?>
            <?= nav_link('account/register.php', 'Register', 'nav-cta') ?>
          <?php endif; ?>
        </span>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="main" class="wrap main">
<?php foreach (take_flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
<?php endforeach;
}

function render_footer(string $area = 'public'): void
{
    ?>
</main>
<?php if ($area === 'staff'): ?>
<footer class="footer footer-staff">
  <div class="wrap footer-inner">
    <span>Signed in as <?= e(current_user()['name'] ?? '') ?><?= !empty(current_user()['centre_name']) ? ' · ' . e(current_user()['centre_name']) : '' ?></span>
    <a href="<?= e(url('index.php')) ?>">Public site</a>
  </div>
</footer>
<?php else:
    try {
        $footerCentres = q_all('SELECT id, name, county, address, phone, opening_hours FROM centres WHERE active = 1 ORDER BY county, name');
        $footerAgencies = q_all('SELECT agency, COUNT(*) AS n FROM services WHERE active = 1 GROUP BY agency ORDER BY n DESC, agency LIMIT 8');
    } catch (Throwable $ex) {
        $footerCentres = $footerAgencies = [];
    }
    $phone = (string)config('app.support_phone');
    ?>
<footer class="footer">
  <div class="flag-stripe" aria-hidden="true"><span></span><span></span><span></span></div>

  <?php if ($footerCentres): ?>
  <section class="footer-centres" aria-labelledby="footer-centres-h">
    <div class="wrap">
      <div class="footer-centres-head">
        <h2 id="footer-centres-h">Huduma Centres near you <span class="footer-sw">· Vituo vya Huduma</span></h2>
        <a class="footer-pill" href="<?= e(url('book.php')) ?>">Book a visit →</a>
      </div>
      <ul class="footer-centre-list">
        <?php foreach ($footerCentres as $fc): ?>
          <li>
            <span class="footer-county"><?= e($fc['county']) ?></span>
            <strong><?= e($fc['name']) ?></strong>
            <span>📍 <?= e($fc['address']) ?></span>
            <span>🕘 <?= e($fc['opening_hours']) ?></span>
            <?php if ($fc['phone']): ?><span>☎ <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $fc['phone'])) ?>"><?= e($fc['phone']) ?></a></span><?php endif; ?>
            <a class="footer-book" href="<?= e(url('book.php', ['centre' => $fc['id']])) ?>">Book at this centre →</a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (config('app.sample_content')): ?><p class="footer-note">Centre details shown are sample data for this pilot.</p><?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <div class="footer-main">
    <div class="wrap footer-grid">
      <section class="footer-about">
        <a class="brand brand-footer" href="<?= e(url('index.php')) ?>">
          <?= brand_logo(40) ?>
          <span class="brand-text"><span class="brand-name"><?= e(config('app.name')) ?></span><span class="brand-tag">Your guide to Huduma Centre services</span></span>
        </a>
        <p>Find out what each government service needs, apply online on the official portal, or book an assisted visit at a Huduma Centre.</p>
        <p class="footer-disclaimer">An independent pilot. This site is not the official eCitizen portal and does not take payments. Apply and pay only on <a href="https://www.ecitizen.go.ke" target="_blank" rel="noopener">ecitizen.go.ke</a>.</p>
      </section>
      <section aria-labelledby="footer-agencies-h">
        <h2 id="footer-agencies-h">Services by agency</h2>
        <ul class="footer-links">
          <?php foreach ($footerAgencies as $ag): ?>
            <li><a href="<?= e(url('services.php', ['agency' => $ag['agency']])) ?>"><?= e($ag['agency']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="<?= e(url('services.php')) ?>"><strong>All services →</strong></a></li>
        </ul>
      </section>
      <section aria-labelledby="footer-links-h">
        <h2 id="footer-links-h">Quick links</h2>
        <ul class="footer-links">
          <li><a href="<?= e(url('assistant.php')) ?>">Ask SmartAssist</a></li>
          <li><a href="<?= e(url('book.php')) ?>">Book an appointment</a></li>
          <li><a href="<?= e(url('booking.php')) ?>">Find my appointment</a></li>
          <li><a href="<?= e(url('track.php')) ?>">Track a request</a></li>
          <li><a href="<?= e(url('feedback.php')) ?>">Give feedback</a></li>
          <li><a href="<?= e(url('account/register.php')) ?>">Create an account</a></li>
        </ul>
      </section>
      <section aria-labelledby="footer-help-h">
        <h2 id="footer-help-h">Need help? <span class="footer-sw">· Msaada</span></h2>
        <ul class="footer-links footer-contact">
          <li>☎ <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></li>
          <li>✉ <a href="mailto:<?= e(config('app.support_email')) ?>"><?= e(config('app.support_email')) ?></a></li>
          <li>🕘 Mon–Fri, 8:00am – 5:00pm</li>
        </ul>
        <p>No smartphone or email? Walk in to any Huduma Centre and an officer will help you.</p>
        <p class="footer-warn">Never share your PIN, passwords or ID number through chat or email.</p>
      </section>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="wrap footer-bottom-inner">
      <span>© <?= date('Y') ?> <?= e(config('app.name', 'Huduma SmartAssist')) ?> · Pilot service</span>
      <span><a href="<?= e(url('privacy.php')) ?>">Privacy</a> · <a href="<?= e(url('services.php')) ?>">Services</a> · <a href="<?= e(url('staff/login.php')) ?>">Staff sign in</a></span>
    </div>
  </div>
</footer>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php
}


/** Render form field error message. */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="field-error" id="err-' . e($field) . '">' . e($errors[$field]) . '</p>' : '';
}

function paginate_links(int $page, int $pages, array $query): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pager" aria-label="Pages">';
    $script = basename($_SERVER['SCRIPT_NAME']);
    for ($i = 1; $i <= $pages; $i++) {
        $q = array_merge($query, ['page' => $i]);
        $html .= $i === $page ? '<span class="current">' . $i . '</span>' : '<a href="' . e($script . '?' . http_build_query($q)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}
