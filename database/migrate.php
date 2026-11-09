<?php
declare(strict_types=1);

/**
 * Upgrades an existing database to the current schema. Safe to run more than once.
 *   php database/migrate.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();
$dbName = config('db.name');

$columnExists = function (string $table, string $column) use ($dbName): bool {
    return (bool)q_val(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$dbName, $table, $column]
    );
};

// 1. Citizen accounts.
$pdo->exec("CREATE TABLE IF NOT EXISTS citizens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "citizens table: ok\n";

foreach (['appointments', 'service_requests'] as $table) {
    if (!$columnExists($table, 'citizen_id')) {
        $pdo->exec("ALTER TABLE {$table}
            ADD COLUMN citizen_id INT UNSIGNED NULL AFTER created_by,
            ADD KEY idx_citizen (citizen_id),
            ADD FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL");
        echo "{$table}.citizen_id: added\n";
    } else {
        echo "{$table}.citizen_id: already present\n";
    }
}
// 2. Official application links + eCitizen service catalogue.
if (!$columnExists('services', 'apply_url')) {
    $pdo->exec('ALTER TABLE services ADD COLUMN apply_url VARCHAR(255) NULL AFTER bookable');
    echo "services.apply_url: added\n";
}
require_once __DIR__ . '/catalogue.php';
echo 'eCitizen services added: ', import_ecitizen_catalogue($pdo), "\n";

echo "Migration complete.\n";
