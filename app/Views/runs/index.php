<?php $title='Scraper Runs'; ob_start(); ?>
<h2 class="mb-3">Scraper Runs</h2>
<div class="card p-3"><table class="table table-sm">
<thead><tr><th>ID</th><th>Source</th><th>Started</th><th>Finished</th><th>Status</th><th>Found</th><th>New</th></tr></thead><tbody>
<?php foreach ($runs as $r): ?>
<tr><td><a href="<?= BASE_URL ?>/runs/<?= $r['id'] ?>"><?= $r['id'] ?></a></td><td><?= htmlspecialchars($r['source']) ?></td><td><?= htmlspecialchars($r['started_at']) ?></td><td><?= htmlspecialchars($r['finished_at'] ?? '') ?></td><td><?= htmlspecialchars($r['status']) ?></td><td><?= $r['listings_found'] ?></td><td><?= $r['listings_new'] ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
