<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Shopify Make-Overs & Revamps | Diesel Designs';
$metaDescription = 'Browse available Shopify revamp services and completed Shopify make-over portfolio work.';

$productStmt = db()->prepare(
    "SELECT p.*, pi.image_path, pi.alt_text
     FROM products p
     LEFT JOIN product_images pi ON pi.id = (
         SELECT id FROM product_images
         WHERE product_id = p.id
         ORDER BY sort_order, id
         LIMIT 1
     )
     WHERE p.status = 'active' AND p.service_type = 'shopify_makeover'
     ORDER BY p.sort_order, p.name"
);
$productStmt->execute();
$products = $productStmt->fetchAll();

$stmt = db()->prepare("SELECT * FROM portfolio_projects WHERE status='published' AND section_type='shopify_makeover' ORDER BY sort_order ASC, created_at DESC");
$stmt->execute();
$projects = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero category-hero">
    <p class="eyebrow">Shopify Themes / Make-Overs</p>
    <h1>Shopify Make-Overs</h1>
    <p>Available Shopify revamps, theme refreshes, homepage glow-ups, color makeovers, graphics updates, and completed Shopify work all in one place.</p>
</section>

<section class="section category-block">
    <h2>Available Shopify Revamps</h2>
    <?php if ($products): ?>
        <div class="grid">
            <?php foreach ($products as $product): ?>
                <?= product_service_card($product) ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">No Shopify revamps are available for purchase right now.</div>
    <?php endif; ?>
</section>

<section class="section category-block">
    <h2>Completed Shopify Portfolio</h2>
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
