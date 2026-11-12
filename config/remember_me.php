<?php

const REMEMBER_ME_COOKIE = 'jengo_remember';
const REMEMBER_ME_LIFETIME = 2592000;

function rememberMeCookieOptions(int $expires): array
{
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';

    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function clearRememberMeCookie(): void
{
    setcookie(REMEMBER_ME_COOKIE, '', rememberMeCookieOptions(time() - 3600));
    unset($_COOKIE[REMEMBER_ME_COOKIE]);
}

function forgetRememberMeToken(PDO $pdo): void
{
    if (!isset($_COOKIE[REMEMBER_ME_COOKIE])) {
        return;
    }

    $cookie = $_COOKIE[REMEMBER_ME_COOKIE];

    if (is_string($cookie) && preg_match('/\A([a-f0-9]{32}):[a-f0-9]{64}\z/i', $cookie, $matches)) {
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->execute([$matches[1]]);
    }

    clearRememberMeCookie();
}

function issueRememberMeToken(PDO $pdo, ?int $userId, bool $systemAdmin = false): void
{
    if ($systemAdmin) {
        $userId = null;
        $accountType = 'system_admin';
    } elseif ($userId !== null) {
        $accountType = 'user';
    } else {
        throw new InvalidArgumentException('A user ID is required for a user remember token.');
    }

    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);

    $stmt = $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, account_type, selector, validator_hash, expires_at)
         VALUES (?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))'
    );
    $stmt->execute([$userId, $accountType, $selector, $validatorHash]);

    setcookie(
        REMEMBER_ME_COOKIE,
        $selector . ':' . $validator,
        rememberMeCookieOptions(time() + REMEMBER_ME_LIFETIME)
    );
    $_COOKIE[REMEMBER_ME_COOKIE] = $selector . ':' . $validator;
}

function restoreRememberMeUser(PDO $pdo): ?array
{
    if (!isset($_COOKIE[REMEMBER_ME_COOKIE])) {
        return null;
    }

    $cookie = $_COOKIE[REMEMBER_ME_COOKIE];

    if (!is_string($cookie) || !preg_match('/\A([a-f0-9]{32}):([a-f0-9]{64})\z/i', $cookie, $matches)) {
        clearRememberMeCookie();
        return null;
    }

    $selector = $matches[1];
    $validator = $matches[2];
    $stmt = $pdo->prepare(
        'SELECT rt.id AS token_id, rt.user_id, rt.account_type, rt.validator_hash,
                u.id AS account_id, u.name, u.role
         FROM remember_tokens rt
         LEFT JOIN users u ON u.id = rt.user_id
         WHERE rt.selector = ? AND rt.expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $stmt->execute([$selector]);
    $token = $stmt->fetch();

    if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        $deleteStmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $deleteStmt->execute([$selector]);
        clearRememberMeCookie();
        return null;
    }

    if ($token['account_type'] === 'system_admin') {
        $user = [
            'id' => 999999,
            'name' => 'System Administrator',
            'role' => 'admin',
        ];
    } elseif ($token['account_type'] === 'user' && $token['account_id'] !== null) {
        $user = [
            'id' => (int) $token['account_id'],
            'name' => $token['name'],
            'role' => $token['role'],
        ];
    } else {
        $deleteStmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $deleteStmt->execute([$selector]);
        clearRememberMeCookie();
        return null;
    }

    $nextSelector = bin2hex(random_bytes(16));
    $nextValidator = bin2hex(random_bytes(32));
    $updateStmt = $pdo->prepare(
        'UPDATE remember_tokens
         SET selector = ?, validator_hash = ?,
             expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY)
         WHERE id = ?'
    );
    $updateStmt->execute([$nextSelector, hash('sha256', $nextValidator), $token['token_id']]);

    setcookie(
        REMEMBER_ME_COOKIE,
        $nextSelector . ':' . $nextValidator,
        rememberMeCookieOptions(time() + REMEMBER_ME_LIFETIME)
    );
    $_COOKIE[REMEMBER_ME_COOKIE] = $nextSelector . ':' . $nextValidator;

    return $user;
}

function restoreRememberMeSession(PDO $pdo): void
{
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if (
        session_status() !== PHP_SESSION_ACTIVE
        || isset($_SESSION['user_id'])
        || ($scriptName === 'login.php' && $requestMethod === 'POST')
        || !isset($_COOKIE[REMEMBER_ME_COOKIE])
    ) {
        return;
    }

    $user = restoreRememberMeUser($pdo);
    if ($user === null) {
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
}
