<?php $title='Run #'.$run['id']; ob_start(); ?>
<h2>Run #<?= $run['id'] ?> · <?= htmlspecialchars($run['source']) ?></h2>
<div class="card p-3">
<dl class="row">
<dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= htmlspecialchars($run['status']) ?></dd>
<dt class="col-sm-3">Started</dt><dd class="col-sm-9"><?= htmlspecialchars($run['started_at']) ?></dd>
<dt class="col-sm-3">Finished</dt><dd class="col-sm-9"><?= htmlspecialchars($run['finished_at'] ?? '') ?></dd>
<dt class="col-sm-3">Found / New</dt><dd class="col-sm-9"><?= $run['listings_found'] ?> / <?= $run['listings_new'] ?></dd>
<?php if ($run['error_message']): ?><dt class="col-sm-3">Error</dt><dd class="col-sm-9 text-danger"><?= htmlspecialchars($run['error_message']) ?></dd><?php endif; ?>
</dl>
<?php if ($run['raw_log']): ?><h6>Log</h6><pre style="max-height:400px; overflow:auto;"><?= htmlspecialchars($run['raw_log']) ?></pre><?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
