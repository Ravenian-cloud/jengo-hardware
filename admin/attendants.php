<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

// Auth Guard: Require Admin Role
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$msg = '';
$error = '';

// Process Attendant Registration / Role Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_attendant') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error = "Name, email, and password are required.";
    } else {
        try {
            // Check if email already exists
            $checkStmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            $existingUser = $checkStmt->fetch();

            if ($existingUser) {
                // If user exists, promote their role to 'attendant'
                $updateStmt = $pdo->prepare("UPDATE users SET role = 'attendant' WHERE id = ?");
                $updateStmt->execute([$existingUser['id']]);
                $msg = "Existing account with email {$email} has been updated to Attendant role.";
            } else {
                // Register a new attendant account
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'attendant')");
                $insertStmt->execute([$name, $email, $phone, $hashedPassword]);
                $msg = "New attendant account successfully created for {$name}.";
            }
        } catch (\PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch all existing attendants
try {
    $attendantStmt = $pdo->query("SELECT id, name, email, phone, role, created_at FROM users WHERE role = 'attendant' ORDER BY id DESC");
    $attendants = $attendantStmt->fetchAll();
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendants - Jengo Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../public/css/liquid-glass.css">
    <script src="../public/js/theme-mode.js" defer></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 text-white min-h-screen p-6 flex flex-col justify-between flex-shrink-0">
        <div>
            <h2 class="text-xl font-black text-gray-300 tracking-wider mb-8">JENGO ADMIN</h2>
            <nav class="space-y-3">
                <a href="dashboard.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">Dashboard</a>
                <a href="payments.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">Payment Logs</a>
                <a href="attendants.php" class="block py-2 px-3 rounded bg-gray-800 font-bold text-white">Enroll Attendants</a>
                <a href="../public/shop.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">View Shop</a>
            </nav>
        </div>
        <a href="inventory.php" class="block py-2 px-3 rounded hover:bg-gray-800 font-medium">Inventory Catalog</a>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-8 overflow-y-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold">Enroll Store Attendants</h1>
                <p class="text-gray-500 text-sm">Register staff members who can process and fulfill orders from the attendant dashboard.</p>
            </div>
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
            <!-- Add Attendant Form -->
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-bold mb-4 text-gray-900">Add New Attendant</h2>
                <form method="POST" action="attendants.php" class="space-y-4">
                    <input type="hidden" name="action" value="add_attendant">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Full Name</label>
                        <input type="text" name="name" required placeholder="e.g. John Doe" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email Address</label>
                        <input type="email" name="email" required placeholder="attendant@jengo.com" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Phone Number</label>
                        <input type="text" name="phone" placeholder="254700000000" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Password</label>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:border-gray-500">
                    </div>

                    <button type="submit" class="w-full bg-gray-800 hover:bg-gray-700 text-white font-bold py-2.5 rounded transition text-sm">
                        Create Attendant Account
                    </button>
                </form>
            </div>

            <!-- Existing Attendants Table -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-bold mb-4 text-gray-900">Enrolled Attendants</h2>
                <?php if (empty($attendants)): ?>
                    <p class="text-gray-500 text-sm">No dedicated attendants enrolled yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
                                    <th class="p-3">ID</th>
                                    <th class="p-3">Name</th>
                                    <th class="p-3">Email</th>
                                    <th class="p-3">Phone</th>
                                    <th class="p-3">Role</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php foreach ($attendants as $att): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="p-3 font-mono font-bold">#<?= $att['id'] ?></td>
                                        <td class="p-3 font-bold text-gray-900"><?= htmlspecialchars($att['name']) ?></td>
                                        <td class="p-3 text-gray-600"><?= htmlspecialchars($att['email']) ?></td>
                                        <td class="p-3 text-gray-500 text-xs font-mono"><?= htmlspecialchars($att['phone'] ?? 'N/A') ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 text-xs font-extrabold uppercase rounded bg-gray-200 text-gray-900">
                                                <?= htmlspecialchars($att['role']) ?>
                                            </span>
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