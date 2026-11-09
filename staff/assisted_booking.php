<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_staff();

$centres = centres_in_scope();
$centreId = input_int('centre') ?: (int)($centres[0]['id'] ?? 0);
if (!can_access_centre($centreId)) {
    $centreId = (int)($centres[0]['id'] ?? 0);
}
$serviceId = input_int('service');
$services = $centreId ? q_all(
    'SELECT s.* FROM services s JOIN centre_services cs ON cs.service_id = s.id WHERE cs.centre_id = ? AND s.active = 1 AND s.bookable = 1 ORDER BY s.name',
    [$centreId]
) : [];

$errors = [];
if (is_post()) {
    csrf_check();
    [$data, $errors] = validate_booking_details($_POST, false);
    $slotId = input_int('slot_id');
    if (!$serviceId) {
        $errors['service'] = 'Choose a service.';
    }
    if (!$slotId) {
        $errors['slot_id'] = 'Choose a time slot.';
    }
    if (!$errors) {
        try {
            $slot = q_one('SELECT centre_id FROM slots WHERE id = ?', [$slotId]);
            if (!$slot || !can_access_centre((int)$slot['centre_id']) || (int)$slot['centre_id'] !== $centreId) {
                throw new BookingException('Invalid slot for this centre.');
            }
            $id = book_appointment($slotId, $serviceId, $data, 'assisted', (int)$user['id']);
            $a = appointment_details($id);
            audit('assisted_booking', $a['reference']);
            if (!empty($_POST['check_in_now'])) {
                q("UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = ?", [$id]);
            }
            flash('success', "Booked {$a['reference']} for {$a['full_name']} at " . fmt_time($a['start_time']) . ', ' . fmt_date($a['slot_date']) . '. Give the citizen this reference.');
            redirect('staff/appointments.php', ['date' => $a['slot_date']]);
        } catch (BookingException $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}

// Assisted bookings may use today's remaining slots (up to start time).
$slotsByDay = [];
if ($centreId) {
    $rows = q_all(
        "SELECT * FROM slots WHERE centre_id = ? AND slot_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 14 DAY
            AND status = 'open' AND booked < capacity AND TIMESTAMP(slot_date, start_time) > NOW()
          ORDER BY slot_date, start_time",
        [$centreId]
    );
    foreach ($rows as $r) {
        $slotsByDay[$r['slot_date']][] = $r;
    }
}

render_header('Assisted booking', 'staff');
?>
<h1>Assisted booking</h1>
<p class="muted">Book on behalf of a walk-in visitor or a caller without internet access. Email is optional.</p>

<form class="card form-inline" method="get">
  <?php if (count($centres) > 1): ?>
    <label>Centre
      <select name="centre" data-autosubmit>
        <?php foreach ($centres as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $centreId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <label>Service
    <select name="service" data-autosubmit>
      <option value="">Choose…</option>
      <?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $serviceId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="btn" type="submit">Continue</button></noscript>
</form>

<?php if (!$centreId): ?>
  <div class="alert alert-warning">Your account is not assigned to a centre. Ask an administrator.</div>
<?php elseif ($serviceId): ?>
<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="centre" value="<?= $centreId ?>"><input type="hidden" name="service" value="<?= $serviceId ?>">
  <?php if (isset($errors['general'])): ?><div class="alert alert-error"><?= e($errors['general']) ?></div><?php endif; ?>
  <fieldset>
    <legend>Time slot</legend>
    <?= field_error($errors, 'slot_id') ?>
    <?php if (!$slotsByDay): ?><p class="muted">No open slots. Serve as a walk-in or add capacity.</p><?php endif; ?>
    <?php foreach (array_slice($slotsByDay, 0, 5, true) as $date => $slots): ?>
      <div class="slot-day"><h3><?= e(fmt_date($date)) ?><?= $date === date('Y-m-d') ? ' (today)' : '' ?></h3>
        <div class="slot-grid">
          <?php foreach ($slots as $sl): ?>
            <label class="slot"><input type="radio" name="slot_id" value="<?= (int)$sl['id'] ?>" <?= input_int('slot_id') === (int)$sl['id'] ? 'checked' : '' ?>>
              <span><?= e(fmt_time($sl['start_time'])) ?><small><?= (int)$sl['capacity'] - (int)$sl['booked'] ?> left</small></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </fieldset>
  <div class="grid grid-3">
    <label>Full name<input type="text" name="full_name" value="<?= old('full_name') ?>" required></label>
    <label>Email (optional)<input type="email" name="email" value="<?= old('email') ?>"></label>
    <label>Phone (optional)<input type="tel" name="phone" value="<?= old('phone') ?>"></label>
  </div>
  <?= field_error($errors, 'full_name') ?><?= field_error($errors, 'email') ?><?= field_error($errors, 'phone') ?>
  <label class="check"><input type="checkbox" name="needs_assistance" value="1"> Needs extra assistance</label>
  <label class="check"><input type="checkbox" name="check_in_now" value="1"> Visitor is here now — check in immediately</label>
  <p class="muted small">Explain to the citizen how their contact details will be used (see the privacy notice).</p>
  <button class="btn btn-primary" type="submit">Book</button>
</form>
<?php endif;
render_footer('staff');
