<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

// Handle Adding Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    
    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, price, stock) VALUES (?, ?, ?, ?)");
    $stmt->execute([$category_id, $name, $price, $stock]);
    header('Location: products.php');
    exit;
}

// Fetch Categories and Products
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Management - Jengo Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 text-stone-800">

    <nav class="bg-stone-900 text-white p-4 flex justify-between items-center">
        <div class="space-x-4">
            <a href="dashboard.php" class="font-bold text-orange-500">Admin Dashboard</a>
            <a href="products.php" class="hover:text-orange-400">Products</a>
            <a href="payments.php" class="hover:text-orange-400">Payment Audit Logs</a>
        </div>
        <a href="../public/logout.php" class="bg-red-600 px-3 py-1 rounded text-sm font-bold">Logout</a>
    </nav>

    <div class="max-w-6xl mx-auto py-8 px-4 grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Add Product Form -->
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-bold mb-4">Add New Product</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_product" value="1">
                <div>
                    <label class="block text-sm font-semibold mb-1">Product Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Category</label>
                    <select name="category_id" class="w-full px-3 py-2 border rounded">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Price (KES)</label>
                    <input type="number" step="0.01" name="price" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Initial Stock Quantity</label>
                    <input type="number" name="stock" required class="w-full px-3 py-2 border rounded">
                </div>
                <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 rounded">Save Product</button>
            </form>
        </div>

        <!-- Product Inventory Table -->
        <div class="md:col-span-2 bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-bold mb-4">Current Inventory</h3>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-stone-100 text-stone-600 text-sm">
                        <th class="p-2">Name</th>
                        <th class="p-2">Category</th>
                        <th class="p-2">Price</th>
                        <th class="p-2">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr class="border-b">
                            <td class="p-2 font-bold"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="p-2 text-sm text-stone-500"><?= htmlspecialchars($p['category_name']) ?></td>
                            <td class="p-2">KES <?= number_format($p['price'], 2) ?></td>
                            <td class="p-2">
                                <span class="px-2 py-0.5 rounded text-xs <?= $p['stock'] <= 5 ? 'bg-red-100 text-red-800 font-bold' : 'bg-green-100 text-green-800' ?>">
                                    <?= $p['stock'] ?> units
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>