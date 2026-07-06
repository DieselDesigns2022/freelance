<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Website Builds & Revamps by Diesel Designs';
$metaDescription = 'Website builds, Shopify revamps, visual refreshes, and portfolio work by Diesel Designs for small businesses, digital shops, and online brands.';

$stmt = db()->query("SELECT * FROM portfolio_projects WHERE status='published' AND is_featured=1 ORDER BY sort_order ASC, created_at DESC LIMIT 6");
$projects = $stmt->fetchAll();

$faqStmt = db()->query("SELECT question, answer FROM faqs WHERE status='published' ORDER BY sort_order ASC, created_at ASC LIMIT 6");
$faqs = $faqStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div>
        <p class="eyebrow">Website Builds & Revamps by Diesel Designs</p>
        <h1>Websites & Shopify Glow-Ups Built With Personality</h1>
        <p>Custom website builds, Shopify revamps, and design refreshes for small businesses, digital shops, and online brands that want to look polished without losing their personality.</p>
        <div class="hero-actions">
            <a class="btn" href="portfolio.php">View My Work</a>
            <a class="btn btn-accent" href="request-website.php">Request a Website Build</a>
            <a class="btn btn-ghost" href="faq.php">Read FAQs</a>
        </div>
    </div>
</section>
<section class="section">
    <p class="eyebrow">Services</p>
    <h2>Website help that meets your brand where it is</h2>
    <div class="services">
        <article><h3>Website Builds</h3><p>New small business websites, portfolio sites, landing pages, digital shop websites, and simple service websites built with clear structure and personality.</p></article>
        <article><h3>Website Revamps</h3><p>Refresh an existing site with updated visuals, better layout flow, stronger mobile presentation, and a more polished brand feel.</p></article>
        <article><h3>Shopify Make-Overs</h3><p>Shopify theme refreshes, homepage glow-ups, color updates, graphics, and store polish for digital shops and online brands.</p></article>
        <article><h3>Graphics & Color Customization</h3><p>Visual website refreshes focused on graphics, colors, branding polish, and presentation when a full rebuild is not needed.</p></article>
    </div>
</section>
<section class="section">
    <h2>Featured Work</h2>
    <?php if ($projects): ?>
        <div class="grid">
            <?php foreach ($projects as $project): ?><?= project_card($project) ?><?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">Featured portfolio work will be added soon.</div>
    <?php endif; ?>
</section>
<section class="section">
    <p class="eyebrow">Why Work With Diesel Designs</p>
    <h2>Polished websites without losing personality</h2>
    <div class="services">
        <article><h3>Personality-packed design</h3><p>Design choices are made to feel like your brand, not a generic template.</p></article>
        <article><h3>Small-business friendly</h3><p>Clear service options for brands that need practical, polished online spaces.</p></article>
        <article><h3>Clear communication</h3><p>Scope, goals, and next steps are confirmed before work begins.</p></article>
        <article><h3>SEO-minded structure</h3><p>Pages are planned with readable headings, helpful content, metadata, and internal links.</p></article>
        <article><h3>Mobile-friendly layouts</h3><p>Designs are built to work well across desktop, tablet, and mobile screens.</p></article>
        <article><h3>Portfolio-focused visuals</h3><p>Your work, offers, products, or brand visuals stay front and center.</p></article>
    </div>
</section>
<section class="section">
    <p class="eyebrow">Questions</p>
    <h2>Website Build FAQ</h2>
    <?php if ($faqs): ?>
        <div class="faq-list">
            <?php foreach ($faqs as $faq): ?>
                <details class="faq-item"><summary><?= e($faq['question']) ?></summary><p><?= nl2br(e($faq['answer'])) ?></p></details>
            <?php endforeach; ?>
        </div>
        <p><a class="btn btn-ghost" href="faq.php">Read the Full FAQ</a></p>
    <?php else: ?>
        <div class="empty">FAQs are being added. You can still request a website build or revamp any time.</div>
    <?php endif; ?>
</section>
<section class="section request-band">
    <h2>Ready to talk about your website?</h2>
    <p>Send the basics about your website build, revamp, Shopify make-over, or visual refresh and Diesel Designs will review the request.</p>
    <a class="btn btn-accent" href="request-website.php">Request a Website Build or Revamp</a>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
