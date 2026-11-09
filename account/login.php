<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$next = safe_next(input('next') ?: null);
if (current_citizen()) {
    redirect_after_citizen_auth($next);
}

$error = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(trim(input('email')));
    if (too_many_login_attempts($email)) {
        $error = 'Too many failed attempts. Wait 15 minutes and try again.';
    } elseif (attempt_citizen_login($email, (string)($_POST['password'] ?? ''))) {
        flash('success', 'Welcome back, ' . current_citizen()['full_name'] . '.');
        redirect_after_citizen_auth($next);
    } else {
        $error = 'Email or password is incorrect.';
    }
}

render_header('Sign in');
?>
<div class="narrow auth-box">
  <h1>Sign in <span class="footer-sw">· Ingia</span></h1>
  <p class="muted">Sign in to book and manage your Huduma Centre appointments. Browsing services and asking SmartAssist do not need an account.</p>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
    <label>Email<input type="email" name="email" value="<?= old('email') ?>" required autocomplete="username" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary" type="submit">Sign in</button>
  </form>
  <div class="card">
    <strong>First time here?</strong>
    <p class="muted small">Create a free account in under a minute. You only need your name, an email address and a password.</p>
    <a class="btn" href="<?= e(url('account/register.php', array_filter(['next' => $next]))) ?>">Create an account</a>
  </div>
  <p class="muted small">No email or smartphone? Visit any Huduma Centre and an officer will book for you without an account.</p>
</div>
<?php render_footer();
