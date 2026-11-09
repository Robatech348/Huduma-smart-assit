<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_staff();

$centres = centres_in_scope();
$errors = [];

// Prefill from an appointment
$prefill = [];
$fromAppointment = input_int('from_appointment');
if ($fromAppointment) {
    $ap = q_one('SELECT * FROM appointments WHERE id = ?', [$fromAppointment]);
    if ($ap && can_access_centre((int)$ap['centre_id'])) {
        $prefill = $ap;
    } else {
        $fromAppointment = 0;
    }
}

if (is_post()) {
    csrf_check();
    $centreId = input_int('centre_id');
    $serviceId = input_int('service_id');
    [$data, $errors] = validate_booking_details($_POST, false);
    if (!can_access_centre($centreId) || !q_val('SELECT 1 FROM centres WHERE id = ?', [$centreId])) {
        $errors['centre_id'] = 'Choose a valid centre.';
    }
    if (!q_val('SELECT 1 FROM services WHERE id = ?', [$serviceId])) {
        $errors['service_id'] = 'Choose a service.';
    }
    if ($data['email'] === '' && $data['phone'] === '') {
        $errors['email'] = 'Give an email (for updates) or at least a phone number (for tracking).';
    }
    $apptId = input_int('appointment_id') ?: null;
    if ($apptId && q_val('SELECT 1 FROM service_requests WHERE appointment_id = ?', [$apptId])) {
        $errors['general'] = 'A request already exists for this appointment.';
    }
    if (!$errors) {
        $ref = make_reference('REQ', 'service_requests');
        // Link to the citizen's account when the request comes from their online booking.
        $citizenId = $apptId ? q_val('SELECT citizen_id FROM appointments WHERE id = ?', [$apptId]) : null;
        q('INSERT INTO service_requests (reference, track_token, appointment_id, centre_id, service_id, full_name, email, phone, created_by, citizen_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            $ref, make_token(), $apptId, $centreId, $serviceId, $data['full_name'], $data['email'] ?: null, $data['phone'] ?: null, $user['id'], $citizenId ?: null,
        ]);
        $rid = (int)db()->lastInsertId();
        $note = mb_substr(input('public_note'), 0, 2000);
        q('INSERT INTO request_updates (request_id, status, public_note, staff_id) VALUES (?,?,?,?)', [$rid, 'submitted', $note ?: null, $user['id']]);
        notify_request_status($rid, $note);
        audit('request_created', $ref);
        flash('success', "Request {$ref} created." . ($data['email'] ? ' The citizen has been emailed a tracking link.' : ' Give the citizen this reference to track it.'));
        redirect('staff/request.php', ['id' => $rid]);
    }
    $prefill = $_POST;
}

// List
$status = input('status');
$search = input('search');
[$scope, $params] = scope_sql('r.centre_id');
$where = [$scope];
if ($status === 'open') {
    $where[] = "r.status NOT IN ('completed','rejected')";
} elseif (isset(REQUEST_STATUSES[$status])) {
    $where[] = 'r.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[] = '(r.reference LIKE ? OR r.full_name LIKE ? OR r.email LIKE ? OR r.phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$page = max(1, input_int('page', 1));
$total = (int)q_val('SELECT COUNT(*) FROM service_requests r WHERE ' . implode(' AND ', $where), $params);
$pages = (int)ceil($total / 25);
$rows = q_all(
    'SELECT r.*, sv.name AS service_name, c.name AS centre_name FROM service_requests r
       JOIN services sv ON sv.id = r.service_id JOIN centres c ON c.id = r.centre_id
      WHERE ' . implode(' AND ', $where) . ' ORDER BY r.updated_at DESC LIMIT 25 OFFSET ' . (($page - 1) * 25),
    $params
);
$allServices = active_services();

render_header('Service requests', 'staff');
?>
<h1>Service requests</h1>

<details class="card" <?= ($fromAppointment || $errors) ? 'open' : '' ?>>
  <summary><strong>+ New service request</strong> <span class="muted small">— e.g. an application lodged at the desk that the citizen will track</span></summary>
  <form method="post" class="form" style="margin-top:14px">
    <?= csrf_field() ?>
    <?php if (isset($errors['general'])): ?><div class="alert alert-error"><?= e($errors['general']) ?></div><?php endif; ?>
    <?php if ($fromAppointment): ?><input type="hidden" name="appointment_id" value="<?= $fromAppointment ?>">
      <p class="small">Linked to appointment <strong><?= e($prefill['reference'] ?? '') ?></strong></p><?php endif; ?>
    <div class="grid grid-2">
      <label>Centre
        <select name="centre_id" required>
          <?php foreach ($centres as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($prefill['centre_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select><?= field_error($errors, 'centre_id') ?>
      </label>
      <label>Service
        <select name="service_id" required>
          <option value="">Choose…</option>
          <?php foreach ($allServices as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($prefill['service_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select><?= field_error($errors, 'service_id') ?>
      </label>
      <label>Citizen full name<input type="text" name="full_name" value="<?= e($prefill['full_name'] ?? '') ?>" required></label>
      <label>Email (for status emails)<input type="email" name="email" value="<?= e($prefill['email'] ?? '') ?>"></label>
      <label>Phone<input type="tel" name="phone" value="<?= e($prefill['phone'] ?? '') ?>"></label>
    </div>
    <?= field_error($errors, 'full_name') ?><?= field_error($errors, 'email') ?><?= field_error($errors, 'phone') ?>
    <label>Message to citizen (optional, visible to them)<textarea name="public_note" maxlength="2000" placeholder="e.g. Your application has been lodged. Expected processing: 3 weeks."><?= e($_POST['public_note'] ?? '') ?></textarea></label>
    <button class="btn btn-primary" type="submit">Create request</button>
  </form>
</details>

<form class="card form-inline" method="get">
  <label>Status
    <select name="status" data-autosubmit>
      <option value="">All</option>
      <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open (not completed)</option>
      <?php foreach (REQUEST_STATUSES as $k => [$label]): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Search<input type="search" name="search" value="<?= e($search) ?>" placeholder="Reference, name, email, phone"></label>
  <button class="btn" type="submit">Filter</button>
</form>

<div class="card">
  <p class="muted small"><?= $total ?> request(s)</p>
  <?php if (!$rows): ?><p class="empty">No requests found.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Reference</th><th>Citizen</th><th>Service</th><th>Status</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= e(url('staff/request.php', ['id' => $r['id']])) ?>"><strong><?= e($r['reference']) ?></strong></a></td>
        <td><?= e($r['full_name']) ?><br><span class="small muted"><?= e($r['email'] ?: $r['phone']) ?></span></td>
        <td><?= e($r['service_name']) ?><?php if (staff_centre_scope() === null): ?><br><span class="small muted"><?= e($r['centre_name']) ?></span><?php endif; ?></td>
        <td><?= status_badge($r['status'], REQUEST_STATUSES) ?></td>
        <td class="small"><?= e(fmt_datetime($r['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginate_links($page, $pages, array_filter(['status' => $status, 'search' => $search])) ?>
  <?php endif; ?>
</div>
<?php render_footer('staff');
