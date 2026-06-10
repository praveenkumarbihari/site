<?php
/**
 * One-time setup: creates database and tables.
 * Visit http://localhost/ssm/setup.php once, then delete this file.
 */
require_once __DIR__ . '/config/database.php';

$messages = [];

try {
    ensureDatabaseExists();
    $db = getDB();
    runSqlFile($db, __DIR__ . '/sql/schema.sql');
    $messages[] = 'MySQL database and tables created successfully!';
} catch (PDOException $e) {
    $messages[] = dbErrorMessage($e);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SSM Setup</title>
    <style>
        body { font-family: Inter, sans-serif; background: #FEF7F0; padding: 40px; }
        .box { background: white; max-width: 600px; margin: 0 auto; padding: 32px; border-radius: 24px; }
        .ok { color: #15803D; } .err { color: #B91C1C; }
        a { color: #FC8019; }
    </style>
</head>
<body>
    <div class="box">
        <h1>SSM Database Setup</h1>
        <?php foreach ($messages as $msg): ?>
            <p class="<?= str_starts_with($msg, 'Wrong') || str_contains($msg, 'error') || str_contains($msg, 'not') ? 'err' : 'ok' ?>"><?= htmlspecialchars($msg) ?></p>
        <?php endforeach; ?>
        <p><a href="register.php">Create Admin Account →</a></p>
        <p><a href="index.php">Go to Home</a></p>
    </div>
</body>
</html>
