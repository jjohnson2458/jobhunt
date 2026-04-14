<?php $title='Listings'; ob_start(); ?>
<h2 class="mb-3">Job Listings</h2>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-2"><select name="track_id" class="form-select form-select-sm"><option value="">All tracks</option><?php foreach ($tracks as $t): ?><option value="<?= $t['id'] ?>" <?= ($filters['track_id']??'')==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Any status</option><?php foreach (['new','reviewed','starred','hidden','blacklisted'] as $s): ?><option value="<?= $s ?>" <?= ($filters['status']??'')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select name="source" class="form-select form-select-sm"><option value="">Any source</option><?php foreach (['indeed','ziprecruiter','monster','linkedin'] as $s): ?><option value="<?= $s ?>" <?= ($filters['source']??'')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><input type="number" name="min_score" class="form-control form-control-sm" placeholder="Min score" value="<?= htmlspecialchars($filters['min_score']??'') ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button></div>
</form>
<div class="card p-3">
<table class="table table-sm table-hover">
<thead><tr><th>Score</th><th>Title</th><th>Company</th><th>Location</th><th>Salary</th><th>Source</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($listings as $l): ?>
<tr>
  <td><span class="score-pill"><?= (int)$l['score'] ?></span></td>
  <td><a href="<?= BASE_URL ?>/listings/<?= $l['id'] ?>"><?= htmlspecialchars($l['title']) ?></a></td>
  <td><?= htmlspecialchars($l['company']) ?></td>
  <td><?= htmlspecialchars($l['location'] ?? '') ?><?= $l['is_remote'] ? ' <span class="badge bg-success">remote</span>' : '' ?></td>
  <td><?= htmlspecialchars($l['salary_text'] ?? '') ?></td>
  <td><?= htmlspecialchars($l['source']) ?></td>
  <td><?= htmlspecialchars($l['status']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$listings): ?><tr><td colspan="7" class="text-muted">No listings match.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
