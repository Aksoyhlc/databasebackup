<?php

namespace Aksoyhlc\Databasebackup\Backup;

use PDO;
use Exception;
use DateInterval;
use DateTime;
use Aksoyhlc\Databasebackup\Core\ConfigManager;
use Aksoyhlc\Databasebackup\Core\DatabaseConnection;
use Aksoyhlc\Databasebackup\Helpers\Logger;
use Aksoyhlc\Databasebackup\Helpers\CacheManager;
use Aksoyhlc\Databasebackup\Storage\FtpUploader;

/**
 * BackupManager - Main class that creates and manages database backups
 *
 * This class is the main coordinator for database backup operations.
 * It performs fundamental operations such as creating, listing, deleting,
 * preparing for download, and cleaning up old backups.
 *
 * @package Aksoyhlc\Databasebackup\Backup
 */
class BackupManager
{
    /**
     * Database connection
     * @var DatabaseConnection
     */
    private DatabaseConnection $dbConnection;

    /**
     * Configuration manager
     * @var ConfigManager
     */
    private ConfigManager $config;

    /**
     * Logger object
     * @var Logger
     */
    private Logger $logger;

    /**
     * Cache manager
     * @var CacheManager
     */
    private CacheManager $cache;

    /**
     * FTP uploader
     * @var FtpUploader|null
     */
    private ?FtpUploader $ftpUploader = null;

    /**
     * SQL generator
     * @var SqlGenerator
     */
    private SqlGenerator $sqlGenerator;

    /**
     * Initializes the BackupManager class
     *
     * @param ConfigManager $config Configuration manager
     * @param DatabaseConnection $dbConnection Database connection
     * @param Logger $logger Object to be used for logging
     * @param CacheManager $cache Object to be used for cache management
     * @throws Exception
     */
    public function __construct(
        ConfigManager $config,
        DatabaseConnection $dbConnection,
        Logger $logger,
        CacheManager $cache
    ) {
        $this->config = $config;
        $this->dbConnection = $dbConnection;
        $this->logger = $logger;
        $this->cache = $cache;

        // Check if backup directory exists
        $this->ensureBackupDirectoryExists();

        // Create SQL Generator
        $this->sqlGenerator = new SqlGenerator(
            $this->dbConnection,
            $this->config,
            $this->logger
        );

        // Create FTP Uploader (if FTP is enabled)
        if ($this->config->isFtpEnabled()) {
            $this->ftpUploader = new FtpUploader($this->config, $this->logger);
        }
    }

    /**
     * Ensures that the backup directory exists, creates it if not
     *
     * @throws Exception If directory cannot be created
     */
    private function ensureBackupDirectoryExists(): void
    {
        if (!is_dir($this->config->getBackupPath())) {
            if (!mkdir($this->config->getBackupPath(), 0755, true)) {
                $this->logger->error("Could not create backup directory: {$this->config->getBackupPath()}");
                throw new Exception("Could not create backup directory: {$this->config->getBackupPath()}");
            }
            $this->logger->info("Backup directory created: {$this->config->getBackupPath()}");
        }
    }

    /**
     * Triggers progress callback
     *
     * @param string $status Message
     * @param int $current Current step
     * @param int $total Total steps
     */
    private function triggerProgress(string $status, int $current, int $total): void
    {
        $progressCallback = $this->config->getProgressCallback();
        if ($progressCallback) {
            call_user_func($progressCallback, $status, $current, $total);
        }
    }

    /**
     * Returns a list of existing backup files
     *
     * @return array Information about backup files
     */
    public function listBackups(): array
    {
        $cachedBackups = $this->cache->get('database_backups_list');
        if ($cachedBackups !== null) {
            return $cachedBackups;
        }

        $backups = $this->getBackupFiles();
        $this->cache->set('database_backups_list', $backups);
        return $backups;
    }

    /**
     * Finds backup files and returns their information
     *
     * @return array Information about backup files
     */
    private function getBackupFiles(): array
    {
        $rawFiles = scandir($this->config->getBackupPath());
        $backups = [];

        if ($rawFiles) {
            foreach ($rawFiles as $file) {
                $isSqlFile = strtolower(substr($file, -4)) === '.sql';
                $isSqlGzFile = strtolower(substr($file, -7)) === '.sql.gz';

                if ($file === '.' || $file === '..' || !($isSqlFile || $isSqlGzFile)) {
                    continue;
                }
                $filePath = $this->config->getBackupPath() . '/' . $file;
                if (is_file($filePath)) {
                    $backups[] = [
                        'file_name' => $file,
                        'size' => $this->formatFileSize(filesize($filePath)),
                        'date_timestamp' => filemtime($filePath),
                        'date' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'compressed' => $isSqlGzFile
                    ];
                }
            }
        }

        usort($backups, function ($a, $b) {
            return $b['date_timestamp'] - $a['date_timestamp'];
        });

        foreach ($backups as &$backup) {
            unset($backup['date_timestamp']);
        }

        return $backups;
    }

    /**
     * Converts file size to a readable format
     *
     * @param int $bytes File size (in bytes)
     * @return string Formatted file size
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Creates a new database backup
     *
     * @return array Success status and message ['success' => bool, 'message' => string, 'fileName' => string|null]
     */
    public function createBackup(): array
    {
        $this->logger->info("Backup process is starting...");
        $startTime = microtime(true);

        try {
            // Create backup content with SQL Generator
            list($output, $currentOperation, $totalOperations) = $this->sqlGenerator->generateSqlBackup();

            // Determine file name and path
            $fileNameSuffix = $this->config->isCompressOutput() ? '.sql.gz' : '.sql';
            $fileName = 'backup_' . $this->config->getDbConfig()['dbname'] . '_' . date('Y-m-d_H-i-s') . $fileNameSuffix;
            $filePath = $this->config->getBackupPath() . '/' . $fileName;

            // Save the file
            if ($this->config->isCompressOutput()) {
                $this->triggerProgress("Compressing backup: {$fileName}", ++$currentOperation, $totalOperations);
                $gz = gzopen($filePath, 'w9');
                if ($gz === false) {
                    throw new Exception("Compressed backup file could not be opened/written: {$filePath}");
                }
                gzwrite($gz, $output);
                gzclose($gz);
                $this->logger->info("Backup file successfully compressed: {$filePath}");
            } else {
                if (file_put_contents($filePath, $output) === false) {
                    throw new Exception("Backup file could not be written: {$filePath}");
                }
                $this->logger->info("Backup file successfully created: {$filePath}");
            }

            // Clean old backups
            $this->triggerProgress("Cleaning old backups", ++$currentOperation, $totalOperations);
            $this->cleanOldBackups();
            $this->cache->forget('database_backups_list');

            // Upload to FTP (if enabled)
            if ($this->config->isFtpEnabled() && $this->ftpUploader) {
                $this->logger->info("Starting FTP upload: {$fileName}");
                try {
                    $this->ftpUploader->upload($filePath, $fileName);
                    $this->logger->info("Backup file successfully uploaded to FTP: {$fileName}");
                } catch (Exception $e) {
                    $this->logger->error('Automatic FTP backup error: ' . $e->getMessage());
                    // FTP error doesn't make the backup creation fail, continue
                }
            }

            // Return result information
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            $this->logger->info("Backup successfully completed. File: {$fileName}. Duration: {$duration} seconds.");
            return [
                'success' => true,
                'message' => "Backup successfully completed. File: {$fileName}",
                'fileName' => $fileName
            ];

        } catch (Exception $e) {
            $this->logger->error('Backup error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during backup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Deletes old spare files (according to number and age)
     */
    public function cleanOldBackups(): void
    {
        $this->logger->info("Cleaning old backups...");
        $files = [];
        $dirHandle = opendir($this->config->getBackupPath());

        if ($dirHandle) {
            while (false !== ($entry = readdir($dirHandle))) {
                $isSqlFile = strtolower(substr($entry, -4)) === '.sql';
                $isSqlGzFile = strtolower(substr($entry, -7)) === '.sql.gz';

                if ($entry != "." && $entry != ".." && ($isSqlFile || $isSqlGzFile)) {
                    $filePath = $this->config->getBackupPath() . '/' . $entry;
                    if (is_file($filePath)) {
                        $files[$filePath] = filemtime($filePath);
                    }
                }
            }
            closedir($dirHandle);
        }

        arsort($files);
        $deletedCount = 0;

        // Clean based on maximum backup count
        if (count($files) > $this->config->getMaxBackupCount()) {
            $filesToDelete = array_slice($files, $this->config->getMaxBackupCount(), null, true);
            foreach (array_keys($filesToDelete) as $fileToDelete) {
                if (unlink($fileToDelete)) {
                    $this->logger->info("Old backup (count limit) deleted: {$fileToDelete}");
                    $deletedCount++;
                } else {
                    $this->logger->error("Could not delete old backup (count limit): {$fileToDelete}");
                }
            }
            $files = array_slice($files, 0, $this->config->getMaxBackupCount(), true);
        }

        // Clean based on age
        $cutoffDate = (new DateTime())->sub(new DateInterval("P{$this->config->getMaxBackupAgeDays()}D"))->getTimestamp();
        foreach ($files as $file => $timestamp) {
            if ($timestamp < $cutoffDate) {
                if (unlink($file)) {
                    $this->logger->info("Old backup (age limit) deleted: {$file}");
                    $deletedCount++;
                } else {
                    $this->logger->error("Could not delete old backup (age limit): {$file}");
                }
            }
        }

        if ($deletedCount > 0) {
            $this->cache->forget('database_backups_list');
        }

        $this->logger->info("Old backup cleanup completed. {$deletedCount} files deleted.");
    }

    /**
     * Prepares the specified backup file for download
     *
     * @param string $fileName File name to download
     * @return array File information if successful, error message if not
     */
    public function prepareDownload(string $fileName): array
    {
        $filePath = $this->config->getBackupPath() . '/' . basename($fileName);

        $isSqlFile = strtolower(substr($fileName, -4)) === '.sql';
        $isSqlGzFile = strtolower(substr($fileName, -7)) === '.sql.gz';

        if (!file_exists($filePath) || !($isSqlFile || $isSqlGzFile)) {
            $this->logger->error("Backup file to download not found or invalid: {$fileName}");
            return ['success' => false, 'message' => 'Backup file not found or invalid.'];
        }

        $mimeType = $isSqlGzFile ? 'application/gzip' : 'application/sql';
        if ($isSqlGzFile && !function_exists('gzopen')) {
            // gzopen function is not available but this is not critical for the download operation
        }

        $this->logger->info("File prepared for download: {$fileName}");
        return [
            'success' => true,
            'filePath' => $filePath,
            'fileName' => basename($fileName),
            'mimeType' => $mimeType,
            'isCompressed' => $isSqlGzFile,
            'message' => 'File ready for download.'
        ];
    }

    /**
     * Deletes the specified backup file.
     *
     * @param string $fileName File name to delete.
     * @return array Operation result ['success' => bool, 'message' => string]
     */
    public function deleteBackup(string $fileName): array
    {
        try {
            $filePath = $this->config->getBackupPath() . '/' . basename($fileName);

            $isSqlFile = strtolower(substr($fileName, -4)) === '.sql';
            $isSqlGzFile = strtolower(substr($fileName, -7)) === '.sql.gz';

            if (!file_exists($filePath) || !($isSqlFile || $isSqlGzFile)) {
                return ['success' => false, 'message' => 'Backup file to delete not found or invalid.'];
            }

            if (unlink($filePath)) {
                $this->cache->forget('database_backups_list');
                $this->logger->info("Backup file successfully deleted: {$fileName}");
                return ['success' => true, 'message' => 'Backup file successfully deleted.'];
            } else {
                $this->logger->error("An error occurred while deleting file: {$fileName}");
                return ['success' => false, 'message' => 'An error occurred while deleting the file.'];
            }
        } catch (Exception $e) {
            $this->logger->error("File deletion error ({$fileName}): " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while deleting the file: ' . $e->getMessage()];
        }
    }

    /**
     * Uploads the specified backup file to FTP.
     *
     * @param string $fileName File name to upload.
     * @return array Operation result ['success' => bool, 'message' => string]
     */
    public function uploadBackupToFtp(string $fileName): array
    {
        try {
            if (!$this->config->isFtpEnabled() || !$this->ftpUploader) {
                return ['success' => false, 'message' => 'FTP backup feature is not active.'];
            }

            $localFilePath = $this->config->getBackupPath() . '/' . basename($fileName);
            $isSqlFile = strtolower(substr($fileName, -4)) === '.sql';
            $isSqlGzFile = strtolower(substr($fileName, -7)) === '.sql.gz';

            if (!file_exists($localFilePath) || !($isSqlFile || $isSqlGzFile)) {
                return ['success' => false, 'message' => 'Backup file to upload not found or invalid.'];
            }

            $this->ftpUploader->upload($localFilePath, basename($fileName));
            $this->logger->info("Manual FTP upload successful: {$fileName}");
            return ['success' => true, 'message' => 'Backup file successfully uploaded to FTP server.'];

        } catch (Exception $e) {
            $this->logger->error('An error occurred during manual FTP upload: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during FTP upload: ' . $e->getMessage()
            ];
        }
    }
}