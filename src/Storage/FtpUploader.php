<?php

namespace Aksoyhlc\Databasebackup\Storage;

use Exception;
use Aksoyhlc\Databasebackup\Core\ConfigManager;
use Aksoyhlc\Databasebackup\Helpers\Logger;

/**
 * FtpUploader - Manages database backup file uploads to FTP servers
 *
 * This class handles uploading database backup files to FTP/FTPS servers.
 * It manages operations such as folder creation, connection management,
 * and file transfer.
 *
 * @package Aksoyhlc\Databasebackup\Storage
 */
class FtpUploader
{
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
     * Initializes the FtpUploader class
     *
     * @param ConfigManager $config Configuration manager
     * @param Logger $logger Object to be used for logging
     */
    public function __construct(ConfigManager $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Uploads a file to the FTP server
     *
     * @param string $localFilePath Local file path
     * @param string $remoteFileName Remote server file name
     * @return bool Success status
     * @throws Exception When FTP connection or upload fails
     */
    public function upload(string $localFilePath, string $remoteFileName): bool
    {
        if (!$this->config->isFtpEnabled()) {
            $this->logger->error('FTP backup feature is not enabled.');
            throw new Exception('FTP backup feature is not enabled.');
        }
        
        if (empty($this->config->getFtpHost()) || empty($this->config->getFtpUsername())) {
            $this->logger->error('FTP backup requires Host or Username.');
            throw new Exception('Required FTP settings (host, username) are missing.');
        }
        
        if (!function_exists('ftp_connect')) {
            $this->logger->error('FTP functions (ftp_connect) are not enabled as a PHP extension.');
            throw new Exception('PHP FTP extension is not enabled.');
        }
        
        $conn = null;
        try {
            $this->logger->debug("Connecting to FTP: {$this->config->getFtpHost()}:{$this->config->getFtpPort()}");
            
            // Is it SSL connection or normal connection?
            if ($this->config->isFtpSsl()) {
                if (!function_exists('ftp_ssl_connect')) {
                    throw new Exception('PHP FTP SSL extension (ftp_ssl_connect) is not enabled.');
                }
                $conn = ftp_ssl_connect($this->config->getFtpHost(), $this->config->getFtpPort());
            } else {
                $conn = ftp_connect($this->config->getFtpHost(), $this->config->getFtpPort());
            }
            
            if (!$conn) {
                throw new Exception("Could not connect to FTP server: {$this->config->getFtpHost()}");
            }
            $this->logger->debug("FTP connection successful.");
            
            // Login
            if (!ftp_login($conn, $this->config->getFtpUsername(), $this->config->getFtpPassword())) {
                throw new Exception('Could not login to FTP server. Username or password may be incorrect.');
            }
            $this->logger->debug("FTP login successful. User: {$this->config->getFtpUsername()}");
            
            // Passive mode
            if ($this->config->isFtpPassive()) {
                if (!ftp_pasv($conn, true)) {
                    $this->logger->info("Could not switch to FTP Passive mode. Will try anyway.");
                } else {
                    $this->logger->debug("Switched to FTP Passive mode.");
                }
            }
            
            // Prepare remote directory
            $targetPath = rtrim($this->config->getFtpRemotePath(), '/') . '/' . $remoteFileName;
            $remoteDir = dirname($targetPath);
            
            // Create directories (if needed)
            if ($remoteDir !== '.' && $remoteDir !== '/') {
                if ($this->config->getFtpRemotePath() !== '' && $this->config->getFtpRemotePath() !== '/') {
                    $this->createRemoteDirectories($conn, $this->config->getFtpRemotePath());
                }
            } else {
                if (!@ftp_chdir($conn, '/')) {
                    $this->logger->debug("Could not change to FTP root directory. Will try upload anyway.");
                } else {
                    $this->logger->debug("Changed to FTP root directory.");
                }
            }
            
            // Upload file
            $this->logger->debug("Uploading file to FTP: {$remoteFileName} -> {$targetPath}");
            if (!ftp_put($conn, $remoteFileName, $localFilePath, FTP_BINARY)) {
                throw new Exception("Could not upload file to FTP server: {$remoteFileName}. Path: {$this->config->getFtpRemotePath()}");
            }
            
            $this->logger->info("Backup file successfully uploaded to FTP server: {$this->config->getFtpRemotePath()}{$remoteFileName}");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('FTP upload error: ' . $e->getMessage());
            throw $e;
        } finally {
            if ($conn) {
                ftp_close($conn);
                $this->logger->debug("FTP connection closed.");
            }
        }
    }
    
    /**
     * Creates folder structure on FTP server
     *
     * @param resource $conn FTP connection resource
     * @param string $remotePath Remote directory path to create
     * @throws Exception When directory creation fails
     */
    private function createRemoteDirectories($conn, string $remotePath): void
    {
        $folders = explode('/', trim($remotePath, '/'));
        $currentFtpPath = '';
        
        foreach ($folders as $folder) {
            if (empty($folder)) continue;
            $currentFtpPath .= '/' . $folder;
            
            if (!@ftp_chdir($conn, $currentFtpPath)) {
                $this->logger->debug("Could not change to directory on FTP, creating: {$currentFtpPath}");
                if (!ftp_mkdir($conn, $currentFtpPath)) {
                    throw new Exception("Could not create directory on FTP: {$currentFtpPath}. Check permissions.");
                }
                if (!ftp_chdir($conn, $currentFtpPath)) {
                    throw new Exception("Could not change to created directory on FTP: {$currentFtpPath}");
                }
            }
        }
        
        $this->logger->debug("Changed to target directory on FTP: {$currentFtpPath}");
    }
    
    /**
     * Tests FTP connection
     *
     * @return bool Is connection successful?
     */
    public function testConnection(): bool
    {
        if (!$this->config->isFtpEnabled()) {
            return false;
        }
        
        $conn = null;
        try {
            // Establish connection
            if ($this->config->isFtpSsl()) {
                if (!function_exists('ftp_ssl_connect')) {
                    return false;
                }
                $conn = ftp_ssl_connect($this->config->getFtpHost(), $this->config->getFtpPort());
            } else {
                $conn = ftp_connect($this->config->getFtpHost(), $this->config->getFtpPort());
            }
            
            if (!$conn) {
                return false;
            }
            
            // Login
            if (!ftp_login($conn, $this->config->getFtpUsername(), $this->config->getFtpPassword())) {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        } finally {
            if ($conn) {
                ftp_close($conn);
            }
        }
    }
}