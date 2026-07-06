<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $count === 0) {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    if (strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        db()->prepare('INSERT INTO admin_users (name,email,password_hash,created_at) VALUES (?,?,?,NOW())')
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);

        flash('success', 'Admin account created. Please log in.');
        redirect('login.php');
    }
}

$adminTitle = 'Admin Setup';
include __DIR__ . '/includes/admin-header.php';
?>
<section class="admin-card">
    <h1>Admin Setup</h1>
    <?php if ($count > 0): ?>
        <p>Setup is already complete.</p>
        <a class="btn" href="login.php">Go to login</a>
    <?php else: ?>
        <?php foreach ($errors as $error): ?>
            <p class="error-text"><?= e($error) ?></p>
        <?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label>Name<input name="name" required></label>
            <label>Email<input type="email" name="email" required></label>
            <label>Password<input type="password" name="password" minlength="8" required></label>
            <button class="btn">Create First Admin</button>
        </form>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
