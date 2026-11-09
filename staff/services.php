<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$centres = q_all('SELECT * FROM centres ORDER BY name');
$editId = input_int('edit');
$fields = ['name', 'agency', 'category', 'summary', 'requirements', 'steps', 'fee', 'processing_time', 'keywords'];
$errors = [];

if (is_post()) {
    csrf_check();
    $id = input_int('id');
    $d = [];
    foreach ($fields as $f) {
        $d[$f] = trim((string)($_POST[$f] ?? ''));
    }
    foreach (['name', 'agency', 'category', 'summary', 'requirements', 'steps'] as $req) {
        if ($d[$req] === '') {
            $errors[$req] = 'Required.';
        }
    }
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(input('slug') ?: $d['name'])), '-');
    if ($slug === '' || q_val('SELECT 1 FROM services WHERE slug = ? AND id <> ?', [$slug, $id])) {
        $errors['slug'] = 'Slug must be unique.';
    }
    $applyUrl = trim(input('apply_url'));
    if ($applyUrl !== '' && !preg_match('#^https://[a-z0-9.-]+\.[a-z]{2,}(/\S*)?$#i', $applyUrl)) {
        $errors['apply_url'] = 'Use a full https:// link to the official portal.';
    }
    $bookable = !empty($_POST['bookable']) ? 1 : 0;
    $active = !empty($_POST['active']) ? 1 : 0;
    $centreIds = array_map('intval', (array)($_POST['centres'] ?? []));

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        if ($id) {
            q('UPDATE services SET slug=?, name=?, agency=?, category=?, summary=?, requirements=?, steps=?, fee=?, processing_time=?, keywords=?, bookable=?, apply_url=?, active=? WHERE id=?',
                [$slug, $d['name'], $d['agency'], $d['category'], $d['summary'], $d['requirements'], $d['steps'], $d['fee'] ?: 'Free', $d['processing_time'] ?: 'Varies', $d['keywords'], $bookable, $applyUrl ?: null, $active, $id]);
        } else {
            q('INSERT INTO services (slug, name, agency, category, summary, requirements, steps, fee, processing_time, keywords, bookable, apply_url, active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$slug, $d['name'], $d['agency'], $d['category'], $d['summary'], $d['requirements'], $d['steps'], $d['fee'] ?: 'Free', $d['processing_time'] ?: 'Varies', $d['keywords'], $bookable, $applyUrl ?: null, $active]);
            $id = (int)$pdo->lastInsertId();
        }
        q('DELETE FROM centre_services WHERE service_id = ?', [$id]);
        foreach ($centreIds as $cid) {
            q('INSERT IGNORE INTO centre_services (centre_id, service_id) VALUES (?,?)', [$cid, $id]);
        }
        $pdo->commit();
        audit('service_saved', $d['name']);
        flash('success', 'Service saved. SmartAssist answers now use the updated information.');
        redirect('staff/services.php');
    }
    $editId = $id ?: -1;
}

$service = null;
$offeredAt = [];
if ($editId > 0) {
    $service = q_one('SELECT * FROM services WHERE id = ?', [$editId]);
    $offeredAt = array_map('intval', array_column(q_all('SELECT centre_id FROM centre_services WHERE service_id = ?', [$editId]), 'centre_id'));
}
if (is_post()) {
    $service = array_merge($service ?? [], $_POST);
    $offeredAt = array_map('intval', (array)($_POST['centres'] ?? []));
}
$list = q_all('SELECT s.*, (SELECT COUNT(*) FROM centre_services cs WHERE cs.service_id = s.id) AS centres FROM services s ORDER BY s.category, s.name');

render_header('Services', 'staff');
?>
<div class="spread">
  <h1>Service catalogue</h1>
  <a class="btn btn-primary" href="<?= e(url('staff/services.php', ['edit' => -1])) ?>">+ New service</a>
</div>
<p class="muted">This is the approved content shown to citizens and used by SmartAssist. Keep it accurate and confirmed by each agency.</p>

<?php if ($editId): ?>
<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($service['id'] ?? 0) ?>">
  <h2><?= !empty($service['id']) ? 'Edit service' : 'New service' ?></h2>
  <div class="grid grid-2">
    <label>Name<input type="text" name="name" value="<?= e($service['name'] ?? '') ?>" required><?= field_error($errors, 'name') ?></label>
    <label>Slug <span class="hint">used in links</span><input type="text" name="slug" value="<?= e($service['slug'] ?? '') ?>"><?= field_error($errors, 'slug') ?></label>
    <label>Agency<input type="text" name="agency" value="<?= e($service['agency'] ?? '') ?>" required><?= field_error($errors, 'agency') ?></label>
    <label>Category<input type="text" name="category" value="<?= e($service['category'] ?? '') ?>" required list="cats"><?= field_error($errors, 'category') ?></label>
    <label>Fee<input type="text" name="fee" value="<?= e($service['fee'] ?? '') ?>"></label>
    <label>Processing time<input type="text" name="processing_time" value="<?= e($service['processing_time'] ?? '') ?>"></label>
  </div>
  <datalist id="cats"><?php foreach (array_unique(array_column($list, 'category')) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
  <label>Summary<textarea name="summary" required><?= e($service['summary'] ?? '') ?></textarea><?= field_error($errors, 'summary') ?></label>
  <div class="grid grid-2">
    <label>Requirements <span class="hint">one per line</span><textarea name="requirements" rows="6" required><?= e($service['requirements'] ?? '') ?></textarea><?= field_error($errors, 'requirements') ?></label>
    <label>Steps <span class="hint">one per line</span><textarea name="steps" rows="6" required><?= e($service['steps'] ?? '') ?></textarea><?= field_error($errors, 'steps') ?></label>
  </div>
  <label>Search keywords <span class="hint">synonyms and Kiswahili terms citizens might use</span><input type="text" name="keywords" value="<?= e($service['keywords'] ?? '') ?>"></label>
  <label>Apply online link <span class="hint">official portal, e.g. https://www.ecitizen.go.ke (leave blank if in-person only)</span><input type="url" name="apply_url" value="<?= e($_POST['apply_url'] ?? ($service['apply_url'] ?? '')) ?>" placeholder="https://"><?= field_error($errors, 'apply_url') ?></label>
  <fieldset><legend>Offered at</legend>
    <div class="grid grid-3">
      <?php foreach ($centres as $c): ?><label class="check"><input type="checkbox" name="centres[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $offeredAt, true) ? 'checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach; ?>
    </div>
  </fieldset>
  <div class="row">
    <label class="check"><input type="checkbox" name="bookable" value="1" <?= ($service['bookable'] ?? 1) ? 'checked' : '' ?>> Bookable online</label>
    <label class="check"><input type="checkbox" name="active" value="1" <?= ($service['active'] ?? 1) ? 'checked' : '' ?>> Active (visible to citizens)</label>
  </div>
  <div class="row"><button class="btn btn-primary" type="submit">Save service</button><a class="btn" href="<?= e(url('staff/services.php')) ?>">Cancel</a></div>
</form>
<?php endif; ?>

<div class="card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Service</th><th>Category</th><th>Centres</th><th>Status</th><th>Updated</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $s): ?>
      <tr>
        <td><strong><?= e($s['name']) ?></strong><br><span class="small muted"><?= e($s['agency']) ?></span></td>
        <td><?= e($s['category']) ?></td>
        <td><?= (int)$s['centres'] ?></td>
        <td><?= $s['active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-grey">Hidden</span>' ?> <?= $s['bookable'] ? '<span class="badge badge-blue">Bookable</span>' : '' ?></td>
        <td class="small"><?= e(fmt_date($s['updated_at'])) ?></td>
        <td><a class="btn btn-sm" href="<?= e(url('staff/services.php', ['edit' => $s['id']])) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php render_footer('staff');
