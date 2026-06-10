<?php

define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_NAME', 'ssm');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP MySQL on 3307 — empty password by default

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (!extension_loaded('pdo_mysql')) {
            throw new PDOException(
                'MySQL driver not loaded in Apache. Enable extension=pdo_mysql in php.ini, then restart Apache from XAMPP.'
            );
        }
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (PHP_SAPI !== 'cli') {
                http_response_code(503);
                header('Content-Type: text/html; charset=utf-8');
                $detail = htmlspecialchars(dbErrorMessage($e));
                echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database unavailable — SSM</title>
    <style>
        body { font-family: Inter, Segoe UI, sans-serif; background: #FEF7F0; margin: 0; padding: 40px 20px; }
        .box { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        h1 { color: #B91C1C; font-size: 1.25rem; margin: 0 0 12px; }
        p { color: #444; line-height: 1.6; margin: 0 0 12px; }
        ol { color: #444; line-height: 1.7; padding-left: 1.25rem; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: .9em; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Database unavailable</h1>
        <p>{$detail}</p>
        <p><strong>To fix:</strong></p>
        <ol>
            <li>Start <strong>MySQL</strong> from the XAMPP Control Panel.</li>
            <li>Visit <code>setup.php</code> once to create the <code>ssm</code> database and tables.</li>
            <li>Refresh this page.</li>
        </ol>
    </div>
</body>
</html>
HTML;
                exit;
            }
            throw $e;
        }
    }
    return $pdo;
}

function dbErrorMessage(PDOException $e): string
{
    $msg = $e->getMessage();
    if (str_contains($msg, 'could not find driver') || str_contains($msg, 'pdo_mysql')) {
        return 'MySQL PHP driver is missing. Enable pdo_mysql in php.ini, then restart Apache from XAMPP.';
    }
    if (str_contains($msg, 'Connection refused') || str_contains($msg, 'actively refused') || str_contains($msg, '2002')) {
        return 'MySQL is not running. Start MySQL from the XAMPP Control Panel.';
    }
    if (str_contains($msg, 'Access denied')) {
        return 'Wrong MySQL username or password. Update config/database.php (default XAMPP: user root, empty password).';
    }
    if (str_contains($msg, 'Unknown database')) {
        return 'Database "ssm" not found. Start MySQL, then visit setup.php to create it.';
    }
    return 'Database error: ' . $msg;
}

function ensureDatabaseExists(): void
{
    if (!extension_loaded('pdo_mysql')) {
        throw new PDOException('MySQL driver (pdo_mysql) is not loaded.');
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(
        'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', DB_NAME) . '`'
        . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
}

function runSqlFile(PDO $db, string $path): void
{
    $sql = file_get_contents($path);
    $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
    foreach (preg_split('/;\s*\n/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $db->exec($statement);
        }
    }
}
