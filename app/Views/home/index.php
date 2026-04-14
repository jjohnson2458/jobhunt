<?php
// Decoy public landing — looks like a real foot-traffic analytics product
$title = 'Foot Traffic Analytics';
ob_start(); ?>
<div class="row justify-content-center">
  <div class="col-lg-8 text-center py-5">
    <h1 class="display-5">Foot Traffic Analytics</h1>
    <p class="lead text-muted">Pedestrian flow data and dwell-time analytics for retail and commercial properties.</p>
    <p class="text-muted">Service portal for existing clients only. Please contact your account manager for access.</p>
  </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
