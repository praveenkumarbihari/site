<?php
require_once __DIR__ . '/includes/auth.php';
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
    <title>SSM | Swiggy Support Management – Operations Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #FFF9F5 0%, #FFEFE4 100%);
            color: #1E2A3E;
            overflow-x: hidden;
        }
        :root {
            --swiggy-orange: #FC8019;
            --glass-white: rgba(255, 255, 255, 0.88);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 32px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 24px 0; flex-wrap: wrap; }
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo i { font-size: 38px; color: var(--swiggy-orange); }
        .logo h1 {
            font-size: 28px; font-weight: 800;
            background: linear-gradient(135deg, #FC8019, #FF5200);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .logo span { font-size: 14px; font-weight: 500; color: #5A6E85; }
        .nav-buttons { display: flex; gap: 16px; }
        .btn-primary {
            background: var(--swiggy-orange); border: none; padding: 12px 32px; border-radius: 40px;
            color: white; font-weight: 600; cursor: pointer; transition: all 0.2s;
            box-shadow: 0 4px 8px rgba(252, 128, 25, 0.2); text-decoration: none; display: inline-block;
        }
        .btn-primary:hover { background: #FF5200; transform: scale(1.02); }
        .btn-outline {
            background: transparent; border: 1px solid #FC8019; padding: 12px 32px; border-radius: 40px;
            color: #FC8019; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block;
        }
        .btn-outline:hover { background: rgba(252, 128, 25, 0.08); }
        .hero { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 80px 0 60px; }
        .hero h1 {
            font-size: 3.8rem; font-weight: 800;
            background: linear-gradient(145deg, #1E2A3E, #FC8019);
            -webkit-background-clip: text; background-clip: text; color: transparent; max-width: 900px;
        }
        .hero p { font-size: 1.3rem; color: #4B5C76; margin: 24px 0 32px; max-width: 680px; }
        .hero-stats { display: flex; gap: 48px; justify-content: center; margin-top: 48px; flex-wrap: wrap; }
        .stat-card {
            background: var(--glass-white); backdrop-filter: blur(4px); padding: 20px 32px;
            border-radius: 64px; box-shadow: var(--shadow-xl); text-align: center; min-width: 160px;
        }
        .stat-card h2 { font-size: 2.2rem; color: var(--swiggy-orange); }
        .section-title { font-size: 2.2rem; font-weight: 700; margin: 64px 0 32px; text-align: center; }
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 28px; margin: 40px 0;
        }
        .feature-card {
            background: white; border-radius: 32px; padding: 28px 20px; text-align: center;
            transition: all 0.25s; box-shadow: 0 10px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(252,128,25,0.15);
        }
        .feature-card:hover { transform: translateY(-8px); box-shadow: 0 30px 40px -20px rgba(252,128,25,0.3); border-color: #FC8019; }
        .feature-card i { font-size: 44px; color: var(--swiggy-orange); margin-bottom: 16px; }
        .workflow {
            background: white; border-radius: 60px; padding: 40px; margin: 60px 0;
            display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; box-shadow: var(--shadow-xl);
        }
        .step { display: flex; align-items: center; gap: 12px; font-weight: 600; }
        .step span {
            background: #FC8019; width: 36px; height: 36px; display: inline-flex;
            align-items: center; justify-content: center; border-radius: 60px; color: white;
        }
        footer {
            border-top: 1px solid rgba(0,0,0,0.08); padding: 40px 0; text-align: center;
            display: flex; gap: 40px; justify-content: center; flex-wrap: wrap; margin-top: 40px;
        }
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.2rem; }
            .container { padding: 0 20px; }
            .navbar { flex-direction: column; gap: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="logo">
                <i class="fas fa-hamburger"></i>
                <h1>SSM<span> | Swiggy Support</span></h1>
            </div>
            <div class="nav-buttons">
                <a href="login.php" class="btn-outline">Login</a>
                <a href="register.php" class="btn-primary">Create Account</a>
            </div>
        </div>

        <div class="hero">
            <h1>Swiggy Customer Support <br> Operations Platform</h1>
            <p>Resolve customer issues faster with AI‑assisted workflows & real‑time visibility</p>
            <a href="login.php" class="btn-primary" style="padding: 14px 48px;">Launch Portal →</a>
            <div class="hero-stats">
                <div class="stat-card"><h2>10M+</h2><p>Monthly Requests</p></div>
                <div class="stat-card"><h2>95%</h2><p>Resolution Rate</p></div>
                <div class="stat-card"><h2>24x7</h2><p>Support Ops</p></div>
                <div class="stat-card"><h2>100K+</h2><p>Active Partners</p></div>
            </div>
        </div>

        <h2 class="section-title">Powerful support tools</h2>
        <div class="features-grid">
            <div class="feature-card"><i class="fas fa-ticket-alt"></i><h3>Ticket Management</h3><p>Centralized queue</p></div>
            <div class="feature-card"><i class="fas fa-truck"></i><h3>Live Order Tracking</h3><p>Real-time location</p></div>
            <div class="feature-card"><i class="fas fa-coins"></i><h3>Refund Management</h3><p>One-click approvals</p></div>
            <div class="feature-card"><i class="fas fa-robot"></i><h3>AI Resolution</h3><p>Smart suggestions</p></div>
            <div class="feature-card"><i class="fas fa-chart-line"></i><h3>Analytics & CSAT</h3><p>Performance insights</p></div>
            <div class="feature-card"><i class="fas fa-file-import"></i><h3>Data Import</h3><p>CSV & Excel upload</p></div>
        </div>

        <div class="workflow">
            <div class="step"><span>1</span> Customer Issue</div><i class="fas fa-arrow-right"></i>
            <div class="step"><span>2</span> Ticket Creation</div><i class="fas fa-arrow-right"></i>
            <div class="step"><span>3</span> Agent Assignment</div><i class="fas fa-arrow-right"></i>
            <div class="step"><span>4</span> Investigation</div><i class="fas fa-arrow-right"></i>
            <div class="step"><span>5</span> Resolution</div><i class="fas fa-arrow-right"></i>
            <div class="step"><span>6</span> Feedback</div>
        </div>

        <footer>
            <a href="#">About SSM</a> <a href="#">Privacy Policy</a> <a href="#">Contact Support</a> <a href="#">Terms of Service</a>
        </footer>
    </div>
</body>
</html>
