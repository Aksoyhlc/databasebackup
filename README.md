# Database Backup

Backup system for MySQL and MariaDB databases. Create, list, download, delete, and upload backups to FTP without `mysqldump`.

## Why Use This Package

### Safe and Reliable
- No shell_exec or exec required (mysqldump needs these)
- PHP-based, no system command execution
- Works under hosting provider restrictions

### Use Cases
- Shared hosting without mysqldump access
- VPS and cloud servers
- Automated backup systems
- Web-based backup interfaces

### Key Features
- No mysqldump dependency
- Full database backup without shell commands
- Selective backup (exclude tables, structure-only, data-only)
- Optional compression and FTP upload
- Debug logging and progress tracking

### Security
- No system command execution risk
- Compatible with hosting security rules
- SSL/TLS for FTP connections

## Features

- Full database backup (tables, views, triggers, stored procedures)
- Exclude specific tables or backup only structure or data
- Gzip compression
- Auto-cleanup by count or age
- FTP/FTPS upload (automatic or manual)
- Progress callback
- Logging

## Installation

```bash
composer require aksoyhlc/databasebackup
```

## Basic Usage

```php
<?php

use Aksoyhlc\Databasebackup\DatabaseBackupService;

// Basic configuration
$dbConfig = [
    'host' => 'localhost',      // Database server
    'dbname' => 'database',     // Database name
    'user' => 'root',           // Username
    'pass' => 'password',       // Password
    'charset' => 'utf8mb4',     // Character set (optional)
    'port' => 3306              // Port (optional)
];

// Backup directory
$backupPath = __DIR__ . '/backups';

// Initialize backup service
$backupService = new DatabaseBackupService($dbConfig, $backupPath);

// Create backup
$result = $backupService->createBackup();

if ($result['success']) {
    echo "Backup successful: " . $result['fileName'];
} else {
    echo "Error: " . $result['message'];
}
```

## Advanced Usage

```php
<?php

use Aksoyhlc\Databasebackup\DatabaseBackupService;

$dbConfig = [
    'host' => 'localhost',      // Database server
    'dbname' => 'database',     // Database name
    'user' => 'root',           // Username
    'pass' => 'password',       // Password
    'charset' => 'utf8mb4',     // Character set
    'port' => 3306              // Port number
];

$backupPath = __DIR__ . '/backups';

// Advanced configuration options
$options = [
    // ---- Cache Settings ----
    'cacheTime' => 3600,        // Cache duration (seconds)
    
    // ---- Backup File Cleanup Settings ----
    'maxBackupCount' => 10,     // Maximum number of backups (excess will be deleted automatically)
    'maxBackupAgeDays' => 30,   // Maximum backup age (days, older ones will be deleted automatically)
    
    // ---- Content Filtering Settings ----
    'excludedTables' => [       // Tables to exclude from backup
        'log_table', 
        'temp_data'
    ],
    'tableModes' => [           // Table backup modes
        // 'full': Table structure and data (default)
        // 'structure_only': Only table structure
        // 'data_only': Only table data
        'large_table' => 'structure_only',
        'settings' => 'full'
    ],
    
    // ---- Backup Format Settings ----
    'compressOutput' => true,   // Compress the backup file? (gzip)
    'removeDefiners' => true,   // Remove SQL DEFINER statements?
    
    // ---- Progress Tracking ----
    'progressCallback' => function($status, $current, $total) {
        // Callback function for progress status
        echo "{$status}: {$current}/{$total}\n";
    },
    
    // ---- FTP Settings ----
    'ftpConfig' => [
        'enabled' => true,      // Is FTP backup active?
        'host' => 'ftp.example.com',  // FTP server address
        'username' => 'ftpuser',      // FTP username
        'password' => 'ftppass',      // FTP password
        'port' => 21,                 // FTP port number
        'path' => '/backups',         // Remote directory path
        'ssl' => false,               // Use SSL?
        'passive' => true             // Use passive mode?
    ]
];

$backupService = new DatabaseBackupService($dbConfig, $backupPath, $options);

// Create backup
$result = $backupService->createBackup();

// List backups
$backups = $backupService->listBackups();
foreach ($backups as $backup) {
    echo "File: {$backup['file_name']}, Size: {$backup['size']}, Date: {$backup['date']}\n";
}

// Download a backup
$downloadInfo = $backupService->prepareDownload('backup_database_2023-01-01_12-00-00.sql.gz');
if ($downloadInfo['success']) {
    // Information for serving the file to the user
    $filePath = $downloadInfo['filePath'];
    $fileName = $downloadInfo['fileName'];
    $mimeType = $downloadInfo['mimeType'];
}

// Delete a backup
$deleteResult = $backupService->deleteBackup('backup_database_2023-01-01_12-00-00.sql.gz');

// Upload a backup to FTP
$uploadResult = $backupService->uploadBackupToFtp('backup_database_2023-01-01_12-00-00.sql.gz');

// Clean old backups
$backupService->cleanOldBackups();
```

## Performance

DatabaseBackup uses **streaming architecture** and **unbuffered queries** to handle databases of any size with minimal resource usage. No `mysqldump` dependency required.

### Benchmarks (1,050,000 records, 7 tables)

| Mode | File Size | Duration |
|------|-----------|----------|
| Uncompressed | 1.33 GB | 17.0 s |
| Gzip compressed | 314 MB | 57.5 s |

### Benchmark (Employees DB: 3,920,015 records, 6 tables + 2 views)

| Mode | File Size | Duration |
|------|-----------|----------|
| Gzip compressed | 33.7 MB | 46.6 s |

### vs `mysqldump` (1,050,000 records)

| Tool | File Size | Duration |
|------|-----------|----------|
| **DatabaseBackup** (stream) | 1.33 GB | 17.0 s |
| `mysqldump` | 1.30 GB | 11.8 s |
| **DatabaseBackup** (gzip) | 314 MB | 57.5 s |
| `mysqldump` + `gzip` (pipe) | 314 MB | 31.1 s |

### Key Performance Features

- **Streaming writes**: SQL goes directly to disk, not accumulated in memory.
- **Unbuffered queries**: One row at a time, no result set buffering.
- **Configurable batch size**: Tune `batchSize` for INSERT performance.

## Special Characters

UTF-8 characters, emoji, JSON, BLOB, backslash, and multi-line text are preserved during backup and restore.

Tested with: `😀🔥🎉💯✅`, `👨‍👩‍👧‍👦`, `JSON`, `BLOB`, multi-line text, `O'Brien`, `C:\Users\path`, null values.

## Log Messages

```php
// Record messages with different log levels
$backupService->logMessage("Custom information message", "INFO");
$backupService->error("An error occurred");
$backupService->info("Information message");
$backupService->debug("Detailed debugging information");
```

## Connection Tests

```php
// Test database connection
if ($backupService->testDatabaseConnection()) {
    echo "Database connection successful";
}

// Test FTP connection
if ($backupService->testFtpConnection()) {
    echo "FTP connection successful";
}

// Get database version
echo "Database version: " . $backupService->getDatabaseVersion();
```

## Configuration Parameters

### Database Configuration (`$dbConfig`)
| Parameter | Description | Default |
|-----------|-------------|---------|
| `host` | Database server address | `'localhost'` |
| `dbname` | Database name | - |
| `user` | Database username | - |
| `pass` | Database password | - |
| `charset` | Database character set | `'utf8mb4'` |
| `port` | Database server port number | `3306` |

### General Configuration Options (`$options`)

#### Cache Settings
| Parameter | Description | Default |
|-----------|-------------|---------|
| `cacheTime` | Cache duration (seconds) | `3600` |

#### Backup File Cleanup Settings
| Parameter | Description | Default |
|-----------|-------------|---------|
| `maxBackupCount` | Maximum number of backups to keep | `10` |
| `maxBackupAgeDays` | Maximum backup age to keep (days) | `365` |

#### Content Filtering Settings
| Parameter | Description | Default |
|-----------|-------------|---------|
| `excludedTables` | Array of table names to exclude from backup | `[]` |
| `tableModes` | Table backup modes (`'full'`, `'structure_only'`, `'data_only'`) | `[]` |

#### Backup Format Settings
| Parameter | Description | Default |
|-----------|-------------|---------|
| `compressOutput` | Compress the backup file? (gzip) | `false` |
| `removeDefiners` | Remove SQL DEFINER statements? | `true` |
| `batchSize` | Number of rows per INSERT statement | `100` |

#### Progress Tracking
| Parameter | Description | Default |
|-----------|-------------|---------|
| `progressCallback` | Progress status notification function `function($status, $current, $total)` | `null` |

#### FTP Configuration (`ftpConfig`)
| Parameter | Description | Default |
|-----------|-------------|---------|
| `enabled` | Is FTP backup active? | `false` |
| `host` | FTP server address | `''` |
| `username` | FTP username | `''` |
| `password` | FTP password | `''` |
| `port` | FTP port number | `21` |
| `path` | Remote directory path | `'/'` |
| `ssl` | Use SSL? | `false` |
| `passive` | Use passive mode? | `true` |

## Requirements

- PHP 7.4 or higher
- PDO PHP Extension
- MySQL or MariaDB database
- For FTP operations: FTP PHP Extension
- For compression: Zlib PHP Extension

## License
This project is open-sourced software licensed under the [GPL license](https://www.gnu.org/copyleft/gpl.html)