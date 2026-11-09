<?php
require __DIR__ . '/includes/bootstrap.php';

$services = active_services(true);
$serviceId = input_int('service');
$centreId = input_int('centre');
$service = $serviceId ? q_one('SELECT * FROM services WHERE id = ? AND active = 1 AND bookable = 1', [$serviceId]) : null;
$centres = $service ? centres_for_service((int)$service['id']) : [];
if ($service && count($centres) === 1 && !$centreId) {
    $centreId = (int)$centres[0]['id'];
}
$centre = null;
foreach ($centres as $c) {
    if ((int)$c['id'] === $centreId) {
        $centre = $c;
    }
}

$citizen = current_citizen();
$errors = [];
if (is_post() && $service && $centre) {
    csrf_check();
    if (!$citizen) {
        // Booking needs an account; come back to this service and centre after signing in.
        require_citizen('Please sign in or create an account to book your appointment.');
    }
    // Name and email come from the account; phone and assistance can be set per booking.
    [$data, $errors] = validate_booking_details([
        'full_name'        => $citizen['full_name'],
        'email'            => $citizen['email'],
        'phone'            => $_POST['phone'] ?? '',
        'needs_assistance' => $_POST['needs_assistance'] ?? '',
    ], true);
    $data['citizen_id'] = (int)$citizen['id'];
    $slotId = input_int('slot_id');
    if (!$slotId) {
        $errors['slot_id'] = 'Choose a time slot.';
    }
    if (empty($_POST['consent'])) {
        $errors['consent'] = 'Please confirm you agree to how we use your details.';
    }
    if (!$errors && rate_limited('booking', 5, 3600)) {
        $errors['general'] = 'Too many bookings from this browser. Please try later or visit a centre.';
    }
    if (!$errors) {
        try {
            $slot = q_one('SELECT centre_id FROM slots WHERE id = ?', [$slotId]);
            if (!$slot || (int)$slot['centre_id'] !== (int)$centre['id']) {
                throw new BookingException('Please choose a time slot at the selected centre.');
            }
            $id = book_appointment($slotId, (int)$service['id'], $data);
            $token = q_val('SELECT manage_token FROM appointments WHERE id = ?', [$id]);
            $_SESSION['my_bookings'][] = $id;
            flash('success', 'Your appointment is booked. A confirmation has been sent to ' . $data['email'] . '.');
            redirect('booking.php', ['token' => $token]);
        } catch (BookingException $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}

$slotsByDay = $centre ? upcoming_slots((int)$centre['id']) : [];
$step = !$service ? 1 : (!$centre ? 2 : 3);

render_header('Book a visit');
?>
<h1>Book a visit</h1>
<div class="wizard" aria-label="Progress">
  <span class="<?= $step === 1 ? 'on' : '' ?>">1. Service</span>
  <span class="<?= $step === 2 ? 'on' : '' ?>">2. Centre</span>
  <span class="<?= $step === 3 ? 'on' : '' ?>">3. Time & details</span>
</div>

<form class="card form-inline" method="get">
  <label>Service
    <select name="service" data-autosubmit required>
      <option value="">Choose a service…</option>
      <?php foreach ($services as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $service && (int)$s['id'] === (int)$service['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if ($service): ?>
    <label>Centre
      <select name="centre" data-autosubmit required>
        <option value="">Choose a centre…</option>
        <?php foreach ($centres as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $centre && (int)$c['id'] === (int)$centre['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['county']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <noscript><button class="btn" type="submit">Continue</button></noscript>
</form>

<?php if ($service && !$centres): ?>
  <div class="alert alert-info">No participating centre currently offers online booking for this service. Visit your nearest Huduma Centre.</div>
<?php endif; ?>

<?php if ($service && $centre): ?>
  <div class="grid grid-2" style="align-items:start">
    <form method="post" class="card form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="service" value="<?= (int)$service['id'] ?>">
      <input type="hidden" name="centre" value="<?= (int)$centre['id'] ?>">
      <?php if (isset($errors['general'])): ?><div class="alert alert-error"><?= e($errors['general']) ?></div><?php endif; ?>

      <fieldset>
        <legend>Choose a time</legend>
        <?= field_error($errors, 'slot_id') ?>
        <?php if (!$slotsByDay): ?>
          <p class="muted">No open slots in the next <?= (int)config('app.booking_window_days') ?> days at this centre. Try another centre, or walk in during opening hours.</p>
        <?php endif; ?>
        <?php $selected = input_int('slot_id'); foreach ($slotsByDay as $date => $slots): ?>
          <div class="slot-day">
            <h3><?= e(fmt_date($date)) ?></h3>
            <div class="slot-grid">
              <?php foreach ($slots as $sl): ?>
                <label class="slot <?= $sl['available'] ? '' : 'full' ?>">
                  <input type="radio" name="slot_id" value="<?= (int)$sl['id'] ?>" <?= $sl['available'] ? '' : 'disabled' ?> <?= $selected === (int)$sl['id'] ? 'checked' : '' ?>>
                  <span><?= e(fmt_time($sl['start_time'])) ?>
                    <small><?= $sl['status'] === 'closed' ? 'Closed' : ($sl['remaining'] > 0 ? $sl['remaining'] . ' left' : 'Full') ?></small></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <?php if ($citizen): ?>
      <fieldset class="form">
        <legend>Your details</legend>
        <p class="booking-as">Booking as <strong><?= e($citizen['full_name']) ?></strong> · <?= e($citizen['email']) ?>
          <a class="small" href="<?= e(url('account/index.php')) ?>">Edit</a></p>
        <label>Phone (optional)<input type="tel" name="phone" value="<?= e($_POST['phone'] ?? (string)$citizen['phone']) ?>" autocomplete="tel" placeholder="0712 345 678"></label>
        <?= field_error($errors, 'phone') ?>
        <label class="check"><input type="checkbox" name="needs_assistance" value="1" <?= !empty($_POST['needs_assistance']) ? 'checked' : '' ?>> I may need extra assistance at the centre (e.g. mobility, sight, hearing).</label>
        <label class="check"><input type="checkbox" name="consent" value="1" <?= !empty($_POST['consent']) ? 'checked' : '' ?>> I agree that my name and contact details are used only to manage this appointment, as described in the <a href="<?= e(url('privacy.php')) ?>" target="_blank">privacy notice</a>.</label>
        <?= field_error($errors, 'consent') ?>
        <p class="muted small">Do not enter your ID number. Staff will verify documents in person.</p>
      </fieldset>
      <button class="btn btn-primary" type="submit" <?= $slotsByDay ? '' : 'disabled' ?>>Confirm booking</button>
      <?php else: ?>
      <div class="signin-gate">
        <h3>Sign in to book</h3>
        <p class="muted">You can look at the open times above. To reserve one, sign in, or create a free account if this is your first time.</p>
        <?php $back = url('book.php', ['service' => $service['id'], 'centre' => $centre['id']]); ?>
        <div class="row">
          <a class="btn btn-primary" href="<?= e(url('account/login.php', ['next' => $back])) ?>">Sign in</a>
          <a class="btn" href="<?= e(url('account/register.php', ['next' => $back])) ?>">Create an account</a>
        </div>
        <p class="muted small">No email or smartphone? An officer at any Huduma Centre can book for you.</p>
      </div>
      <?php endif; ?>
    </form>

    <aside>
      <div class="card accent">
        <div class="kicker">You are booking</div>
        <h2><?= e($service['name']) ?></h2>
        <p class="muted"><?= e($centre['name']) ?><br><?= e($centre['address']) ?><br><?= e($centre['opening_hours']) ?></p>
        <h3>Bring these documents</h3>
        <ul class="checklist">
          <?php foreach (lines($service['requirements']) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
        </ul>
        <p class="small" style="margin-top:12px"><strong>Fee:</strong> <?= e($service['fee']) ?></p>
      </div>
      <p class="muted small">Booking reserves a place within the centre's planned capacity. It is not a guarantee of zero waiting time.</p>
    </aside>
  </div>
<?php endif;
render_footer();
