<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_citizen();

$errors = [];
if (is_post()) {
    csrf_check();
    if (input('action') === 'profile') {
        $name = trim(input('full_name'));
        $phone = trim(input('phone'));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $errors['full_name'] = 'Enter your full name (3–120 characters).';
        }
        if ($phone !== '' && !valid_phone($phone)) {
            $errors['phone'] = 'Enter a valid phone number, e.g. 0712 345 678.';
        }
        if (!$errors) {
            q('UPDATE citizens SET full_name = ?, phone = ? WHERE id = ?', [$name, $phone !== '' ? normalise_phone($phone) : null, $me['id']]);
            flash('success', 'Your details were updated.');
            redirect('account/index.php');
        }
    } elseif (input('action') === 'password') {
        $hash = q_val('SELECT password_hash FROM citizens WHERE id = ?', [$me['id']]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$hash)) {
            $errors['current_password'] = 'Your current password is incorrect.';
        } elseif (strlen($new) < CITIZEN_MIN_PASSWORD) {
            $errors['new_password'] = 'Use a password of at least ' . CITIZEN_MIN_PASSWORD . ' characters.';
        } elseif ($new !== (string)($_POST['new_password_confirm'] ?? '')) {
            $errors['new_password_confirm'] = 'The two passwords do not match.';
        }
        if (!$errors) {
            q('UPDATE citizens SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Your password was changed.');
            redirect('account/index.php');
        }
    }
}

$appointments = q_all(
    "SELECT a.reference, a.manage_token, a.status, s.slot_date, s.start_time, sv.name AS service_name, c.name AS centre_name,
            (TIMESTAMP(s.slot_date, s.start_time) >= NOW() AND a.status IN ('booked','checked_in')) AS upcoming
       FROM appointments a
       JOIN slots s ON s.id = a.slot_id
       JOIN services sv ON sv.id = a.service_id
       JOIN centres c ON c.id = a.centre_id
      WHERE a.citizen_id = ?
      ORDER BY s.slot_date DESC, s.start_time DESC
      LIMIT 100",
    [$me['id']]
);
$upcoming = array_reverse(array_values(array_filter($appointments, fn($a) => (int)$a['upcoming'] === 1))); // soonest first
$past = array_values(array_filter($appointments, fn($a) => (int)$a['upcoming'] !== 1));
$requests = q_all(
    'SELECT r.reference, r.track_token, r.status, r.updated_at, sv.name AS service_name
       FROM service_requests r JOIN services sv ON sv.id = r.service_id
      WHERE r.citizen_id = ? ORDER BY r.updated_at DESC LIMIT 50',
    [$me['id']]
);

$appointmentItem = function (array $a, string $button): string {
    return '<li><div><strong>' . e($a['service_name']) . '</strong> ' . status_badge($a['status'], APPOINTMENT_STATUSES)
        . '<br><span class="small muted">' . e(fmt_date($a['slot_date'])) . ', ' . e(fmt_time($a['start_time']))
        . ' · ' . e($a['centre_name']) . ' · ' . e($a['reference']) . '</span></div>'
        . '<a class="btn btn-sm" href="' . e(url('booking.php', ['token' => $a['manage_token']])) . '">' . e($button) . '</a></li>';
};

render_header('My account');
?>
<div class="spread">
  <h1>Hello, <?= e($me['full_name']) ?></h1>
  <a class="btn btn-primary" href="<?= e(url('book.php')) ?>">+ Book a visit</a>
</div>

<div class="grid grid-2" style="align-items:start">
  <div>
    <div class="card">
      <h2>Upcoming appointments</h2>
      <?php if (!$upcoming): ?>
        <p class="muted">You have no upcoming appointments. <a href="<?= e(url('services.php')) ?>">Browse services</a> to book one.</p>
      <?php else: ?>
        <ul class="account-list"><?php foreach ($upcoming as $a) { echo $appointmentItem($a, 'View / cancel'); } ?></ul>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Service requests</h2>
      <?php if (!$requests): ?>
        <p class="muted">No requests yet. When a centre opens a request for you, it appears here so you can track it.</p>
      <?php else: ?>
        <ul class="account-list">
          <?php foreach ($requests as $r): ?>
            <li>
              <div><strong><?= e($r['service_name']) ?></strong> <?= status_badge($r['status'], REQUEST_STATUSES) ?><br>
                <span class="small muted"><?= e($r['reference']) ?> · updated <?= e(fmt_datetime($r['updated_at'])) ?></span></div>
              <a class="btn btn-sm" href="<?= e(url('track.php', ['token' => $r['track_token']])) ?>">Track</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <?php if ($past): ?>
      <div class="card">
        <h2>Past appointments</h2>
        <ul class="account-list"><?php foreach ($past as $a) { echo $appointmentItem($a, 'Details'); } ?></ul>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <form method="post" class="card form" novalidate>
      <h2>My details</h2>
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <label>Full name<input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? $me['full_name']) ?>" required maxlength="120" autocomplete="name"></label>
      <?= field_error($errors, 'full_name') ?>
      <label>Email<input type="email" value="<?= e($me['email']) ?>" disabled></label>
      <p class="hint">To change your email, contact <?= e(config('app.support_email')) ?>.</p>
      <label>Phone (optional)<input type="tel" name="phone" value="<?= e($_POST['phone'] ?? (string)$me['phone']) ?>" autocomplete="tel"></label>
      <?= field_error($errors, 'phone') ?>
      <button class="btn" type="submit">Save details</button>
    </form>

    <form method="post" class="card form" novalidate>
      <h2>Change password</h2>
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
      <?= field_error($errors, 'current_password') ?>
      <label>New password<input type="password" name="new_password" required minlength="<?= CITIZEN_MIN_PASSWORD ?>" autocomplete="new-password"></label>
      <?= field_error($errors, 'new_password') ?>
      <label>Confirm new password<input type="password" name="new_password_confirm" required autocomplete="new-password"></label>
      <?= field_error($errors, 'new_password_confirm') ?>
      <button class="btn" type="submit">Change password</button>
    </form>

    <form method="post" action="<?= e(url('account/logout.php')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Sign out</button></form>
  </div>
</div>
<?php render_footer();
