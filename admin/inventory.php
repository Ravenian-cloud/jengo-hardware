<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

// Auth Guard: Allow both Admin and Attendant
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'attendant'])) {
    header('Location: ../public/login.php');
    exit;
}

$msg = '';
$error = '';

// Handle Adding New Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name        = trim($_POST['name'] ?? '');
    $category    = trim($_POST['category'] ?? 'General Hardware');
    $price       = (float)($_POST['price'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (empty($name) || $price <= 0 || $stock < 0) {
        $error = "Product name, positive price, and non-negative stock are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $price, $stock, $description]);
            $msg = "Product '{$name}' successfully added to inventory!";
        } catch (\PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Quick Stock Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $new_stock  = (int)($_POST['stock'] ?? 0);

    if ($product_id > 0 && $new_stock >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $stmt->execute([$new_stock, $product_id]);
            $msg = "Stock level updated for Product #{$product_id}.";
        } catch (\PDOException $e) {
            $error = "Failed to update stock: " . $e->getMessage();
        }
    }
}

// Fetch All Products
try {
    $productsStmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
    $products = $productsStmt->fetchAll();
} catch (\PDOException $e) {
    die("Database Error on Inventory Page: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 text-stone-800 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-stone-900 text-white min-h-screen p-6 flex flex-col justify-between flex-shrink-0">
        <div>
            <h2 class="text-xl font-black text-orange-500 tracking-wider mb-8">JENGO STORE</h2>
            <nav class="space-y-3">
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="dashboard.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">Dashboard</a>
                    <a href="payments.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">Payment Logs</a>
                    <a href="attendants.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">Enroll Attendants</a>
                <?php else: ?>
                    <a href="../attendant/dashboard.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">Fulfillment Hub</a>
                <?php endif; ?>
                <a href="inventory.php" class="block py-2 px-3 rounded bg-orange-600 font-bold text-white">Inventory Catalog</a>
                <a href="../public/shop.php" class="block py-2 px-3 rounded hover:bg-stone-800 font-medium">View Shop</a>
            </nav>
        </div>
        <a href="../public/logout.php" class="block text-center bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded">Logout</a>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-8 overflow-y-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold">Inventory Management</h1>
                <p class="text-stone-500 text-sm">Add new items and manage stock levels for the store.</p>
            </div>
            <span class="text-xs bg-stone-200 text-stone-700 px-3 py-1.5 rounded font-bold uppercase">
                Logged in as: <?= htmlspecialchars($_SESSION['user_role']) ?>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Add Product Form -->
            <div class="bg-white p-6 rounded-lg shadow-sm border border-stone-200 h-fit">
                <h2 class="text-lg font-bold mb-4 text-stone-900">Add New Product</h2>
                <form method="POST" action="inventory.php" class="space-y-4">
                    <input type="hidden" name="action" value="add_product">

                    <div>
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Product Name</label>
                        <input type="text" name="name" required placeholder="e.g. Cement 50kg" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Category</label>
                        <select name="category" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500 bg-white">
                            <option value="Building Materials">Building Materials</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Tools & Hardware">Tools & Hardware</option>
                            <option value="Paints & Finishes">Paints & Finishes</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Price (KES)</label>
                            <input type="number" step="0.01" name="price" required placeholder="750.00" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Stock Quantity</label>
                            <input type="number" name="stock" required placeholder="50" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Description</label>
                        <textarea name="description" rows="3" placeholder="Brief product specifications..." class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-2.5 rounded transition text-sm">
                        Add to Store Inventory
                    </button>
                </form>
            </div>

            <!-- Existing Products Table -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm border border-stone-200">
                <h2 class="text-lg font-bold mb-4 text-stone-900">Current Inventory Catalog</h2>
                <?php if (empty($products)): ?>
                    <p class="text-stone-500 text-sm">No products in inventory yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b bg-stone-50 text-stone-600 text-xs uppercase tracking-wider">
                                    <th class="p-3">ID</th>
                                    <th class="p-3">Product</th>
                                    <th class="p-3">Category</th>
                                    <th class="p-3">Price</th>
                                    <th class="p-3">Stock Level</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 text-sm">
                                <?php foreach ($products as $prod): ?>
                                    <tr class="hover:bg-stone-50 transition">
                                        <td class="p-3 font-mono font-bold">#<?= $prod['id'] ?></td>
                                        <td class="p-3">
                                            <div class="font-bold text-stone-900"><?= htmlspecialchars($prod['name']) ?></div>
                                            <div class="text-xs text-stone-400 truncate max-w-xs"><?= htmlspecialchars($prod['description'] ?? '') ?></div>
                                        </td>
                                        <td class="p-3 text-xs text-stone-500 uppercase"><?= htmlspecialchars($prod['category'] ?? 'General') ?></td>
                                        <td class="p-3 font-bold text-stone-800">KES <?= number_format($prod['price'], 2) ?></td>
                                        <td class="p-3">
                                            <form method="POST" action="inventory.php" class="flex items-center space-x-2">
                                                <input type="hidden" name="action" value="update_stock">
                                                <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                                <input type="number" name="stock" value="<?= (int)$prod['stock'] ?>" class="w-20 border rounded p-1 text-xs font-bold text-center">
                                                <button type="submit" class="bg-stone-800 hover:bg-stone-900 text-white text-xs px-2.5 py-1 rounded font-bold">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

</body>
</html>