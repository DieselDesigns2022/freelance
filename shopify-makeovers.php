<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Shopify Make-Overs | Diesel Designs';
$metaDescription = 'Browse Shopify theme refreshes, branding updates, and store glow-ups.';

$stmt = db()->prepare("SELECT * FROM portfolio_projects WHERE status='published' AND section_type='shopify_makeover' ORDER BY sort_order ASC, created_at DESC");
$stmt->execute();
$projects = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <p class="eyebrow">Shopify Themes / Make-Overs</p>
    <h1>Shopify Make-Overs</h1>
    <p>Theme refreshes, homepage glow-ups, color makeovers, graphics updates, and store design polish.</p>
</section>
<section class="section">
    <?php if ($projects): ?>
        <div class="grid">
            <?php foreach ($projects as $project): ?>
                <?= project_card($project) ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">Shopify make-over portfolio coming soon.</div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
