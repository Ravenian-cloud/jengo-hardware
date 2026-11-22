<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? '';
$canWriteReview = $isLoggedIn && $userRole === 'customer';
$isReviewReadOnly = in_array($userRole, ['admin', 'attendant'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canWriteReview) {
        if (!$isLoggedIn && !$isReviewReadOnly) {
            $_SESSION['review_flash_error'] = 'Please log in to write a review.';
            header('Location: login.php');
            exit;
        }

        $error = 'Admin and attendant accounts can only read customer reviews.';
    } elseif (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Your session has expired. Please try again.';
    } else {
        $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5]
        ]);
        $title = trim($_POST['title'] ?? '');
        $review = trim($_POST['review'] ?? '');

        if ($rating === false || $rating === null) {
            $error = 'Choose a rating from 1 to 5 stars.';
        } elseif ($title === '' || mb_strlen($title) > 150) {
            $error = 'Enter a review title of 150 characters or fewer.';
        } elseif ($review === '' || mb_strlen($review) > 2000) {
            $error = 'Write a review of 2000 characters or fewer.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO reviews (user_id, customer_name, rating, title, review)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name), rating = VALUES(rating),
                     title = VALUES(title), review = VALUES(review), created_at = CURRENT_TIMESTAMP'
                );
                $stmt->execute([
                    (int) $_SESSION['user_id'],
                    $_SESSION['user_name'] ?? 'Customer',
                    $rating,
                    $title,
                    $review
                ]);
                $success = 'Thank you. Your review has been published.';
            } catch (PDOException $e) {
                $error = 'We could not publish your review. Please try again.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $reviews = $pdo->query(
        'SELECT id, customer_name, rating, title, review, created_at
         FROM reviews
         ORDER BY created_at DESC, id DESC
         LIMIT 50'
    )->fetchAll();

    $summary = $pdo->query(
        'SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating
         FROM reviews'
    )->fetch();
} catch (PDOException $e) {
    $reviews = [];
    $summary = ['review_count' => 0, 'average_rating' => 0];
    $error = $error ?: 'Reviews are temporarily unavailable.';
}

$averageRating = (float) ($summary['average_rating'] ?? 0);
$reviewCount = (int) ($summary['review_count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - Jengo Hardware</title>
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
                <a href="reviews.php" class="text-gray-300 font-medium">Reviews</a>
                <?php if (($isLoggedIn) && ($_SESSION['user_role'] ?? '') === 'customer'): ?>
                    <a href="customer_dashboard.php" class="hover:text-gray-300 font-medium">Dashboard</a>
                <?php endif; ?>
                <?php if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'attendant'], true) && !isset($_SESSION['user_id'])): ?>
                    <a href="cart.php" class="hover:text-gray-300 font-medium">Cart (<?= $cartCount ?>)</a>
                    <a href="login.php" class="hover:text-gray-300 font-medium">Login</a>
                    <a href="register.php" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-sm font-bold">Register</a>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-sm font-bold">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-10 flex-grow w-full">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-8">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-gray-600 mb-2">Community feedback</p>
                <h1 class="text-4xl font-black">Customer Reviews</h1>
            </div>
            <div class="bg-white border rounded-lg px-6 py-4 text-center shadow-sm">
                <div class="text-3xl font-black"><?= number_format($averageRating, 1) ?> / 5</div>
                <div class="text-amber-500 text-lg" aria-label="<?= number_format($averageRating, 1) ?> out of 5 stars">
                    <?= str_repeat('★', (int) round($averageRating)) ?><?= str_repeat('☆', 5 - (int) round($averageRating)) ?>
                </div>
                <p class="text-sm text-gray-500"><?= $reviewCount ?> <?= $reviewCount === 1 ? 'review' : 'reviews' ?></p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-300 text-green-900 rounded-lg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-300 text-red-900 rounded-lg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <section class="lg:col-span-2">
                <?php if (empty($reviews)): ?>
                    <div class="bg-white border rounded-lg p-8 text-center shadow-sm">
                        <h2 class="text-xl font-bold mb-2">Be the first to review Jengo Hardware</h2>
                        <p class="text-gray-500">
                            <?= $isReviewReadOnly ? 'No customer reviews have been submitted yet.' : 'Share your experience with our materials and service.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="space-y-5">
                        <?php foreach ($reviews as $item): ?>
                            <article class="bg-white border rounded-lg p-6 shadow-sm">
                                <div class="flex justify-between gap-4">
                                    <div>
                                        <div class="text-amber-500" aria-label="<?= (int) $item['rating'] ?> out of 5 stars">
                                            <?= str_repeat('★', (int) $item['rating']) ?><?= str_repeat('☆', 5 - (int) $item['rating']) ?>
                                        </div>
                                        <h2 class="text-xl font-bold mt-2"><?= htmlspecialchars($item['title']) ?></h2>
                                    </div>
                                    <time class="text-xs text-gray-500 whitespace-nowrap" datetime="<?= htmlspecialchars($item['created_at']) ?>">
                                        <?= date('M j, Y', strtotime($item['created_at'])) ?>
                                    </time>
                                </div>
                                <p class="mt-3 text-gray-700 whitespace-pre-line"><?= htmlspecialchars($item['review']) ?></p>
                                <p class="mt-4 text-sm font-bold text-gray-600">— <?= htmlspecialchars($item['customer_name']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside>
                <div class="bg-white border rounded-lg p-6 shadow-sm lg:sticky lg:top-6">
                    <?php if ($canWriteReview): ?>
                        <h2 class="text-xl font-bold mb-1">Share your experience</h2>
                        <p class="text-sm text-gray-500 mb-5">You can submit or update one review.</p>
                        <form method="POST" action="reviews.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <fieldset class="mb-5">
                                <legend class="block text-sm font-bold mb-2">Rating</legend>
                                <div class="flex gap-2" aria-label="Choose a rating">
                                    <?php for ($star = 5; $star >= 1; $star--): ?>
                                        <input class="sr-only peer" type="radio" id="star<?= $star ?>" name="rating" value="<?= $star ?>" required>
                                        <label for="star<?= $star ?>" class="text-3xl text-gray-300 cursor-pointer peer-checked:text-amber-500 hover:text-amber-500">★</label>
                                    <?php endfor; ?>
                                </div>
                            </fieldset>
                            <label class="block text-sm font-bold mb-2" for="title">Review title</label>
                            <input id="title" name="title" maxlength="150" required class="w-full px-4 py-2 border rounded-lg mb-4" placeholder="Great service">
                            <label class="block text-sm font-bold mb-2" for="review">Your review</label>
                            <textarea id="review" name="review" maxlength="2000" required rows="5" class="w-full px-4 py-2 border rounded-lg" placeholder="Tell us about your experience"></textarea>
                            <button type="submit" class="mt-4 w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-2.5 rounded-lg">Publish Review</button>
                        </form>
                    <?php elseif ($isReviewReadOnly): ?>
                        <h2 class="text-xl font-bold mb-2">Read-only access</h2>
                        <p class="text-sm text-gray-600">
                            <?= $userRole === 'admin' ? 'Admin accounts' : 'Attendant accounts' ?> can view customer reviews but cannot write or update them.
                        </p>
                    <?php else: ?>
                        <h2 class="text-xl font-bold mb-2">Want to leave a review?</h2>
                        <p class="text-sm text-gray-600 mb-5">Sign in to share your experience with Jengo Hardware.</p>
                        <a href="login.php" class="block w-full text-center bg-gray-800 hover:bg-gray-900 text-white font-bold py-2.5 rounded-lg">Log in to Review</a>
                        <a href="register.php" class="block text-center text-sm font-bold text-gray-700 mt-4 hover:underline">Create an account</a>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>

    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
