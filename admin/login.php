<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (login_admin(trim($_POST['email'] ?? ''), $_POST['password'] ?? '')) {
        redirect('dashboard.php');
    }

    $error = 'Invalid email or password.';
}

$adminTitle = 'Admin Login';
include __DIR__ . '/includes/admin-header.php';
?>
<section class="admin-card">
    <h1>Admin Login</h1>
    <?php if ($error): ?>
        <p class="error-text"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>Email<input type="email" name="email" required></label>
        <label>Password<input type="password" name="password" required></label>
        <button class="btn">Log In</button>
    </form>
    <p class="muted">First time? Run setup if no admin exists.</p>
    <a href="setup.php">Create first admin</a>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
