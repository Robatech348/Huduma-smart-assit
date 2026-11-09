<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$next = safe_next(input('next') ?: null);
if (current_citizen()) {
    redirect_after_citizen_auth($next);
}

$errors = [];
if (is_post()) {
    csrf_check();
    if (rate_limited('register', 5, 3600)) {
        $errors['general'] = 'Too many sign-ups from this browser. Please try again later.';
    } else {
        [$id, $errors] = register_citizen($_POST);
        if ($id) {
            citizen_sign_in($id);
            flash('success', 'Your account is ready. You can now book appointments.');
            redirect_after_citizen_auth($next);
        }
    }
}

render_header('Create an account');
?>
<div class="narrow auth-box">
  <h1>Create an account <span class="footer-sw">· Jisajili</span></h1>
  <p class="muted">An account lets you book appointments and see all your bookings and requests in one place.</p>
  <?php if (isset($errors['general'])): ?><div class="alert alert-error" role="alert"><?= e($errors['general']) ?></div><?php endif; ?>
  <form method="post" class="card form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
    <label>Full name<input type="text" name="full_name" value="<?= old('full_name') ?>" required maxlength="120" autocomplete="name"></label>
    <?= field_error($errors, 'full_name') ?>
    <label>Email <span class="hint">Your booking confirmations and reminders are sent here.</span><input type="email" name="email" value="<?= old('email') ?>" required maxlength="160" autocomplete="email"></label>
    <?= field_error($errors, 'email') ?>
    <label>Phone (optional)<input type="tel" name="phone" value="<?= old('phone') ?>" autocomplete="tel" placeholder="0712 345 678"></label>
    <?= field_error($errors, 'phone') ?>
    <label>Password <span class="hint">At least <?= CITIZEN_MIN_PASSWORD ?> characters.</span><input type="password" name="password" required minlength="<?= CITIZEN_MIN_PASSWORD ?>" autocomplete="new-password"></label>
    <?= field_error($errors, 'password') ?>
    <label>Confirm password<input type="password" name="password_confirm" required autocomplete="new-password"></label>
    <?= field_error($errors, 'password_confirm') ?>
    <label class="check"><input type="checkbox" name="consent" value="1" <?= !empty($_POST['consent']) ? 'checked' : '' ?>> I agree that my name and contact details are used only to manage my appointments, as described in the <a href="<?= e(url('privacy.php')) ?>" target="_blank">privacy notice</a>.</label>
    <?= field_error($errors, 'consent') ?>
    <p class="muted small">Do not enter your ID number. Staff verify documents in person.</p>
    <button class="btn btn-primary" type="submit">Create account</button>
  </form>
  <p>Already have an account? <a href="<?= e(url('account/login.php', array_filter(['next' => $next]))) ?>">Sign in</a></p>
</div>
<?php render_footer();
