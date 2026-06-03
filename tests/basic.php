<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aksoyhlc\Databasebackup\DatabaseBackupService;

const TEST_DB_HOST = "127.0.0.1";
const TEST_DB_NAME = "test_database";
const TEST_DB_USER = "root";
const TEST_DB_PASS = "";
const TEST_DB_CHARSET = "utf8mb4";
const TEST_BACKUP_PATH = __DIR__ . "/my_database_backups";
const TEST_DB_PORT = "3306";

$testResults = ["passed" => 0, "failed" => 0, "details" => []];

function test_case(string $description, callable $testFunction): void
{
    global $testResults;
    echo "--------------------------------------------------\n";
    echo "TEST: {$description}\n";
    try {
        if (is_dir(TEST_BACKUP_PATH)) {
            foreach (glob(TEST_BACKUP_PATH . '/*') as $file) {
                if (is_file($file)) {
                    @unlink($file);
                } elseif (is_dir($file)) {
                    @rmdir($file);
                }
            }
            @rmdir(TEST_BACKUP_PATH);
        }
        if (!is_dir(TEST_BACKUP_PATH)) {
            mkdir(TEST_BACKUP_PATH, 0777, true);
        }

        $testFunction();
        $testResults["passed"]++;
        $testResults["details"][] = [
            "status" => "PASSED",
            "description" => $description,
        ];
        echo "RESULT: PASSED\n";
    } catch (Exception $e) {
        $testResults["failed"]++;
        $testResults["details"][] = [
            "status" => "FAILED",
            "description" => $description,
            "message" => $e->getMessage(),
        ];
        echo "RESULT: FAILED - Error: " . $e->getMessage() . "\n";
    }
    echo "--------------------------------------------------\n\n";
}

function assert_true($condition, string $message = "Assertion failed: condition is not true"): void
{
    if ($condition !== true) {
        throw new Exception($message);
    }
}

function assert_not_empty($value, string $message = "Assertion failed: value is empty"): void
{
    if (empty($value)) {
        throw new Exception($message);
    }
}

function assert_file_exists(string $filename, string $message = "Assertion failed: file does not exist"): void
{
    if (!file_exists($filename)) {
        throw new Exception($message . " ({$filename})");
    }
}

function assert_contains_string(string $haystack, string $needle, string $message = "Assertion failed: haystack does not contain needle"): void
{
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message . " (Searched: {$needle})");
    }
}

function assert_equals($expected, $actual, string $message = "Assertion failed: expected does not equal actual"): void
{
    if ($expected !== $actual) {
        throw new Exception(
            $message .
            " (Expected: " .
            var_export($expected, true) .
            ", Got: " .
            var_export($actual, true) .
            ")"
        );
    }
}

function assert_false($condition, string $message = "Assertion failed: condition is not false"): void
{
    if ($condition !== false) {
        throw new Exception($message);
    }
}

function setupTestDatabase(): PDO
{
    $dsnBase = "mysql:host=" . TEST_DB_HOST;
    if (!empty(TEST_DB_PORT)) {
        $dsnBase .= ";port=" . TEST_DB_PORT;
    }
    $dsnBase .= ";charset=" . TEST_DB_CHARSET;

    $pdoBase = new PDO($dsnBase, TEST_DB_USER, TEST_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdoBase->exec("DROP DATABASE IF EXISTS " . TEST_DB_NAME);
    $pdoBase->exec(
        "CREATE DATABASE " .
        TEST_DB_NAME .
        " CHARACTER SET " .
        TEST_DB_CHARSET .
        " COLLATE " .
        TEST_DB_CHARSET .
        "_general_ci"
    );
    $pdoBase = null;

    $dsn = "mysql:host=" . TEST_DB_HOST;
    if (!empty(TEST_DB_PORT)) {
        $dsn .= ";port=" . TEST_DB_PORT;
    }
    $dsn .= ";dbname=" . TEST_DB_NAME . ";charset=" . TEST_DB_CHARSET;

    $pdo = new PDO($dsn, TEST_DB_USER, TEST_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Users table (matching seed structure)
    $pdo->exec("
        CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            city VARCHAR(50) DEFAULT NULL,
            status TINYINT NOT NULL DEFAULT 1,
            balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY email_unique (email)
        ) ENGINE=InnoDB
    ");
    $pdo->exec("INSERT INTO users (name, email, city, status, balance) VALUES
        ('John Smith', 'john@example.com', 'New York', 1, 150.00),
        ('Jane Doe', 'jane@example.com', 'Los Angeles', 1, 250.50)");

    // Categories table (matching seed structure)
    $pdo->exec("
        CREATE TABLE categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB
    ");
    $pdo->exec("INSERT INTO categories (name, description, is_active) VALUES
        ('Electronics', 'Electronic devices and accessories', 1),
        ('Books', 'Printed and digital books', 1)");

    // Logs table (matching seed structure)
    $pdo->exec("
        CREATE TABLE logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            level ENUM('debug','info','warning','error') NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX level_idx (level)
        ) ENGINE=InnoDB
    ");
    $pdo->exec("INSERT INTO logs (level, message, context) VALUES
        ('info', 'User logged in', '{\"user_id\": 1}'),
        ('warning', 'Failed payment attempt', '{\"user_id\": 2, \"amount\": 99.99}')");

    // Create a view on categories
    try {
        $pdo->exec("CREATE VIEW active_categories AS SELECT id, name FROM categories WHERE is_active = 1");
    } catch (PDOException $e) {
        echo "WARNING: Could not create view (active_categories). View-related tests may be affected. Error: " .
            $e->getMessage() . "\n";
    }

    return $pdo;
}

function cleanupTestEnvironment(): void
{
    try {
        $dsnBase = "mysql:host=" . TEST_DB_HOST . ";charset=" . TEST_DB_CHARSET;
        $pdoBase = new PDO($dsnBase, TEST_DB_USER, TEST_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdoBase->exec("DROP DATABASE IF EXISTS " . TEST_DB_NAME);
        echo "Test database (" . TEST_DB_NAME . ") dropped.\n";
    } catch (PDOException $e) {
        echo "WARNING: Could not drop test database: " . $e->getMessage() . "\n";
    }

    if (is_dir(TEST_BACKUP_PATH)) {
        foreach (glob(TEST_BACKUP_PATH . '/*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            } elseif (is_dir($file)) {
                @rmdir($file);
            }
        }
        @rmdir(TEST_BACKUP_PATH);
        echo "Test backup directory (" . TEST_BACKUP_PATH . ") cleaned.\n";
    }
}

echo "Setting up test environment...\n";
try {
    setupTestDatabase();
} catch (PDOException $e) {
    echo "CRITICAL ERROR: Could not create test database. Tests cannot run.\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
echo "Test environment ready.\n\n";

$dbConfig = [
    "host" => TEST_DB_HOST,
    "dbname" => TEST_DB_NAME,
    "user" => TEST_DB_USER,
    "pass" => TEST_DB_PASS,
    "charset" => TEST_DB_CHARSET,
];
$baseOptions = ["cacheTime" => 0, "removeDefiners" => true];

test_case("1. Basic Backup Creation", function () use ($dbConfig, $baseOptions) {
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $baseOptions);
    $result = $service->createBackup();

    assert_true($result["success"], "Backup should succeed. Message: " . ($result["message"] ?? "N/A"));
    assert_not_empty($result["fileName"], "Backup file name should not be empty.");
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Backup file should be created.");

    $content = file_get_contents($backupFilePath);
    assert_contains_string($content, TEST_DB_NAME, "Backup should contain database name.");
    assert_contains_string($content, "CREATE TABLE `users`", "Backup should contain users table structure.");
    assert_contains_string($content, "INSERT INTO `users`", "Backup should contain users table data.");
    assert_contains_string($content, "VIEW `active_categories`", "Backup should contain active_categories view.");
});

test_case("2. Compressed Backup Creation", function () use ($dbConfig, $baseOptions) {
    $options = array_merge($baseOptions, ["compressOutput" => true]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);
    $result = $service->createBackup();

    assert_true($result["success"], "Compressed backup should succeed.");
    assert_true(str_ends_with($result["fileName"], ".sql.gz"), "File name should end with .sql.gz.");
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Compressed backup file should be created.");

    if (function_exists("gzopen")) {
        $gz = gzopen($backupFilePath, "r");
        $content = "";
        while (!gzeof($gz) && strlen($content) < 65536) {
            $content .= gzread($gz, 8192);
        }
        gzclose($gz);
        assert_contains_string($content, "CREATE TABLE `users`", "Compressed backup should contain users table structure.");
    } else {
        echo "WARNING: gzopen not available, compressed content check skipped.\n";
    }
});

test_case("3. Backup Listing and Cleanup (by count)", function () use ($dbConfig, $baseOptions) {
    $options = array_merge($baseOptions, ["maxBackupCount" => 2]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);

    $service->createBackup();
    sleep(1);
    $service->createBackup();
    sleep(1);
    $result3 = $service->createBackup();

    assert_true($result3["success"], "3rd backup should succeed.");

    $backups = $service->listBackups();
    assert_equals(2, count($backups), "Should keep max 2 backups. Found: " . count($backups));

    $filesInDir = glob(TEST_BACKUP_PATH . "/*.sql*");
    assert_equals(2, count($filesInDir), "Directory should also have max 2 backup files.");
});

test_case("4. Backup Deletion", function () use ($dbConfig, $baseOptions) {
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $baseOptions);
    $createResult = $service->createBackup();
    $fileName = $createResult["fileName"];
    $filePath = TEST_BACKUP_PATH . "/" . $fileName;

    assert_file_exists($filePath, "File should exist before deletion.");
    $deleteResult = $service->deleteBackup($fileName);
    assert_true($deleteResult["success"], "Deletion should succeed.");
    assert_false(file_exists($filePath), "File should not exist after deletion.");
});

test_case("5. Excluded Tables", function () use ($dbConfig, $baseOptions) {
    $options = array_merge($baseOptions, ["excludedTables" => ["logs"]]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);
    $result = $service->createBackup();

    assert_true($result["success"], "Backup with excluded table should succeed.");
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Backup file should be created.");

    $content = file_get_contents($backupFilePath);
    assert_contains_string($content, "CREATE TABLE `users`", "users table should be in backup.");
    assert_false(strpos($content, "CREATE TABLE `logs`") !== false, "logs table should NOT be in backup.");
    assert_false(strpos($content, "INSERT INTO `logs`") !== false, "logs table data should NOT be in backup.");
});

test_case("6. Table Modes (Structure Only)", function () use ($dbConfig, $baseOptions) {
    $options = array_merge($baseOptions, [
        "tableModes" => ["users" => "structure_only"],
    ]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);
    $result = $service->createBackup();

    assert_true($result["success"], "Backup with table mode should succeed.");
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Backup file should be created.");

    $content = file_get_contents($backupFilePath);
    assert_contains_string($content, "CREATE TABLE `users`", "users structure should be in backup.");
    assert_false(strpos($content, "INSERT INTO `users`") !== false, "users data should NOT be in backup.");
    assert_contains_string($content, "CREATE TABLE `categories`", "categories (default mode) should be fully in backup.");
    assert_contains_string($content, "INSERT INTO `categories`", "categories data should be in backup.");
});

test_case("7. DEFINER Removal (with View)", function () use ($dbConfig, $baseOptions) {
    $optionsWithRemoveDefiner = array_merge($baseOptions, ["removeDefiners" => true]);
    $serviceWithRemove = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $optionsWithRemoveDefiner);
    $resultWithRemove = $serviceWithRemove->createBackup();

    assert_true($resultWithRemove["success"], "Backup with removeDefiners=true should succeed.");
    $backupFilePathWithRemove = TEST_BACKUP_PATH . "/" . $resultWithRemove["fileName"];
    assert_file_exists($backupFilePathWithRemove, "Backup file (removeDefiner=true) should be created.");
    $contentWithRemove = file_get_contents($backupFilePathWithRemove);

    assert_false(strpos($contentWithRemove, "DEFINER=") !== false, "Backup with removeDefiners=true should NOT contain DEFINER=.");
    assert_contains_string($contentWithRemove, "VIEW `active_categories`", "View should be in backup (removeDefiner=true).");

    $optionsWithoutRemoveDefiner = array_merge($baseOptions, ["removeDefiners" => false]);
    $serviceWithoutRemove = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $optionsWithoutRemoveDefiner);
    $resultWithoutRemove = $serviceWithoutRemove->createBackup();

    assert_true($resultWithoutRemove["success"], "Backup with removeDefiners=false should succeed.");
    $backupFilePathWithoutRemove = TEST_BACKUP_PATH . "/" . $resultWithoutRemove["fileName"];
    assert_file_exists($backupFilePathWithoutRemove, "Backup file (removeDefiner=false) should be created.");
    $contentWithoutRemove = file_get_contents($backupFilePathWithoutRemove);

    assert_contains_string($contentWithoutRemove, "VIEW `active_categories`", "View should be in backup (removeDefiner=false).");
});

test_case("8. Table Modes (Data Only)", function () use ($dbConfig, $baseOptions) {
    $options = array_merge($baseOptions, [
        "tableModes" => ["users" => "data_only"],
    ]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);
    $result = $service->createBackup();

    assert_true($result["success"], "Backup with data_only mode should succeed.");
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Backup file should be created.");

    $content = file_get_contents($backupFilePath);
    assert_false(strpos($content, "CREATE TABLE `users`") !== false, "users CREATE should NOT be in backup (data_only).");
    assert_contains_string($content, "INSERT INTO `users`", "users INSERT should be in backup (data_only).");
    assert_contains_string($content, "CREATE TABLE `categories`", "categories (default mode) should have structure.");
    assert_contains_string($content, "INSERT INTO `categories`", "categories data should be in backup.");
});

test_case("9. Prepare Download", function () use ($dbConfig, $baseOptions) {
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $baseOptions);
    $createResult = $service->createBackup();
    $fileName = $createResult["fileName"];

    $download = $service->prepareDownload($fileName);
    assert_true($download["success"], "Prepare download should succeed.");
    assert_equals($fileName, $download["fileName"], "File name should match.");
    assert_file_exists($download["filePath"] ?? '', "File path should exist.");
    assert_equals('application/sql', $download["mimeType"], "Mime type should be application/sql.");
    assert_false($download["isCompressed"], "Plain SQL should not be marked compressed.");
});

test_case("10. List Backups", function () use ($dbConfig, $baseOptions) {
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $baseOptions);
    $service->createBackup();
    sleep(1);
    $service->createBackup();

    $backups = $service->listBackups();
    assert_equals(2, count($backups), "Should list exactly 2 backups.");
    assert_true(isset($backups[0]["file_name"]), "Backup entry should have file_name.");
    assert_true(isset($backups[0]["size"]), "Backup entry should have size.");
    assert_true(isset($backups[0]["date"]), "Backup entry should have date.");
});

test_case("11. Backup Cleanup by Age", function () use ($dbConfig, $baseOptions) {
    $oldFile = TEST_BACKUP_PATH . "/backup_old_test_2020-01-01_00-00-00.sql";
    file_put_contents($oldFile, "-- old backup");
    touch($oldFile, strtotime("-10 days"));

    $options = array_merge($baseOptions, ["maxBackupAgeDays" => 7]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);
    $service->createBackup();

    assert_false(file_exists($oldFile), "Old backup should be deleted by age limit.");
});

test_case("12. Data Integrity - Row Count Match", function () use ($dbConfig, $baseOptions) {
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $baseOptions);
    $result = $service->createBackup();
    assert_true($result["success"], "Backup should succeed.");
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    $content = file_get_contents($backupFilePath);

    preg_match_all('/INSERT INTO `users` \([^)]+\) VALUES\s*\n(.+?);/s', $content, $matches);
    $backupRowCount = 0;
    foreach ($matches[1] as $block) {
        $backupRowCount += substr_count($block, '),') + 1;
    }

    $pdo = new PDO(
        "mysql:host=" . TEST_DB_HOST . ";dbname=" . TEST_DB_NAME . ";charset=" . TEST_DB_CHARSET,
        TEST_DB_USER,
        TEST_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $dbRowCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    assert_equals($dbRowCount, $backupRowCount, "User row count in backup should match database.");
});

test_case("13. Empty Table Backup", function () use ($dbConfig, $baseOptions) {
    $pdo = new PDO(
        "mysql:host=" . TEST_DB_HOST . ";dbname=" . TEST_DB_NAME . ";charset=" . TEST_DB_CHARSET,
        TEST_DB_USER,
        TEST_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE TABLE empty_test_table (id INT PRIMARY KEY, name VARCHAR(50))");

    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $baseOptions);
    $result = $service->createBackup();
    assert_true($result["success"], "Backup with empty table should succeed.");

    $content = file_get_contents(TEST_BACKUP_PATH . "/" . $result["fileName"]);
    assert_contains_string($content, "CREATE TABLE `empty_test_table`", "Empty table structure should be in backup.");
    assert_false(strpos($content, "INSERT INTO `empty_test_table`") !== false, "Empty table should not have INSERT.");
});

test_case("14. Backup Restore and Data Integrity", function () use ($dbConfig, $baseOptions) {
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $baseOptions);
    $result = $service->createBackup();
    assert_true($result["success"], "Backup should succeed.");
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Backup file should exist for restore test.");

    $restoreDbName = TEST_DB_NAME . "_restore";
    $dsnBase = "mysql:host=" . TEST_DB_HOST . ";charset=" . TEST_DB_CHARSET;
    $pdoBase = new PDO($dsnBase, TEST_DB_USER, TEST_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdoBase->exec("DROP DATABASE IF EXISTS {$restoreDbName}");
    $pdoBase->exec("CREATE DATABASE {$restoreDbName} CHARACTER SET " . TEST_DB_CHARSET . " COLLATE " . TEST_DB_CHARSET . "_general_ci");
    $pdoBase = null;

    $cmd = sprintf(
        'mysql -h%s -P%s -u%s %s %s < %s',
        escapeshellarg(TEST_DB_HOST),
        escapeshellarg(TEST_DB_PORT),
        escapeshellarg(TEST_DB_USER),
        TEST_DB_PASS ? '-p' . escapeshellarg(TEST_DB_PASS) : '',
        escapeshellarg($restoreDbName),
        escapeshellarg($backupFilePath)
    );
    exec($cmd . ' 2>&1', $output, $exitCode);
    assert_equals(0, $exitCode, "MySQL restore command should succeed. Output: " . implode("\n", $output));

    $dsnRestore = "mysql:host=" . TEST_DB_HOST . ";dbname=" . $restoreDbName . ";charset=" . TEST_DB_CHARSET;
    $pdoRestore = new PDO($dsnRestore, TEST_DB_USER, TEST_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $originalUsers = (new PDO(
        "mysql:host=" . TEST_DB_HOST . ";dbname=" . TEST_DB_NAME . ";charset=" . TEST_DB_CHARSET,
        TEST_DB_USER,
        TEST_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    ))->query("SELECT email, name, city, status, balance FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    $restoredUsers = $pdoRestore->query("SELECT email, name, city, status, balance FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    assert_equals($originalUsers, $restoredUsers, "Restored users data should match original.");

    $originalCategories = (new PDO(
        "mysql:host=" . TEST_DB_HOST . ";dbname=" . TEST_DB_NAME . ";charset=" . TEST_DB_CHARSET,
        TEST_DB_USER,
        TEST_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    ))->query("SELECT name, description, is_active FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    $restoredCategories = $pdoRestore->query("SELECT name, description, is_active FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    assert_equals($originalCategories, $restoredCategories, "Restored categories data should match original.");

    $pdoBase = new PDO($dsnBase, TEST_DB_USER, TEST_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdoBase->exec("DROP DATABASE IF EXISTS {$restoreDbName}");
});

test_case("15. Invalid Database Config", function () {
    $badConfig = [
        "host" => "999.999.999.999",
        "dbname" => "nonexistent",
        "user" => "baduser",
        "pass" => "badpass",
        "charset" => "utf8mb4",
    ];
    $caught = false;
    try {
        $service = new DatabaseBackupService($badConfig, TEST_BACKUP_PATH, ["cacheTime" => 0]);
        $service->createBackup();
    } catch (Exception $e) {
        $caught = true;
    }
    assert_true($caught, "Should throw exception for invalid DB config.");
});

echo "\n\n--- TEST SUMMARY ---\n";
echo "Passed: {$testResults["passed"]}\n";
echo "Failed: {$testResults["failed"]}\n";
if ($testResults["failed"] > 0) {
    echo "\n--- FAILED TEST DETAILS ---\n";
    foreach ($testResults["details"] as $detail) {
        if ($detail["status"] === "FAILED") {
            echo "Description: {$detail["description"]}\n  Error: {$detail["message"]}\n\n";
        }
    }
}
echo "\nCleaning up test environment...\n";
cleanupTestEnvironment();
echo "Tests completed.\n";
exit($testResults["failed"] > 0 ? 1 : 0);
