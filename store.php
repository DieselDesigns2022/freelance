<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Store | Website Kits, Builds & Revamps | Diesel Designs';
$metaDescription = 'Browse Diesel Designs website kits, website builds, Shopify make-overs, revamps, and custom service packages.';
$products = table_exists(db(), 'products')
    ? db()->query("SELECT * FROM products WHERE status = 'active' ORDER BY sort_order, name")->fetchAll()
    : [];

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <p class="eyebrow">Diesel Designs Store</p>
    <h1>Website kits, builds, and revamps</h1>
    <p>Start a project purchase request. Payment is handled manually after your order and contract are reviewed.</p>
</section>

<section class="section grid">
    <?php if (!$products): ?>
        <div class="empty">
            <h2>No services are available right now</h2>
            <p>Please check back soon or submit a custom website request.</p>
            <p><a class="btn" href="request-website.php">Request a Website</a></p>
        </div>
    <?php endif; ?>

    <?php foreach ($products as $product): ?>
        <article class="project-card">
            <div class="card-body">
                <span class="pill"><?= e(service_type_label($product['service_type'])) ?></span>
                <h3><?= e($product['name']) ?></h3>
                <p><?= e($product['short_description']) ?></p>
                <p>
                    <strong><?= e(money_format_dd($product['price'])) ?></strong>
                    <?php if ($product['turnaround_text']): ?> · <?= e($product['turnaround_text']) ?><?php endif; ?>
                </p>
                <div class="card-actions">
                    <a class="btn" href="product-service.php?slug=<?= e($product['slug']) ?>">View Details</a>
                    <a class="btn btn-accent" href="purchase.php?product=<?= e($product['slug']) ?>">Purchase / Start Order</a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
