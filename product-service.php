<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$product = null;

if ($slug !== '' && table_exists(db(), 'products')) {
    $stmt = db()->prepare("SELECT * FROM products WHERE slug = ? AND status = 'active'");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
}

$pageTitle = $product ? $product['name'] . ' | Diesel Designs Store' : 'Service Not Found | Diesel Designs';
$metaDescription = $product ? substr($product['short_description'], 0, 155) : 'This Diesel Designs service is not available.';

if ($product) {
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => $product['name'],
        'description' => $product['short_description'],
        'provider' => ['@type' => 'LocalBusiness', 'name' => 'Diesel Designs'],
        'offers' => ['@type' => 'Offer', 'price' => (string) $product['price'], 'priceCurrency' => 'USD'],
    ];
}

include __DIR__ . '/includes/header.php';
?>
<?php if (!$product): ?>
    <section class="page-hero">
        <h1>Service not available</h1>
        <p>This service is unavailable or unpublished.</p>
        <p><a class="btn" href="store.php">Back to Store</a></p>
    </section>
<?php else: ?>
    <section class="page-hero">
        <p class="eyebrow"><?= e(service_type_label($product['service_type'])) ?></p>
        <h1><?= e($product['name']) ?></h1>
        <p><?= e($product['short_description']) ?></p>
        <p>
            <strong><?= e(money_format_dd($product['price'])) ?></strong>
            <?php if ($product['deposit_amount']): ?> · Deposit: <?= e(money_format_dd($product['deposit_amount'])) ?><?php endif; ?>
        </p>
        <p><a class="btn btn-accent" href="purchase.php?product=<?= e($product['slug']) ?>">Begin Purchase</a></p>
    </section>

    <section class="section">
        <h2>Details</h2>
        <p><?= nl2br(e($product['full_description'])) ?></p>

        <?php if ($product['includes_text']): ?>
            <h2>Includes</h2>
            <p><?= nl2br(e($product['includes_text'])) ?></p>
        <?php endif; ?>

        <?php if ($product['turnaround_text']): ?>
            <h2>Turnaround / Process</h2>
            <p><?= nl2br(e($product['turnaround_text'])) ?></p>
        <?php endif; ?>

        <?php if ($product['requirements_text']): ?>
            <h2>Requirements</h2>
            <p><?= nl2br(e($product['requirements_text'])) ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
