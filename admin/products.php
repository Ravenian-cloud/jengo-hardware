<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/product_images.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

// Handle Adding Product
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $imagePath = null;
    $uploadedFilePath = null;
    $categoryName = false;

    if ($category_id > 0) {
        $categoryStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $categoryStmt->execute([$category_id]);
        $categoryName = $categoryStmt->fetchColumn();
    }

    if ($name === '') {
        $error = 'Product name is required.';
    } elseif ($categoryName === false) {
        $error = 'Please select a valid product category.';
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $image = $_FILES['image'];
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if ($image['error'] !== UPLOAD_ERR_OK) {
            $error = 'The image upload failed. Please try again.';
        } elseif ($image['size'] > 5 * 1024 * 1024) {
            $error = 'The image must be 5 MB or smaller.';
        } else {
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($image['tmp_name']);

            if (!isset($allowedTypes[$mimeType]) || getimagesize($image['tmp_name']) === false) {
                $error = 'Please upload a valid JPG, PNG, or WebP image.';
            } else {
                $uploadDirectory = __DIR__ . '/../public/uploads/products';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    $error = 'Unable to create the product image directory.';
                } else {
                    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
                    $uploadedFilePath = $uploadDirectory . '/' . $fileName;
                    if (move_uploaded_file($image['tmp_name'], $uploadedFilePath)) {
                        $imagePath = 'uploads/products/' . $fileName;
                    } else {
                        $uploadedFilePath = null;
                        $error = 'Unable to save the uploaded image.';
                    }
                }
            }
        }
    }

    if ($error === '') {
        if ($imagePath === null) {
            $imagePath = product_image_for_item($name, $categoryName);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO products (category_id, name, price, stock, photo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$category_id, $name, $price, $stock, $imagePath]);
            header('Location: products.php');
            exit;
        } catch (PDOException $e) {
            if ($uploadedFilePath !== null && is_file($uploadedFilePath)) {
                unlink($uploadedFilePath);
            }
            $error = 'Unable to save the product. Please try again.';
        }
    }
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
    <link rel="stylesheet" href="../public/css/liquid-glass.css">
    <script src="../public/js/theme-mode.js" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">

    <nav class="bg-gray-900 text-white p-4 flex justify-between items-center">
        <div class="space-x-4">
            <a href="dashboard.php" class="font-bold text-gray-300">Admin Dashboard</a>
            <a href="products.php" class="hover:text-gray-300">Products</a>
            <a href="payments.php" class="hover:text-gray-300">Payment Audit Logs</a>
        </div>
        <a href="../public/logout.php" class="bg-gray-700 px-3 py-1 rounded text-sm font-bold">Logout</a>
    </nav>

    <div class="max-w-6xl mx-auto py-8 px-4 grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Add Product Form -->
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-bold mb-4">Add New Product</h3>
            <?php if ($error !== ''): ?>
                <p class="mb-4 rounded bg-gray-200 px-3 py-2 text-sm text-gray-900"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
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
                <div>
                    <label class="block text-sm font-semibold mb-1">Product Image (optional; JPG, PNG, or WebP, max 5 MB)</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full text-sm">
                    <p class="mt-1 text-xs text-gray-500">If omitted, a representative image is selected from the product name and category.</p>
                </div>
                <button type="submit" class="w-full bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 rounded">Save Product</button>
            </form>
        </div>

        <!-- Product Inventory Table -->
        <div class="md:col-span-2 bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-bold mb-4">Current Inventory</h3>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-sm">
                        <th class="p-2">Image</th>
                        <th class="p-2">Name</th>
                        <th class="p-2">Category</th>
                        <th class="p-2">Price</th>
                        <th class="p-2">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr class="border-b">
                            <td class="p-2">
                                <img src="../public/<?= htmlspecialchars(product_image_url($p['photo'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>" class="h-12 w-12 rounded object-cover">
                            </td>
                            <td class="p-2 font-bold"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="p-2 text-sm text-gray-500"><?= htmlspecialchars($p['category_name']) ?></td>
                            <td class="p-2">KES <?= number_format($p['price'], 2) ?></td>
                            <td class="p-2">
                                <span class="px-2 py-0.5 rounded text-xs <?= $p['stock'] <= 5 ? 'bg-gray-200 text-gray-900 font-bold' : 'bg-gray-100 text-gray-800' ?>">
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