<?php $title='Not Found'; ob_start(); ?>
<div class="text-center py-5"><h1>404</h1><p class="text-muted">Not found.</p><a href="<?= BASE_URL ?>/">Home</a></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
