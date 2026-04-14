<?php $title = 'Sign In'; ob_start(); ?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4">
      <h4 class="mb-3">Client Portal Sign In</h4>
      <form method="post" action="<?= BASE_URL ?>/login">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
        <button class="btn btn-primary w-100">Sign In</button>
      </form>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
