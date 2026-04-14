<?php $title=$listing['title']; ob_start(); ?>
<div class="row">
<div class="col-lg-8">
  <div class="card p-4 mb-3">
    <h3><?= htmlspecialchars($listing['title']) ?> <span class="score-pill ms-2"><?= (int)$listing['score'] ?></span></h3>
    <div class="text-muted mb-2"><?= htmlspecialchars($listing['company']) ?> · <?= htmlspecialchars($listing['location'] ?? '') ?> · <?= htmlspecialchars($listing['source']) ?></div>
    <div class="mb-2"><strong>Salary:</strong> <?= htmlspecialchars($listing['salary_text'] ?? 'n/a') ?></div>
    <?php if ($listing['source_url']): ?><div class="mb-2"><a href="<?= htmlspecialchars($listing['source_url']) ?>" target="_blank" rel="noreferrer">Open original posting →</a></div><?php endif; ?>
    <?php if ($listing['score_reason']): ?><div class="alert alert-light small"><strong>Score reason:</strong> <?= htmlspecialchars($listing['score_reason']) ?></div><?php endif; ?>
    <hr>
    <pre style="white-space: pre-wrap; font-family: inherit;"><?= htmlspecialchars($listing['description'] ?? '') ?></pre>
  </div>
</div>
<div class="col-lg-4">
  <div class="card p-3 mb-3">
    <h5>Actions</h5>
    <form method="post" action="<?= BASE_URL ?>/listings/<?= $listing['id'] ?>/generate" class="mb-2">
      <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
      <button class="btn btn-primary w-100">Generate Resume + Cover Letter</button>
    </form>
    <form method="post" action="<?= BASE_URL ?>/listings/<?= $listing['id'] ?>/apply" class="mb-2">
      <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
      <button class="btn btn-success w-100">Mark as Applied</button>
    </form>
    <?php foreach (['starred','reviewed','hidden','blacklisted'] as $st): ?>
    <form method="post" action="<?= BASE_URL ?>/listings/<?= $listing['id'] ?>/status" class="d-inline">
      <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
      <input type="hidden" name="status" value="<?= $st ?>">
      <button class="btn btn-sm btn-outline-primary mb-1"><?= ucfirst($st) ?></button>
    </form>
    <?php endforeach; ?>
  </div>
  <?php if ($application): ?>
  <div class="card p-3">
    <h5>Application</h5>
    <div><strong>Status:</strong> <?= htmlspecialchars($application['status']) ?></div>
    <?php if ($application['cover_letter_path']): ?><div><a href="<?= BASE_URL . '/' . htmlspecialchars($application['cover_letter_path']) ?>">Cover letter file</a></div><?php endif; ?>
    <?php if ($application['resume_path']): ?><div><a href="<?= BASE_URL . '/' . htmlspecialchars($application['resume_path']) ?>">Tailored resume</a></div><?php endif; ?>
    <?php if ($application['cover_letter_text']): ?>
    <hr><h6>Draft</h6>
    <pre style="white-space:pre-wrap; font-family:inherit; max-height:400px; overflow:auto;"><?= htmlspecialchars($application['cover_letter_text']) ?></pre>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
