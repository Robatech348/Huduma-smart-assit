<?php
declare(strict_types=1);

/**
 * Installer: creates the database, tables, sample data and the first admin.
 *
 * Web: open /install/install.php in a browser (only works while no users exist).
 * CLI: php install/install.php admin@example.org "StrongPassword1" ["Admin Name"]
 */

$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) {
    copy($root . '/config.sample.php', $root . '/config.php');
}
$config = require $root . '/config.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Africa/Nairobi');
$isCli = PHP_SAPI === 'cli';

function out(string $msg): void
{
    global $isCli;
    echo $isCli ? $msg . PHP_EOL : '<li>' . htmlspecialchars($msg) . '</li>';
}

$c = $config['db'];
try {
    $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], (int)$c['port']), $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    exit('Cannot connect to MySQL: ' . $e->getMessage() . ($isCli ? "\n" : '') . ' Start MySQL from the XAMPP control panel and check config.php.');
}
$dbName = preg_replace('/[^A-Za-z0-9_]/', '', $c['name']);
$server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)$c['port'], $dbName), $c['user'], $c['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$hasUsers = false;
try {
    $hasUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (PDOException $e) {
    // tables not created yet
}
if ($hasUsers) {
    exit('Huduma SmartAssist is already installed. To reinstall, drop the database `' . $dbName . '` first.' . ($isCli ? "\n" : ''));
}

// Collect admin credentials.
$adminEmail = $adminPass = $adminName = '';
$error = '';
if ($isCli) {
    $adminEmail = $argv[1] ?? 'admin@smartassist.local';
    $adminPass = $argv[2] ?? '';
    $adminName = $argv[3] ?? 'System Administrator';
    if ($adminPass === '') {
        $adminPass = bin2hex(random_bytes(6));
        echo "No password given; generated one: {$adminPass}\n";
    }
} else {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $adminEmail = trim($_POST['email'] ?? '');
        $adminPass = $_POST['password'] ?? '';
        $adminName = trim($_POST['name'] ?? '') ?: 'System Administrator';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid admin email.';
        } elseif (strlen($adminPass) < 10) {
            $error = 'Password must be at least 10 characters.';
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $error) {
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install Huduma SmartAssist</title>
        <link rel="stylesheet" href="../assets/css/app.css"></head><body><main class="wrap main narrow">
        <div class="card"><h1>Install Huduma SmartAssist</h1>
        <p>This creates the <code><?= htmlspecialchars($dbName) ?></code> database, sample pilot data and the first administrator account.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" class="form">
          <label>Admin name<input name="name" value="<?= htmlspecialchars($_POST['name'] ?? 'System Administrator') ?>"></label>
          <label>Admin email<input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></label>
          <label>Admin password (min 10 characters)<input type="password" name="password" required minlength="10"></label>
          <button class="btn btn-primary" type="submit">Install</button>
        </form></div></main></body></html><?php
        exit;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Installing…</title><link rel="stylesheet" href="../assets/css/app.css"></head><body><main class="wrap main narrow"><div class="card"><h1>Installing</h1><ul>';
}

// Schema
$sql = file_get_contents($root . '/database/schema.sql');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
    $pdo->exec($stmt);
}
out('Database tables created.');

// Seed (only if empty)
$seed = require $root . '/database/seed.php';
if ((int)$pdo->query('SELECT COUNT(*) FROM centres')->fetchColumn() === 0) {
    $ins = $pdo->prepare('INSERT INTO centres (name, county, address, phone, email, opening_hours) VALUES (?,?,?,?,?,?)');
    $centreIds = [];
    foreach ($seed['centres'] as $row) {
        $ins->execute($row);
        $centreIds[] = (int)$pdo->lastInsertId();
    }
    $insS = $pdo->prepare('INSERT INTO services (slug, name, agency, category, summary, requirements, steps, fee, processing_time, keywords, bookable) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $insCS = $pdo->prepare('INSERT INTO centre_services (centre_id, service_id) VALUES (?,?)');
    foreach ($seed['services'] as $s) {
        $insS->execute(array_slice($s, 0, 11));
        $sid = (int)$pdo->lastInsertId();
        $where = $s[11] === null ? array_keys($centreIds) : $s[11];
        foreach ($where as $idx) {
            $insCS->execute([$centreIds[$idx], $sid]);
        }
    }
    $insF = $pdo->prepare('INSERT INTO faqs (question, answer, keywords) VALUES (?,?,?)');
    foreach ($seed['faqs'] as $f) {
        $insF->execute($f);
    }
    require_once $root . '/database/catalogue.php';
    $extra = import_ecitizen_catalogue($pdo);
    out("{$extra} eCitizen services added to the catalogue.");
    out(count($centreIds) . ' centres, ' . count($seed['services']) . ' services and ' . count($seed['faqs']) . ' FAQs added (sample data).');

    // Slots: next 21 days, Mon-Fri, 08:30-16:30 hourly, capacity 6.
    $insSlot = $pdo->prepare('INSERT IGNORE INTO slots (centre_id, slot_date, start_time, end_time, capacity) VALUES (?,?,?,?,?)');
    $count = 0;
    for ($d = 0; $d <= 21; $d++) {
        $day = new DateTimeImmutable("today +{$d} day");
        if ((int)$day->format('N') > 5) {
            continue;
        }
        foreach ($centreIds as $cid) {
            for ($h = 8; $h < 16; $h++) {
                $start = sprintf('%02d:30:00', $h);
                $end = sprintf('%02d:30:00', $h + 1);
                if ($h === 12) {
                    continue; // lunch hour reserved for walk-ins
                }
                $insSlot->execute([$cid, $day->format('Y-m-d'), $start, $end, 6]);
                $count++;
            }
        }
    }
    out("{$count} appointment slots generated for the next 3 weeks.");

    // Demo staff account for the first centre.
    $staffPass = bin2hex(random_bytes(5));
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role, centre_id) VALUES (?,?,?,'staff',?)")
        ->execute(['Demo Centre Officer', 'officer@smartassist.local', password_hash($staffPass, PASSWORD_DEFAULT), $centreIds[0]]);
    out("Demo staff account (Huduma Centre GPO): officer@smartassist.local / {$staffPass}");
}

$pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,'admin')")
    ->execute([$adminName, strtolower($adminEmail), password_hash($adminPass, PASSWORD_DEFAULT)]);
out("Administrator created: {$adminEmail}");
out('Done. Sign in at staff/login.php. Delete or protect the install folder in production.');

if (!$isCli) {
    echo '</ul><p><a class="btn btn-primary" href="../staff/login.php">Go to staff sign in</a> <a class="btn" href="../index.php">Open public site</a></p></div></main></body></html>';
}
