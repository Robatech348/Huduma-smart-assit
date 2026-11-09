<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$centres = q_all('SELECT id, name FROM centres ORDER BY name');

if (is_post()) {
    csrf_check();
    $id = input_int('id');
    $name = mb_substr(input('name'), 0, 120);
    $email = strtolower(input('email'));
    $role = input('role') === 'admin' ? 'admin' : 'staff';
    $centreId = input_int('centre_id') ?: null;
    $active = !empty($_POST['active']) ? 1 : 0;
    $password = (string)($_POST['password'] ?? '');

    $err = '';
    if ($name === '' || !valid_email($email)) {
        $err = 'Enter a name and valid email.';
    } elseif (q_val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $id])) {
        $err = 'That email is already used.';
    } elseif ($role === 'staff' && !$centreId) {
        $err = 'Staff accounts must be assigned to a centre.';
    } elseif (!$id && strlen($password) < 10) {
        $err = 'New accounts need a password of at least 10 characters.';
    } elseif ($id && $password !== '' && strlen($password) < 10) {
        $err = 'Password must be at least 10 characters.';
    } elseif ($id === (int)$me['id'] && (!$active || $role !== 'admin')) {
        $err = 'You cannot deactivate or demote your own account.';
    }

    if ($err) {
        flash('error', $err);
    } else {
        if ($id) {
            q('UPDATE users SET name=?, email=?, role=?, centre_id=?, active=? WHERE id=?', [$name, $email, $role, $centreId, $active, $id]);
            if ($password !== '') {
                q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
        } else {
            q('INSERT INTO users (name, email, password_hash, role, centre_id, active) VALUES (?,?,?,?,?,?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $centreId, $active]);
        }
        audit('user_saved', $email);
        flash('success', 'User saved.');
    }
    redirect('staff/users.php');
}

$users = q_all('SELECT u.*, c.name AS centre_name FROM users u LEFT JOIN centres c ON c.id = u.centre_id ORDER BY u.active DESC, u.name');
render_header('Users', 'staff');
?>
<h1>Staff accounts</h1>
<p class="muted">Staff see and manage only their own centre. Administrators manage all centres and content.</p>

<?php foreach (array_merge($users, [['id' => 0, 'name' => '', 'email' => '', 'role' => 'staff', 'centre_id' => null, 'active' => 1, 'centre_name' => null, 'last_login_at' => null]]) as $u): ?>
  <details class="card" <?= $u['id'] ? '' : 'open' ?>>
    <summary><strong><?= $u['id'] ? e($u['name']) : '+ Add a user' ?></strong>
      <?php if ($u['id']): ?><span class="muted small"> · <?= e($u['email']) ?> · <?= e(ucfirst($u['role'])) ?><?= $u['centre_name'] ? ' · ' . e($u['centre_name']) : '' ?> · last sign-in <?= e(fmt_datetime($u['last_login_at'])) ?></span><?= $u['active'] ? '' : ' <span class="badge badge-grey">Inactive</span>' ?><?php endif; ?></summary>
    <form method="post" class="form" style="margin-top:12px" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
      <div class="grid grid-3">
        <label>Name<input type="text" name="name" value="<?= e($u['name']) ?>" required></label>
        <label>Email<input type="email" name="email" value="<?= e($u['email']) ?>" required></label>
        <label>Role<select name="role"><option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Centre staff</option><option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option></select></label>
        <label>Centre<select name="centre_id"><option value="">— none (admins) —</option>
          <?php foreach ($centres as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$u['centre_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        <label><?= $u['id'] ? 'New password (leave blank to keep)' : 'Password' ?><input type="password" name="password" minlength="10" autocomplete="new-password" <?= $u['id'] ? '' : 'required' ?>></label>
      </div>
      <label class="check"><input type="checkbox" name="active" value="1" <?= $u['active'] ? 'checked' : '' ?>> Active</label>
      <div><button class="btn btn-primary" type="submit">Save user</button></div>
    </form>
  </details>
<?php endforeach;
render_footer('staff');
