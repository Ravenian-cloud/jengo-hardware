<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

if (($_SESSION['user_role'] ?? '') === 'attendant') {
    header('Location: ../attendant/dashboard.php');
    exit;
}

// Auth Guard: Require login to checkout
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: shop.php');
    exit;
}

// Fetch Cart Details
$cartItems = [];
$totalAmount = 0;
try {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    if (!empty($ids)) {
        $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
        $products = $stmt->fetchAll();

        foreach ($products as $p) {
            $qty = $_SESSION['cart'][$p['id']];
            $subtotal = $p['price'] * $qty;
            $totalAmount += $subtotal;
            $cartItems[] = [
                'product' => $p,
                'quantity' => $qty,
                'subtotal' => $subtotal
            ];
        }
    }
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch user defaults
$userStmt = $pdo->prepare("SELECT name, phone FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Jengo Hardware</title>
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
                <a href="shop.php" class="hover:text-gray-300 font-medium">Shop</a>
                <a href="cart.php" class="hover:text-gray-300 font-medium">Cart</a>
                <a href="logout.php" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-sm font-bold">Logout</a>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8 flex-grow w-full">
        <h1 class="text-3xl font-bold mb-6">Order Checkout</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Payment Form -->
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <h2 class="text-xl font-bold mb-4 border-b pb-2">Select Payment Method</h2>
                
                <form action="../api/mpesa_stk.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">M-Pesa Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="254712345678" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-500 focus:outline-none">
                        <span class="text-xs text-gray-500">Format: 2547XXXXXXXX or 2541XXXXXXXX</span>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Delivery Option</label>
                        <select name="delivery_type" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-500 focus:outline-none">
                            <option value="pickup">Store Pickup (Rongai Branch)</option>
                            <option value="delivery">Local Delivery</option>
                        </select>
                    </div>

                    <input type="hidden" name="amount" value="<?= $totalAmount ?>">

                    <div class="pt-4 border-t space-y-3">
                        <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 rounded-lg flex items-center justify-center gap-2">
                            Pay with M-Pesa STK Push
                        </button>
                    </div>
                </form>

            </div>

            <!-- Order Summary -->
            <div class="bg-white p-6 rounded-lg shadow-sm border h-fit">
                <h2 class="text-xl font-bold mb-4 border-b pb-2">Order Summary</h2>
                <div class="space-y-3 mb-4">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="flex justify-between text-sm">
                            <span><?= htmlspecialchars($item['product']['name']) ?> (x<?= $item['quantity'] ?>)</span>
                            <span class="font-bold">KES <?= number_format($item['subtotal'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t pt-4 flex justify-between items-center text-lg font-bold">
                    <span>Total Amount</span>
                    <span class="text-gray-700">KES <?= number_format($totalAmount, 2) ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>