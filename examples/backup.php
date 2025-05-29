<?php

use Aksoyhlc\Databasebackup\DatabaseBackupService;

require __DIR__ . '/../vendor/autoload.php';


$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'test',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
    // 'port' => 3306  // Optional, default is 3306
];

// Backup path
$backupDir = __DIR__ . '/my_database_backups';

// FTP Settings (optional)
$ftpSettings = [
    'enabled' => false, // Set to true to enable FTP
    'host' => 'ftp.example.com',
    'username' => 'ftpuser',
    'password' => 'ftppassword',
    'port' => 21, // Usually 990 for SSL, 21 for normal
    'path' => '/backups/mysql/', // Backup folder on remote server
    'ssl' => false, // Set to true if using SSL/TLS
    'passive' => true // Passive mode is generally recommended
];

$options = [
    'cacheTime' => 600, // 10-minute cache
    'maxBackupCount' => 5,
    'maxBackupAgeDays' => 30,
    'ftpConfig' => $ftpSettings
];

try {
    $backupService = new DatabaseBackupService($dbConfig, $backupDir, $options);

    // 1. Create a new backup
    echo "Creating backup...\n";
    $createResult = $backupService->createBackup();
    if ($createResult['success']) {
        echo "Success: " . $createResult['message'] . " File: " . $createResult['fileName'] . "\n";
        $lastBackupFile = $createResult['fileName'];

        // 2. Also upload the created backup to FTP (already tried in createBackup if FTP is enabled)
        // if ($lastBackupFile) {
        //     echo "Uploading to FTP: {$lastBackupFile}\n";
        //     $ftpUploadResult = $backupService->uploadBackupToFtp($lastBackupFile);
        //     echo ($ftpUploadResult['success'] ? "FTP Success: " : "FTP Error: ") . $ftpUploadResult['message'] . "\n";
        // }

    } else {
        echo "Error: " . $createResult['message'] . "\n";
    }

    // 3. List backups
    echo "\nExisting Backups:\n";
    $backups = $backupService->listBackups();
    if (empty($backups)) {
        echo "No backups found.\n";
    } else {
        foreach ($backups as $backup) {
            echo "- {$backup['file_name']} (Size: {$backup['size']}, Date: {$backup['date']})\n";
        }
    }

    // 4. Preparing a backup file for download (in a web environment, you'll set the headers yourself)
    if (!empty($backups)) {
        $fileToDownload = $backups[0]['file_name'];
        echo "\nPreparing for download: {$fileToDownload}\n";
        $downloadPrep = $backupService->prepareDownload($fileToDownload);
        if ($downloadPrep['success']) {
            echo "File ready for download: {$downloadPrep['filePath']}\n";
            // In web environment:
            // header('Content-Description: File Transfer');
            // header('Content-Type: application/octet-stream');
            // header('Content-Disposition: attachment; filename="' . $downloadPrep['fileName'] . '"');
            // header('Expires: 0');
            // header('Cache-Control: must-revalidate');
            // header('Pragma: public');
            // header('Content-Length: ' . filesize($downloadPrep['filePath']));
            // readfile($downloadPrep['filePath']);
            // exit;
        } else {
            echo "Download error: " . $downloadPrep['message'] . "\n";
        }
    }


    // 5. Delete a backup file
    // if (count($backups) > 1) { // Let's delete the oldest one if there are at least two backups (for testing)
    //     $oldestBackup = end($backups)['file_name'];
    //     echo "\nDeleting: {$oldestBackup}\n";
    //     $deleteResult = $backupService->deleteBackup($oldestBackup);
    //     echo ($deleteResult['success'] ? "Delete Successful: " : "Delete Error: ") . $deleteResult['message'] . "\n";
    //
    //     echo "\nBackups after deletion:\n";
    //     $backups = $backupService->listBackups(); // Gets the updated list because it clears the cache
    //     foreach ($backups as $backup) {
    //         echo "- {$backup['file_name']} (Size: {$backup['size']}, Date: {$backup['date']})\n";
    //     }
    // }


} catch (Exception $e) {
    die("Critical Error: " . $e->getMessage() . "\n");
}


