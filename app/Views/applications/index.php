<?php $title='Applications'; ob_start(); ?>
<h2 class="mb-3">Applications</h2>
<div class="card p-3">
<table class="table table-sm table-hover">
<thead><tr><th>Company</th><th>Title</th><th>Location</th><th>Salary</th><th>Deadline</th><th>Materials</th><th></th></tr></thead>
<tbody>
<?php foreach ($folders as $f): ?>
<tr>
  <td><strong><?= htmlspecialchars($f['company']) ?></strong></td>
  <td><?= htmlspecialchars($f['title']) ?></td>
  <td><?= htmlspecialchars($f['location']) ?></td>
  <td><?= htmlspecialchars($f['salary']) ?></td>
  <td><?= $f['deadline'] ? '<span class="text-danger">' . htmlspecialchars($f['deadline']) . '</span>' : '' ?></td>
  <td>
    <?= $f['hasCover'] ? '<span class="badge bg-success">Cover</span>' : '<span class="badge bg-secondary">No cover</span>' ?>
    <?= $f['hasResume'] ? '<span class="badge bg-success">Resume</span>' : '' ?>
  </td>
  <td><a href="<?= BASE_URL ?>/applications/<?= urlencode($f['slug']) ?>" class="btn btn-sm btn-primary">View</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$folders): ?><tr><td colspan="7" class="text-muted">No applications yet. Save an Indeed page to Downloads and run <code>php scripts/process_jobs.php</code></td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
