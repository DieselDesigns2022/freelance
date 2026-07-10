<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$adminTitle = $adminTitle ?? 'Portfolio Admin';
$currentAdmin = admin_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($adminTitle) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin">
<header class="admin-header">
    <a class="logo" href="<?= $currentAdmin ? 'dashboard.php' : '../index.php' ?>">Diesel<span>Admin</span></a>
    <nav>
        <?php if ($currentAdmin): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="projects.php">Projects</a>
            <a href="products.php">Products</a>
            <a href="contract-templates.php">Contract Templates</a>
            <a href="orders.php">Orders</a>
            <a href="requests.php">Requests</a>
            <a href="faqs.php">FAQs</a>
            <a href="projects-create.php">Add Project</a>
            <a href="../index.php">Public Site</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="../index.php">Public Site</a>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>
<main class="admin-main">
<?php foreach (['success', 'error'] as $flashKey): ?>
    <?php if ($message = flash($flashKey)): ?>
        <div class="flash <?= e($flashKey) ?>"><?= e($message) ?></div>
    <?php endif; ?>
<?php endforeach; ?>
