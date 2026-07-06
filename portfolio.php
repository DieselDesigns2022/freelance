<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Website Design Portfolio | Diesel Designs';
$metaDescription = 'Browse Diesel Designs website builds, Shopify make-overs, website revamps, and visual website refresh portfolio work.';

$stmt = db()->prepare("SELECT * FROM portfolio_projects WHERE status='published' ORDER BY sort_order ASC, created_at DESC");
$stmt->execute();
$projects = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <p class="eyebrow">Portfolio</p>
    <h1>Website Design Portfolio</h1>
    <p>Browse published website builds, Shopify make-overs, and design refreshes created for small businesses, digital shops, and online brands.</p>
</section>
<section class="section">
    <?php if ($projects): ?>
        <div class="grid"><?php foreach ($projects as $project): ?><?= project_card($project) ?><?php endforeach; ?></div>
    <?php else: ?>
        <div class="empty">Portfolio work is coming soon.</div>
    <?php endif; ?>
</section>
<section class="section request-band"><h2>Want your website here next?</h2><a class="btn btn-accent" href="request-website.php">Request a Website Build or Revamp</a></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
