<?php

namespace Aksoyhlc\Databasebackup\Backup;

use PDO;
use Aksoyhlc\Databasebackup\Core\ConfigManager;
use Aksoyhlc\Databasebackup\Core\DatabaseConnection;
use Aksoyhlc\Databasebackup\Helpers\Logger;

/**
 * SqlGenerator - Creates database backup content in SQL format
 *
 * This class is used to generate SQL commands for database backup operations.
 * It produces SQL code for table structures, table data, views, triggers, 
 * and stored procedures.
 *
 * @package Aksoyhlc\Databasebackup\Backup
 */
class SqlGenerator
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
     * Initializes the SqlGenerator class
     *
     * @param DatabaseConnection $dbConnection Database connection
     * @param ConfigManager $config Configuration manager
     * @param Logger $logger Object to be used for logging
     */
    public function __construct(
        DatabaseConnection $dbConnection,
        ConfigManager $config,
        Logger $logger
    ) {
        $this->dbConnection = $dbConnection;
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Creates SQL content for complete database backup
     *
     * @return array [SQL content, current operation count, total operation count]
     */
    public function generateSqlBackup(): array
    {
        $pdo = $this->dbConnection->getPdo();
        $output = $this->generateBackupHeader();
        $currentOperation = 1;
        
        // Get lists of tables, views, triggers, and stored procedures
        $allTables = $pdo->query('SHOW FULL TABLES WHERE Table_Type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
        $allViews = $pdo->query('SHOW FULL TABLES WHERE Table_Type = "VIEW"')->fetchAll(PDO::FETCH_NUM);
        $allTriggers = $pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_OBJ);
        $allRoutines = $pdo->query('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_OBJ);
        
        // Calculate total number of operations
        $totalOperations = 0;
        foreach ($allTables as $tableArray) {
            if (in_array($tableArray[0], $this->config->getExcludedTables())) continue;
            $mode = $this->config->getTableModes()[$tableArray[0]] ?? 'full';
            if ($mode === 'full' || $mode === 'structure_only') $totalOperations++;
            if ($mode === 'full' || $mode === 'data_only') $totalOperations++;
        }
        $totalOperations += count($allViews) + count($allTriggers) + count($allRoutines) + 3;
        
        // Process table structures and data
        $this->logger->debug("Getting table structures and data...");
        foreach ($allTables as $tableArray) {
            $tableName = $tableArray[0];
            if (in_array($tableName, $this->config->getExcludedTables())) {
                $this->logger->info("{$tableName} table excluded.");
                continue;
            }

            $mode = $this->config->getTableModes()[$tableName] ?? 'full';

            if ($mode === 'full' || $mode === 'structure_only') {
                $this->triggerProgress("Getting table structure: {$tableName}", ++$currentOperation, $totalOperations);
                $output .= $this->getTableStructure($tableName);
            }
            if ($mode === 'full' || $mode === 'data_only') {
                $this->triggerProgress("Getting table data: {$tableName}", ++$currentOperation, $totalOperations);
                $output .= $this->getTableData($tableName);
            }
        }

        // Process views
        $this->logger->debug("Getting view structures...");
        foreach ($allViews as $viewArray) {
            $viewName = $viewArray[0];
            $this->triggerProgress("Getting view structure: {$viewName}", ++$currentOperation, $totalOperations);
            $output .= $this->getViewStructure($viewName);
        }

        // Process triggers
        $this->logger->debug("Getting trigger structures...");
        foreach ($allTriggers as $trigger) {
            $this->triggerProgress("Getting trigger structure: {$trigger->Trigger}", ++$currentOperation, $totalOperations);
            $output .= $this->getTriggerStructure($trigger);
        }

        // Process stored procedures and functions
        $this->logger->debug("Getting routine (Procedure/Function) structures...");
        foreach ($allRoutines as $routine) {
            $fullRoutineInfo = $pdo->query(
                "SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = '{$routine->ROUTINE_NAME}'"
            )->fetch(PDO::FETCH_OBJ);

            if ($fullRoutineInfo) {
                $this->triggerProgress("Getting routine structure: {$fullRoutineInfo->ROUTINE_NAME}", ++$currentOperation, $totalOperations);
                $output .= $this->getRoutineStructure($fullRoutineInfo);
            }
        }
        
        // Finalize backup file
        $output .= $this->generateBackupFooter();
        $this->triggerProgress("Finalizing backup", ++$currentOperation, $totalOperations);
        
        return [$output, $currentOperation, $totalOperations];
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
     * Creates backup file header information
     *
     * @return string SQL header content
     */
    public function generateBackupHeader(): string
    {
        $pdo = $this->dbConnection->getPdo();
        $versionQuery = $pdo->query('SELECT VERSION() as version');
        $dbVersion = $versionQuery->fetchColumn();
        $dbConfig = $this->config->getDbConfig();

        return "-- -------------------------------------------------------\n" .
            "-- Database Backup: {$dbConfig['dbname']}\n" .
            "-- Server Version: {$dbVersion}\n" .
            '-- Creation Date: ' . date('Y-m-d H:i:s') . "\n" .
            "-- -------------------------------------------------------\n\n" .
            "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n" .
            "SET AUTOCOMMIT = 0;\n" .
            "START TRANSACTION;\n" .
            "SET time_zone = \"+00:00\";\n\n" .
            "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n" .
            "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n" .
            "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n" .
            "SET NAMES {$this->config->getEffectiveCharset()};\n" .
            "SET FOREIGN_KEY_CHECKS=0;\n\n";
    }

    /**
     * Creates backup file footer information
     *
     * @return string SQL footer content
     */
    public function generateBackupFooter(): string
    {
        return "\nSET FOREIGN_KEY_CHECKS=1;\n" .
            "COMMIT;\n\n" .
            "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n" .
            "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n" .
            "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n" .
            "-- Backup completed: " . date('Y-m-d H:i:s') . "\n";
    }

    /**
     * Returns the table structure as SQL
     *
     * @param string $tableName Table name
     * @return string Table structure SQL query
     */
    public function getTableStructure(string $tableName): string
    {
        $pdo = $this->dbConnection->getPdo();
        $this->logger->debug("Getting structure for `{$tableName}`...");
        $stmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
        $structure = $stmt->fetch(PDO::FETCH_ASSOC);
        return "\n--\n-- Table structure: `{$tableName}`\n--\n\n" .
            "DROP TABLE IF EXISTS `{$tableName}`;\n" .
            $structure['Create Table'] . ";\n\n";
    }

    /**
     * Returns table data as SQL
     *
     * @param string $tableName Table name
     * @return string Table data SQL query
     */
    public function getTableData(string $tableName): string
    {
        $pdo = $this->dbConnection->getPdo();
        $this->logger->debug("Getting data for `{$tableName}`...");
        $output = '';
        $stmt = $pdo->prepare("SELECT * FROM `{$tableName}`");
        $stmt->execute();

        $rowCount = 0;
        $columns = [];
        $batchSize = 100;
        $currentBatch = [];

        if ($stmt->rowCount() > 0) {
            $output .= "\n--\n-- Dumping table data: `{$tableName}`\n--\n";
            $output .= "LOCK TABLES `{$tableName}` WRITE;\n";
            $output .= "/*!40000 ALTER TABLE `{$tableName}` DISABLE KEYS */;\n";
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($rowCount === 0) {
                $columns = array_keys($row);
            }

            $rowData = array_map(function ($value) {
                if (is_null($value)) return 'NULL';
                if (is_string($value)) {
                    // Create safe SQL string
                    return "'" . addslashes($value) . "'";
                }
                if (is_bool($value)) return $value ? '1' : '0';
                return $value;
            }, $row);
            $currentBatch[] = '(' . implode(', ', $rowData) . ')';
            $rowCount++;

            if (count($currentBatch) >= $batchSize) {
                $output .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES\n" .
                    implode(",\n", $currentBatch) . ";\n";
                $currentBatch = [];
            }
        }

        if (!empty($currentBatch)) {
            $output .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES\n" .
                implode(",\n", $currentBatch) . ";\n";
        }

        if ($stmt->rowCount() > 0) {
            $output .= "/*!40000 ALTER TABLE `{$tableName}` ENABLE KEYS */;\n";
            $output .= "UNLOCK TABLES;\n";
        }
        $stmt->closeCursor();
        return $output;
    }

    /**
     * Returns view structure as SQL
     *
     * @param string $viewName View name
     * @return string View structure SQL query
     */
    public function getViewStructure(string $viewName): string
    {
        $pdo = $this->dbConnection->getPdo();
        $this->logger->debug("Getting view structure for `{$viewName}`...");
        $stmt = $pdo->query("SHOW CREATE VIEW `{$viewName}`");
        $view = $stmt->fetch(PDO::FETCH_ASSOC);
        $createViewSql = $view['Create View'];

        if ($this->config->isRemoveDefiners()) {
            $createViewSql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $createViewSql);
        }

        return "\n--\n-- View structure: `{$viewName}`\n--\n\n" .
            "DROP VIEW IF EXISTS `{$viewName}`;\n" .
            "/*!50001 CREATE ALGORITHM=UNDEFINED */\n" .
            "/*!50013 " . trim($createViewSql) . " */;\n\n";
    }

    /**
     * Returns trigger structure as SQL
     *
     * @param object $trigger Trigger object
     * @return string Trigger structure SQL query
     */
    public function getTriggerStructure(object $trigger): string
    {
        $pdo = $this->dbConnection->getPdo();
        $this->logger->debug("Getting trigger structure for `{$trigger->Trigger}`...");

        $sql = "\n--\n-- Trigger: `{$trigger->Trigger}`\n--\n";
        $sql .= "DROP TRIGGER IF EXISTS `{$trigger->Trigger}`;\n";
        $sql .= "DELIMITER ;;\n";

        $stmt = $pdo->query("SHOW CREATE TRIGGER `{$trigger->Trigger}`");
        $triggerDef = $stmt->fetch(PDO::FETCH_ASSOC);
        $createTriggerSql = $triggerDef['SQL Original Statement'] ??
            ($triggerDef["Create Trigger"] ??
                "TRIGGER `{$trigger->Trigger}` {$trigger->Timing} {$trigger->Event} ON `{$trigger->Table}` FOR EACH ROW {$trigger->Statement}");

        if ($this->config->isRemoveDefiners()) {
            $createTriggerSql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $createTriggerSql, 1);
        }
        
        $sql .= "CREATE " . $createTriggerSql . ";;\n";
        $sql .= "DELIMITER ;\n\n";
        
        return $sql;
    }

    /**
     * Returns stored procedure or function structure as SQL
     *
     * @param object $routine Stored procedure/function object
     * @return string Stored procedure/function structure SQL query
     */
    public function getRoutineStructure(object $routine): string
    {
        $pdo = $this->dbConnection->getPdo();
        $type = $routine->ROUTINE_TYPE;
        $name = $routine->ROUTINE_NAME;
        $this->logger->debug("Getting routine structure for `{$name}` ({$type})...");

        $stmt = $pdo->query("SHOW CREATE {$type} `{$name}`");
        $definition = $stmt->fetch(PDO::FETCH_ASSOC);
        $createKey = "Create " . ucfirst(strtolower($type));

        $createSql = $definition[$createKey] ?? null;
        if (!$createSql && $type === 'PROCEDURE') $createSql = $definition['Create Procedure'] ?? null;
        if (!$createSql && $type === 'FUNCTION') $createSql = $definition['Create Function'] ?? null;

        if (!$createSql) {
            $this->logger->error("Could not get definition for `{$name}` ({$type}).");
            return "-- ERROR: Could not get definition for {$name} ({$type}).\n";
        }

        if ($this->config->isRemoveDefiners()) {
            $createSql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $createSql, 1);
        }

        return "\n--\n-- {$type}: `{$name}`\n--\n" .
            "DROP {$type} IF EXISTS `{$name}`;\n" .
            "DELIMITER ;;\n" .
            $createSql . ";;\n" .
            "DELIMITER ;\n\n";
    }
}