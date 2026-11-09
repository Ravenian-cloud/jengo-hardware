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

// Auth Guard: Block Admin users from shopping cart actions
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add') {
        $qty = (int)($_POST['quantity'] ?? 1);
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $qty;
        } else {
            $_SESSION['cart'][$product_id] = $qty;
        }
    } elseif ($action === 'update') {
        $qty = (int)($_POST['quantity'] ?? 1);
        if ($qty > 0) {
            $_SESSION['cart'][$product_id] = $qty;
        } else {
            unset($_SESSION['cart'][$product_id]);
        }
    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][$product_id]);
    }
    header('Location: cart.php');
    exit;
}

// Fetch details for items in cart
$cartItems = [];
$totalAmount = 0;
if (!empty($_SESSION['cart'])) {
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
                    'product'  => $p,
                    'quantity' => $qty,
                    'subtotal' => $subtotal
                ];
            }
        }
    } catch (\PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}

$cartCount = array_sum($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/liquid-glass.css">
    <script src="js/theme-mode.js" defer></script>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <nav class="bg-gray-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <a href="home.php" class="text-2xl font-black text-gray-300 tracking-wider">JENGO HARDWARE</a>
            <div class="space-x-6 flex items-center">
                <a href="home.php" class="hover:text-gray-300 font-medium">Home</a>
                <a href="shop.php" class="hover:text-gray-300 font-medium">Shop</a>
                <a href="cart.php" class="text-gray-300 font-medium">Cart (<?= $cartCount ?>)</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (($_SESSION['user_role'] ?? '') === 'attendant'): ?>
                        <a href="../attendant/dashboard.php" class="hover:text-gray-300 font-medium">Attendant Desk</a>
                    <?php else: ?>
                        <a href="customer_dashboard.php" class="hover:text-gray-300 font-medium">Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-sm font-bold">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="hover:text-gray-300 font-medium">Login</a>
                    <a href="register.php" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-sm font-bold">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-4 py-8 flex-grow w-full">
        <h1 class="text-3xl font-bold mb-6">Shopping Cart</h1>

        <?php if (empty($cartItems)): ?>
            <div class="bg-white p-8 rounded-lg shadow-sm text-center">
                <p class="text-gray-500 mb-4">Your cart is currently empty.</p>
                <a href="shop.php" class="bg-gray-800 text-white font-bold px-6 py-2 rounded-lg hover:bg-gray-700">Browse Shop</a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6 border border-gray-200">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 text-sm border-b">
                            <th class="p-4">Product</th>
                            <th class="p-4">Price</th>
                            <th class="p-4">Quantity</th>
                            <th class="p-4">Subtotal</th>
                            <th class="p-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="p-4 font-bold text-gray-900"><?= htmlspecialchars($item['product']['name']) ?></td>
                                <td class="p-4 text-gray-700">KES <?= number_format($item['product']['price'], 2) ?></td>
                                <td class="p-4">
                                    <form action="cart.php" method="POST" class="flex items-center gap-2">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?= $item['product']['id'] ?>">
                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['product']['stock'] ?>" class="w-16 px-2 py-1 border rounded text-center">
                                        <button type="submit" class="text-xs bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded font-semibold text-gray-800">Update</button>
                                    </form>
                                </td>
                                <td class="p-4 font-bold text-gray-900">KES <?= number_format($item['subtotal'], 2) ?></td>
                                <td class="p-4">
                                    <form action="cart.php" method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?= $item['product']['id'] ?>">
                                        <button type="submit" class="text-gray-700 hover:underline text-sm font-semibold">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-between items-center bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div>
                    <span class="text-gray-500 text-sm">Total Amount:</span>
                    <p class="text-3xl font-extrabold text-gray-900">KES <?= number_format($totalAmount, 2) ?></p>
                </div>
                <a href="checkout.php" class="bg-gray-800 hover:bg-gray-700 text-white font-bold px-8 py-3 rounded-lg text-lg transition">Proceed to Checkout</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>