<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

// Auth Guard: Require Admin Role
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

try {
    // Total Orders Count
    $orderCountStmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $totalOrders = $orderCountStmt->fetchColumn();

    // Total Revenue
    $revStmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'paid'");
    $totalRevenue = $revStmt->fetchColumn() ?: 0;

    // Recent Orders
    $recentStmt = $pdo->query("SELECT o.*, u.name AS customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");
    $recentOrders = $recentStmt->fetchAll();
} catch (\PDOException $e) {
    die("Database Error on Admin Dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../public/css/liquid-glass.css">
    <script src="../public/js/theme-mode.js" defer></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 text-white min-h-screen p-6 flex flex-col justify-between">
        <div>
            <h2 class="text-xl font-black text-gray-300 tracking-wider mb-8">JENGO ADMIN</h2>
            <nav class="space-y-4">
                <a href="dashboard.php" class="block py-2 px-3 rounded bg-gray-800 font-bold text-white">Dashboard</a>
                <a href="../public/shop.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">View Shop</a>
            </nav>
        </div>
        <a href="attendants.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">Enroll Attendants</a>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Admin Control Center</h1>
            <span class="text-sm text-gray-500">Logged in as: <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong></span>
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <span class="text-gray-500 text-sm font-semibold">Total Orders</span>
                <p class="text-3xl font-black text-gray-900 mt-1"><?= $totalOrders ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <span class="text-gray-500 text-sm font-semibold">Total Revenue (Paid)</span>
                <p class="text-3xl font-black text-gray-600 mt-1">KES <?= number_format($totalRevenue, 2) ?></p>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-bold mb-4">Recent Customer Orders</h2>
            <?php if (empty($recentOrders)): ?>
                <p class="text-gray-500">No orders recorded yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b bg-gray-50 text-gray-600 text-sm">
                                <th class="p-3">Order ID</th>
                                <th class="p-3">Customer</th>
                                <th class="p-3">Amount</th>
                                <th class="p-3">Delivery</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $ord): ?>
                                <tr class="border-b hover:bg-gray-50 text-sm">
                                    <td class="p-3 font-bold">#<?= $ord['id'] ?></td>
                                    <td class="p-3"><?= htmlspecialchars($ord['customer_name']) ?></td>
                                    <td class="p-3 font-semibold">KES <?= number_format($ord['total_amount'], 2) ?></td>
                                    <td class="p-3 uppercase text-xs"><?= htmlspecialchars($ord['delivery_type']) ?></td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-bold uppercase <?= $ord['status'] === 'paid' ? 'bg-gray-100 text-gray-800' : 'bg-gray-200 text-gray-900' ?>">
                                            <?= htmlspecialchars($ord['status']) ?>
                                        </span>
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