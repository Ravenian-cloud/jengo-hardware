<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/remember_me.php';

$error = '';
if (isset($_GET['remember_logout_failed'])) {
    $error = 'You are signed out, but the saved login could not be revoked. Contact an administrator.';
}

// Define Dedicated Admin Credentials
define('ADMIN_EMAIL', 'ravenian9114@gmail.com');
define('ADMIN_PASSWORD', 'Admin9114'); // Change this to your desired password

function completeLogin(PDO $pdo, ?int $userId, string $name, string $role, bool $rememberMe, bool $systemAdmin = false): void
{
    forgetRememberMeToken($pdo);
    if ($rememberMe) {
        issueRememberMeToken($pdo, $userId, $systemAdmin);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $systemAdmin ? 999999 : $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = $role;

    if ($role === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($role === 'attendant') {
        header('Location: ../attendant/dashboard.php');
    } else {
        header('Location: customer_dashboard.php');
    }
    exit;
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod !== 'POST' && isset($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif (($_SESSION['user_role'] ?? '') === 'attendant') {
        header('Location: ../attendant/dashboard.php');
    } else {
        header('Location: customer_dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rememberMe = ($_POST['remember_me'] ?? '') === '1';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Dedicated admin credentials don't map to a row in the users table.
            if (strtolower($email) === strtolower(ADMIN_EMAIL) && $password === ADMIN_PASSWORD) {
                completeLogin($pdo, null, 'System Administrator', 'admin', $rememberMe, true);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    completeLogin($pdo, (int) $user['id'], $user['name'], $user['role'], $rememberMe);
                } else {
                    $error = "Invalid email or password.";
                }
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
    <link rel="stylesheet" href="css/liquid-glass.css">
    <script src="js/theme-mode.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center p-4">

    <div class="auth-card bg-white p-8 rounded-lg shadow-md max-w-md w-full border-t-4 border-gray-500">
        <div class="text-center mb-6">
            <a href="home.php" class="text-2xl font-black text-gray-300 tracking-wider">JENGO HARDWARE</a>
            <p class="text-gray-500 text-sm mt-1">Sign in to your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-gray-200 border-l-4 border-gray-600 text-gray-800 text-sm font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Email Address</label>
                <input type="email" name="email" required class="w-full border rounded px-3 py-2 text-gray-800 focus:outline-none focus:border-gray-500">
            </div>

            <div>
                <label for="login-password" class="block text-sm font-bold text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <input id="login-password" type="password" name="password" required autocomplete="current-password" class="w-full border rounded px-3 py-2 pr-12 text-gray-800 focus:outline-none focus:border-gray-500">
                    <button type="button" id="toggle-password" aria-label="Show password" aria-controls="login-password" aria-pressed="false" class="absolute right-1 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        <svg id="show-password-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg id="hide-password-icon" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.584 10.587a2 2 0 002.829 2.829" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.88 5.09A10.94 10.94 0 0112 4.89c4.478 0 8.268 2.943 9.542 7a10.96 10.96 0 01-4.152 5.354M6.228 6.228A10.96 10.96 0 012.458 12c1.274 4.057 5.064 7 9.542 7 1.16 0 2.27-.206 3.304-.584" />
                        </svg>
                    </button>
                </div>
            </div>

            <label for="remember-me" class="flex items-center gap-2 text-sm text-gray-700">
                <input id="remember-me" type="checkbox" name="remember_me" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>Keep me logged in</span>
            </label>

            <button type="submit" class="w-full bg-gray-800 hover:bg-gray-700 text-white font-bold py-2.5 rounded transition">
                Sign In
            </button>
        </form>

        <p class="text-xs text-center text-gray-500 mt-6">
            Don't have an account? <a href="register.php" class="text-gray-700 font-bold hover:underline">Register here</a>
        </p>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>
    <script>
        const passwordInput = document.getElementById('login-password');
        const togglePasswordButton = document.getElementById('toggle-password');
        const showPasswordIcon = document.getElementById('show-password-icon');
        const hidePasswordIcon = document.getElementById('hide-password-icon');

        togglePasswordButton.addEventListener('click', () => {
            const isPasswordVisible = passwordInput.type === 'password';
            passwordInput.type = isPasswordVisible ? 'text' : 'password';
            togglePasswordButton.setAttribute('aria-pressed', String(isPasswordVisible));
            togglePasswordButton.setAttribute('aria-label', isPasswordVisible ? 'Hide password' : 'Show password');
            showPasswordIcon.classList.toggle('hidden', isPasswordVisible);
            hidePasswordIcon.classList.toggle('hidden', !isPasswordVisible);
        });
    </script>
</body>
</html>