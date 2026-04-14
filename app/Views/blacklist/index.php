<?php $title='Blacklist'; ob_start(); ?>
<h2 class="mb-3">Blacklist</h2>
<div class="row">
<div class="col-md-5"><div class="card p-3"><h5>Add</h5>
<form method="post" action="<?= BASE_URL ?>/blacklist/add">
  <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
  <div class="mb-2"><select name="track_id" class="form-select form-select-sm"><option value="">Global (all tracks)</option><?php foreach ($tracks as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?></select></div>
  <div class="mb-2"><select name="type" class="form-select form-select-sm"><option>company</option><option>keyword</option><option>recruiter</option><option>domain</option></select></div>
  <div class="mb-2"><input class="form-control form-control-sm" name="pattern" placeholder="pattern" required></div>
  <div class="mb-2"><input class="form-control form-control-sm" name="reason" placeholder="reason (optional)"></div>
  <button class="btn btn-primary btn-sm">Add</button>
</form></div></div>
<div class="col-md-7"><div class="card p-3"><table class="table table-sm">
<thead><tr><th>Type</th><th>Pattern</th><th>Track</th><th>Reason</th><th></th></tr></thead><tbody>
<?php foreach ($items as $i): ?>
<tr><td><?= htmlspecialchars($i['type']) ?></td><td><?= htmlspecialchars($i['pattern']) ?></td><td><?= $i['track_id'] ?: 'global' ?></td><td><?= htmlspecialchars($i['reason'] ?? '') ?></td>
<td><form method="post" action="<?= BASE_URL ?>/blacklist/<?= $i['id'] ?>/delete"><input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>"><button class="btn btn-sm btn-link text-danger">×</button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
