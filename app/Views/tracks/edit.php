<?php $title = $track ? 'Edit Track' : 'New Track'; ob_start(); ?>
<h2><?= $title ?></h2>
<form method="post" action="<?= BASE_URL ?>/tracks/<?= $track['id'] ?? '' ?><?= $track ? '/edit' : '/create' ?>">
  <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
  <div class="card p-3">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= htmlspecialchars($track['name'] ?? '') ?>" required></div>
      <div class="col-md-3"><label class="form-label">Salary Floor</label><input type="number" class="form-control" name="salary_floor" value="<?= (int)($track['salary_floor'] ?? 0) ?>"></div>
      <div class="col-md-3 d-flex align-items-end"><div class="form-check me-3"><input type="checkbox" class="form-check-input" name="is_active" <?= ($track['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label">Active</label></div><div class="form-check"><input type="checkbox" class="form-check-input" name="remote_ok" <?= ($track['remote_ok'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label">Remote OK</label></div></div>
      <div class="col-12"><label class="form-label">Role keywords (comma-separated)</label><textarea class="form-control" name="role_keywords" rows="2"><?= htmlspecialchars($track['role_keywords'] ?? '') ?></textarea></div>
      <div class="col-12"><label class="form-label">Exclude keywords</label><textarea class="form-control" name="exclude_keywords" rows="2"><?= htmlspecialchars($track['exclude_keywords'] ?? '') ?></textarea></div>
      <div class="col-md-6"><label class="form-label">Locations</label><input class="form-control" name="locations" value="<?= htmlspecialchars($track['locations'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">Resume template (path)</label><input class="form-control" name="resume_template" value="<?= htmlspecialchars($track['resume_template'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">Cover letter tone</label><input class="form-control" name="cover_letter_tone" value="<?= htmlspecialchars($track['cover_letter_tone'] ?? 'professional') ?>"></div>
      <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($track['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= BASE_URL ?>/tracks">Cancel</a></div>
  </div>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
