<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';

// Define Dedicated Admin Credentials
define('ADMIN_EMAIL', 'ravenian9114@gmail.com');
define('ADMIN_PASSWORD', 'Admin9114'); // Change this to your desired password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // 1. Check if entering dedicated Admin Credentials
        if (strtolower($email) === strtolower(ADMIN_EMAIL) && $password === ADMIN_PASSWORD) {
            $_SESSION['user_id']   = 999999; // Dedicated admin ID placeholder
            $_SESSION['user_name'] = 'System Administrator';
            $_SESSION['user_role'] = 'admin';

            header('Location: ../admin/dashboard.php');
            exit;
        }

        // 2. Otherwise, authenticate standard database users (Customers / Attendants)
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                // Redirect based on database role
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/dashboard.php');
                } elseif ($user['role'] === 'attendant') {
                    header('Location: ../attendant/dashboard.php');
                } else {
                    header('Location: customer_dashboard.php');
                }
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (\PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full border-t-4 border-orange-500">
        <div class="text-center mb-6">
            <a href="home.php" class="text-2xl font-black text-orange-500 tracking-wider">JENGO HARDWARE</a>
            <p class="text-stone-500 text-sm mt-1">Sign in to your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 text-sm font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-stone-700 mb-1">Email Address</label>
                <input type="email" name="email" required class="w-full border rounded px-3 py-2 text-stone-800 focus:outline-none focus:border-orange-500">
            </div>

            <div>
                <label class="block text-sm font-bold text-stone-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full border rounded px-3 py-2 text-stone-800 focus:outline-none focus:border-orange-500">
            </div>

            <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-2.5 rounded transition">
                Sign In
            </button>
        </form>

        <p class="text-xs text-center text-stone-500 mt-6">
            Don't have an account? <a href="register.php" class="text-orange-600 font-bold hover:underline">Register here</a>
        </p>
    </div>

</body>
</html>