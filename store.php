<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Available Website & Shopify Services | Diesel Designs';
$metaDescription = 'Browse available Diesel Designs website builds, revamps, Shopify make-overs, kits, and custom service packages.';

$products = [];
if (table_exists(db(), 'products')) {
    $products = db()->query(
        "SELECT p.*, pi.image_path, pi.alt_text
         FROM products p
         LEFT JOIN product_images pi ON pi.id = (
             SELECT id FROM product_images
             WHERE product_id = p.id
             ORDER BY sort_order, id
             LIMIT 1
         )
         WHERE p.status = 'active'
         ORDER BY p.sort_order, p.name"
    )->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero category-hero">
    <p class="eyebrow">Diesel Designs Services</p>
    <h1>Available website & Shopify services</h1>
    <p>Browse available service packages. Payment is handled manually after your order and contract are reviewed.</p>
</section>

<section class="section category-block">
    <?php if ($products): ?>
        <div class="grid">
            <?php foreach ($products as $product): ?>
                <?= product_service_card($product) ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">
            <h2>No services are available right now</h2>
            <p>Please check back soon or submit a custom website request.</p>
            <p><a class="btn" href="request-website.php">Request a Website</a></p>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
