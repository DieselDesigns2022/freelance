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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="logo" href="index.php">Diesel<span>Designs</span></a>
    <nav>
        <a href="index.php">Home</a>
        <a href="services.php">Services</a>
        <a href="portfolio.php">Portfolio</a>
        <a href="websites.php">Website Builds</a>
        <a href="shopify-makeovers.php">Shopify Make-Overs</a>
        <a href="faq.php">FAQ</a>
        <a href="request-website.php">Request a Website</a>
        <a href="#contact">Contact</a>
    </nav>
</header>
<main>
