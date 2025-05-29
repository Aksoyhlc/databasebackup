<?php

namespace Aksoyhlc\Databasebackup\Core;

use PDO;
use PDOException;
use Exception;
use Aksoyhlc\Databasebackup\Helpers\Logger;

/**
 * DatabaseConnection - Manages database connection
 *
 * This class establishes and manages the database connection required
 * for database backup operations. It tries alternative connection options
 * in case of connection problems and provides error management.
 *
 * @package Aksoyhlc\Databasebackup\Core
 */
class DatabaseConnection
{
    /**
     * PDO connection object
     * @var PDO
     */
    private PDO $pdo;
    
    /**
     * Database connection settings
     * @var array
     */
    private array $dbConfig;
    
    /**
     * Logger object
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Initializes the DatabaseConnection class
     *
     * @param array $dbConfig Database configuration [host, dbname, user, pass, charset, port]
     * @param Logger $logger Object to be used for logging
     */
    public function __construct(array $dbConfig, Logger $logger)
    {
        $this->dbConfig = $dbConfig;
        $this->logger = $logger;
        $this->connect();
    }
    
    /**
     * Connects to the database
     *
     * @throws Exception If connection fails
     */
    private function connect(): void
    {
        $host = $this->dbConfig['host'];
        $db = $this->dbConfig['dbname'];
        $user = $this->dbConfig['user'];
        $pass = $this->dbConfig['pass'];
        $charset = $this->dbConfig['charset'] ?? 'utf8mb4';
        $port = $this->dbConfig['port'] ?? 3306;
        
        // If host is localhost, 127.0.0.1 will also be tried if connection fails
        $tryAlternativeHost = ($host === 'localhost');
        $alternativeHost = '127.0.0.1';
        
        // First check server connection, then connect to the database
        $serverDsn = "mysql:host={$host};port={$port};charset={$charset}";
        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            // First connect to the server
            $serverPdo = new PDO($serverDsn, $user, $pass, $pdoOptions);
            
            // Check if the database exists
            $stmt = $serverPdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$db}'");
            $dbExists = $stmt->fetchColumn();
            
            if (!$dbExists) {
                $this->logger->error("Database not found: {$db}");
                throw new Exception("Database not found: {$db}");
            }
            
            // Connect to the database
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
            $this->pdo = new PDO($dsn, $user, $pass, $pdoOptions);
            $this->logger->info("Successfully connected to database: {$db}");
        } catch (PDOException $e) {
            // If connection fails with localhost, try with 127.0.0.1
            if ($tryAlternativeHost) {
                $this->logger->info("Connection failed with '{$host}', trying '{$alternativeHost}'...");
                
                try {
                    // Connect to the server with alternative host
                    $alternativeServerDsn = "mysql:host={$alternativeHost};port={$port};charset={$charset}";
                    $serverPdo = new PDO($alternativeServerDsn, $user, $pass, $pdoOptions);
                    
                    // Check if the database exists
                    $stmt = $serverPdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$db}'");
                    $dbExists = $stmt->fetchColumn();
                    
                    if (!$dbExists) {
                        $this->logger->error("Database not found: {$db}");
                        throw new Exception("Database not found: {$db}");
                    }
                    
                    // Connect to the database
                    $alternativeDsn = "mysql:host={$alternativeHost};port={$port};dbname={$db};charset={$charset}";
                    $this->pdo = new PDO($alternativeDsn, $user, $pass, $pdoOptions);
                    $this->logger->info("Successfully connected to database with alternative host ({$alternativeHost}): {$db}");
                    return;
                } catch (PDOException $e2) {
                    // Connection failed with both hosts
                    $this->logger->error("Database connection error (with both '{$host}' and '{$alternativeHost}'): " . $e2->getMessage());
                    throw new Exception("Could not establish database connection. Please check your connection settings.");
                }
            }
            
            // No localhost attempt was made and connection failed
            $this->logger->error("Database connection error: " . $e->getMessage());
            throw new Exception("Could not establish database connection. Error: " . $e->getMessage());
        }
    }
    
    /**
     * Returns the PDO object
     *
     * @return PDO PDO object
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
    
    /**
     * Executes a query and returns the results
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return \PDOStatement Query result
     */
    public function query(string $query, array $params = []): \PDOStatement
    {
        if (empty($params)) {
            return $this->pdo->query($query);
        } else {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        }
    }
    
    /**
     * Creates a prepared query object
     *
     * @param string $query SQL query
     * @return \PDOStatement Prepared query object
     */
    public function prepare(string $query): \PDOStatement
    {
        return $this->pdo->prepare($query);
    }
    
    /**
     * Executes a query and returns the number of affected rows
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return int Number of affected rows
     */
    public function execute(string $query, array $params = []): int
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * Tests the database connection
     *
     * @return bool Connection status
     */
    public function testConnection(): bool
    {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            $this->logger->error("Connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Returns the database version
     *
     * @return string Database version
     */
    public function getDatabaseVersion(): string
    {
        $versionQuery = $this->pdo->query('SELECT VERSION() as version');
        return $versionQuery->fetchColumn();
    }
}