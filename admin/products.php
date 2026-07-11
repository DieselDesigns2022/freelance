<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? 'archive';

    if ($id <= 0) {
        flash('error', 'Choose a valid product.');
        redirect('products.php');
    }

    if ($action === 'delete') {
        try {
            $imagePaths = [];

            if (table_exists(db(), 'product_images')) {
                $imgStmt = db()->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
                $imgStmt->execute([$id]);
                foreach ($imgStmt->fetchAll() as $image) {
                    if (!empty($image['image_path'])) {
                        $imagePaths[] = $image['image_path'];
                    }
                }
            }

            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);

            foreach ($imagePaths as $imagePath) {
                delete_portfolio_file($imagePath);
            }

            flash('success', 'Product deleted.');
        } catch (Throwable $e) {
            flash('error', 'Product could not be deleted. Archive it instead if it already has related order history.');
        }

        redirect('products.php');
    }

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

<?php if ($msg = flash('error')): ?>
    <p class="error-text"><?= e($msg) ?></p>
<?php endif; ?>

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
                    <td class="table-actions">
                        <a href="products-edit.php?id=<?= (int) $product['id'] ?>">Edit</a>

                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                            <button>Archive</button>
                        </form>

                        <form method="post" class="inline" onsubmit="return confirm('Delete this product permanently?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                            <button class="danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
