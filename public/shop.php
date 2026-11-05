<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

try {
    // Fetch categories for filtering
    $catStmt = $pdo->query("SELECT * FROM categories");
    $categories = $catStmt->fetchAll();

    // Build query based on category filter or search query
    $selectedCategory = $_GET['category'] ?? '';
    $searchQuery      = trim($_GET['search'] ?? '');

    $sql = "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE 1=1";
    $params = [];

    if (!empty($selectedCategory)) {
        $sql .= " AND p.category_id = ?";
        $params[] = $selectedCategory;
    }

    if (!empty($searchQuery)) {
        $sql .= " AND p.name LIKE ?";
        $params[] = "%$searchQuery%";
    }

    $sql .= " ORDER BY p.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$isAdmin   = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Products - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-stone-800 min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-stone-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <a href="home.php" class="text-2xl font-black text-orange-500 tracking-wider">JENGO HARDWARE</a>
            <div class="space-x-6 flex items-center">
                <a href="home.php" class="hover:text-orange-400 font-medium">Home</a>
                <a href="shop.php" class="text-orange-400 font-medium">Shop</a>
                
                <?php if ($isAdmin): ?>
                    <a href="../admin/dashboard.php" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded text-sm font-bold">Admin Dashboard</a>
                    <a href="logout.php" class="hover:text-orange-400 text-sm font-medium">Logout</a>
                <?php else: ?>
                    <a href="cart.php" class="hover:text-orange-400 font-medium">Cart (<?= $cartCount ?>)</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (($_SESSION['user_role'] ?? '') === 'attendant'): ?>
                            <a href="../attendant/dashboard.php" class="hover:text-orange-400 font-medium">Attendant Desk</a>
                        <?php else: ?>
                            <a href="customer_dashboard.php" class="hover:text-orange-400 font-medium">Dashboard</a>
                        <?php endif; ?>
                        <a href="logout.php" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded text-sm font-bold">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="hover:text-orange-400 font-medium">Login</a>
                        <a href="register.php" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded text-sm font-bold">Register</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Section -->
    <div class="max-w-7xl mx-auto px-4 py-8 flex-grow w-full">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">Browse Products</h1>
            <?php if ($isAdmin): ?>
                <span class="text-xs bg-amber-100 text-amber-900 border border-amber-300 font-bold px-3 py-1.5 rounded-full flex items-center">
                    🔒 Admin Mode — Catalog Purchasing Disabled
                </span>
            <?php endif; ?>
        </div>

        <!-- Search & Filter Form -->
        <form method="GET" action="shop.php" class="bg-white p-4 rounded-lg shadow-sm mb-8 flex flex-col md:flex-row gap-4">
            <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search products..." class="flex-grow px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
            
            <select name="category" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $selectedCategory == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-bold px-6 py-2 rounded-lg">Filter</button>
            <a href="shop.php" class="bg-stone-200 hover:bg-stone-300 text-stone-700 font-bold px-4 py-2 rounded-lg text-center">Reset</a>
        </form>

        <!-- Product Grid -->
        <?php if (empty($products)): ?>
            <p class="text-center text-stone-500 py-12">No products found matching your search.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($products as $prod): ?>
                    <div class="bg-white border rounded-lg overflow-hidden shadow-sm flex flex-col">
                        <div class="h-48 bg-stone-200 flex items-center justify-center text-stone-500 font-semibold p-4 text-center">
                            <?= htmlspecialchars($prod['name']) ?>
                        </div>
                        <div class="p-5 flex flex-col flex-grow">
                            <span class="text-xs text-orange-600 font-bold uppercase tracking-wider"><?= htmlspecialchars($prod['category_name']) ?></span>
                            <h3 class="text-lg font-bold mt-1 text-stone-900"><?= htmlspecialchars($prod['name']) ?></h3>
                            <div class="mt-auto pt-4 flex justify-between items-center">
                                <span class="text-xl font-extrabold text-stone-800">KES <?= number_format($prod['price'], 2) ?></span>
                                <span class="text-xs px-2 py-1 rounded <?= $prod['stock'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $prod['stock'] > 0 ? 'In Stock (' . $prod['stock'] . ')' : 'Out of Stock' ?>
                                </span>
                            </div>

                            <!-- Purchase Actions vs Admin Badge -->
                            <?php if ($isAdmin): ?>
                                <div class="mt-4 w-full bg-stone-100 border border-stone-200 text-stone-600 text-xs font-bold py-2.5 rounded text-center">
                                    🔒 Admin View Only
                                </div>
                            <?php elseif ($prod['stock'] > 0): ?>
                                <form action="cart.php" method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="w-full bg-stone-800 hover:bg-stone-900 text-white font-bold py-2 rounded transition">Add to Cart</button>
                                </form>
                            <?php else: ?>
                                <button disabled class="mt-4 w-full bg-stone-300 text-stone-500 font-bold py-2 rounded cursor-not-allowed">Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>