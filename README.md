# Database Backup

Advanced backup system for MySQL and MariaDB databases. This package allows you to easily backup, list, download, delete, and upload databases to FTP without using `mysqldump`.

## Why Should You Use This Package?

### 🚀 Safe and Reliable
- No need for dangerous system commands like `shell_exec`/`exec` required for `mysqldump`
- Completely PHP-based, safe and controlled backup process
- Not affected by hosting provider restrictions

### 💡 Use Cases
- Shared hosting environments (where mysqldump access is not available)
- VPS and cloud servers
- Automated backup systems
- Web-based backup interfaces
- Multiple database management

### ⭐ Key Features
- Backup without using `mysqldump`
- Full database backup without shell commands
- Selective backup (exclude specific tables or backup only structure/data)
- Automatic compression and FTP upload
- Detailed debugging and logging
- Progress tracking and status notifications

### 🔒 Security Advantages
- No risk of executing system commands
- Compatible with hosting provider security restrictions
- Controlled and isolated backup process
- Secure FTP connections (SSL/TLS support)

## Features

- Complete database backup (including tables, views, triggers, and stored procedures)
- Exclusion of selected tables or backing up only structure/data
- Compression of backups (gzip)
- Automatic cleanup of old backups (based on number and age)
- Automatic or manual upload to FTP/FTPS
- Progress tracking
- Comprehensive logging

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