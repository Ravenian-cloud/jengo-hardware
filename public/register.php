<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address format.';
    } elseif (!preg_match('/^2547\d{8}$/', $phone) && !preg_match('/^2541\d{8}$/', $phone)) {
        $error = 'Phone number must be in format 2547XXXXXXXX or 2541XXXXXXXX.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                // Hash password securely
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                
                // Insert new customer record
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
                if ($stmt->execute([$name, $email, $phone, $hashedPassword])) {
                    $success = 'Account created successfully! You can now log in.';
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
            }
        } catch (\PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/liquid-glass.css">
    <script src="js/theme-mode.js" defer></script>
</head>
<body class="bg-gray-100 flex flex-col items-center min-h-screen p-4">
    <div class="auth-card bg-white p-8 rounded-lg shadow-md w-full max-w-md border-t-4 border-gray-600">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Jengo Hardware</h2>
        <p class="text-center text-gray-500 mb-6 text-sm">Create a new customer account</p>
        
        <?php if ($error): ?>
            <div class="bg-gray-200 border border-gray-500 text-gray-800 px-4 py-3 rounded mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-gray-100 border border-gray-400 text-gray-700 px-4 py-3 rounded mb-4 text-sm">
                <?= htmlspecialchars($success) ?>
                <a href="login.php" class="underline font-bold ml-1">Login here</a>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-1">Full Name</label>
                <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-1">Email Address</label>
                <input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-1">Phone Number (2547XXXXXXXX)</label>
                <input type="text" name="phone" placeholder="254712345678" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-1">Password</label>
                <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500">
            </div>
            <button type="submit" class="w-full bg-gray-800 hover:bg-gray-700 text-white font-bold py-2.5 rounded-lg transition duration-200">Register</button>
        </form>
        <p class="text-sm text-center text-gray-600 mt-5">Already registered? <a href="login.php" class="text-gray-700 font-bold hover:underline">Log in</a></p>
    </div>
    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>