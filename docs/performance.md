# Performance Guide

This comprehensive guide covers optimization techniques, best practices, and troubleshooting for achieving maximum performance with the Elysian DataProcessor.

## Table of Contents

1. [Performance Overview](#performance-overview)
2. [Memory Management](#memory-management)
3. [Chunking Strategy](#chunking-strategy)
4. [Swoole Coroutines](#swoole-coroutines)
5. [Database Optimization](#database-optimization)
6. [Cloud Storage Performance](#cloud-storage-performance)
7. [Monitoring and Profiling](#monitoring-and-profiling)
8. [Performance Benchmarks](#performance-benchmarks)
9. [Troubleshooting](#troubleshooting)
10. [Best Practices Summary](#best-practices-summary)

## Performance Overview

The Elysian DataProcessor is designed for high-performance data processing with the following optimization strategies:

- **Memory-Efficient Processing**: Uses PHP generators and streaming
- **Chunked Processing**: Processes data in configurable chunks
- **Swoole Coroutines**: Parallel processing for enhanced performance
- **Optimized Database Operations**: Bulk inserts and transactions
- **Cloud Storage Integration**: Direct cloud processing without local storage

### Performance Factors

Key factors affecting performance:
1. **Hardware**: CPU, RAM, and storage speed
2. **Data Size**: Number of rows and columns
3. **Complexity**: Validation rules and data transformations
4. **Network**: Latency and bandwidth for cloud storage
5. **Database**: Engine, indexes, and configuration

## Memory Management

### Memory Limits

Set appropriate memory limits based on your data size and available resources:

```php
$processor = new DataProcessor();
$result = $processor->import($importer, 'large-file.xlsx', [
    'max_memory' => 1024  // 1GB for large files
]);
```

**Memory Recommendations:**

| File Size | Recommended Memory | Use Case |
|-----------|-------------------|----------|
| < 10MB | 256MB | Small imports |
| 10MB - 100MB | 512MB | Medium datasets |
| 100MB - 1GB | 1024MB | Large files |
| > 1GB | 2048MB+ | Enterprise data |

### Memory-Efficient Code Patterns

#### Use Generators in Exports
```php
// Good: Memory-efficient generator
public function query(): Generator {
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

// Bad: Loads everything into memory
public function query(): Generator {
    $allRecords = Model::all(); // Memory intensive
    foreach ($allRecords as $record) {
        yield $record;
    }
}
```

#### Avoid Large Array Accumulation
```php
// Good: Process in chunks
public function process(array $data): void {
    foreach ($data as $item) {
        User::create($item); // Process immediately
    }
}

// Bad: Accumulates in memory
private array $allData = [];
public function process(array $data): void {
    $this->allData = array_merge($this->allData, $data); // Memory grows
}
```

#### Monitor Memory Usage
```php
public function process(array $data): void {
    $memoryBefore = memory_get_usage(true);
    
    // Process data
    foreach ($data as $item) {
        User::create($item);
    }
    
    $memoryAfter = memory_get_usage(true);
    $memoryUsed = $memoryAfter - $memoryBefore;
    
    if ($memoryUsed > 50 * 1024 * 1024) { // 50MB threshold
        error_log("High memory usage detected: " . ($memoryUsed / 1024 / 1024) . "MB");
    }
}
```

## Chunking Strategy

Chunking is critical for processing large datasets efficiently. The optimal chunk size depends on your data characteristics and system resources.

### Chunk Size Guidelines

**Import Chunking:**
- Small datasets (< 10k rows): 500-1000 rows
- Medium datasets (10k-100k rows): 1000-2000 rows
- Large datasets (100k-1M rows): 2000-5000 rows
- Very large datasets (1M+ rows): 5000-10000 rows

**Export Chunking:**
- Small datasets: 1000-2000 rows
- Medium datasets: 2000-3000 rows
- Large datasets: 3000-5000 rows
- Very large datasets: 5000-10000 rows

### Dynamic Chunking

Implement adaptive chunking based on system resources:

```php
use Elysian\DataProcessor\Contracts\WithChunking;

class AdaptiveImport implements Importable, WithChunking {
    public function chunkSize(): int {
        $availableMemory = $this->getAvailableMemory();
        $systemLoad = sys_getloadavg()[0];
        
        // Adjust based on memory and CPU load
        if ($availableMemory > 2048 && $systemLoad < 2.0) {
            return 10000; // High performance mode
        } elseif ($availableMemory > 1024 && $systemLoad < 4.0) {
            return 5000;  // Balanced mode
        } elseif ($availableMemory > 512) {
            return 2000;  // Conservative mode
        } else {
            return 500;   // Low resource mode
        }
    }
    
    public function maxFileSize(): int {
        return $this->getAvailableMemory() * 1024 * 1024; // MB to bytes
    }
    
    public function chunkRows(): int {
        return $this->chunkSize() * 5; // 5x chunk size for file splitting
    }
    
    private function getAvailableMemory(): int {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return 4096; // Unlimited - assume high-end server
        
        $value = (int)$limit;
        $unit = strtolower(substr($limit, -1));
        
        return match($unit) {
            'g' => $value * 1024,
            'm' => $value,
            'k' => $value / 1024,
            default => $value / 1024 / 1024
        };
    }
}
```

### Chunk Size Testing

Test different chunk sizes to find the optimal value:

```php
class ChunkSizeTester {
    public function testChunkSizes(array $chunkSizes, string $testFile): array {
        $results = [];
        
        foreach ($chunkSizes as $size) {
            $startTime = microtime(true);
            $startMemory = memory_get_peak_usage(true);
            
            $processor = new DataProcessor();
            $result = $processor->import(new TestImport($size), $testFile, [
                'chunk_size' => $size
            ]);
            
            $endTime = microtime(true);
            $endMemory = memory_get_peak_usage(true);
            
            $results[$size] = [
                'time' => $endTime - $startTime,
                'memory' => $endMemory - $startMemory,
                'rows_per_second' => $result['rows'] / ($endTime - $startTime)
            ];
        }
        
        return $results;
    }
}

// Usage
$tester = new ChunkSizeTester();
$results = $tester->testChunkSizes([500, 1000, 2000, 5000], 'test-data.xlsx');

foreach ($results as $chunkSize => $metrics) {
    echo "Chunk Size: {$chunkSize}\n";
    echo "  Time: {$metrics['time']}s\n";
    echo "  Memory: " . ($metrics['memory'] / 1024 / 1024) . "MB\n";
    echo "  Speed: {$metrics['rows_per_second']} rows/sec\n\n";
}
```

## Swoole Coroutines

Swoole coroutines enable parallel processing for significant performance improvements.

### Prerequisites

Install Swoole extension:
```bash
# Via PECL
pecl install swoole

# Via package manager (Ubuntu/Debian)
apt-get install php-swoole

# Via package manager (CentOS/RHEL)
yum install php-swoole
```

### Enabling Coroutines

Implement the `ShouldQueue` contract:

```php
use Elysian\DataProcessor\Contracts\ShouldQueue;

class HighPerformanceImport implements Importable, ShouldQueue, WithChunking {
    public function onQueue(): ?string {
        return 'high-priority';
    }
    
    public function timeout(): int {
        return 1800; // 30 minutes for large datasets
    }
    
    public function memory(): int {
        return 2048; // 2GB per worker
    }
    
    public function chunkSize(): int {
        return 10000; // Larger chunks for parallel processing
    }
    
    // ... other methods
}
```

### Usage with Coroutines

```php
$processor = new DataProcessor();
$result = $processor->import(new HighPerformanceImport(), 'massive-dataset.xlsx', [
    'use_queue' => true,     // Enable Swoole coroutines
    'chunk_size' => 10000,   // Large chunks
    'max_memory' => 4096     // High memory limit
]);
```

### Performance Benefits

Swoole coroutines provide:
- **Parallel Processing**: Multiple workers process chunks simultaneously
- **Non-blocking I/O**: Better resource utilization
- **Scalability**: Handles larger datasets more efficiently
- **CPU Utilization**: Better use of multi-core systems

### Coroutine Configuration

Optimize Swoole settings for your workload:

```php
// In your bootstrap file or configuration
if (extension_loaded('swoole')) {
    // Set worker count based on CPU cores
    $workerCount = max(1, (int)(shell_exec('nproc') * 0.8)); // 80% of cores
    
    // Configure Swoole settings
    ini_set('swoole.use_shortname', 'Off');
    
    // These would be set if you were configuring a Swoole server
    // but for DataProcessor they're handled internally
}
```

## Database Optimization

Database operations are often the bottleneck in data processing. Optimize for bulk operations.

### Bulk Insert Strategies

#### Basic Bulk Insert
```php
public function process(array $data): void {
    if (empty($data)) return;
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Build bulk insert query
    $placeholders = str_repeat('(?,?,?),', count($data));
    $placeholders = rtrim($placeholders, ',');
    
    $sql = "INSERT INTO users (name, email, age) VALUES {$placeholders}";
    $stmt = $pdo->prepare($sql);
    
    // Flatten data array
    $values = [];
    foreach ($data as $row) {
        $values[] = $row['name'];
        $values[] = $row['email'];
        $values[] = $row['age'];
    }
    
    $stmt->execute($values);
}
```

#### Advanced Bulk Insert with Upsert
```php
public function process(array $data): void {
    if (empty($data)) return;
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true
    ]);
    
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
    foreach ($data as $row) {
        $values = array_merge($values, [
            $row['name'],
            $row['email'],
            $row['age'],
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
    }
    
    $stmt->execute($values);
}
```

#### Load Data Infile (MySQL)
For maximum performance with MySQL:

```php
public function process(array $data): void {
    if (empty($data)) return;
    
    // Create temporary CSV file
    $tempFile = tempnam(sys_get_temp_dir(), 'bulk_insert_');
    $handle = fopen($tempFile, 'w');
    
    foreach ($data as $row) {
        fputcsv($handle, [
            $row['name'],
            $row['email'],
            $row['age'],
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
    }
    fclose($handle);
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true
    ]);
    
    $sql = "LOAD DATA LOCAL INFILE '{$tempFile}' 
            INTO TABLE users 
            FIELDS TERMINATED BY ',' 
            ENCLOSED BY '\"' 
            LINES TERMINATED BY '\\n'
            (name, email, age, created_at, updated_at)";
    
    $pdo->exec($sql);
    unlink($tempFile);
}
```

### Transaction Management

Use transactions for data integrity and performance:

```php
public function process(array $data): void {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $pdo->beginTransaction();
    
    try {
        // Disable autocommit for better performance
        $pdo->exec('SET autocommit = 0');
        
        // Process data in batches
        $batchSize = 1000;
        $batches = array_chunk($data, $batchSize);
        
        foreach ($batches as $batch) {
            $this->insertBatch($pdo, $batch);
        }
        
        $pdo->commit();
        $pdo->exec('SET autocommit = 1');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $pdo->exec('SET autocommit = 1');
        throw $e;
    }
}

private function insertBatch(PDO $pdo, array $batch): void {
    $placeholders = str_repeat('(?,?,?),', count($batch));
    $placeholders = rtrim($placeholders, ',');
    
    $sql = "INSERT INTO users (name, email, age) VALUES {$placeholders}";
    $stmt = $pdo->prepare($sql);
    
    $values = [];
    foreach ($batch as $row) {
        $values = array_merge($values, array_values($row));
    }
    
    $stmt->execute($values);
}
```

### Index Management

Optimize indexes for bulk operations:

```sql
-- Disable indexes during large imports
ALTER TABLE users DISABLE KEYS;

-- Perform bulk insert
-- (Your import process runs here)

-- Re-enable indexes
ALTER TABLE users ENABLE KEYS;
```

```php
public function process(array $data): void {
    $pdo = new PDO($dsn, $user, $pass);
    
    // Disable indexes for performance
    $pdo->exec('ALTER TABLE users DISABLE KEYS');
    
    try {
        // Perform bulk insert
        $this->bulkInsert($pdo, $data);
        
        // Re-enable indexes
        $pdo->exec('ALTER TABLE users ENABLE KEYS');
        
    } catch (Exception $e) {
        // Re-enable indexes even on failure
        $pdo->exec('ALTER TABLE users ENABLE KEYS');
        throw $e;
    }
}
```

### Database-Specific Optimizations

#### MySQL Optimizations
```sql
-- Increase buffer sizes
SET innodb_buffer_pool_size = 2G;
SET innodb_log_file_size = 512M;
SET innodb_log_buffer_size = 64M;

-- Disable binary logging during import
SET sql_log_bin = 0;

-- Increase batch size
SET innodb_autoinc_lock_mode = 2;
```

#### PostgreSQL Optimizations
```sql
-- Increase work memory
SET work_mem = '256MB';

-- Disable WAL archiving during import
SET archive_mode = off;

-- Use COPY for bulk inserts
COPY users(name, email, age) FROM STDIN WITH CSV;
```

## Cloud Storage Performance

### Regional Optimization

Use storage regions close to your application:

```php
// Good: Same region as application
$config = [
    'region' => 'us-west-2' // App also in us-west-2
];

// Bad: Cross-region transfers are slower and costly
$config = [
    'region' => 'eu-west-1' // App in us-west-2
];
```

### Parallel Cloud Operations

Enable parallel processing for cloud operations:

```php
$result = $processor->import($importer, 's3://bucket/large-file.xlsx', [
    'storage_type' => 's3',
    'use_queue' => true,     // Enables parallel processing
    'chunk_size' => 10000,   // Larger chunks for cloud
    'max_memory' => 2048
]);
```

### Transfer Optimization

Optimize transfer settings:

```php
// For large files, use larger chunks
$result = $processor->export($exporter, 's3://bucket/export.xlsx', [
    'storage_type' => 's3',
    'chunk_size' => 20000,   // Large chunks for exports
    'format' => 'csv'        // CSV is typically faster than XLSX
]);
```

### Multi-Part Upload Configuration

For S3, large files automatically use multi-part uploads:

```php
$config = [
    'region' => 'us-east-1',
    'credentials' => [...],
    'multipart_threshold' => 64 * 1024 * 1024, // 64MB threshold
    'multipart_chunksize' => 16 * 1024 * 1024   // 16MB chunks
];
```

## Monitoring and Profiling

### Built-in Performance Metrics

```php
$result = $processor->import($importer, 'file.xlsx');

echo "Performance Metrics:\n";
echo "- Rows processed: {$result['rows']}\n";
echo "- Time taken: {$result['time']} seconds\n";
echo "- Throughput: " . round($result['rows'] / $result['time']) . " rows/second\n";
echo "- Chunks processed: {$result['chunks']}\n";
```

### Custom Performance Monitoring

```php
class PerformanceMonitor {
    private float $startTime;
    private int $startMemory;
    private array $metrics = [];
    
    public function start(): void {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_peak_usage(true);
    }
    
    public function checkpoint(string $label): void {
        $this->metrics[$label] = [
            'time' => microtime(true) - $this->startTime,
            'memory' => memory_get_peak_usage(true) - $this->startMemory,
            'memory_current' => memory_get_usage(true)
        ];
    }
    
    public function getReport(): array {
        return $this->metrics;
    }
}

// Usage in import class
class MonitoredImport implements Importable {
    private PerformanceMonitor $monitor;
    
    public function __construct() {
        $this->monitor = new PerformanceMonitor();
        $this->monitor->start();
    }
    
    public function process(array $data): void {
        $this->monitor->checkpoint('process_start');
        
        // Your processing logic
        foreach ($data as $item) {
            User::create($item);
        }
        
        $this->monitor->checkpoint('process_end');
        
        // Log performance data
        $report = $this->monitor->getReport();
        error_log(sprintf(
            "Processed %d rows in %.2fs using %.2fMB",
            count($data),
            $report['process_end']['time'] - $report['process_start']['time'],
            ($report['process_end']['memory'] - $report['process_start']['memory']) / 1024 / 1024
        ));
    }
}
```

### System Resource Monitoring

```php
class SystemMonitor {
    public function getCpuUsage(): float {
        $load = sys_getloadavg();
        return $load[0]; // 1-minute average
    }
    
    public function getMemoryUsage(): array {
        return [
            'used' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => $this->getMemoryLimit()
        ];
    }
    
    public function getDiskUsage(string $path = '.'): array {
        return [
            'free' => disk_free_space($path),
            'total' => disk_total_space($path)
        ];
    }
    
    private function getMemoryLimit(): int {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return PHP_INT_MAX;
        
        $value = (int)$limit;
        $unit = strtolower(substr($limit, -1));
        
        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }
    
    public function logSystemStatus(): void {
        $cpu = $this->getCpuUsage();
        $memory = $this->getMemoryUsage();
        $disk = $this->getDiskUsage();
        
        error_log(sprintf(
            "System Status - CPU: %.2f, Memory: %.1f%%, Disk: %.1f%% free",
            $cpu,
            ($memory['used'] / $memory['limit']) * 100,
            ($disk['free'] / $disk['total']) * 100
        ));
    }
}
```

## Performance Benchmarks

### Typical Performance Ranges

Based on standard hardware configurations:

| Dataset Size | Hardware | Performance Range | Notes |
|-------------|----------|------------------|-------|
| 1K rows | Basic (2GB RAM) | 2,000-5,000 rows/sec | CPU bound |
| 10K rows | Standard (4GB RAM) | 1,000-3,000 rows/sec | Mixed workload |
| 100K rows | High-end (8GB+ RAM) | 500-2,000 rows/sec | I/O bound |
| 1M+ rows | Enterprise (16GB+ RAM) | 200-1,000 rows/sec | Database bound |

### Benchmark Testing

```php
class PerformanceBenchmark {
    public function runImportBenchmark(array $testSizes = [1000, 10000, 100000]): array {
        $results = [];
        
        foreach ($testSizes as $size) {
            $testFile = $this->generateTestFile($size);
            
            $startTime = microtime(true);
            $startMemory = memory_get_peak_usage(true);
            
            $processor = new DataProcessor();
            $result = $processor->import(new BenchmarkImport(), $testFile);
            
            $endTime = microtime(true);
            $endMemory = memory_get_peak_usage(true);
            
            $results[$size] = [
                'rows' => $result['rows'],
                'time' => $endTime - $startTime,
                'memory' => $endMemory - $startMemory,
                'rows_per_second' => $result['rows'] / ($endTime - $startTime),
                'memory_per_row' => ($endMemory - $startMemory) / $result['rows']
            ];
            
            unlink($testFile);
        }
        
        return $results;
    }
    
    private function generateTestFile(int $rowCount): string {
        $testFile = tempnam(sys_get_temp_dir(), 'benchmark_') . '.csv';
        $handle = fopen($testFile, 'w');
        
        // Write header
        fputcsv($handle, ['Name', 'Email', 'Age', 'Phone']);
        
        // Write test data
        for ($i = 1; $i <= $rowCount; $i++) {
            fputcsv($handle, [
                "User {$i}",
                "user{$i}@example.com",
                rand(18, 80),
                '555-' . str_pad($i, 4, '0', STR_PAD_LEFT)
            ]);
        }
        
        fclose($handle);
        return $testFile;
    }
}

// Run benchmark
$benchmark = new PerformanceBenchmark();
$results = $benchmark->runImportBenchmark();

foreach ($results as $size => $metrics) {
    echo "Dataset: {$size} rows\n";
    echo "  Time: {$metrics['time']}s\n";
    echo "  Speed: {$metrics['rows_per_second']} rows/sec\n";
    echo "  Memory: " . ($metrics['memory'] / 1024 / 1024) . "MB\n";
    echo "  Memory/row: " . ($metrics['memory_per_row'] / 1024) . "KB\n\n";
}
```

## Troubleshooting

### Common Performance Issues

#### Memory Exhaustion
```php
// Symptom: "Fatal error: Allowed memory size exhausted"
// Solutions:
// 1. Reduce chunk size
$options['chunk_size'] = 500; // Smaller chunks

// 2. Increase memory limit
$options['max_memory'] = 2048; // More memory

// 3. Use generators properly
public function query(): Generator {
    // Don't load all data at once
    User::chunk(1000, function($users) {
        foreach ($users as $user) {
            yield $user;
        }
    });
}
```

#### Slow Database Operations
```php
// Symptom: Very slow processing speed
// Solutions:
// 1. Use bulk inserts
public function process(array $data): void {
    // Don't do this (slow)
    foreach ($data as $item) {
        User::create($item); // Individual queries
    }
    
    // Do this instead (fast)
    $this->bulkInsert($data); // Single query
}

// 2. Check for missing indexes
// CREATE INDEX idx_email ON users(email);
// CREATE INDEX idx_created_at ON users(created_at);

// 3. Use transactions
$pdo->beginTransaction();
// ... bulk operations
$pdo->commit();
```

#### CPU Bottlenecks
```php
// Symptom: High CPU usage, slow processing
// Solutions:
// 1. Enable Swoole coroutines
$options['use_queue'] = true;

// 2. Optimize validation rules
public function rules(): array {
    return [
        // Simple rules are faster
        0 => 'required|max:255',
        // Complex regex is slower
        1 => 'email' // Use built-in validators
    ];
}

// 3. Simplify data transformations
public function map(array $row): array {
    return [
        // Simple assignment is fastest
        'name' => $row[0],
        'email' => $row[1],
        // Avoid complex operations
        'formatted_name' => $this->complexFormatting($row[0]) // Slow
    ];
}
```

#### Network/Cloud Issues
```php
// Symptom: Slow cloud storage operations
// Solutions:
// 1. Use correct region
$config['region'] = 'us-west-2'; // Same as your app

// 2. Increase chunk sizes for cloud
$options['chunk_size'] = 10000; // Larger chunks

// 3. Enable parallel processing
$options['use_queue'] = true;

// 4. Check network connectivity
$startTime = microtime(true);
$result = $processor->import($importer, 's3://bucket/file.xlsx', $options);
$transferTime = microtime(true) - $startTime;

if ($transferTime > $result['time'] * 2) {
    error_log('Network may be slow - transfer time is unusually high');
}
```

### Performance Profiling

Use Xdebug for detailed profiling:

```php
// Enable profiling
ini_set('xdebug.profiler_enable', 1);
ini_set('xdebug.profiler_output_dir', '/tmp/xdebug');

$processor = new DataProcessor();
$result = $processor->import($importer, 'file.xlsx');

// Analyze results with tools like:
// - KCachegrind
// - Webgrind
// - PhpStorm profiler
```

### Memory Leak Detection

Detect and prevent memory leaks:

```php
class MemoryLeakDetector {
    private int $baselineMemory;
    private array $snapshots = [];
    
    public function setBaseline(): void {
        $this->baselineMemory = memory_get_usage(true);
        gc_collect_cycles(); // Force garbage collection
    }
    
    public function takeSnapshot(string $label): void {
        gc_collect_cycles();
        $this->snapshots[$label] = [
            'memory' => memory_get_usage(true),
            'delta' => memory_get_usage(true) - $this->baselineMemory,
            'time' => microtime(true)
        ];
    }
    
    public function detectLeaks(int $thresholdMB = 100): array {
        $leaks = [];
        $previousMemory = $this->baselineMemory;
        
        foreach ($this->snapshots as $label => $snapshot) {
            $deltaFromPrevious = $snapshot['memory'] - $previousMemory;
            
            if ($deltaFromPrevious > $thresholdMB * 1024 * 1024) {
                $leaks[] = [
                    'label' => $label,
                    'memory_increase' => $deltaFromPrevious / 1024 / 1024,
                    'total_memory' => $snapshot['memory'] / 1024 / 1024
                ];
            }
            
            $previousMemory = $snapshot['memory'];
        }
        
        return $leaks;
    }
}

// Usage
$detector = new MemoryLeakDetector();
$detector->setBaseline();

foreach ($largeDataChunks as $index => $chunk) {
    $processor->process($chunk);
    $detector->takeSnapshot("chunk_{$index}");
}

$leaks = $detector->detectLeaks(50); // 50MB threshold

if (!empty($leaks)) {
    foreach ($leaks as $leak) {
        error_log("Memory leak detected at {$leak['label']}: +{$leak['memory_increase']}MB (total: {$leak['total_memory']}MB)");
    }
}
```

### Error Diagnosis

Common error patterns and solutions:

```php
class ErrorDiagnostics {
    public function diagnosePerformanceIssue(array $metrics): array {
        $issues = [];
        
        // Check processing speed
        if ($metrics['rows_per_second'] < 100) {
            $issues[] = [
                'type' => 'slow_processing',
                'message' => 'Processing speed is below 100 rows/second',
                'suggestions' => [
                    'Enable Swoole coroutines',
                    'Increase chunk size',
                    'Optimize database queries',
                    'Check for memory leaks'
                ]
            ];
        }
        
        // Check memory usage
        $memoryPerRow = $metrics['memory_used'] / $metrics['rows_processed'];
        if ($memoryPerRow > 1024) { // 1KB per row threshold
            $issues[] = [
                'type' => 'high_memory_usage',
                'message' => 'Memory usage per row is high',
                'suggestions' => [
                    'Use smaller chunk sizes',
                    'Implement proper generators',
                    'Avoid data accumulation',
                    'Check for circular references'
                ]
            ];
        }
        
        // Check error rate
        if ($metrics['error_rate'] > 0.01) { // 1% error rate
            $issues[] = [
                'type' => 'high_error_rate',
                'message' => 'High error rate detected',
                'suggestions' => [
                    'Review validation rules',
                    'Check data quality',
                    'Implement better error handling',
                    'Add data preprocessing'
                ]
            ];
        }
        
        return $issues;
    }
}
```

## Best Practices Summary

### Memory Management
1. **Use generators** for large datasets to avoid loading everything into memory
2. **Set appropriate memory limits** based on file size and available resources
3. **Monitor memory usage** during processing and implement alerts
4. **Avoid accumulating data** in instance variables or large arrays
5. **Force garbage collection** periodically for long-running processes

### Chunking Strategy
1. **Start with default chunk sizes** (1000 rows) and adjust based on testing
2. **Test different chunk sizes** for your specific use case and hardware
3. **Use larger chunks** for cloud storage operations to reduce overhead
4. **Implement adaptive chunking** for varying workloads and system conditions
5. **Consider data complexity** when determining optimal chunk size

### Database Operations
1. **Use bulk inserts** instead of individual queries for better performance
2. **Wrap operations in transactions** to improve consistency and speed
3. **Disable indexes** during large imports and re-enable afterward
4. **Optimize database configuration** for bulk operations
5. **Use database-specific optimizations** like MySQL's LOAD DATA INFILE

### Cloud Storage
1. **Use regional optimization** by placing storage close to your application
2. **Enable parallel processing** with Swoole for cloud operations
3. **Use larger chunk sizes** for cloud operations to minimize API calls
4. **Monitor transfer speeds** and costs for optimization opportunities
5. **Implement retry logic** for transient network issues

### Monitoring and Profiling
1. **Track key metrics**: rows/second, memory usage, processing time, error rates
2. **Implement custom monitoring** for critical operations and business logic
3. **Log performance data** for historical analysis and trend detection
4. **Set up alerts** for performance degradation or resource exhaustion
5. **Use profiling tools** like Xdebug for detailed performance analysis

### General Optimization Principles
1. **Profile before optimizing** - measure performance to identify bottlenecks
2. **Focus on bottlenecks** - optimize the slowest parts first for maximum impact
3. **Test with realistic data** - use production-like datasets for accurate results
4. **Monitor in production** - performance can vary significantly with real workloads
5. **Document optimizations** - maintain records of what works for future reference

### Error Handling and Recovery
1. **Implement graceful degradation** when resources are constrained
2. **Provide meaningful error messages** with actionable suggestions
3. **Add retry mechanisms** for transient failures
4. **Log detailed error information** for debugging and analysis
5. **Implement circuit breakers** for external service dependencies

### Scalability Considerations
1. **Design for horizontal scaling** from the beginning
2. **Use queue systems** for processing large workloads
3. **Implement proper caching** for frequently accessed data
4. **Consider database sharding** for very large datasets
5. **Plan for growth** - design systems that can handle 10x current load

### Security and Performance
1. **Validate input data** efficiently without compromising performance
2. **Implement rate limiting** to prevent resource exhaustion
3. **Use secure connections** for cloud storage without sacrificing speed
4. **Audit file access patterns** to optimize storage and retrieval
5. **Monitor for suspicious activity** that might indicate performance attacks

### Development Best Practices
1. **Write performance tests** alongside functional tests
2. **Use version control** for performance configurations
3. **Document performance requirements** clearly
4. **Create performance baselines** for regression testing
5. **Review code** with performance implications in mind

## Conclusion

The Elysian DataProcessor is designed to handle datasets ranging from thousands to millions of rows efficiently. By following the guidelines in this performance guide, you can:

- **Achieve optimal throughput** for your specific hardware and data characteristics
- **Handle large datasets** without running into memory or performance limitations  
- **Scale operations** as your data processing needs grow
- **Troubleshoot issues** quickly when performance problems arise
- **Monitor and maintain** consistent performance over time

Remember that performance optimization is an iterative process. Start with the default settings, measure your current performance, identify bottlenecks, and apply the appropriate optimizations from this guide. Always test changes with realistic data to ensure improvements translate to your production environment.

For additional support or advanced optimization needs, consult the Elysian DataProcessor documentation or reach out to the development team with your specific use case and performance requirements.

