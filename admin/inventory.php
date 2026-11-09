<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/product_images.php';

// Auth Guard: Allow both Admin and Attendant
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'attendant'])) {
    header('Location: ../public/login.php');
    exit;
}

$msg = '';
$error = '';
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Handle Adding New Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name        = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = (float)($_POST['price'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $categoryName = false;

    if ($category_id > 0) {
        $categoryStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $categoryStmt->execute([$category_id]);
        $categoryName = $categoryStmt->fetchColumn();
    }

    if ($name === '' || $price <= 0 || $stock < 0 || $categoryName === false) {
        $error = "Product name, valid category, positive price, and non-negative stock are required.";
    } else {
        try {
            $photo = product_image_for_item($name, $categoryName);
            $stmt = $pdo->prepare("INSERT INTO products (category_id, name, category, price, stock, description, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$category_id, $name, $categoryName, $price, $stock, $description, $photo]);
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
    <link rel="stylesheet" href="../public/css/liquid-glass.css">
    <script src="../public/js/theme-mode.js" defer></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 text-white min-h-screen p-6 flex flex-col justify-between flex-shrink-0">
        <div>
            <h2 class="text-xl font-black text-gray-300 tracking-wider mb-8">JENGO STORE</h2>
            <nav class="space-y-3">
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="dashboard.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">Dashboard</a>
                    <a href="payments.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">Payment Logs</a>
                    <a href="attendants.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">Enroll Attendants</a>
                <?php else: ?>
                    <a href="../attendant/dashboard.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">Fulfillment Hub</a>
                <?php endif; ?>
                <a href="inventory.php" class="block py-2 px-3 rounded bg-gray-800 font-bold text-white">Inventory Catalog</a>
                <a href="../public/shop.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">View Shop</a>
            </nav>
        </div>
        <a href="../public/logout.php" class="block text-center bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 rounded">Logout</a>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-8 overflow-y-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold">Inventory Management</h1>
                <p class="text-gray-500 text-sm">Add items with an automatic representative photo and manage store stock levels.</p>
            </div>
            <span class="text-xs bg-gray-200 text-gray-700 px-3 py-1.5 rounded font-bold uppercase">
                Logged in as: <?= htmlspecialchars($_SESSION['user_role']) ?>
            </span>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="mb-6 p-4 bg-gray-100 border-l-4 border-gray-500 text-gray-800 rounded text-sm font-medium">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 bg-gray-200 border-l-4 border-gray-600 text-gray-900 rounded text-sm font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Add Product Form -->
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 h-fit">
                <h2 class="text-lg font-bold mb-4 text-gray-900">Add New Product</h2>
                <div class="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm font-bold text-gray-800">Scan a product QR code</p>
                    <p class="mt-1 text-xs text-gray-600">A valid product QR automatically fills and submits the inventory form.</p>
                    <button type="button" id="start-qr-scan" class="mt-3 rounded bg-gray-800 px-4 py-2 text-sm font-bold text-white hover:bg-gray-700">
                        Scan QR Code
                    </button>
                    <div id="qr-reader" class="mt-3 hidden w-full"></div>
                    <p id="qr-scan-status" class="mt-2 text-xs text-gray-600" aria-live="polite"></p>
                    <details class="mt-2 text-xs text-gray-500">
                        <summary class="cursor-pointer">QR format</summary>
                        <code class="mt-1 block break-all">{"name":"Cement 50kg","category":"Cement","price":750.00,"stock":20,"description":"General purpose cement"}</code>
                    </details>
                </div>
                <form method="POST" action="inventory.php" class="space-y-4">
                    <input type="hidden" name="action" value="add_product">

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Product Name</label>
                        <input type="text" id="product-name" name="name" required placeholder="e.g. Cement 50kg" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category</label>
                        <select id="product-category" name="category_id" required class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500 bg-white">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Price (KES)</label>
                            <input type="number" id="product-price" step="0.01" name="price" required placeholder="750.00" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Stock Quantity</label>
                            <input type="number" id="product-stock" name="stock" required placeholder="50" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Description</label>
                        <textarea id="product-description" name="description" rows="3" placeholder="Brief product specifications..." class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-gray-800 hover:bg-gray-700 text-white font-bold py-2.5 rounded transition text-sm">
                        Add to Store Inventory
                    </button>
                </form>
            </div>

            <!-- Existing Products Table -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-bold mb-4 text-gray-900">Current Inventory Catalog</h2>
                <?php if (empty($products)): ?>
                    <p class="text-gray-500 text-sm">No products in inventory yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
                                    <th class="p-3">ID</th>
                                    <th class="p-3">Image</th>
                                    <th class="p-3">Product</th>
                                    <th class="p-3">Category</th>
                                    <th class="p-3">Price</th>
                                    <th class="p-3">Stock Level</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php foreach ($products as $prod): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="p-3 font-mono font-bold">#<?= $prod['id'] ?></td>
                                        <td class="p-3">
                                            <img src="../public/<?= htmlspecialchars(product_image_url($prod['photo'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?>" class="h-12 w-12 rounded object-cover">
                                        </td>
                                        <td class="p-3">
                                            <div class="font-bold text-gray-900"><?= htmlspecialchars($prod['name']) ?></div>
                                            <div class="text-xs text-gray-400 truncate max-w-xs"><?= htmlspecialchars($prod['description'] ?? '') ?></div>
                                        </td>
                                        <td class="p-3 text-xs text-gray-500 uppercase"><?= htmlspecialchars($prod['category'] ?? 'General') ?></td>
                                        <td class="p-3 font-bold text-gray-800">KES <?= number_format($prod['price'], 2) ?></td>
                                        <td class="p-3">
                                            <form method="POST" action="inventory.php" class="flex items-center space-x-2">
                                                <input type="hidden" name="action" value="update_stock">
                                                <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                                <input type="number" name="stock" value="<?= (int)$prod['stock'] ?>" class="w-20 border rounded p-1 text-xs font-bold text-center">
                                                <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white text-xs px-2.5 py-1 rounded font-bold">Update</button>
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

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const scanButton = document.getElementById('start-qr-scan');
        const scannerElement = document.getElementById('qr-reader');
        const scanStatus = document.getElementById('qr-scan-status');
        const productForm = document.querySelector('form[action="inventory.php"]');
        const categorySelect = document.getElementById('product-category');
        let qrScanner;
        let handlingScan = false;

        scanButton.addEventListener('click', function () {
            if (typeof Html5Qrcode === 'undefined') {
                scanStatus.textContent = 'The QR scanner could not load. Check your internet connection and try again.';
                return;
            }

            scanButton.disabled = true;
            scannerElement.classList.remove('hidden');
            scanStatus.textContent = 'Allow camera access and scan a product QR code.';
            qrScanner = new Html5Qrcode('qr-reader');
            qrScanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                function (decodedText) {
                    if (handlingScan) {
                        return;
                    }
                    handlingScan = true;

                    try {
                        const product = JSON.parse(decodedText);
                        if (!product || Array.isArray(product) || typeof product !== 'object') {
                            throw new Error('QR data must be a JSON object.');
                        }

                        const name = typeof product.name === 'string' ? product.name.trim() : '';
                        const category = typeof product.category === 'string' ? product.category.trim() : '';
                        const price = Number(product.price);
                        const stock = Number(product.stock);
                        const description = product.description === undefined ? '' : product.description;
                        const categoryOption = Array.from(categorySelect.options).find(function (option) {
                            return option.textContent.trim().toLowerCase() === category.toLowerCase();
                        });

                        if (!name || !categoryOption || !Number.isFinite(price) || price <= 0 ||
                            !Number.isInteger(stock) || stock < 0 || typeof description !== 'string') {
                            throw new Error('QR must contain a product name, existing category, positive price, non-negative stock, and optional description.');
                        }

                        document.getElementById('product-name').value = name;
                        categorySelect.value = categoryOption.value;
                        document.getElementById('product-price').value = price;
                        document.getElementById('product-stock').value = stock;
                        document.getElementById('product-description').value = description;
                        scanStatus.textContent = 'Product scanned. Adding it to inventory...';

                        qrScanner.stop().then(function () {
                            HTMLFormElement.prototype.submit.call(productForm);
                        }).catch(function () {
                            scanStatus.textContent = 'Could not stop the camera after scanning. Submit the filled form to continue.';
                            scanButton.disabled = false;
                            handlingScan = false;
                        });
                    } catch (error) {
                        scanStatus.textContent = error.message;
                        handlingScan = false;
                    }
                },
                function () {}
            ).catch(function () {
                scannerElement.classList.add('hidden');
                scanButton.disabled = false;
                scanStatus.textContent = 'Could not start the camera. Allow camera access and use HTTPS or localhost.';
            });
        });
    </script>
</body>
</html>