<?php
require_once __DIR__ . '/includes/auth.php';

$error = '';
$success = '';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = trim($_POST['employee_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($employeeId === '' || $name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            $result = registerAdmin($employeeId, $name, $email, $password);
            if ($result['success']) {
                header('Location: login.php?registered=1');
                exit;
            }
            $error = $result['message'];
        } catch (PDOException $e) {
            $error = dbErrorMessage($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | SSM Swiggy Support</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #FEF7F0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        :root { --swiggy-orange: #FC8019; }
        .register-card {
            background: white; width: 480px; max-width: 100%; padding: 40px;
            border-radius: 32px; box-shadow: 0 30px 60px rgba(0,0,0,0.12);
        }
        .register-card h2 { color: #1E2A3E; margin-bottom: 8px; }
        .register-card p { color: #5A6E85; margin-bottom: 24px; }
        input {
            width: 100%; padding: 14px 16px; margin: 8px 0; border-radius: 32px;
            border: 1px solid #E2E8F0; font-size: 0.95rem;
        }
        input:focus { outline: none; border-color: var(--swiggy-orange); }
        .btn {
            background: var(--swiggy-orange); color: white; width: 100%; padding: 14px;
            border: none; border-radius: 40px; font-weight: 700; cursor: pointer; margin-top: 16px;
        }
        .btn:hover { background: #FF5200; }
        .alert { padding: 12px 16px; border-radius: 16px; margin-bottom: 16px; font-size: 14px; }
        .alert-error { background: #FEE2E2; color: #B91C1C; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: var(--swiggy-orange); text-decoration: none; font-weight: 600; }
        .logo { text-align: center; margin-bottom: 24px; color: var(--swiggy-orange); font-size: 2rem; }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="logo"><i class="fas fa-hamburger"></i></div>
        <h2>Create Admin Account</h2>
        <p>Register to access the Swiggy Support dashboard</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="employee_id" placeholder="Employee ID" required value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>">
            <input type="text" name="name" placeholder="Full Name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <input type="password" name="password" placeholder="Password (min 6 chars)" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit" class="btn">Create Account →</button>
        </form>
        <div class="links">
            <a href="login.php">Already have an account? Login</a>
        </div>
    </div>
</body>
</html>
