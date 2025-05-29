<?php

namespace Aksoyhlc\Databasebackup;

use Exception;
use Aksoyhlc\Databasebackup\Core\ConfigManager;
use Aksoyhlc\Databasebackup\Core\DatabaseConnection;
use Aksoyhlc\Databasebackup\Helpers\Logger;
use Aksoyhlc\Databasebackup\Helpers\CacheManager;
use Aksoyhlc\Databasebackup\Backup\BackupManager;
use Aksoyhlc\Databasebackup\Storage\FtpUploader;

/**
 * DatabaseBackupService - MySQL/MariaDB Database Backup System
 *
 * This class is the main entry point for performing operations such as 
 * backing up, listing, downloading, deleting, and uploading MySQL or 
 * MariaDB databases to FTP.
 *
 * Features:
 * - Complete database backup (including tables, views, triggers, and stored procedures)
 * - Exclusion of selected tables or backing up only structure/data
 * - Compression of backups (gzip)
 * - Automatic cleanup of old backups (based on number and age)
 * - Automatic or manual upload to FTP/FTPS
 * - Progress tracking
 * - Comprehensive logging
 *
 * @package Aksoyhlc\Databasebackup
 * @author Ökkeş Aksoy <aksoyhlc@gmail.com>
 * @version 1.0.0
 */
class DatabaseBackupService
{
    /**
     * Configuration manager
     * @var ConfigManager
     */
    private ConfigManager $config;

    /**
     * Database connection
     * @var DatabaseConnection
     */
    private DatabaseConnection $dbConnection;

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
     * Backup manager
     * @var BackupManager
     */
    private BackupManager $backupManager;

    /**
     * Creates a DatabaseBackupService.
     *
     * @param array $dbConfig Database connection settings:
     *                        - 'host': Database server (e.g. 'localhost')
     *                        - 'dbname': Database name
     *                        - 'user': Database username
     *                        - 'pass': Database password
     *                        - 'charset': Character set (optional, default: 'utf8mb4')
     *                        - 'port': Port number (optional, default: 3306)
     *
     * @param string $backupPath Directory path where backups will be stored
     *
     * @param array $options Optional settings (all optional):
     *
     *                       # Cache Settings
     *                       - 'cacheTime': Cache duration in seconds (default: 3600)
     *
     *                       # Backup File Cleanup Settings
     *                       - 'maxBackupCount': Maximum number of backups to keep (default: 10)
     *                       - 'maxBackupAgeDays': Maximum backup age to keep, days (default: 365)
     *
     *                       # Content Filtering Settings
     *                       - 'excludedTables': Tables to exclude from backup (default: [])
     *                                Example: ['log_table', 'temp_data']
     *                       - 'tableModes': Table backup modes (default: [])
     *                                Values: 'full' (default), 'structure_only', 'data_only'
     *                                Example: ['large_table' => 'structure_only', 'settings' => 'data_only']
     *
     *                       # Backup Format Settings
     *                       - 'compressOutput': Compress the backup file? (default: false)
     *                       - 'removeDefiners': Remove SQL DEFINER statements? (default: true)
     *
     *                       # Progress Tracking
     *                       - 'progressCallback': Progress callback function (default: null)
     *                                Example: function($status, $current, $total) { echo "$status: $current/$total\n"; }
     *
     *                       # FTP Settings
     *                       - 'ftpConfig': FTP configuration (default: ['enabled' => false])
     *                                - 'enabled': Is FTP active? (default: false)
     *                                - 'host': FTP server address
     *                                - 'username': FTP username
     *                                - 'password': FTP password
     *                                - 'port': FTP port number (default: 21)
     *                                - 'path': Remote directory path (default: '/')
     *                                - 'ssl': Use SSL? (default: false)
     *                                - 'passive': Use passive mode? (default: true)
     *
     * @throws Exception If database connection cannot be established or backup directory cannot be created
     */
    public function __construct(array $dbConfig, string $backupPath, array $options = [])
    {
        // Create configuration manager
        $this->config = new ConfigManager($dbConfig, $backupPath, $options);

        // Create logger
        $this->logger = new Logger($backupPath);

        // Create database connection
        try {
            $this->dbConnection = new DatabaseConnection($dbConfig, $this->logger);
        } catch (Exception $e) {
            $this->logger->error("Could not establish database connection: " . $e->getMessage());
            throw $e;
        }

        // Create cache manager
        $this->cache = new CacheManager(
            $this->config->getCacheFilePath(),
            $this->config->getCacheTime()
        );

        // Create backup manager
        $this->backupManager = new BackupManager(
            $this->config,
            $this->dbConnection,
            $this->logger,
            $this->cache
        );
    }

    /**
     * Returns a list of existing backup files.
     *
     * @return array Information about backup files.
     */
    public function listBackups(): array
    {
        return $this->backupManager->listBackups();
    }

    /**
     * Creates a new database backup.
     *
     * @return array Success status and message ['success' => bool, 'message' => string, 'fileName' => string|null]
     */
    public function createBackup(): array
    {
        return $this->backupManager->createBackup();
    }

    /**
     * Deletes old backup files (by number and age).
     */
    public function cleanOldBackups(): void
    {
        $this->backupManager->cleanOldBackups();
    }

    /**
     * Prepares the specified backup file for download.
     *
     * @param string $fileName File name to download.
     * @return array File information if successful, error message if not.
     */
    public function prepareDownload(string $fileName): array
    {
        return $this->backupManager->prepareDownload($fileName);
    }

    /**
     * Deletes the specified backup file.
     *
     * @param string $fileName File name to delete.
     * @return array Success status and message.
     */
    public function deleteBackup(string $fileName): array
    {
        return $this->backupManager->deleteBackup($fileName);
    }

    /**
     * Uploads the selected backup file to FTP.
     *
     * @param string $fileName Name of the file to upload (only file name, not path).
     * @return array Success status and message ['success' => bool, 'message' => string]
     */
    public function uploadBackupToFtp(string $fileName): array
    {
        return $this->backupManager->uploadBackupToFtp($fileName);
    }

    /**
     * Writes a message to the log file.
     *
     * @param string $message Message to log.
     * @param string $level Log level (ERROR, INFO, DEBUG).
     */
    public function logMessage(string $message, string $level = Logger::LOG_LEVEL_INFO): void
    {
        $this->logger->log($message, $level);
    }

    /**
     * Logs an error message.
     *
     * @param string $message Error message to log.
     */
    public function error(string $message): void
    {
        $this->logger->error($message);
    }

    /**
     * Logs an information message.
     *
     * @param string $message Information message to log.
     */
    public function info(string $message): void
    {
        $this->logger->info($message);
    }

    /**
     * Logs a debug message.
     *
     * @param string $message Debug message to log.
     */
    public function debug(string $message): void
    {
        $this->logger->debug($message);
    }

    /**
     * Tests the database connection.
     *
     * @return bool Is the connection successful?
     */
    public function testDatabaseConnection(): bool
    {
        return $this->dbConnection->testConnection();
    }

    /**
     * Tests the FTP connection.
     *
     * @return bool Is the connection successful?
     */
    public function testFtpConnection(): bool
    {
        if (!$this->config->isFtpEnabled()) {
            return false;
        }
        
        $ftpUploader = new FtpUploader($this->config, $this->logger);
        return $ftpUploader->testConnection();
    }

    /**
     * Returns the database version.
     *
     * @return string Database version
     */
    public function getDatabaseVersion(): string
    {
        return $this->dbConnection->getDatabaseVersion();
    }
}