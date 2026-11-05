<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

// Auth Guard: Require Attendant or Admin Role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['attendant', 'admin'])) {
    header('Location: ../public/login.php');
    exit;
}

// Handle Order Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id   = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';

    $allowed_statuses = ['pending', 'paid', 'processing', 'completed', 'cancelled'];

    if ($order_id > 0 && in_array($new_status, $allowed_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            $msg = "Order #{$order_id} status updated to " . ucfirst($new_status) . ".";
        } catch (\PDOException $e) {
            $error = "Failed to update order status: " . $e->getMessage();
        }
    }
}

try {
    // Fetch metrics
    $pendingStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $pendingCount = $pendingStmt->fetchColumn();

    $paidStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid', 'processing')");
    $activeCount = $paidStmt->fetchColumn();

    // Fetch all active orders joining customer info
    $sql = "SELECT o.*, u.name AS customer_name, u.phone AS customer_phone 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            ORDER BY o.id DESC";
    $ordersStmt = $pdo->query($sql);
    $orders = $ordersStmt->fetchAll();

} catch (\PDOException $e) {
    die("Database Error on Attendant Dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendant Dashboard - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 text-stone-800 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-stone-900 text-white min-h-screen p-6 flex flex-col justify-between flex-shrink-0">
        <div>
            <h2 class="text-xl font-black text-orange-500 tracking-wider mb-8">ATTENDANT DESK</h2>
            <nav class="space-y-3">
                <a href="dashboard.php" class="block py-2 px-3 rounded bg-orange-600 font-bold text-white">Fulfillment Hub</a>
                <a href="../public/shop.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">Store Catalog</a>
            </nav>
        </div>
        <a href="../admin/inventory.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">Inventory Catalog</a>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-8 overflow-y-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold">Order Fulfillment & Verification</h1>
                <p class="text-stone-500 text-sm">Verify paid pickup/delivery orders and dispatch hardware goods.</p>
            </div>
            <span class="text-sm bg-white px-4 py-2 rounded border border-stone-200">
                Staff: <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Attendant') ?></strong>
            </span>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-800 rounded text-sm font-medium">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-800 rounded text-sm font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-stone-200">
                <span class="text-stone-500 text-xs font-bold uppercase">Pending Unpaid Orders</span>
                <p class="text-3xl font-black text-amber-600 mt-1"><?= $pendingCount ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-stone-200">
                <span class="text-stone-500 text-xs font-bold uppercase">Ready for Pickup / Dispatch</span>
                <p class="text-3xl font-black text-green-600 mt-1"><?= $activeCount ?></p>
            </div>
        </div>

        <!-- Orders Processing Table -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-stone-200">
            <h2 class="text-lg font-bold mb-4">Store Orders</h2>
            <?php if (empty($orders)): ?>
                <p class="text-stone-500 text-sm">No orders registered in the system yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b bg-stone-50 text-stone-600 text-xs uppercase tracking-wider">
                                <th class="p-3">Order ID</th>
                                <th class="p-3">Customer</th>
                                <th class="p-3">Amount</th>
                                <th class="p-3">Fulfillment</th>
                                <th class="p-3">Current Status</th>
                                <th class="p-3">Update Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-sm">
                            <?php foreach ($orders as $ord): ?>
                                <tr class="hover:bg-stone-50 transition">
                                    <td class="p-3 font-mono font-bold">#<?= $ord['id'] ?></td>
                                    <td class="p-3">
                                        <div class="font-bold text-stone-900"><?= htmlspecialchars($ord['customer_name']) ?></div>
                                        <div class="text-xs text-stone-500"><?= htmlspecialchars($ord['customer_phone'] ?? 'No Phone') ?></div>
                                    </td>
                                    <td class="p-3 font-extrabold text-stone-800">KES <?= number_format($ord['total_amount'], 2) ?></td>
                                    <td class="p-3 uppercase text-xs font-semibold text-stone-600"><?= htmlspecialchars($ord['delivery_type']) ?></td>
                                    <td class="p-3">
                                        <span class="px-2.5 py-1 text-xs rounded-full font-bold uppercase 
                                            <?= $ord['status'] === 'completed' ? 'bg-green-100 text-green-800' : '' ?>
                                            <?= $ord['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : '' ?>
                                            <?= $ord['status'] === 'pending' ? 'bg-amber-100 text-amber-800' : '' ?>
                                            <?= $ord['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : '' ?>">
                                            <?= htmlspecialchars($ord['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <form method="POST" class="flex items-center space-x-2">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                            <select name="status" class="text-xs border rounded p-1.5 bg-stone-50 font-medium">
                                                <option value="pending" <?= $ord['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="paid" <?= $ord['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                                <option value="processing" <?= $ord['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                                <option value="completed" <?= $ord['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="cancelled" <?= $ord['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" class="bg-stone-800 hover:bg-stone-900 text-white text-xs px-3 py-1.5 rounded font-bold">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>