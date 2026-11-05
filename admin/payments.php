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
    // Fetch all payment logs joining with orders and user details
    $sql = "SELECT p.*, o.delivery_type, u.name AS customer_name, u.email AS customer_email 
            FROM payments p 
            JOIN orders o ON p.order_id = o.id 
            JOIN users u ON o.user_id = u.id 
            ORDER BY p.id DESC";
            
    $stmt = $pdo->query($sql);
    $payments = $stmt->fetchAll();

    // Calculate total collected revenue
    $totalPaidStmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'paid' OR status = 'completed'");
    $totalPaid = $totalPaidStmt->fetchColumn() ?: 0;

} catch (\PDOException $e) {
    die("Database Error on Payments Page: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Transactions - Jengo Hardware Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 text-stone-800 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-stone-900 text-white min-h-screen p-6 flex flex-col justify-between flex-shrink-0">
        <div>
            <h2 class="text-xl font-black text-orange-500 tracking-wider mb-8">JENGO ADMIN</h2>
            <nav class="space-y-3">
                <a href="dashboard.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">Dashboard</a>
                <a href="payments.php" class="block py-2 px-3 rounded bg-orange-600 font-bold text-white">Payment Logs</a>
                <a href="../public/shop.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">View Shop</a>
            </nav>
        </div>
        <a href="inventory.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">Inventory Catalog</a>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-8 overflow-y-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold">Payment Transactions</h1>
                <p class="text-stone-500 text-sm">Monitor all incoming M-Pesa STK Push and Paystack transactions.</p>
            </div>
            <div class="bg-white p-4 rounded-lg border shadow-sm text-right">
                <span class="text-xs text-stone-500 font-bold uppercase block">Total Revenue Cleared</span>
                <span class="text-2xl font-black text-green-600">KES <?= number_format($totalPaid, 2) ?></span>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-stone-200">
            <h2 class="text-lg font-bold mb-4">Transaction Records</h2>
            <?php if (empty($payments)): ?>
                <div class="text-center py-12">
                    <p class="text-stone-500 text-base mb-2">No payment transactions found in database.</p>
                    <p class="text-xs text-stone-400">Transactions will automatically record here when users pay via M-Pesa or Paystack.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b bg-stone-50 text-stone-600 text-xs uppercase tracking-wider">
                                <th class="p-3">Payment ID</th>
                                <th class="p-3">Order ID</th>
                                <th class="p-3">Customer</th>
                                <th class="p-3">Gateway</th>
                                <th class="p-3">Amount</th>
                                <th class="p-3">Receipt / Ref</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-sm">
                            <?php foreach ($payments as $pay): ?>
                                <tr class="hover:bg-stone-50 transition">
                                    <td class="p-3 font-mono font-bold">#<?= $pay['id'] ?></td>
                                    <td class="p-3 font-semibold text-orange-600">
                                        <a href="dashboard.php" class="hover:underline">#<?= $pay['order_id'] ?></a>
                                    </td>
                                    <td class="p-3">
                                        <div class="font-bold text-stone-900"><?= htmlspecialchars($pay['customer_name']) ?></div>
                                        <div class="text-xs text-stone-400"><?= htmlspecialchars($pay['customer_email']) ?></div>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-extrabold uppercase rounded <?= strtolower($pay['method']) === 'mpesa' ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800' ?>">
                                            <?= htmlspecialchars($pay['method']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-black text-stone-800">KES <?= number_format($pay['amount'], 2) ?></td>
                                    <td class="p-3 font-mono text-xs text-stone-600"><?= htmlspecialchars($pay['receipt_or_reference'] ?? 'N/A') ?></td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-bold uppercase <?= in_array(strtolower($pay['status']), ['paid', 'completed', 'success']) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= htmlspecialchars($pay['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-xs text-stone-500 whitespace-nowrap"><?= htmlspecialchars($pay['created_at'] ?? 'N/A') ?></td>
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