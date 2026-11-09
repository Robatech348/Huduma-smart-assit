<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('action') === 'delete' && $id) {
        q('DELETE FROM faqs WHERE id = ?', [$id]);
        flash('success', 'FAQ deleted.');
    } else {
        $question = mb_substr(input('question'), 0, 255);
        $answer = input('answer');
        $keywords = mb_substr(input('keywords'), 0, 255);
        $active = !empty($_POST['active']) ? 1 : 0;
        if ($question === '' || $answer === '') {
            flash('error', 'Question and answer are required.');
        } elseif ($id) {
            q('UPDATE faqs SET question=?, answer=?, keywords=?, active=? WHERE id=?', [$question, $answer, $keywords, $active, $id]);
            flash('success', 'FAQ updated.');
        } else {
            q('INSERT INTO faqs (question, answer, keywords, active) VALUES (?,?,?,?)', [$question, $answer, $keywords, $active]);
            flash('success', 'FAQ added.');
        }
    }
    audit('faq_saved', (string)$id);
    redirect('staff/faqs.php');
}

$faqs = q_all('SELECT * FROM faqs ORDER BY id');
render_header('FAQs', 'staff');
?>
<h1>Frequently asked questions</h1>
<p class="muted">General answers SmartAssist can give (opening hours, booking, privacy, etc.).</p>

<?php foreach (array_merge($faqs, [['id' => 0, 'question' => '', 'answer' => '', 'keywords' => '', 'active' => 1]]) as $f): ?>
  <details class="card" <?= $f['id'] ? '' : 'open' ?>>
    <summary><strong><?= $f['id'] ? e($f['question']) : '+ Add a new FAQ' ?></strong><?= $f['id'] && !$f['active'] ? ' <span class="badge badge-grey">Hidden</span>' : '' ?></summary>
    <form method="post" class="form" style="margin-top:12px">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <label>Question<input type="text" name="question" value="<?= e($f['question']) ?>" maxlength="255" required></label>
      <label>Answer<textarea name="answer" required><?= e($f['answer']) ?></textarea></label>
      <label>Keywords<input type="text" name="keywords" value="<?= e($f['keywords']) ?>" maxlength="255"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= $f['active'] ? 'checked' : '' ?>> Active</label>
      <div class="row">
        <button class="btn btn-primary" type="submit">Save</button>
        <?php if ($f['id']): ?><button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Delete this FAQ?')">Delete</button><?php endif; ?>
      </div>
    </form>
  </details>
<?php endforeach;
render_footer('staff');
