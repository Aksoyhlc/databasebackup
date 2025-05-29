<?php

namespace Aksoyhlc\Databasebackup\Helpers;

/**
 * Logger - Keeps logs for database backup operations
 *
 * This class records events, information, and errors that occur during
 * the database backup process to specified files. It can use separate files
 * for different log levels (ERROR, INFO, DEBUG).
 *
 * @package Aksoyhlc\Databasebackup\Helpers
 */
class Logger
{
    /**
     * Error level - for critical errors
     */
    public const LOG_LEVEL_ERROR = 'ERROR';
    
    /**
     * Info level - for general information
     */
    public const LOG_LEVEL_INFO = 'INFO';
    
    /**
     * Debug level - for detailed debugging information
     */
    public const LOG_LEVEL_DEBUG = 'DEBUG';
    
    /**
     * Main directory where log files will be saved
     * @var string
     */
    private string $logDirectory;
    
    /**
     * Error log filename
     * @var string
     */
    private string $errorLogFilename = 'backup_errors.log';
    
    /**
     * Activity log filename
     * @var string
     */
    private string $activityLogFilename = 'backup_activity.log';

    /**
     * Initializes the Logger class
     *
     * @param string $logDirectory Directory where log files will be saved
     */
    public function __construct(string $logDirectory)
    {
        $this->logDirectory = rtrim($logDirectory, '/\\');
        $this->ensureLogDirectoryExists();
    }
    
    /**
     * Checks if the log directory exists, creates it if not
     */
    private function ensureLogDirectoryExists(): void
    {
        if (!is_dir($this->logDirectory)) {
            if (!mkdir($this->logDirectory, 0755, true)) {
                throw new \Exception("Could not create log directory: {$this->logDirectory}");
            }
        }
    }
    
    /**
     * Writes a log message
     *
     * @param string $message Message to log
     * @param string $level Log level (ERROR, INFO, DEBUG)
     */
    public function log(string $message, string $level = self::LOG_LEVEL_ERROR): void
    {
        $logFile = $this->logDirectory . '/' . $this->activityLogFilename;
        if ($level === self::LOG_LEVEL_ERROR) {
            $logFile = $this->logDirectory . '/' . $this->errorLogFilename;
        }
        
        $formattedMessage = date('[Y-m-d H:i:s]') . " [{$level}] DatabaseBackupService: " . $message . PHP_EOL;
        error_log($formattedMessage, 3, $logFile);
    }
    
    /**
     * Logs an error message
     *
     * @param string $message Error message to log
     */
    public function error(string $message): void
    {
        $this->log($message, self::LOG_LEVEL_ERROR);
    }
    
    /**
     * Logs an information message
     *
     * @param string $message Information message to log
     */
    public function info(string $message): void
    {
        $this->log($message, self::LOG_LEVEL_INFO);
    }
    
    /**
     * Logs a debug message
     *
     * @param string $message Debug message to log
     */
    public function debug(string $message): void
    {
        $this->log($message, self::LOG_LEVEL_DEBUG);
    }
    
    /**
     * Logs an exception to the log file
     *
     * @param \Exception $e Exception to log
     * @param string $context Optional context information
     */
    public function exception(\Exception $e, string $context = ''): void
    {
        $message = ($context ? "[$context] " : '') . 'Exception: ' . $e->getMessage() . 
                  ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $this->error($message);
    }
}