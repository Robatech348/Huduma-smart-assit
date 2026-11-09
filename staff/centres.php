<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = input_int('id');
    $d = [
        mb_substr(input('name'), 0, 120), mb_substr(input('county'), 0, 80), mb_substr(input('address'), 0, 255),
        mb_substr(input('phone'), 0, 40) ?: null, mb_substr(input('email'), 0, 160) ?: null,
        mb_substr(input('opening_hours'), 0, 160) ?: 'Mon-Fri 8:00am - 5:00pm', !empty($_POST['active']) ? 1 : 0,
    ];
    if ($d[0] === '' || $d[1] === '' || $d[2] === '') {
        flash('error', 'Name, county and address are required.');
    } elseif ($d[4] && !valid_email($d[4])) {
        flash('error', 'Enter a valid email.');
    } else {
        if ($id) {
            q('UPDATE centres SET name=?, county=?, address=?, phone=?, email=?, opening_hours=?, active=? WHERE id=?', [...$d, $id]);
        } else {
            q('INSERT INTO centres (name, county, address, phone, email, opening_hours, active) VALUES (?,?,?,?,?,?,?)', $d);
        }
        audit('centre_saved', $d[0]);
        flash('success', 'Centre saved.' . ($id ? '' : ' Next: add its services (Services page) and publish slots (Capacity page).'));
    }
    redirect('staff/centres.php');
}

$centres = q_all(
    'SELECT c.*, (SELECT COUNT(*) FROM centre_services cs WHERE cs.centre_id = c.id) AS services,
            (SELECT COUNT(*) FROM users u WHERE u.centre_id = c.id AND u.active = 1) AS staff
       FROM centres c ORDER BY c.name'
);
render_header('Centres', 'staff');
?>
<h1>Huduma Centres</h1>
<?php foreach (array_merge($centres, [['id' => 0, 'name' => '', 'county' => '', 'address' => '', 'phone' => '', 'email' => '', 'opening_hours' => 'Mon-Fri 8:00am - 5:00pm', 'active' => 1, 'services' => 0, 'staff' => 0]]) as $c): ?>
  <details class="card" <?= $c['id'] ? '' : 'open' ?>>
    <summary><strong><?= $c['id'] ? e($c['name']) : '+ Add a centre' ?></strong>
      <?php if ($c['id']): ?><span class="muted small"> · <?= e($c['county']) ?> · <?= (int)$c['services'] ?> services · <?= (int)$c['staff'] ?> staff</span><?= $c['active'] ? '' : ' <span class="badge badge-grey">Inactive</span>' ?><?php endif; ?></summary>
    <form method="post" class="form" style="margin-top:12px">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <div class="grid grid-3">
        <label>Name<input type="text" name="name" value="<?= e($c['name']) ?>" required></label>
        <label>County<input type="text" name="county" value="<?= e($c['county']) ?>" required></label>
        <label>Opening hours<input type="text" name="opening_hours" value="<?= e($c['opening_hours']) ?>"></label>
        <label>Phone<input type="text" name="phone" value="<?= e($c['phone']) ?>"></label>
        <label>Email<input type="email" name="email" value="<?= e($c['email']) ?>"></label>
      </div>
      <label>Address<input type="text" name="address" value="<?= e($c['address']) ?>" required></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?>> Active (accepting bookings)</label>
      <div><button class="btn btn-primary" type="submit">Save centre</button></div>
    </form>
  </details>
<?php endforeach;
render_footer('staff');
