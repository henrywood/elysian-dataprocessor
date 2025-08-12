# Contracts Reference Guide

This document provides comprehensive documentation for all available contracts in the Elysian DataProcessor library. Contracts define the interface that your import and export classes must implement to work with the DataProcessor system.

## Table of Contents

1. [Contract Overview](#contract-overview)
2. [Core Contracts](#core-contracts)
3. [Feature Contracts](#feature-contracts)
4. [Contract Combinations](#contract-combinations)
5. [Implementation Examples](#implementation-examples)
6. [Best Practices](#best-practices)

## Contract Overview

The DataProcessor uses a contract-based architecture that allows you to implement only the features you need. Contracts are PHP interfaces that define specific functionality for data processing operations.

### Contract Categories

- **Core Contracts**: Required for basic import/export functionality
- **Feature Contracts**: Optional contracts that add specific capabilities
- **Validation Contracts**: Data validation and integrity checking
- **Performance Contracts**: Optimization features like chunking and queuing
- **Storage Contracts**: Cloud storage and configuration

## Core Contracts

### Importable Contract

**Purpose**: Required interface for all import classes
**Namespace**: `Elysian\DataProcessor\Contracts\Importable`

```php
<?php
namespace Elysian\DataProcessor\Contracts;

interface Importable {
    /**
     * Map raw row data to desired format
     * 
     * @param array $row Raw row data from file (indexed array)
     * @return array Mapped data for processing
     */
    public function map(array $row): array;

    /**
     * Process chunk of mapped data
     * 
     * @param array $data Array of mapped row data
     * @return void
     */
    public function process(array $data): void;
}
```

**Usage Example**:
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;

class UsersImport implements Importable {
    
    public function map(array $row): array {
        return [
            'name' => trim($row[0] ?? ''),
            'email' => strtolower(trim($row[1] ?? '')),
            'age' => (int)($row[2] ?? 0),
            'phone' => trim($row[3] ?? ''),
        ];
    }
    
    public function process(array $data): void {
        foreach ($data as $userData) {
            User::create($userData);
        }
    }
}
```

**Key Points**:
- `map()` transforms each row from the file into your desired format
- `process()` handles batches of mapped data (chunked processing)
- Row data is provided as indexed array (0, 1, 2, etc.)

### Exportable Contract

**Purpose**: Required interface for all export classes
**Namespace**: `Elysian\DataProcessor\Contracts\Exportable`

```php
<?php
namespace Elysian\DataProcessor\Contracts;

use Generator;

interface Exportable {
    /**
     * Get data generator for memory-efficient processing
     * 
     * @return Generator Yields individual records
     */
    public function query(): Generator;

    /**
     * Map data record to export format
     * 
     * @param mixed $data Single data record from query()
     * @return array Array of values for export row
     */
    public function map($data): array;
}
```

**Usage Example**:
```php
<?php
namespace App\Exports;

use Elysian\DataProcessor\Contracts\Exportable;
use Generator;

class UsersExport implements Exportable {
    
    public function query(): Generator {
        $chunkSize = 1000;
        $offset = 0;
        
        do {
            $users = User::offset($offset)->limit($chunkSize)->get();
            
            foreach ($users as $user) {
                yield $user;
            }
            
            $offset += $chunkSize;
        } while (count($users) === $chunkSize);
    }
    
    public function map($user): array {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->age,
            $user->phone ?: 'N/A',
            $user->created_at->format('Y-m-d H:i:s')
        ];
    }
}
```

**Key Points**:
- `query()` must return a Generator for memory efficiency
- `map()` transforms each record into an array for file output
- Use database chunking/pagination in query() for large datasets

## Feature Contracts

### WithValidation Contract

**Purpose**: Adds data validation capabilities to imports
**Namespace**: `Elysian\DataProcessor\Contracts\WithValidation`

```php
<?php
namespace Elysian\DataProcessor\Contracts;

interface WithValidation {
    /**
     * Get validation rules for import data
     * 
     * @return array Rules array where keys are column indices and values are rule strings
     */
    public function rules(): array;
}
```

**Usage Example**:
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithValidation;

class ValidatedUsersImport implements Importable, WithValidation {
    
    public function rules(): array {
        return [
            0 => 'required|max:255',              // Name column
            1 => 'required|email|max:255',        // Email column
            2 => 'required|numeric|min:13|max:120', // Age column
            3 => 'nullable|regex:/^[\+]?[1-9][\d\s\-\(\)]+$/' // Phone column
        ];
    }
    
    public function map(array $row): array {
        return [
            'name' => trim($row[0] ?? ''),
            'email' => strtolower(trim($row[1] ?? '')),
            'age' => (int)($row[2] ?? 0),
            'phone' => trim($row[3] ?? ''),
        ];
    }
    
    public function process(array $data): void {
        foreach ($data as $userData) {
            User::create($userData);
        }
    }
}
```

**Available Validation Rules**:
- `required` - Field must not be empty
- `email` - Must be valid email format
- `numeric` - Must be numeric value
- `max:n` - Maximum length/value
- `min:n` - Minimum length/value
- `regex:/pattern/` - Custom regex validation

**Key Points**:
- Rules are keyed by column index (0-based)
- Invalid rows are automatically skipped with warnings
- Use with `--validate true` flag (default)

### WithChunking Contract

**Purpose**: Configures chunking behavior for performance optimization
**Namespace**: `Elysian\DataProcessor\Contracts\WithChunking`

```php
<?php
namespace Elysian\DataProcessor\Contracts;

interface WithChunking {
    /**
     * Get chunk size for processing
     * 
     * @return int Number of rows to process per chunk
     */
    public function chunkSize(): int;

    /**
     * Get maximum file size in bytes before chunking
     * 
     * @return int Maximum file size in bytes
     */
    public function maxFileSize(): int;

    /**
     * Get chunk rows for file splitting
     * 
     * @return int Number of rows per file chunk
     */
    public function chunkRows(): int;
}
```

**Usage Example**:
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithChunking;

class OptimizedUsersImport implements Importable, WithChunking {
    
    public function chunkSize(): int {
        return 2000; // Process 2000 rows at a time
    }
    
    public function maxFileSize(): int {
        return 100 * 1024 * 1024; // 100MB max file size
    }
    
    public function chunkRows(): int {
        return 10000; // Split files at 10000 rows
    }
    
    // ... implement Importable methods
    public function map(array $row): array {
        return [
            'name' => trim($row[0] ?? ''),
            'email' => strtolower(trim($row[1] ?? '')),
        ];
    }
    
    public function process(array $data): void {
        // Bulk insert for better performance
        $values = [];
        $placeholders = [];
        
        foreach ($data as $userData) {
            $placeholders[] = '(?, ?)';
            $values[] = $userData['name'];
            $values[] = $userData['email'];
        }
        
        $sql = "INSERT INTO users (name, email) VALUES " . implode(', ', $placeholders);
        $pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
}
```

**Performance Guidelines**:
- **Small datasets**: 500-1000 rows per chunk
- **Medium datasets**: 1000-2000 rows per chunk  
- **Large datasets**: 2000-5000 rows per chunk
- **Very large datasets**: 5000-10000 rows per chunk

### ShouldQueue Contract

**Purpose**: Enables Swoole coroutine processing for high performance
**Namespace**: `Elysian\DataProcessor\Contracts\ShouldQueue`

```php
<?php
namespace Elysian\DataProcessor\Contracts;

interface ShouldQueue {
    /**
     * Get queue name for processing
     * 
     * @return string|null Queue name or null for default
     */
    public function onQueue(): ?string;

    /**
     * Get timeout in seconds for queue jobs
     * 
     * @return int Timeout in seconds
     */
    public function timeout(): int;

    /**
     * Get memory limit in MB for queue processing
     * 
     * @return int Memory limit in MB
     */
    public function memory(): int;
}
```

**Usage Example**:
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\ShouldQueue;
use Elysian\DataProcessor\Contracts\WithChunking;

class HighPerformanceUsersImport implements Importable, ShouldQueue, WithChunking {
    
    public function onQueue(): ?string {
        return 'high-priority'; // Custom queue name
    }
    
    public function timeout(): int {
        return 600; // 10 minutes timeout
    }
    
    public function memory(): int {
        return 1024; // 1GB memory limit
    }
    
    public function chunkSize(): int {
        return 5000; // Larger chunks for queue processing
    }
    
    public function maxFileSize(): int {
        return 500 * 1024 * 1024; // 500MB
    }
    
    public function chunkRows(): int {
        return 25000; // Large file chunks
    }
    
    public function map(array $row): array {
        return [
            'name' => trim($row[0] ?? ''),
            'email' => strtolower(trim($row[1] ?? '')),
            'age' => (int)($row[2] ?? 0),
        ];
    }
    
    public function process(array $data): void {
        // Optimized bulk processing for queued operations
        if (empty($data)) return;
        
        $sql = "INSERT INTO users (name, email, age) VALUES " . 
               str_repeat('(?,?,?),', count($data));
        $sql = rtrim($sql, ',');
        
        $values = [];
        foreach ($data as $userData) {
            $values[] = $userData['name'];
            $values[] = $userData['email'];
            $values[] = $userData['age'];
        }
        
        $pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
}
```

**Requirements**:
- PHP Swoole extension must be installed
- Use with `--use-queue true` flag
- Suitable for large datasets (100k+ rows)

### WithCloudStorage Contract

**Purpose**: Configures cloud storage settings
**Namespace**: `Elysian\DataProcessor\Contracts\WithCloudStorage`

```php
<?php
namespace Elysian\DataProcessor\Contracts;

interface WithCloudStorage {
    /**
     * Get storage configuration
     * 
     * @return array Storage configuration array
     */
    public function storageConfig(): array;
}
```

**Usage Example**:
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithCloudStorage;

class S3UsersImport implements Importable, WithCloudStorage {
    
    public function storageConfig(): array {
        return [
            'type' => 's3',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY')
            ],
            'bucket' => 'my-data-bucket'
        ];
    }
    
    public function map(array $row): array {
        return [
            'name' => trim($row[0] ?? ''),
            'email' => strtolower(trim($row[1] ?? '')),
        ];
    }
    
    public function process(array $data): void {
        foreach ($data as $userData) {
            User::create($userData);
        }
    }
}

// Google Cloud Storage Example
class GCSUsersImport implements Importable, WithCloudStorage {
    
    public function storageConfig(): array {
        return [
            'type' => 'gcs',
            'projectId' => 'my-project-id',
            'keyFilePath' => '/path/to/service-account.json',
            'bucket' => 'my-gcs-bucket'
        ];
    }
    
    // ... other methods
}

// Azure Blob Storage Example
class AzureUsersImport implements Importable, WithCloudStorage {
    
    public function storageConfig(): array {
        return [
            'type' => 'azure',
            'account_name' => 'mystorageaccount',
            'account_key' => getenv('AZURE_STORAGE_KEY'),
            'container' => 'my-container'
        ];
    }
    
    // ... other methods
}
```

**Storage Configuration Options**:

**Amazon S3**:
```php
[
    'type' => 's3',
    'region' => 'us-east-1',
    'credentials' => [
        'key' => 'ACCESS_KEY',
        'secret' => 'SECRET_KEY'
    ]
]
```

**Google Cloud Storage**:
```php
[
    'type' => 'gcs',
    'projectId' => 'project-id',
    'keyFilePath' => '/path/to/key.json'
]
```

**Azure Blob Storage**:
```php
[
    'type' => 'azure',
    'account_name' => 'account',
    'account_key' => 'key'
]
```

## Contract Combinations

### Basic Import Class
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;

class BasicUsersImport implements Importable {
    // Minimal implementation - just core functionality
}
```

### Validated Import Class
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithValidation;

class ValidatedUsersImport implements Importable, WithValidation {
    // Core + validation
}
```

### Performance-Optimized Import Class
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithChunking;
use Elysian\DataProcessor\Contracts\ShouldQueue;

class OptimizedUsersImport implements Importable, WithChunking, ShouldQueue {
    // Core + chunking + queue processing
}
```

### Full-Featured Import Class
```php
<?php
namespace App\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithValidation;
use Elysian\DataProcessor\Contracts\WithChunking;
use Elysian\DataProcessor\Contracts\ShouldQueue;
use Elysian\DataProcessor\Contracts\WithCloudStorage;

class EnterpriseUsersImport implements 
    Importable, 
    WithValidation, 
    WithChunking, 
    ShouldQueue, 
    WithCloudStorage {
    
    // All features enabled
    
    public function rules(): array {
        return [
            0 => 'required|max:255',
            1 => 'required|email',
            2 => 'numeric|min:0|max:150'
        ];
    }
    
    public function chunkSize(): int {
        return 5000;
    }
    
    public function maxFileSize(): int {
        return 1024 * 1024 * 1024; // 1GB
    }
    
    public function chunkRows(): int {
        return 50000;
    }
    
    public function onQueue(): ?string {
        return 'enterprise-imports';
    }
    
    public function timeout(): int {
        return 1800; // 30 minutes
    }
    
    public function memory(): int {
        return 2048; // 2GB
    }
    
    public function storageConfig(): array {
        return [
            'type' => 's3',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY')
            ]
        ];
    }
    
    public function map(array $row): array {
        return [
            'name' => ucwords(strtolower(trim($row[0] ?? ''))),
            'email' => strtolower(trim($row[1] ?? '')),
            'age' => max(0, min(150, (int)($row[2] ?? 0))),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    public function process(array $data): void {
        if (empty($data)) return;
        
        // Enterprise-grade bulk insert with error handling
        $pdo = new PDO('mysql:host=localhost;dbname=enterprise', 'user', 'pass', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $pdo->beginTransaction();
        
        try {
            $placeholders = str_repeat('(?,?,?,?,?),', count($data));
            $placeholders = rtrim($placeholders, ',');
            
            $sql = "INSERT INTO users (name, email, age, created_at, updated_at) 
                    VALUES {$placeholders}
                    ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    age = VALUES(age),
                    updated_at = VALUES(updated_at)";
            
            $stmt = $pdo->prepare($sql);
            
            $values = [];
            foreach ($data as $userData) {
                $values = array_merge($values, array_values($userData));
            }
            
            $stmt->execute($values);
            $pdo->commit();
            
            error_log("Successfully processed " . count($data) . " user records");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error processing user batch: " . $e->getMessage());
            throw $e;
        }
    }
}
```

## Implementation Examples

### Command Usage Examples

#### Basic Import
```bash
{{ELY}} dataproc:import users.xlsx --import-class "App\\Imports\\BasicUsersImport"
```

#### Validated Import
```bash
{{ELY}} dataproc:import users.xlsx \
  --import-class "App\\Imports\\ValidatedUsersImport" \
  --validate true
```

#### High-Performance Import
```bash
{{ELY}} dataproc:import large_dataset.xlsx \
  --import-class "App\\Imports\\OptimizedUsersImport" \
  --chunk-size 5000 \
  --use-queue true \
  --max-memory 2048
```

#### Cloud Import
```bash
{{ELY}} dataproc:import s3://bucket/users.xlsx \
  --import-class "App\\Imports\\S3UsersImport" \
  --storage s3
```

#### Enterprise Import
```bash
{{ELY}} dataproc:import s3://enterprise/massive_users.xlsx \
  --import-class "App\\Imports\\EnterpriseUsersImport" \
  --storage s3 \
  --chunk-size 10000 \
  --max-memory 4096 \
  --use-queue true \
  --validate true
```

## Best Practices

### Contract Selection Guidelines

1. **Always implement core contracts first**:
   - `Importable` for imports
   - `Exportable` for exports

2. **Add feature contracts based on needs**:
   - `WithValidation` for data quality
   - `WithChunking` for performance
   - `ShouldQueue` for large datasets
   - `WithCloudStorage` for cloud operations

3. **Performance considerations**:
   - Use `WithChunking` for files > 10MB
   - Use `ShouldQueue` for datasets > 100k rows
   - Combine both for enterprise-scale processing

4. **Security considerations**:
   - Always use `WithValidation` for user-provided data
   - Use `WithCloudStorage` for secure credential management

### Implementation Best Practices

#### Error Handling
```php
public function process(array $data): void {
    $successful = 0;
    $failed = 0;
    
    foreach ($data as $item) {
        try {
            User::create($item);
            $successful++;
        } catch (Exception $e) {
            $failed++;
            error_log("Import error: " . $e->getMessage());
        }
    }
    
    error_log("Batch: {$successful} success, {$failed} failed");
}
```

#### Memory Optimization
```php
public function query(): Generator {
    // Use generators, not arrays
    $offset = 0;
    $chunkSize = 1000;
    
    do {
        $records = Model::offset($offset)->limit($chunkSize)->get();
        foreach ($records as $record) {
            yield $record; // Memory efficient
        }
        $offset += $chunkSize;
    } while (count($records) === $chunkSize);
}
```

#### Validation Best Practices
```php
public function rules(): array {
    return [
        0 => 'required|max:255|regex:/^[a-zA-Z\s\-\'\.]+$/', // Strict name validation
        1 => 'required|email|max:255',                       // Email with length limit
        2 => 'required|numeric|min:13|max:120',              // Realistic age bounds
        3 => 'nullable|regex:/^[\+]?[1-9][\d\s\-\(\)]+$/'   // International phone format
    ];
}
```

#### Security Best Practices
```php
public function map(array $row): array {
    return [
        'name' => $this->sanitizeName(trim($row[0] ?? '')),
        'email' => $this->sanitizeEmail(trim($row[1] ?? '')),
        'age' => $this->sanitizeAge((int)($row[2] ?? 0)),
    ];
}

private function sanitizeName(string $name): string {
    // Remove dangerous characters
    $name = preg_replace('/[^a-zA-Z\s\-\'\.]/u', '', $name);
    return ucwords(strtolower($name));
}

private function sanitizeEmail(string $email): string {
    $email = strtolower($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ?: '';
}
```

### Contract Combination Recommendations

#### Small Projects (< 10k records)
```php
class SimpleImport implements Importable, WithValidation {
    // Basic functionality with validation
}
```

#### Medium Projects (10k - 100k records)
```php
class MediumImport implements Importable, WithValidation, WithChunking {
    // Add chunking for better performance
}
```

#### Large Projects (100k+ records)
```php
class LargeImport implements 
    Importable, 
    WithValidation, 
    WithChunking, 
    ShouldQueue {
    // Full performance optimization
}
```

#### Enterprise Projects (1M+ records)
```php
class EnterpriseImport implements 
    Importable, 
    WithValidation, 
    WithChunking, 
    ShouldQueue, 
    WithCloudStorage {
    // All features for maximum scalability
}
```

## Conclusion

The DataProcessor contract system provides a flexible, scalable architecture for data processing operations. By implementing only the contracts you need, you can create efficient, maintainable data processing classes that scale from simple imports to enterprise-level operations.

### Key Takeaways:

1. **Start Simple**: Begin with core contracts and add features as needed
2. **Performance Matters**: Use chunking and queuing for large datasets
3. **Validate Everything**: Always validate user-provided data
4. **Think Scale**: Design for your expected data volume
5. **Security First**: Sanitize and validate all input data
6. **Monitor Performance**: Track processing times and error rates

The contract system ensures your code remains maintainable while providing the flexibility to handle any data processing scenario.
