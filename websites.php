<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Website Builds & Revamps | Diesel Designs';
$metaDescription = 'Browse available website build and revamp services plus completed website portfolio work.';

$productStmt = db()->prepare(
    "SELECT p.*, pi.image_path, pi.alt_text
     FROM products p
     LEFT JOIN product_images pi ON pi.id = (
         SELECT id FROM product_images
         WHERE product_id = p.id
         ORDER BY sort_order, id
         LIMIT 1
     )
     WHERE p.status = 'active' AND p.service_type IN ('website_build','website_revamp','website_kit')
     ORDER BY p.sort_order, p.name"
);
$productStmt->execute();
$products = $productStmt->fetchAll();

$stmt = db()->prepare("SELECT * FROM portfolio_projects WHERE status='published' AND section_type='website' ORDER BY sort_order ASC, created_at DESC");
$stmt->execute();
$projects = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero category-hero">
    <p class="eyebrow">Website Builds / Revamps</p>
    <h1>Website Builds & Revamps</h1>
    <p>Available website services and completed website portfolio work all in one place.</p>
</section>

<section class="section category-block">
    <h2>Available Website Services</h2>
    <?php if ($products): ?>
        <div class="grid">
            <?php foreach ($products as $product): ?>
                <?= product_service_card($product) ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">No website build or revamp services are available for purchase right now.</div>
    <?php endif; ?>
</section>

<section class="section category-block">
    <h2>Completed Website Portfolio</h2>
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
