<?php

namespace Aksoyhlc\Databasebackup\Core;

/**
 * ConfigManager - Manages database backup configurations
 *
 * This class stores and manages all configuration parameters needed for
 * database backup operations. Database connection information, backup
 * directory, FTP settings, and other options are managed through this class.
 *
 * @package Aksoyhlc\Databasebackup\Core
 */
class ConfigManager
{
    /**
     * Database connection settings
     * @var array
     */
    private array $dbConfig;

    /**
     * Directory where backups will be stored
     * @var string
     */
    private string $backupPath;

    /**
     * Database character set
     * @var string
     */
    private string $effectiveCharset;

    /**
     * Cache duration (seconds)
     * @var int
     */
    private int $cacheTime;

    /**
     * Cache file path
     * @var string
     */
    private string $cacheFilePath;

    /**
     * Maximum number of backup files
     * @var int
     */
    private int $maxBackupCount;

    /**
     * Maximum backup age (days)
     * @var int
     */
    private int $maxBackupAgeDays;

    /**
     * Tables to exclude from backup
     * @var array
     */
    private array $excludedTables = [];

    /**
     * Table backup modes
     * @var array
     */
    private array $tableModes = [];

    /**
     * Should output be compressed?
     * @var bool
     */
    private bool $compressOutput = false;

    /**
     * Should DEFINER statements be removed?
     * @var bool
     */
    private bool $removeDefiners = true;

    /**
     * Progress callback function
     * @var callable|null
     */
    private $progressCallback = null;

    /**
     * Is FTP active?
     * @var bool
     */
    private bool $ftpEnabled = false;

    /**
     * FTP server address
     * @var string
     */
    private string $ftpHost = '';

    /**
     * FTP username
     * @var string
     */
    private string $ftpUsername = '';

    /**
     * FTP password
     * @var string
     */
    private string $ftpPassword = '';

    /**
     * FTP port number
     * @var int
     */
    private int $ftpPort = 21;

    /**
     * FTP remote directory path
     * @var string
     */
    private string $ftpRemotePath = '/';

    /**
     * Should FTP use SSL?
     * @var bool
     */
    private bool $ftpSsl = false;

    /**
     * Should FTP use passive mode?
     * @var bool
     */
    private bool $ftpPassive = true;

    /**
     * Creates ConfigManager
     *
     * @param array $dbConfig Database connection settings [host, dbname, user, pass, charset, port (optional)]
     * @param string $backupPath Directory where backups will be stored
     * @param array $options Optional settings:
     *                       'cacheTime' (int, seconds),
     *                       'maxBackupCount' (int),
     *                       'maxBackupAgeDays' (int),
     *                       'excludedTables' (array),
     *                       'tableModes' (array), ['tableName' => 'mode']
     *                       'compressOutput' (bool),
     *                       'removeDefiners' (bool),
     *                       'progressCallback' (callable),
     *                       'ftpConfig' (array) [enabled, host, username, password, port, path, ssl, passive]
     */
    public function __construct(array $dbConfig, string $backupPath, array $options = [])
    {
        $this->dbConfig = $dbConfig;
        $this->backupPath = rtrim($backupPath, '/\\');
        $this->effectiveCharset = $this->sanitizeCharset($this->dbConfig['charset'] ?? 'utf8mb4');

        // General settings
        $this->cacheTime = $options['cacheTime'] ?? 3600;
        $this->maxBackupCount = $options['maxBackupCount'] ?? 10;
        $this->maxBackupAgeDays = $options['maxBackupAgeDays'] ?? 365;
        $this->cacheFilePath = $this->backupPath . '/.backup_cache.json';

        // Backup settings
        $this->excludedTables = $options['excludedTables'] ?? [];
        $this->tableModes = $options['tableModes'] ?? [];
        $this->compressOutput = $options['compressOutput'] ?? false;
        $this->removeDefiners = $options['removeDefiners'] ?? true;
        if (isset($options['progressCallback']) && is_callable($options['progressCallback'])) {
            $this->progressCallback = $options['progressCallback'];
        }

        // FTP settings
        if (isset($options['ftpConfig'])) {
            $ftpConf = $options['ftpConfig'];
            $this->ftpEnabled = $ftpConf['enabled'] ?? false;
            $this->ftpHost = $ftpConf['host'] ?? '';
            $this->ftpUsername = $ftpConf['username'] ?? '';
            $this->ftpPassword = $ftpConf['password'] ?? '';
            $this->ftpPort = $ftpConf['port'] ?? 21;
            $this->ftpRemotePath = rtrim($ftpConf['path'] ?? '/', '/') . '/';
            $this->ftpSsl = $ftpConf['ssl'] ?? false;
            $this->ftpPassive = $ftpConf['passive'] ?? true;
        }
    }

    /**
     * Cleans the charset value so it can be safely used in SQL commands
     *
     * @param string $charset Charset to be cleaned
     * @return string Cleaned charset
     */
    private function sanitizeCharset(string $charset): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '', $charset);
        if (empty($cleaned)) {
            return 'utf8mb4';
        }
        return $cleaned;
    }

    /**
     * Returns the database configuration
     *
     * @return array Database configuration
     */
    public function getDbConfig(): array
    {
        return $this->dbConfig;
    }

    /**
     * Returns the backup directory
     *
     * @return string Backup directory
     */
    public function getBackupPath(): string
    {
        return $this->backupPath;
    }

    /**
     * Returns the cache file path
     *
     * @return string Cache file path
     */
    public function getCacheFilePath(): string
    {
        return $this->cacheFilePath;
    }

    /**
     * Returns the cache duration
     *
     * @return int Cache duration (seconds)
     */
    public function getCacheTime(): int
    {
        return $this->cacheTime;
    }

    /**
     * Returns the maximum number of backups
     *
     * @return int Maximum number of backups
     */
    public function getMaxBackupCount(): int
    {
        return $this->maxBackupCount;
    }

    /**
     * Returns the maximum backup age
     *
     * @return int Maximum backup age (days)
     */
    public function getMaxBackupAgeDays(): int
    {
        return $this->maxBackupAgeDays;
    }

    /**
     * Returns the excluded tables
     *
     * @return array Excluded tables
     */
    public function getExcludedTables(): array
    {
        return $this->excludedTables;
    }

    /**
     * Returns the table modes
     *
     * @return array Table modes
     */
    public function getTableModes(): array
    {
        return $this->tableModes;
    }

    /**
     * Returns the output compression status
     *
     * @return bool Is output being compressed?
     */
    public function isCompressOutput(): bool
    {
        return $this->compressOutput;
    }

    /**
     * Returns the status of DEFINER statements removal
     *
     * @return bool Are DEFINER statements being removed?
     */
    public function isRemoveDefiners(): bool
    {
        return $this->removeDefiners;
    }

    /**
     * Returns the progress callback function
     *
     * @return callable|null Progress callback function
     */
    public function getProgressCallback()
    {
        return $this->progressCallback;
    }

    /**
     * Returns the effective character set
     *
     * @return string Effective character set
     */
    public function getEffectiveCharset(): string
    {
        return $this->effectiveCharset;
    }

    /**
     * Is FTP enabled?
     *
     * @return bool Is FTP enabled?
     */
    public function isFtpEnabled(): bool
    {
        return $this->ftpEnabled;
    }

    /**
     * Returns the FTP server address
     *
     * @return string FTP server address
     */
    public function getFtpHost(): string
    {
        return $this->ftpHost;
    }

    /**
     * Returns the FTP username
     *
     * @return string FTP username
     */
    public function getFtpUsername(): string
    {
        return $this->ftpUsername;
    }

    /**
     * Returns the FTP password
     *
     * @return string FTP password
     */
    public function getFtpPassword(): string
    {
        return $this->ftpPassword;
    }

    /**
     * Returns the FTP port number
     *
     * @return int FTP port number
     */
    public function getFtpPort(): int
    {
        return $this->ftpPort;
    }

    /**
     * Returns the FTP remote directory path
     *
     * @return string FTP remote directory path
     */
    public function getFtpRemotePath(): string
    {
        return $this->ftpRemotePath;
    }

    /**
     * Returns the FTP SSL usage status
     *
     * @return bool Is FTP SSL being used?
     */
    public function isFtpSsl(): bool
    {
        return $this->ftpSsl;
    }

    /**
     * Returns the FTP passive mode usage status
     *
     * @return bool Is FTP passive mode being used?
     */
    public function isFtpPassive(): bool
    {
        return $this->ftpPassive;
    }
}