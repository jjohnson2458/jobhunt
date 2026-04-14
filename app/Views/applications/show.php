<?php $title = "$company — $title"; ob_start(); ?>
<div class="row">
<div class="col-lg-8">

  <?php if ($applyEmail): ?>
  <div class="card p-3 mb-3" style="border-left: 4px solid var(--ft-olive);">
    <h5 style="color: var(--ft-olive);">Apply via Email</h5>
    <p class="mb-1"><strong>To:</strong> <a href="mailto:<?= htmlspecialchars($applyEmail) ?>?subject=<?= urlencode(($applyRef ?: '') . ' ' . $title) ?>"><?= htmlspecialchars($applyEmail) ?></a></p>
    <?php if ($applyRef): ?><p class="mb-1"><strong>Reference:</strong> <?= htmlspecialchars($applyRef) ?></p><?php endif; ?>
    <?php if ($applyDeadline): ?><p class="mb-1 text-danger"><strong>Deadline:</strong> <?= htmlspecialchars($applyDeadline) ?></p><?php endif; ?>
    <a href="mailto:<?= htmlspecialchars($applyEmail) ?>?subject=<?= urlencode(($applyRef ?: '') . ' Application - J.J. Johnson') ?>&body=<?= urlencode("Please find my resume and cover letter attached.\n\nBest regards,\nJ.J. Johnson") ?>" class="btn btn-primary mt-2">Open Email App</a>
  </div>
  <?php elseif ($sourceUrl): ?>
  <div class="card p-3 mb-3" style="border-left: 4px solid var(--ft-rust);">
    <h5 style="color: var(--ft-rust);">Apply on Indeed</h5>
    <a href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noreferrer" class="btn btn-primary">Open Indeed Listing →</a>
    <?php if ($applyDeadline): ?><p class="mt-2 mb-0 text-danger"><strong>Deadline:</strong> <?= htmlspecialchars($applyDeadline) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Cover Letter</h5>
      <button class="btn btn-sm btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('cover-text').innerText).then(()=>alert('Copied!'))">Copy to Clipboard</button>
    </div>
    <pre id="cover-text" style="white-space: pre-wrap; font-family: inherit; max-height: 500px; overflow: auto; background: #fff; padding: 15px; border: 1px solid var(--ft-sand);"><?= htmlspecialchars($coverLetter ?: '(No cover letter generated — set ANTHROPIC_API_KEY in .env)') ?></pre>
  </div>

  <div class="card p-3 mb-3">
    <h5>Job Summary</h5>
    <pre style="white-space: pre-wrap; font-family: inherit; max-height: 600px; overflow: auto;"><?= htmlspecialchars($summary) ?></pre>
  </div>
</div>

<div class="col-lg-4">
  <div class="card p-3 mb-3">
    <h5>Downloads</h5>
    <?php if ($resumeFile): ?>
    <a href="<?= BASE_URL ?>/applications/<?= urlencode($slug) ?>/download/<?= urlencode(basename($resumeFile)) ?>" class="btn btn-primary w-100 mb-2">Download Resume</a>
    <?php endif; ?>
    <?php if ($coverLetter): ?>
    <a href="<?= BASE_URL ?>/applications/<?= urlencode($slug) ?>/download/cover_letter.txt" class="btn btn-outline-primary w-100 mb-2">Download Cover Letter</a>
    <?php endif; ?>
  </div>

  <div class="card p-3">
    <h5>Quick Actions</h5>
    <?php if ($sourceUrl): ?>
    <a href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noreferrer" class="btn btn-sm btn-outline-primary w-100 mb-2">View on Indeed</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/applications" class="btn btn-sm btn-link w-100">← All Applications</a>
  </div>
</div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
