<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare("UPDATE products SET status = 'archived', updated_at = NOW() WHERE id = ?")->execute([$id]);
    flash('success', 'Product archived.');
    redirect('products.php');
}

$products = db()->query(
    'SELECT p.*, ct.title AS contract_title, ct.status AS contract_template_status '
    . 'FROM products p LEFT JOIN contract_templates ct ON p.contract_template_id = ct.id '
    . 'ORDER BY p.sort_order, p.name'
)->fetchAll();

$adminTitle = 'Products';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Products</h1>
<p><a class="btn" href="products-create.php">Add Product</a></p>
<section class="admin-card">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Price</th>
                <th>Contract</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <?= e($product['name']) ?>
                        <?php if ($product['status'] === 'active' && (!$product['contract_template_id'] || $product['contract_template_status'] !== 'active')): ?>
                            <br><small class="error-text">Active but missing active contract.</small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($product['status']) ?></td>
                    <td><?= e(money_format_dd($product['price'])) ?></td>
                    <td><?= e($product['contract_title'] ?? 'None') ?></td>
                    <td>
                        <a href="products-edit.php?id=<?= (int) $product['id'] ?>">Edit</a>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                            <button>Archive</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
