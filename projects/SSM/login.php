<?php
require_once __DIR__ . '/includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        try {
            $result = loginAdmin($email, $password);
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            }
            $error = $result['message'];
        } catch (PDOException $e) {
            $error = dbErrorMessage($e);
        }
    }
}

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSM Login | Swiggy Support Operations</title>
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
        .login-wrapper {
            display: flex; width: 1100px; max-width: 100%; background: white;
            border-radius: 48px; overflow: hidden; box-shadow: 0 30px 60px rgba(0,0,0,0.12);
        }
        .brand-panel {
            flex: 1; background: linear-gradient(145deg, #FC8019, #FF5200);
            padding: 48px; display: flex; flex-direction: column; justify-content: center; color: white;
        }
        .brand-panel i { font-size: 70px; margin-bottom: 24px; }
        .brand-panel h2 { font-size: 2rem; margin-bottom: 16px; }
        .brand-panel p { opacity: 0.9; margin-bottom: 32px; line-height: 1.4; }
        .security-badge {
            background: rgba(255,255,255,0.2); border-radius: 40px; padding: 12px 20px;
            display: inline-flex; align-items: center; gap: 12px; width: fit-content;
        }
        .form-panel { flex: 1; padding: 48px; background: white; }
        .form-panel h2 { font-size: 28px; color: #1E2A3E; }
        input {
            width: 100%; padding: 14px 16px; margin: 12px 0; border-radius: 32px;
            border: 1px solid #E2E8F0; font-size: 0.95rem;
        }
        input:focus { outline: none; border-color: var(--swiggy-orange); box-shadow: 0 0 0 3px rgba(252,128,25,0.2); }
        .login-btn {
            background: var(--swiggy-orange); color: white; width: 100%; padding: 14px;
            border: none; border-radius: 40px; font-weight: 700; cursor: pointer; margin-top: 16px;
        }
        .login-btn:hover { background: #FF5200; }
        .alert { padding: 12px 16px; border-radius: 16px; margin-bottom: 16px; font-size: 14px; }
        .alert-error { background: #FEE2E2; color: #B91C1C; }
        .alert-success { background: #DCFCE7; color: #15803D; }
        .register-link { text-align: center; margin-top: 20px; color: #5A6E85; }
        .register-link a { color: var(--swiggy-orange); font-weight: 600; text-decoration: none; }
        @media (max-width: 768px) {
            .login-wrapper { flex-direction: column; border-radius: 32px; }
            .brand-panel { display: none; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="brand-panel">
            <i class="fas fa-headset"></i>
            <h2>SSM · Swiggy Support Management</h2>
            <p>Centralized operations for agents, real‑time resolution intelligence.</p>
            <div class="security-badge"><i class="fas fa-shield-alt"></i> Internal Swiggy Operations Platform</div>
        </div>
        <div class="form-panel">
            <h2>Welcome back</h2>
            <p style="margin-bottom: 28px; color: #5A6E85;">Sign in to your support console</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">Account created! Please login.</div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="login-btn">Login →</button>
            </form>
            <p class="register-link">Don't have an account? <a href="register.php">Create admin account</a></p>
            <p class="register-link"><a href="index.php">← Back to home</a></p>
        </div>
    </div>
</body>
</html>
