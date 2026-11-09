<?php
require __DIR__ . '/includes/bootstrap.php';

$appointment = null;
$error = '';
$token = input('token');

if ($token !== '' && preg_match('/^[a-f0-9]{40}$/', $token)) {
    $id = q_val('SELECT id FROM appointments WHERE manage_token = ?', [$token]);
    $appointment = $id ? appointment_details((int)$id) : null;
    if (!$appointment) {
        $error = 'We could not find that appointment. Check the link in your email.';
    }
} elseif (input('reference') !== '') {
    if (rate_limited('lookup', 10, 600)) {
        $error = 'Too many lookups. Please wait a few minutes.';
    } else {
        $id = q_val('SELECT id FROM appointments WHERE reference = ? AND email = ?', [normalise_reference(input('reference')), strtolower(input('email'))]);
        if ($id) {
            $t = q_val('SELECT manage_token FROM appointments WHERE id = ?', [$id]);
            redirect('booking.php', ['token' => $t]);
        }
        $error = 'No appointment matches that reference and email address.';
    }
}

if ($appointment && is_post() && input('action') === 'cancel') {
    csrf_check();
    $start = new DateTimeImmutable($appointment['slot_date'] . ' ' . $appointment['start_time']);
    if ($start <= new DateTimeImmutable()) {
        flash('error', 'This appointment has already started and can no longer be cancelled online.');
    } elseif (cancel_appointment((int)$appointment['id'])) {
        flash('success', 'Your appointment has been cancelled and the slot released.');
    } else {
        flash('error', 'This appointment cannot be cancelled.');
    }
    redirect('booking.php', ['token' => $token]);
}

render_header('My appointment');

if (!$appointment): ?>
  <div class="narrow">
    <h1>My appointment</h1>
    <p class="muted">Use the link in your confirmation email, or enter your reference and email.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form class="card form" method="get">
      <label>Reference number<input type="text" name="reference" placeholder="HSA-XXXXXX" value="<?= e(input('reference')) ?>" required></label>
      <label>Email used for booking<input type="email" name="email" value="<?= e(input('email')) ?>" required></label>
      <button class="btn btn-primary" type="submit">Find appointment</button>
    </form>
  </div>
<?php else:
    $a = $appointment;
    $upcoming = $a['status'] === 'booked' && new DateTimeImmutable($a['slot_date'] . ' ' . $a['start_time']) > new DateTimeImmutable();
    $hasFeedback = q_val('SELECT 1 FROM feedback WHERE appointment_id = ?', [$a['id']]);
    ?>
  <div class="grid grid-2" style="align-items:start">
    <div class="ticket">
      <div class="kicker" style="color:var(--lime)">Appointment reference</div>
      <div class="ref"><?= e($a['reference']) ?></div>
      <p style="margin:6px 0 16px"><?= status_badge($a['status'], APPOINTMENT_STATUSES) ?></p>
      <dl class="details">
        <dt>Name</dt><dd><?= e($a['full_name']) ?></dd>
        <dt>Service</dt><dd><?= e($a['service_name']) ?></dd>
        <dt>Centre</dt><dd><?= e($a['centre_name']) ?><br><span style="font-weight:400"><?= e($a['centre_address']) ?></span></dd>
        <dt>Date</dt><dd><?= e(fmt_date($a['slot_date'])) ?></dd>
        <dt>Time</dt><dd><?= e(fmt_time($a['start_time'])) ?> – <?= e(fmt_time($a['end_time'])) ?></dd>
      </dl>
    </div>
    <div>
      <?php if ($upcoming): ?>
        <div class="card accent">
          <h2>Before you go</h2>
          <p>Arrive 10 minutes early and show this reference at the service desk.</p>
          <ul class="checklist">
            <?php foreach (lines($a['requirements']) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <div class="row no-print">
          <button class="btn" type="button" onclick="window.print()">Print</button>
          <form method="post" data-confirm="Cancel this appointment? The slot will be released to others.">
            <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
            <button class="btn btn-danger" type="submit">Cancel appointment</button>
          </form>
          <a class="btn" href="<?= e(url('book.php', ['service' => $a['service_id']])) ?>">Book a different time</a>
        </div>
        <p class="muted small">To change the time, cancel this appointment and then book a new one.</p>
      <?php elseif ($a['status'] === 'served' && !$hasFeedback): ?>
        <div class="card accent"><h2>How was your visit?</h2><p>Your feedback helps improve the service.</p>
          <a class="btn btn-primary" href="<?= e(url('feedback.php', ['token' => $a['manage_token']])) ?>">Rate your visit</a></div>
      <?php elseif ($a['status'] === 'cancelled'): ?>
        <div class="card"><p>This appointment was cancelled.</p><a class="btn btn-primary" href="<?= e(url('book.php', ['service' => $a['service_id']])) ?>">Book again</a></div>
      <?php else: ?>
        <div class="card"><p>This appointment is no longer upcoming.</p>
          <?php $req = q_one('SELECT reference, track_token FROM service_requests WHERE appointment_id = ?', [$a['id']]); if ($req): ?>
            <p>Your service request: <a href="<?= e(url('track.php', ['token' => $req['track_token']])) ?>"><?= e($req['reference']) ?></a></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif;
render_footer();
