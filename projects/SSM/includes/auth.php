<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireLoginApi(): void
{
    if (!isLoggedIn()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }
}

function currentAdmin(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT id, employee_id, name, email FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function registerAdmin(string $employeeId, string $name, string $email, string $password): array
{
    $db = getDB();

    $check = $db->prepare('SELECT id FROM admins WHERE email = ? OR employee_id = ?');
    $check->execute([$email, $employeeId]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Employee ID or email already registered.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO admins (employee_id, name, email, password_hash) VALUES (?, ?, ?, ?)');
    $stmt->execute([$employeeId, $name, $email, $hash]);

    return ['success' => true, 'message' => 'Account created successfully. You can now login.'];
}

function loginAdmin(string $email, string $password): array
{
    $stmt = getDB()->prepare('SELECT id, employee_id, name, email, password_hash FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['employee_id'] = $admin['employee_id'];

    return ['success' => true, 'message' => 'Login successful.'];
}

function logoutAdmin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function adminInitials(?string $name): string
{
    if (!$name) {
        return 'SA';
    }
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
