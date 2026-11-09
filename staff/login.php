<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (current_user()) {
    redirect('staff/index.php');
}

$error = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(input('email'));
    if (too_many_login_attempts($email)) {
        $error = 'Too many failed attempts. Wait 15 minutes and try again.';
    } elseif (attempt_login($email, (string)($_POST['password'] ?? ''))) {
        audit('login');
        $intended = $_SESSION['intended'] ?? null;
        unset($_SESSION['intended']);
        if ($intended && str_starts_with($intended, base_path() . '/staff/') && !str_contains($intended, '//')) {
            header('Location: ' . $intended);
            exit;
        }
        redirect('staff/index.php');
    } else {
        $error = 'Email or password is incorrect.';
    }
}

render_header('Staff sign in', 'staff');
?>
<div class="narrow" style="max-width:420px">
  <h1>Staff sign in</h1>
  <p class="muted">For authorised Huduma Centre staff only.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" value="<?= old('email') ?>" required autocomplete="username" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary" type="submit">Sign in</button>
  </form>
</div>
<?php render_footer('public');
