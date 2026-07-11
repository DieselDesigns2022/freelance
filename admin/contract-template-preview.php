<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM contract_templates WHERE id = ?');
$stmt->execute([$id]);
$template = $stmt->fetch();

if (!$template) {
    http_response_code(404);
    exit('Not found');
}

$rendered = render_contract_template($template['body'], [
    'client_name' => 'Sample Client',
    'client_email' => 'client@example.com',
    'business_name' => 'Sample Business',
    'order_id' => 'DD-20260710-00001',
    'product_name' => 'Sample Website Build',
    'product_price' => '$500.00',
    'service_type' => service_type_label($template['service_type']),
    'project_url' => 'https://example.com',
    'order_date' => date('Y-m-d'),
    'designer_name' => 'Diesel Designs',
    'site_name' => 'Diesel Designs',
    'shopify_store_url' => 'https://example.myshopify.com',
    'shopify_store_name' => 'Sample Shopify Store',
    'main_goal' => 'Refresh the homepage and product presentation.',
    'brand_colors' => 'Black, cream, and gold',
    'asset_link' => 'https://example.com/brand-assets',
    'featured_products' => 'Bestsellers and summer collection',
    'requested_sections' => 'Hero, featured products, footer, about section',
    'inspiration_links' => 'https://example.com/inspiration',
    'launch_timing' => 'Within 3 weeks',
    'extra_notes' => 'Please keep the current logo.',
    'intake_summary' => 'Sample Shopify Revamp intake summary.',
]);

$adminTitle = 'Preview Contract';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Preview: <?= e($template['title']) ?></h1>
<section class="admin-card">
    <div><?= nl2br(e($rendered)) ?></div>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
