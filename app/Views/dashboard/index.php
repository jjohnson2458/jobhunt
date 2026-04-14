<?php $title='Dashboard'; ob_start(); ?>
<h2 class="mb-4">Dashboard</h2>
<div class="row g-3 mb-4">
  <?php foreach ($counts as $k => $v): ?>
  <div class="col-md-2"><div class="card p-3 text-center"><div class="text-muted small text-uppercase"><?= $k ?></div><div class="display-6"><?= $v ?></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card p-3">
      <h5>Top New Listings</h5>
      <table class="table table-sm">
        <thead><tr><th>Score</th><th>Title</th><th>Company</th><th>Location</th><th>Source</th></tr></thead>
        <tbody>
        <?php foreach ($top as $l): ?>
          <tr>
            <td><span class="score-pill"><?= (int)$l['score'] ?></span></td>
            <td><a href="<?= BASE_URL ?>/listings/<?= $l['id'] ?>"><?= htmlspecialchars($l['title']) ?></a></td>
            <td><?= htmlspecialchars($l['company']) ?></td>
            <td><?= htmlspecialchars($l['location'] ?? '') ?><?= $l['is_remote'] ? ' <span class="badge bg-success">remote</span>' : '' ?></td>
            <td><?= htmlspecialchars($l['source']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$top): ?><tr><td colspan="5" class="text-muted">No listings yet. Run the scraper.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card p-3">
      <h5>Recent Scraper Runs</h5>
      <ul class="list-unstyled small mb-0">
        <?php foreach ($recent_runs as $r): ?>
          <li class="mb-2"><strong><?= htmlspecialchars($r['source']) ?></strong> · <?= htmlspecialchars($r['status']) ?> · <?= (int)$r['listings_new'] ?> new / <?= (int)$r['listings_found'] ?> found <span class="text-muted"><?= htmlspecialchars($r['started_at']) ?></span></li>
        <?php endforeach; ?>
        <?php if (!$recent_runs): ?><li class="text-muted">No runs yet.</li><?php endif; ?>
      </ul>
      <form method="post" action="<?= BASE_URL ?>/runs/start" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <select name="track_id" class="form-select form-select-sm mb-2">
          <option value="">All tracks</option>
          <?php foreach ($tracks as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm w-100">Queue Scraper Run</button>
      </form>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
