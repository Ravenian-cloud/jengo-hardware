<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

// Protected route: require customer login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // Fetch user orders
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/liquid-glass.css">
    <script src="js/theme-mode.js" defer></script>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-gray-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <a href="home.php" class="text-2xl font-black text-gray-300 tracking-wider">JENGO HARDWARE</a>
            <div class="space-x-6">
                <a href="home.php" class="hover:text-gray-300 font-medium">Home</a>
                <a href="shop.php" class="hover:text-gray-300 font-medium">Shop</a>
                <a href="logout.php" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-sm font-bold">Logout</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8 flex-grow w-full">
        <h1 class="text-3xl font-bold mb-2">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Customer') ?></h1>
        <p class="text-gray-500 mb-8">Manage your profile and track your hardware orders.</p>

        <!-- Orders Section -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-xl font-bold mb-4">Your Order History</h2>
            <?php if (empty($orders)): ?>
                <p class="text-gray-500 py-4">You have not placed any orders yet.</p>
                <a href="shop.php" class="inline-block mt-2 bg-gray-800 hover:bg-gray-700 text-white font-bold px-4 py-2 rounded">Start Shopping</a>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b bg-gray-100 text-gray-600 text-sm">
                                <th class="p-3">Order ID</th>
                                <th class="p-3">Total Amount</th>
                                <th class="p-3">Delivery Type</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-bold">#<?= $order['id'] ?></td>
                                    <td class="p-3">KES <?= number_format($order['total_amount'], 2) ?></td>
                                    <td class="p-3 uppercase text-xs font-semibold"><?= htmlspecialchars($order['delivery_type']) ?></td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-bold uppercase <?= $order['status'] === 'paid' ? 'bg-gray-100 text-gray-800' : 'bg-gray-200 text-gray-900' ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-gray-500 text-sm"><?= $order['created_at'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>