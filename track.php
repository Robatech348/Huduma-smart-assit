<?php
require __DIR__ . '/includes/bootstrap.php';

$request = null;
$error = '';
$token = input('token');

if ($token !== '' && preg_match('/^[a-f0-9]{40}$/', $token)) {
    $request = q_one('SELECT * FROM service_requests WHERE track_token = ?', [$token]);
    if (!$request) {
        $error = 'We could not find that request. Check the link in your email.';
    }
} elseif (input('reference') !== '') {
    if (rate_limited('lookup', 10, 600)) {
        $error = 'Too many lookups. Please wait a few minutes.';
    } else {
        $contact = input('contact');
        $ref = normalise_reference(input('reference'));
        $request = q_one(
            'SELECT * FROM service_requests WHERE reference = ? AND (email = ? OR (phone IS NOT NULL AND phone = ?))',
            [$ref, strtolower($contact), normalise_phone($contact)]
        );
        if (!$request) {
            $error = 'No request matches that reference and contact detail.';
        }
    }
}

render_header('Track a request');

if (!$request): ?>
  <div class="narrow">
    <h1>Track a request</h1>
    <p class="muted">Enter the request reference you received at the centre (it starts with REQ-) and the email or phone number you gave.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form class="card form" method="get">
      <label>Request reference<input type="text" name="reference" placeholder="REQ-XXXXXX" value="<?= e(input('reference')) ?>" required></label>
      <label>Email or phone number<input type="text" name="contact" value="<?= e(input('contact')) ?>" required></label>
      <button class="btn btn-primary" type="submit">Check status</button>
    </form>
    <p class="muted small">Looking for an appointment instead? Go to <a href="<?= e(url('booking.php')) ?>">My appointment</a>.</p>
  </div>
<?php else:
    $r = $request;
    $svc = q_one('SELECT name, agency FROM services WHERE id = ?', [$r['service_id']]);
    $ctr = q_one('SELECT name, phone FROM centres WHERE id = ?', [$r['centre_id']]);
    $updates = q_all('SELECT status, public_note, created_at FROM request_updates WHERE request_id = ? ORDER BY created_at DESC, id DESC', [$r['id']]);
    [$label, $tone, $explain] = REQUEST_STATUSES[$r['status']];
    $order = array_keys(REQUEST_STATUSES);
    ?>
  <div class="kicker">Service request</div>
  <h1><?= e($r['reference']) ?></h1>
  <div class="grid grid-2" style="align-items:start">
    <div class="card accent">
      <p><?= status_badge($r['status'], REQUEST_STATUSES) ?></p>
      <h2><?= e($explain) ?></h2>
      <dl class="details">
        <dt>Service</dt><dd><?= e($svc['name']) ?></dd>
        <dt>Agency</dt><dd><?= e($svc['agency']) ?></dd>
        <dt>Centre</dt><dd><?= e($ctr['name']) ?></dd>
        <dt>Submitted</dt><dd><?= e(fmt_datetime($r['created_at'])) ?></dd>
        <dt>Last update</dt><dd><?= e(fmt_datetime($r['updated_at'])) ?></dd>
      </dl>
      <?php if ($r['status'] === 'action_needed'): ?>
        <div class="alert alert-warning" style="margin-top:16px">Please read the latest message below and contact <?= e($ctr['name']) ?><?= $ctr['phone'] ? ' on ' . e($ctr['phone']) : '' ?>.</div>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>History</h2>
      <ul class="timeline">
        <?php foreach ($updates as $u): ?>
          <li>
            <strong><?= e(REQUEST_STATUSES[$u['status']][0] ?? $u['status']) ?></strong>
            <span class="muted small"> · <?= e(fmt_datetime($u['created_at'])) ?></span>
            <?php if ($u['public_note']): ?><p class="small" style="margin:4px 0 0"><?= nl2br(e($u['public_note'])) ?></p><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <p class="muted small">You receive an email each time staff update this request. Do not send documents by email.</p>
<?php endif;
render_footer();
