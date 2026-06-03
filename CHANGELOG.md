# Changelog

All notable changes to DatabaseBackup will be documented in this file.

## v1.1.0 (2026-06-03)

### New Features
- **Streaming architecture**: SQL written directly to disk via file handles. No memory buildup; databases of any size can be backed up.
- **Unbuffered MySQL queries**: Rows fetched one at a time with `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=false`. No result set buffering.
- **`batchSize` configuration option**: Number of rows per INSERT statement.
- **Partial file cleanup**: Failed backups no longer leave incomplete files on disk.

### Bug Fixes
- **`addslashes()` → `PDO::quote()`**: Replaced unsafe string escaping with charset-aware PDO quoting. Fixes emoji, backslash, and multi-byte character corruption.
- **SQL injection** in schema existence check queries (`DatabaseConnection.php`)
- **SQL injection** in routine metadata query (`SqlGenerator.php`)
- **View restore syntax**: Removed broken `/*!50013 ... */;` wrapper that prevented views from being restored correctly.

### Performance
- Memory usage no longer scales with database size.
- 1,050,000 records (7 tables, 1.33 GB uncompressed) backed up in ~17 seconds.
- 3,920,015 records (Employees DB, 6 tables + 2 views) backed up in ~46 seconds.
- Gzip compression adds ~40 seconds for 1.33 GB of SQL.
- Comparable speed to `mysqldump` for uncompressed output.

### Notes
- Fully backward compatible. No breaking changes.
- Tested with MySQL 8.0 and MariaDB 10.11.

---

## v1.0.0 (Initial Release)

- Basic database backup functionality
- Table structure and data export
- View, trigger, and stored procedure support
- Gzip compression
- FTP/FTPS upload
- Backup listing, download, and deletion
- Automatic cleanup of old backups
- Progress tracking callbacks
- Logging system
