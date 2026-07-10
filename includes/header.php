<?php
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Website Builds & Revamps by Diesel Designs';
$metaDescription = $metaDescription ?? 'Website builds, website revamps, Shopify make-overs, and digital design services by Diesel Designs.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <?php if (!empty($structuredData)): ?><script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script><?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="logo" href="index.php">Diesel<span>Designs</span></a>
    <nav>
        <a href="index.php">Home</a>
        <a href="services.php">Services</a>
        <a href="store.php">Store</a>
        <a href="portfolio.php">Portfolio</a>
        <a href="websites.php">Website Builds</a>
        <a href="shopify-makeovers.php">Shopify Make-Overs</a>
        <a href="faq.php">FAQ</a>
        <a href="request-website.php">Request a Website</a>
        <a href="#contact">Contact</a>
    </nav>
</header>
<main>
