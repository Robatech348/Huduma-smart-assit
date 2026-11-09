<?php
require __DIR__ . '/includes/bootstrap.php';

$token = input('token');
$a = null;
if (preg_match('/^[a-f0-9]{40}$/', $token)) {
    $id = q_val('SELECT id FROM appointments WHERE manage_token = ?', [$token]);
    $a = $id ? appointment_details((int)$id) : null;
}

$errors = [];
$done = $a && q_val('SELECT 1 FROM feedback WHERE appointment_id = ?', [$a['id']]);

if ($a && !$done && $a['status'] === 'served' && is_post()) {
    csrf_check();
    $rating = input_int('rating');
    $prepared = input('prepared');
    $comment = mb_substr(input('comment'), 0, 2000);
    if ($rating < 1 || $rating > 5) {
        $errors['rating'] = 'Choose a rating from 1 to 5 stars.';
    }
    if (!$errors) {
        q('INSERT IGNORE INTO feedback (appointment_id, rating, prepared, comment) VALUES (?,?,?,?)', [
            $a['id'], $rating, $prepared === '' ? null : ($prepared === '1' ? 1 : 0), $comment !== '' ? $comment : null,
        ]);
        flash('success', 'Thank you! Your feedback has been recorded.');
        redirect('feedback.php', ['token' => $token]);
    }
}

render_header('Rate your visit');
?>
<div class="narrow">
  <h1>Rate your visit</h1>
  <?php if (!$a): ?>
    <div class="alert alert-error">This feedback link is not valid. Please use the link in your email.</div>
  <?php elseif ($done): ?>
    <div class="card accent"><h2>Thank you</h2><p>We have received your feedback for <?= e($a['reference']) ?>.</p>
      <a class="btn" href="<?= e(url('index.php')) ?>">Back to home</a></div>
  <?php elseif ($a['status'] !== 'served'): ?>
    <div class="alert alert-info">You can rate your visit after you have been served.</div>
  <?php else: ?>
    <p class="muted"><?= e($a['service_name']) ?> at <?= e($a['centre_name']) ?> on <?= e(fmt_date($a['slot_date'])) ?></p>
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <fieldset>
        <legend>Overall, how was your visit?</legend>
        <div class="rating">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" id="r<?= $i ?>" name="rating" value="<?= $i ?>" <?= input_int('rating') === $i ? 'checked' : '' ?>>
            <label for="r<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★<span class="skip"><?= $i ?> stars</span></label>
          <?php endfor; ?>
        </div>
        <?= field_error($errors, 'rating') ?>
      </fieldset>
      <fieldset>
        <legend>Did you have everything you needed when you arrived?</legend>
        <label class="check"><input type="radio" name="prepared" value="1"> Yes</label>
        <label class="check"><input type="radio" name="prepared" value="0"> No, something was missing or unclear</label>
      </fieldset>
      <label>Anything we should improve? (optional)<textarea name="comment" maxlength="2000"><?= old('comment') ?></textarea></label>
      <p class="muted small">Please do not include personal identification numbers.</p>
      <button class="btn btn-primary" type="submit">Send feedback</button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer();
