<?php $title='Job Tracks'; ob_start(); ?>
<div class="d-flex justify-content-between mb-3"><h2>Job Tracks</h2><a class="btn btn-primary" href="<?= BASE_URL ?>/tracks/create">+ New Track</a></div>
<div class="card p-3"><table class="table">
<thead><tr><th>Name</th><th>Salary Floor</th><th>Locations</th><th>Remote</th><th>Active</th><th></th></tr></thead>
<tbody>
<?php foreach ($tracks as $t): ?>
<tr>
  <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
  <td>$<?= number_format((int)$t['salary_floor']) ?>+</td>
  <td><?= htmlspecialchars($t['locations']) ?></td>
  <td><?= $t['remote_ok']?'Y':'N' ?></td>
  <td><?= $t['is_active']?'Y':'N' ?></td>
  <td><a href="<?= BASE_URL ?>/tracks/<?= $t['id'] ?>/edit">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
