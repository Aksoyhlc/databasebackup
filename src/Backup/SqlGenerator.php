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
 * and stored procedures. All output is streamed directly to a file handle
 * to avoid memory buildup on large datasets.
 *
 * @package Aksoyhlc\Databasebackup\Backup
 */
class SqlGenerator
{
    private DatabaseConnection $dbConnection;
    private ConfigManager $config;
    private Logger $logger;

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
     * Writes data to the output handle (gzip or plain file)
     *
     * @param resource $handle
     * @param string $data
     */
    private function write($handle, string $data): void
    {
        if (get_resource_type($handle) === 'zlib stream') {
            gzwrite($handle, $data);
        } else {
            fwrite($handle, $data);
        }
    }

    /**
     * Creates SQL content for complete database backup
     *
     * @param resource $handle Output file handle (fopen or gzopen)
     * @return array [current operation count, total operation count]
     */
    public function generateSqlBackup($handle): array
    {
        $pdo = $this->dbConnection->getPdo();
        $this->generateBackupHeader($handle);
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
                $this->getTableStructure($tableName, $handle);
            }
            if ($mode === 'full' || $mode === 'data_only') {
                $this->triggerProgress("Getting table data: {$tableName}", ++$currentOperation, $totalOperations);
                $this->getTableData($tableName, $handle);
            }
        }

        // Process views
        $this->logger->debug("Getting view structures...");
        foreach ($allViews as $viewArray) {
            $viewName = $viewArray[0];
            $this->triggerProgress("Getting view structure: {$viewName}", ++$currentOperation, $totalOperations);
            $this->getViewStructure($viewName, $handle);
        }

        // Process triggers
        $this->logger->debug("Getting trigger structures...");
        foreach ($allTriggers as $trigger) {
            $this->triggerProgress("Getting trigger structure: {$trigger->Trigger}", ++$currentOperation, $totalOperations);
            $this->getTriggerStructure($trigger, $handle);
        }

        // Process stored procedures and functions
        $this->logger->debug("Getting routine (Procedure/Function) structures...");
        foreach ($allRoutines as $routine) {
            $stmt = $pdo->query(
                "SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = " . $pdo->quote($routine->ROUTINE_NAME)
            );
            $fullRoutineInfo = $stmt->fetch(PDO::FETCH_OBJ);

            if ($fullRoutineInfo) {
                $this->triggerProgress("Getting routine structure: {$fullRoutineInfo->ROUTINE_NAME}", ++$currentOperation, $totalOperations);
                $this->getRoutineStructure($fullRoutineInfo, $handle);
            }
        }

        // Finalize backup file
        $this->generateBackupFooter($handle);
        $this->triggerProgress("Finalizing backup", ++$currentOperation, $totalOperations);

        return [$currentOperation, $totalOperations];
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
     * @param resource $handle Output file handle
     */
    public function generateBackupHeader($handle): void
    {
        $pdo = $this->dbConnection->getPdo();
        $versionQuery = $pdo->query('SELECT VERSION() as version');
        $dbVersion = $versionQuery->fetchColumn();
        $dbConfig = $this->config->getDbConfig();

        $this->write($handle,
            "-- -------------------------------------------------------\n" .
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
            "SET FOREIGN_KEY_CHECKS=0;\n\n"
        );
    }

    /**
     * Creates backup file footer information
     *
     * @param resource $handle Output file handle
     */
    public function generateBackupFooter($handle): void
    {
        $this->write($handle,
            "\nSET FOREIGN_KEY_CHECKS=1;\n" .
            "COMMIT;\n\n" .
            "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n" .
            "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n" .
            "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n" .
            "-- Backup completed: " . date('Y-m-d H:i:s') . "\n"
        );
    }

    /**
     * Returns the table structure as SQL
     *
     * @param string $tableName Table name
     * @param resource $handle Output file handle
     */
    public function getTableStructure(string $tableName, $handle): void
    {
        $pdo = $this->dbConnection->getPdo();
        $this->logger->debug("Getting structure for `{$tableName}`...");
        $stmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
        $structure = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->write($handle,
            "\n--\n-- Table structure: `{$tableName}`\n--\n\n" .
            "DROP TABLE IF EXISTS `{$tableName}`;\n" .
            $structure['Create Table'] . ";\n\n"
        );
    }

    /**
     * Returns table data as SQL (streaming, unbuffered)
     *
     * @param string $tableName Table name
     * @param resource $handle Output file handle
     */
    public function getTableData(string $tableName, $handle): void
    {
        $pdo = $this->dbConnection->getPdo();
        $batchSize = $this->config->getBatchSize();

        $this->logger->debug("Getting data for `{$tableName}`...");

        // Use unbuffered query to avoid loading all rows into memory
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $stmt = $pdo->prepare("SELECT * FROM `{$tableName}`");
        $stmt->execute();

        $rowCount = 0;
        $columns = [];
        $currentBatch = [];
        $hasData = false;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$hasData) {
                $hasData = true;
                $columns = array_keys($row);
                $this->write($handle, "\n--\n-- Dumping table data: `{$tableName}`\n--\n");
                $this->write($handle, "LOCK TABLES `{$tableName}` WRITE;\n");
                $this->write($handle, "/*!40000 ALTER TABLE `{$tableName}` DISABLE KEYS */;\n");
            }

            $rowData = array_map(function ($value) use ($pdo) {
                if (is_null($value)) return 'NULL';
                if (is_string($value)) {
                    return $pdo->quote($value);
                }
                if (is_bool($value)) return $value ? '1' : '0';
                return $value;
            }, $row);
            $currentBatch[] = '(' . implode(', ', $rowData) . ')';
            $rowCount++;

            if (count($currentBatch) >= $batchSize) {
                $this->write($handle, "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES\n" .
                    implode(",\n", $currentBatch) . ";\n");
                $currentBatch = [];
            }
        }

        $stmt->closeCursor();

        // Restore buffered mode for subsequent queries
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if (!empty($currentBatch)) {
            $this->write($handle, "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES\n" .
                implode(",\n", $currentBatch) . ";\n");
        }

        if ($hasData) {
            $this->write($handle, "/*!40000 ALTER TABLE `{$tableName}` ENABLE KEYS */;\n");
            $this->write($handle, "UNLOCK TABLES;\n");
        }
    }

    /**
     * Returns view structure as SQL
     *
     * @param string $viewName View name
     * @param resource $handle Output file handle
     */
    public function getViewStructure(string $viewName, $handle): void
    {
        $pdo = $this->dbConnection->getPdo();
        $this->logger->debug("Getting view structure for `{$viewName}`...");
        $stmt = $pdo->query("SHOW CREATE VIEW `{$viewName}`");
        $view = $stmt->fetch(PDO::FETCH_ASSOC);
        $createViewSql = $view['Create View'];

        if ($this->config->isRemoveDefiners()) {
            $createViewSql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $createViewSql);
        }

        $this->write($handle,
            "\n--\n-- View structure: `{$viewName}`\n--\n\n" .
            "DROP VIEW IF EXISTS `{$viewName}`;\n" .
            $createViewSql . ";\n\n"
        );
    }

    /**
     * Returns trigger structure as SQL
     *
     * @param object $trigger Trigger object
     * @param resource $handle Output file handle
     */
    public function getTriggerStructure(object $trigger, $handle): void
    {
        $pdo = $this->dbConnection->getPdo();
        $this->logger->debug("Getting trigger structure for `{$trigger->Trigger}`...");

        $stmt = $pdo->query("SHOW CREATE TRIGGER `{$trigger->Trigger}`");
        $triggerDef = $stmt->fetch(PDO::FETCH_ASSOC);
        $createTriggerSql = $triggerDef['SQL Original Statement'] ??
            ($triggerDef["Create Trigger"] ??
                "TRIGGER `{$trigger->Trigger}` {$trigger->Timing} {$trigger->Event} ON `{$trigger->Table}` FOR EACH ROW {$trigger->Statement}");

        if ($this->config->isRemoveDefiners()) {
            $createTriggerSql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $createTriggerSql, 1);
        }

        $this->write($handle,
            "\n--\n-- Trigger: `{$trigger->Trigger}`\n--\n" .
            "DROP TRIGGER IF EXISTS `{$trigger->Trigger}`;\n" .
            "DELIMITER ;;\n" .
            "CREATE " . $createTriggerSql . ";;\n" .
            "DELIMITER ;\n\n"
        );
    }

    /**
     * Returns stored procedure or function structure as SQL
     *
     * @param object $routine Stored procedure/function object
     * @param resource $handle Output file handle
     */
    public function getRoutineStructure(object $routine, $handle): void
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
            $this->write($handle, "-- ERROR: Could not get definition for {$name} ({$type}).\n");
            return;
        }

        if ($this->config->isRemoveDefiners()) {
            $createSql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $createSql, 1);
        }

        $this->write($handle,
            "\n--\n-- {$type}: `{$name}`\n--\n" .
            "DROP {$type} IF EXISTS `{$name}`;\n" .
            "DELIMITER ;;\n" .
            $createSql . ";;\n" .
            "DELIMITER ;\n\n"
        );
    }
}
