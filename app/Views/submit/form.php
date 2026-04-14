<?php $title='Submit Job Posting'; ob_start(); ?>
<div class="row justify-content-center">
<div class="col-lg-7">
  <div class="card p-4">
    <h3 class="mb-3">Submit a Job Posting</h3>
    <p class="text-muted small">Paste a URL, the full job description, or both. Materials will be generated on the next processor run.</p>
    <?php if ($pending): ?>
    <div class="alert alert-info"><strong><?= $pending ?></strong> pending submission(s) waiting to be processed.</div>
    <?php endif; ?>
    <form method="post" action="<?= BASE_URL ?>/submit">
      <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
      <div class="mb-3">
        <label class="form-label">Job Title <span class="text-muted">(optional but helps)</span></label>
        <input class="form-control" name="title" placeholder="e.g. Senior PHP Developer">
      </div>
      <div class="mb-3">
        <label class="form-label">Job URL</label>
        <input type="url" class="form-control" name="url" placeholder="https://www.indeed.com/viewjob?jk=...">
      </div>
      <div class="mb-3">
        <label class="form-label">Full Job Description <span class="text-muted">(paste the whole thing)</span></label>
        <textarea class="form-control" name="text" rows="12" placeholder="Paste the full job posting text here..."></textarea>
      </div>
      <button class="btn btn-primary w-100">Submit for Processing</button>
    </form>
  </div>

  <?php if ($pending): ?>
  <div class="card p-3 mt-3">
    <form method="post" action="<?= BASE_URL ?>/process">
      <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
      <button class="btn btn-success w-100">Process Now (<?= $pending ?> pending)</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="card p-3 mt-3">
    <h5>Share from Phone</h5>
    <p class="small text-muted mb-2">On your phone, share the Indeed job URL to yourself via email, text, or just paste it above. Or email the link to:</p>
    <div class="input-group">
      <input type="text" class="form-control form-control-sm" value="email4johnson@gmail.com" readonly>
      <button class="btn btn-sm btn-outline-primary" onclick="navigator.clipboard.writeText('email4johnson@gmail.com').then(()=>alert('Copied!'))">Copy</button>
    </div>
    <p class="small text-muted mt-2">Subject line: <code>jobhunt: Job Title</code><br>Body: paste the URL or full job description.</p>
  </div>
</div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
