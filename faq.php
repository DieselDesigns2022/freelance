<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Website Build FAQ | Diesel Designs';
$metaDescription = 'Questions and answers about Diesel Designs website builds, website revamps, Shopify make-overs, scope, revisions, and SEO-friendly foundations.';

$stmt = db()->prepare("SELECT * FROM faqs WHERE status='published' ORDER BY sort_order ASC, created_at ASC");
$stmt->execute();
$faqs = $stmt->fetchAll();
$schemaItems = [];

foreach ($faqs as $faq) {
    $schemaItems[] = [
        '@type' => 'Question',
        'name' => $faq['question'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
    ];
}

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <p class="eyebrow">FAQ</p>
    <h1>Website Build FAQ</h1>
    <p>Helpful answers about website builds, website revamps, Shopify make-overs, visual refreshes, scope, communication, and SEO-friendly website foundations.</p>
</section>
<section class="section">
    <?php if ($faqs): ?>
        <div class="faq-list">
            <?php foreach ($faqs as $faq): ?>
                <details class="faq-item">
                    <summary><?= e($faq['question']) ?></summary>
                    <?php if ($faq['category']): ?><p class="pill"><?= e($faq['category']) ?></p><?php endif; ?>
                    <p><?= nl2br(e($faq['answer'])) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">FAQs are being added. If you have a question, send a website request or email Diesel Designs.</div>
    <?php endif; ?>
</section>
<section class="section request-band"><h2>Still have questions?</h2><p>Send the details you have, even if you are not sure what type of website help you need yet.</p><a class="btn btn-accent" href="request-website.php">Request a Website Build or Revamp</a></section>
<?php if ($schemaItems): ?>
<script type="application/ld+json">
<?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $schemaItems], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRETTY_PRINT) ?>
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
