<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

$orders = db()->query(
    'SELECT o.*, ci.signed_at FROM orders o '
    . 'LEFT JOIN contract_instances ci ON ci.order_id = o.id '
    . 'ORDER BY o.created_at DESC'
)->fetchAll();

$adminTitle = 'Orders';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Orders</h1>
<section class="admin-card">
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Order Status</th>
                <th>Payment Status</th>
                <th>Contract Status</th>
                <th>Signed</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= e($order['order_number']) ?></td>
                    <td><?= e($order['customer_name']) ?><br><small><?= e($order['customer_email']) ?></small></td>
                    <td><?= e($order['product_name_snapshot']) ?></td>
                    <td><?= e($order['order_status']) ?></td>
                    <td><?= e($order['payment_status']) ?></td>
                    <td><?= e($order['contract_status']) ?></td>
                    <td><?= e($order['signed_at'] ?? '') ?></td>
                    <td><a href="order-view.php?id=<?= (int) $order['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
