<?php $title='Settings'; ob_start(); ?>
<h2 class="mb-3">Settings</h2>
<form method="post" action="<?= BASE_URL ?>/settings"><input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
<div class="card p-3"><table class="table table-sm">
<thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>
<?php foreach ($settings as $s): ?>
<tr><td><?= htmlspecialchars($s['key']) ?></td><td><input class="form-control form-control-sm" name="settings[<?= htmlspecialchars($s['key']) ?>]" value="<?= htmlspecialchars($s['value']) ?>"></td></tr>
<?php endforeach; ?>
</tbody></table>
<button class="btn btn-primary">Save</button></div></form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
