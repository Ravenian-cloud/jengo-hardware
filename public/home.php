<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

try {
    // Fetch categories
    $catStmt = $pdo->query("SELECT * FROM categories LIMIT 6");
    $categories = $catStmt->fetchAll();

    // Fetch featured products
    $prodStmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 6");
    $products = $prodStmt->fetchAll();
} catch (\PDOException $e) {
    die("Database Error on Home Page: " . $e->getMessage());
}

$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jengo Hardware - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-stone-800 min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-stone-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <a href="home.php" class="text-2xl font-black text-orange-500 tracking-wider">JENGO HARDWARE</a>
            <div class="space-x-6">
                <a href="home.php" class="text-orange-400 font-medium">Home</a>
                <a href="shop.php" class="hover:text-orange-400 font-medium">Shop</a>
                <a href="cart.php" class="hover:text-orange-400 font-medium">Cart (<?= $cartCount ?>)</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="customer_dashboard.php" class="hover:text-orange-400 font-medium">Dashboard</a>
                    <a href="logout.php" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded text-sm font-bold">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="hover:text-orange-400 font-medium">Login</a>
                    <a href="register.php" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded text-sm font-bold">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-stone-800 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-black mb-4">Quality Building & Hardware Supplies</h1>
            <p class="text-stone-300 text-lg mb-8 max-w-2xl mx-auto">Get high-grade cement, steel, plumbing, and electrical materials delivered directly to your site or picked up in-store.</p>
            <a href="shop.php" class="bg-orange-600 hover:bg-orange-700 text-white font-bold px-8 py-3 rounded-lg text-lg inline-block">Browse All Supplies</a>
        </div>
    </div>

    <!-- Categories Section -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        <h2 class="text-2xl font-bold mb-6">Popular Categories</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($categories as $cat): ?>
                <a href="shop.php?category=<?= $cat['id'] ?>" class="bg-white p-6 rounded-lg border text-center font-bold hover:shadow-md hover:border-orange-500 transition">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Featured Products -->
    <div class="max-w-7xl mx-auto px-4 py-8 mb-12 flex-grow">
        <h2 class="text-2xl font-bold mb-6">New Arrivals</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($products as $prod): ?>
                <div class="bg-white border rounded-lg overflow-hidden shadow-sm flex flex-col">
                    <div class="h-40 bg-stone-200 flex items-center justify-center text-stone-500 font-semibold p-4 text-center">
                        <?= htmlspecialchars($prod['name']) ?>
                    </div>
                    <div class="p-5 flex flex-col flex-grow">
                        <span class="text-xs text-orange-600 font-bold uppercase tracking-wider"><?= htmlspecialchars($prod['category_name']) ?></span>
                        <h3 class="text-lg font-bold mt-1 text-stone-900"><?= htmlspecialchars($prod['name']) ?></h3>
                        <div class="mt-auto pt-4 flex justify-between items-center">
                            <span class="text-xl font-extrabold text-stone-800">KES <?= number_format($prod['price'], 2) ?></span>
                            <a href="shop.php" class="text-sm font-bold text-orange-600 hover:underline">View in Shop →</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="bg-stone-900 text-stone-400 py-6 text-center text-sm border-t border-stone-800">
        &copy; <?= date('Y') ?> Jengo Hardware. All rights reserved.
    </footer>

</body>
</html>