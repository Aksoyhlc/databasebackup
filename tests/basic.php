<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aksoyhlc\Databasebackup\DatabaseBackupService;

const TEST_DB_HOST = "127.0.1";
const TEST_DB_NAME = "test_backup_db";
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
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    TEST_BACKUP_PATH,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = $fileinfo->isDir() ? "rmdir" : "unlink";
                @$todo($fileinfo->getRealPath());
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
        echo "SONUÇ: BAŞARILI\n";
    } catch (Exception $e) {
        $testResults["failed"]++;
        $testResults["details"][] = [
            "status" => "FAILED",
            "description" => $description,
            "message" => $e->getMessage(),
        ];
        echo "SONUÇ: BAŞARISIZ - Hata: " . $e->getMessage() . "\n";
    }
    echo "--------------------------------------------------\n\n";
}

function assert_true(
    $condition,
    string $message = "Assertion failed: condition is not true"
): void
{
    if ($condition !== true) {
        throw new Exception($message);
    }
}

function assert_not_empty(
    $value,
    string $message = "Assertion failed: value is empty"
): void
{
    if (empty($value)) {
        throw new Exception($message);
    }
}

function assert_file_exists(
    string $filename,
    string $message = "Assertion failed: file does not exist"
): void
{
    if (!file_exists($filename)) {
        throw new Exception($message . " ({$filename})");
    }
}

function assert_contains_string(
    string $haystack,
    string $needle,
    string $message = "Assertion failed: haystack does not contain needle"
): void
{
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message . " (Aranan: {$needle})");
    }
}

function assert_equals(
    $expected,
    $actual,
    string $message = "Assertion failed: expected does not equal actual"
): void
{
    if ($expected !== $actual) {
        throw new Exception(
            $message .
            " (Beklenen: " .
            var_export($expected, true) .
            ", Gelen: " .
            var_export($actual, true) .
            ")"
        );
    }
}

function assert_false(
    $condition,
    string $message = "Assertion failed: condition is not false"
): void
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

    $pdo->exec(
        "CREATE TABLE items (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50)) ENGINE=InnoDB;"
    );
    $pdo->exec("INSERT INTO items (name) VALUES ('Item 1'), ('Item 2');");

    $pdo->exec(
        "CREATE TABLE logs (id INT AUTO_INCREMENT PRIMARY KEY, message TEXT, log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;"
    );
    $pdo->exec(
        "INSERT INTO logs (message) VALUES ('Log entry 1'), ('Log entry 2');"
    );

    $pdo->exec(
        "CREATE TABLE user_sessions (session_id VARCHAR(255) PRIMARY KEY, user_id INT, data TEXT, last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;"
    );
    $pdo->exec(
        "INSERT INTO user_sessions (session_id, user_id, data) VALUES ('sess_abc123', 1, 'some session data');"
    );

    $currentUser = TEST_DB_USER;
    $currentHost =
        TEST_DB_HOST === "127.0.0.1" || TEST_DB_HOST === "localhost"
            ? "localhost"
            : "%";

    try {
        $pdo->exec(
            "CREATE VIEW items_view AS SELECT id, name FROM items WHERE id = 1;"
        );
    } catch (PDOException $e) {
        echo "UYARI: View (items_view) oluşturulamadı. View ile ilgili testler etkilenebilir. Hata: " .
            $e->getMessage() .
            "\n";
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
        echo "Test veritabanı (" . TEST_DB_NAME . ") silindi.\n";
    } catch (PDOException $e) {
        echo "UYARI: Test veritabanı silinemedi: " . $e->getMessage() . "\n";
    }

    if (is_dir(TEST_BACKUP_PATH)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                TEST_BACKUP_PATH,
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            @$fileinfo->isDir()
                ? rmdir($fileinfo->getRealPath())
                : unlink($fileinfo->getRealPath());
        }
        @rmdir(TEST_BACKUP_PATH);
        echo "Test yedekleme dizini (" . TEST_BACKUP_PATH . ") temizlendi.\n";
    }
}

echo "Test ortamı hazırlanıyor...\n";
try {
    setupTestDatabase();
} catch (PDOException $e) {
    echo "KRİTİK HATA: Test veritabanı oluşturulamadı. Testler çalıştırılamıyor.\n";
    echo "Hata: " . $e->getMessage() . "\n";
    exit(1);
}
echo "Test ortamı hazır.\n\n";

$dbConfig = [
    "host" => TEST_DB_HOST,
    "dbname" => TEST_DB_NAME,
    "user" => TEST_DB_USER,
    "pass" => TEST_DB_PASS,
    "charset" => TEST_DB_CHARSET,
];
$baseOptions = ["cacheTime" => 0, "removeDefiners" => true];

test_case("1. Temel Yedekleme Oluşturma", function () use (
    $dbConfig,
    $baseOptions
) {
    $service = new DatabaseBackupService(
        $dbConfig,
        TEST_BACKUP_PATH,
        $baseOptions
    );
    $result = $service->createBackup();

    assert_true(
        $result["success"],
        "Yedekleme başarılı olmalıydı. Mesaj: " . ($result["message"] ?? "N/A")
    );
    assert_not_empty($result["fileName"], "Yedekleme dosya adı boş olmamalı.");
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Yedek dosyası oluşturulmalıydı.");

    $content = file_get_contents($backupFilePath);
    assert_contains_string($content, TEST_DB_NAME, "Yedek db adını içermeli.");
    assert_contains_string(
        $content,
        "CREATE TABLE `items`",
        "items tablosunun yapısını içermeli."
    );
    assert_contains_string(
        $content,
        "INSERT INTO `items`",
        "items tablosunun verisini içermeli."
    );
    assert_contains_string(
        $content,
        "Item 1",
        "items tablosunun içeriğini içermeli."
    );
    assert_contains_string(
        $content,
        "VIEW `items_view`",
        "items_view yapısını içermeli."
    );
});

test_case("2. Sıkıştırılmış Yedekleme Oluşturma", function () use (
    $dbConfig,
    $baseOptions
) {
    $options = array_merge($baseOptions, ["compressOutput" => true]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);
    $result = $service->createBackup();

    assert_true(
        $result["success"],
        "Sıkıştırılmış yedekleme başarılı olmalıydı."
    );
    assert_true(
        str_ends_with($result["fileName"], ".sql.gz"),
        "Dosya adı .sql.gz ile bitmeli."
    );
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists(
        $backupFilePath,
        "Sıkıştırılmış yedek dosyası oluşturulmalıydı."
    );

    if (function_exists("gzopen")) {
        $gz = gzopen($backupFilePath, "r");
        $content = "";
        while (!gzeof($gz) && strlen($content) < 2048) {
            $content .= gzread($gz, 1024);
        }
        gzclose($gz);
        assert_contains_string(
            $content,
            "CREATE TABLE `items`",
            "Sıkıştırılmış yedek 'items' yapısını içermeli."
        );
    } else {
        echo "UYARI: gzopen fonksiyonu yok, sıkıştırılmış içerik kontrolü atlandı.\n";
    }
});

test_case("3. Yedekleri Listeleme ve Temizleme (Sayıya Göre)", function () use (
    $dbConfig,
    $baseOptions
) {
    $options = array_merge($baseOptions, ["maxBackupCount" => 2]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);

    $service->createBackup();
    sleep(1);
    $service->createBackup();
    sleep(1);
    $result3 = $service->createBackup();

    assert_true($result3["success"], "3. yedekleme başarılı olmalıydı.");

    $backups = $service->listBackups();
    assert_equals(
        2,
        count($backups),
        "Maksimum yedek sayısı (2) korunduğu için listede 2 yedek olmalı. Bulunan: " .
        count($backups)
    );

    $filesInDir = glob(TEST_BACKUP_PATH . "/*.sql*");
    assert_equals(
        2,
        count($filesInDir),
        "Dizinde de maksimum yedek sayısı (2) kadar dosya olmalı."
    );
});

test_case("4. Yedek Silme", function () use ($dbConfig, $baseOptions) {
    $service = new DatabaseBackupService(
        $dbConfig,
        TEST_BACKUP_PATH,
        $baseOptions
    );
    $createResult = $service->createBackup();
    $fileName = $createResult["fileName"];
    $filePath = TEST_BACKUP_PATH . "/" . $fileName;

    assert_file_exists($filePath, "Silmeden önce dosya var olmalı.");
    $deleteResult = $service->deleteBackup($fileName);
    assert_true($deleteResult["success"], "Dosya silme başarılı olmalı.");
    assert_false(
        file_exists($filePath),
        "Dosya silindikten sonra var olmamalı."
    );
});

test_case("5. Hariç Tutulan Tablolar", function () use (
    $dbConfig,
    $baseOptions
) {
    $options = array_merge($baseOptions, [
        "excludedTables" => ["logs"],
    ]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);
    $result = $service->createBackup();

    assert_true(
        $result["success"],
        "Hariç tutulan tabloyla yedekleme başarılı olmalıydı."
    );
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Yedek dosyası oluşturulmalıydı.");

    $content = file_get_contents($backupFilePath);
    assert_contains_string(
        $content,
        "CREATE TABLE `items`",
        "'items' tablosu yedekte olmalı."
    );
    assert_false(
        strpos($content, "CREATE TABLE `logs`") !== false,
        "'logs' tablosu yedekte OLMAMALI."
    );
    assert_false(
        strpos($content, "INSERT INTO `logs`") !== false,
        "'logs' tablosunun verisi yedekte OLMAMALI."
    );
});

test_case("6. Tablo Modları (Structure Only)", function () use (
    $dbConfig,
    $baseOptions
) {
    $options = array_merge($baseOptions, [
        "tableModes" => [
            "user_sessions" => "structure_only",
        ],
    ]);
    $service = new DatabaseBackupService($dbConfig, TEST_BACKUP_PATH, $options);
    $result = $service->createBackup();

    assert_true(
        $result["success"],
        "Tablo moduyla yedekleme başarılı olmalıydı."
    );
    $backupFilePath = TEST_BACKUP_PATH . "/" . $result["fileName"];
    assert_file_exists($backupFilePath, "Yedek dosyası oluşturulmalıydı.");

    $content = file_get_contents($backupFilePath);
    assert_contains_string(
        $content,
        "CREATE TABLE `user_sessions`",
        "'user_sessions' tablosunun yapısı yedekte olmalı."
    );
    assert_false(
        strpos($content, "INSERT INTO `user_sessions`") !== false,
        "'user_sessions' tablosunun verisi yedekte OLMAMALI."
    );
    assert_contains_string(
        $content,
        "CREATE TABLE `items`",
        "'items' tablosu (varsayılan mod) tam olarak yedekte olmalı."
    );
    assert_contains_string(
        $content,
        "INSERT INTO `items`",
        "'items' tablosunun verisi yedekte olmalı."
    );
});

test_case("7. Definer Kaldırma (View ile)", function () use (
    $dbConfig,
    $baseOptions
) {
    $optionsWithRemoveDefiner = array_merge($baseOptions, [
        "removeDefiners" => true,
    ]);
    $serviceWithRemove = new DatabaseBackupService(
        $dbConfig,
        TEST_BACKUP_PATH,
        $optionsWithRemoveDefiner
    );
    $resultWithRemove = $serviceWithRemove->createBackup();

    assert_true(
        $resultWithRemove["success"],
        "Definer kaldırma aktifken yedekleme başarılı olmalı."
    );
    $backupFilePathWithRemove =
        TEST_BACKUP_PATH . "/" . $resultWithRemove["fileName"];
    assert_file_exists(
        $backupFilePathWithRemove,
        "Yedek dosyası (removeDefiner=true) oluşturulmalıydı."
    );
    $contentWithRemove = file_get_contents($backupFilePathWithRemove);

    assert_false(
        strpos($contentWithRemove, "DEFINER=") !== false,
        "removeDefiners true iken yedekte 'DEFINER=' ifadesi OLMAMALI."
    );
    assert_contains_string(
        $contentWithRemove,
        "VIEW `items_view`",
        "View tanımı (removeDefiner=true) yedekte olmalı."
    );

    $optionsWithoutRemoveDefiner = array_merge($baseOptions, [
        "removeDefiners" => false,
    ]);
    $serviceWithoutRemove = new DatabaseBackupService(
        $dbConfig,
        TEST_BACKUP_PATH,
        $optionsWithoutRemoveDefiner
    );
    $resultWithoutRemove = $serviceWithoutRemove->createBackup();

    assert_true(
        $resultWithoutRemove["success"],
        "Definer kaldırma kapalıyken yedekleme başarılı olmalı."
    );
    $backupFilePathWithoutRemove =
        TEST_BACKUP_PATH . "/" . $resultWithoutRemove["fileName"];
    assert_file_exists(
        $backupFilePathWithoutRemove,
        "Yedek dosyası (removeDefiner=false) oluşturulmalıydı."
    );
    $contentWithoutRemove = file_get_contents($backupFilePathWithoutRemove);

    assert_contains_string(
        $contentWithoutRemove,
        "VIEW `items_view`",
        "View tanımı (removeDefiner=false) yedekte olmalı."
    );
});

echo "\n\n--- TEST ÖZETİ ---\n";
echo "Geçen Testler: {$testResults["passed"]}\n";
echo "Başarısız Testler: {$testResults["failed"]}\n";
if ($testResults["failed"] > 0) {
    echo "\n--- BAŞARISIZ TEST DETAYLARI ---\n";
    foreach ($testResults["details"] as $detail) {
        if ($detail["status"] === "FAILED") {
            echo "Açıklama: {$detail["description"]}\n  Hata: {$detail["message"]}\n\n";
        }
    }
}
echo "\nTest ortamı temizleniyor...\n";
cleanupTestEnvironment();
echo "Testler tamamlandı.\n";
exit($testResults["failed"] > 0 ? 1 : 0);
