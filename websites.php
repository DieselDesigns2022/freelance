<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Website Builds | Diesel Designs';
$metaDescription = 'Browse completed website builds and custom online spaces.';

$stmt = db()->prepare("SELECT * FROM portfolio_projects WHERE status='published' AND section_type='website' ORDER BY sort_order ASC, created_at DESC");
$stmt->execute();
$projects = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <p class="eyebrow">Completed Website Builds</p>
    <h1>Website Builds</h1>
    <p>Clean, personality-packed websites for small businesses, blogs, digital shops, portfolios, and online brands.</p>
</section>
<section class="section">
    <?php if ($projects): ?>
        <div class="grid">
            <?php foreach ($projects as $project): ?>
                <?= project_card($project) ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">Website build portfolio coming soon.</div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
