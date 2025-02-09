# 🗄️ PHP Database Archiver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/onlyphp/housekeeping.svg)](https://packagist.org/packages/onlyphp/housekeeping)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/onlyphp/housekeeping.svg)](https://packagist.org/packages/onlyphp/housekeeping)

A powerful and flexible database archiving solution for CodeIgniter 3 applications. This package helps you manage database growth by providing tools to archive and purge data while maintaining data integrity.

## ⚠️ Warning

**DO NOT USE THIS PACKAGE IN PRODUCTION**

This package is under active development and may contain critical bugs. It is primarily intended for personal use and testing. The current version has not undergone rigorous testing and may be unstable.

## 🚀 Features

- ✨ Backup and purge operations with transaction support
- 🔄 Parallel processing support for faster execution
- 📝 Detailed logging with customizable paths
- 🎯 Chunk-based processing to manage memory usage
- 🛡️ Support for unique constraints
- 🔌 Multiple database driver support (MySQL, Oracle)

## 💻 Requirements

- PHP >= 8.0
- MySQL 5.7+ or Oracle 11g+
- PHP PCNTL extension (for parallel processing)
- Composer

## 📦 Installation

### Via Composer

```bash
composer require onlyphp/housekeeping
```

## 🎓 Basic Concepts

### Operation Modes

- **Backup Only (BO)**: Only copies data to archive table
- **Purge Only (PO)**: Only deletes data from source table
- **Backup & Purge (BP)**: Copies data then deletes from source

### Processing Methods

- **Sequential**: Single-threaded processing
- **Parallel**: Multi-threaded processing (Unix/Linux only)

## 🎯 Usage Examples

### Example Connection

```php
// With PDO
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'password');
$archiver = new DatabaseArchiver($pdo);

// With MySQLi
$mysqli = new mysqli('localhost', 'user', 'password', 'test');
$archiver = new DatabaseArchiver($mysqli);

// With CodeIgniter 3
$ci =& get_instance();
$archiver = new DatabaseArchiver($ci->db);
```

### Example 1: Basic Backup Operation

Archive records older than 6 months from the 'orders' table:

```php
$archiver = new DatabaseArchiver($dbCon);
$result = $archiver
    ->driver('mysql')
    ->backupFrom('orders')
    ->primaryKey('order_id')
    ->whereClause('created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)')
    ->mode('BO')  // Backup Only
    ->run();

print_r($result);
```

### Example 2: Backup and Purge with Unique Constraints

Archive and delete completed orders while ensuring no duplicate order numbers:

```php
$archiver = new DatabaseArchiver($dbCon);
$result = $archiver
    ->driver('mysql')
    ->backupFrom('orders')
    ->primaryKey('order_id')
    ->uniqueColumns(['order_number']) // or uniqueColumns('order_number')
    ->whereClause('status = "completed" AND created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)')
    ->mode('BP')  // Backup and Purge
    ->chunk(500)  // Process 500 records at a time
    ->run();

print_r($result);
```

### Example 3: Parallel Processing with Custom Archive Table

Archive large datasets using parallel processing:

```php
$archiver = new DatabaseArchiver($dbCon);
$result = $archiver
    ->driver('mysql')
    ->backupFrom('transactions')
    ->backupTo('transactions_archive_2023')
    ->primaryKey('transaction_id')
    ->whereClause('YEAR(transaction_date) = 2023')
    ->mode('BO')
    ->parallel(4)  // Use 4 parallel processes
    ->chunk(1000)
    ->run();

// Monitor progress
$progress = $archiver->getProgress();
print_r($progress);
```

### Example 4: Oracle Database with Debug Mode

Archive data from an Oracle database with debug logging:

```php
$archiver = new DatabaseArchiver($dbCon);
$result = $archiver
    ->driver('oci')
    ->backupFrom('EMPLOYEES')
    ->primaryKey('EMPLOYEE_ID')
    ->whereClause('TERMINATION_DATE IS NOT NULL')
    ->mode('BP')
    ->onDebug()  // Enable debug mode
    ->logPath('/custom/path/archive.log')
    ->sqlHint('/*+ PARALLEL(4) */')  // Oracle-specific hint
    ->run();
```

### Example 5: Purge-Only Operation with Memory Management

Delete old log entries without backing them up:

```php
$archiver = new DatabaseArchiver($dbCon);
$result = $archiver
    ->driver('mysql')
    ->backupFrom('system_logs')
    ->primaryKey('log_id')
    ->whereClause('created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)')
    ->mode('PO')  // Purge Only
    ->chunk(200)  // Smaller chunks to manage memory
    ->run();
```

## ⚙️ Configuration Options

| Method                        | Description                                  |
| ----------------------------- | -------------------------------------------- |
| `driver(string)`              | Set database driver ('mysql' or 'oci')       |
| `backupFrom(string)`          | Set source table name                        |
| `backupTo(string)`            | Set custom archive table name                |
| `primaryKey(string)`          | Set primary key column                       |
| `whereClause(string)`         | Set WHERE clause for record selection        |
| `mode(string)`                | Set operation mode ('BO', 'PO', or 'BP')     |
| `chunk(int)`                  | Set chunk size (100-50000)                   |
| `parallel(int)`               | Set number of parallel threads (1-16)        |
| `uniqueColumns(array/string)` | Set columns that should be unique in archive |
| `sqlHint(string)`             | Set SQL optimization hint                    |
| `logPath(string)`             | Set custom log file path                     |
| `onDebug()`                   | Enable debug mode                            |
| `allowDuplicate()`            | Allows duplicate records in the archive.     |

## ⚠️ Important Notes

1. Always backup your database before running archiving operations
2. Test the archiving process on a subset of data first
3. Monitor system resources during parallel processing
4. Consider database load and peak hours when scheduling archiving tasks
5. Verify archive integrity after completion

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
