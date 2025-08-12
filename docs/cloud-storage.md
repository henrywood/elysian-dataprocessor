# Cloud Storage Guide

The Elysian DataProcessor supports multiple cloud storage providers for importing and exporting files, allowing you to process data directly from cloud storage without downloading to local storage first.

## Table of Contents

1. [Supported Providers](#supported-providers)
2. [Configuration Methods](#configuration-methods)
3. [Amazon S3](#amazon-s3)
4. [Google Cloud Storage](#google-cloud-storage)
5. [Azure Blob Storage](#azure-blob-storage)
6. [URL Formats](#url-formats)
7. [Usage Examples](#usage-examples)
8. [Best Practices](#best-practices)
9. [Error Handling](#error-handling)
10. [Troubleshooting](#troubleshooting)

## Supported Providers

The DataProcessor supports the following cloud storage providers:

- **Amazon S3** - Simple Storage Service
- **Google Cloud Storage** - Google's object storage
- **Azure Blob Storage** - Microsoft's cloud storage
- **Local Storage** - Local filesystem (default)

Each provider supports full read/write operations for import and export functionality.

## Configuration Methods

There are three ways to configure cloud storage:

1. **Via DataProcessor options** - Pass configuration in method calls
2. **Via Environment Variables** - Set credentials as environment variables
3. **Via WithCloudStorage Contract** - Define configuration in import/export classes

### Method 1: Via DataProcessor Options

```php
use Elysian\DataProcessor\DataProcessor;

$processor = new DataProcessor();
$result = $processor->import($importer, 's3://bucket/file.xlsx', [
    'storage_type' => 's3',
    'storage_config' => json_encode([
        'region' => 'us-east-1',
        'credentials' => [
            'key' => 'YOUR_ACCESS_KEY',
            'secret' => 'YOUR_SECRET_KEY'
        ]
    ])
]);
```

### Method 2: Via Environment Variables

```bash
# Set environment variables
export AWS_ACCESS_KEY_ID="your-access-key"
export AWS_SECRET_ACCESS_KEY="your-secret-key"
export AWS_DEFAULT_REGION="us-east-1"
```

```php
// Use without explicit configuration
$result = $processor->import($importer, 's3://bucket/file.xlsx', [
    'storage_type' => 's3'
    // No storage_config needed - uses environment variables
]);
```

### Method 3: Via WithCloudStorage Contract

```php
use Elysian\DataProcessor\Contracts\WithCloudStorage;

class S3Import implements Importable, WithCloudStorage {
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
    
    // ... other methods
}
```

## Amazon S3

### Prerequisites

Install the AWS SDK:
```bash
composer require aws/aws-sdk-php
```

### Configuration Options

#### Basic Configuration
```php
$config = [
    'region' => 'us-east-1',
    'credentials' => [
        'key' => 'AKIAIOSFODNN7EXAMPLE',
        'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'
    ]
];
```

#### Advanced Configuration
```php
$config = [
    'region' => 'us-east-1',
    'version' => 'latest',
    'credentials' => [
        'key' => 'YOUR_ACCESS_KEY',
        'secret' => 'YOUR_SECRET_KEY',
        'token' => 'SESSION_TOKEN' // For temporary credentials
    ],
    'endpoint' => 'https://s3.amazonaws.com', // Custom endpoint
    'use_path_style_endpoint' => false,
    'signature_version' => 'v4'
];
```

### Environment Variables
```bash
export AWS_ACCESS_KEY_ID="AKIAIOSFODNN7EXAMPLE"
export AWS_SECRET_ACCESS_KEY="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
export AWS_DEFAULT_REGION="us-east-1"
export AWS_SESSION_TOKEN="temporary-token" # For temporary credentials
```

### IAM Permissions

Minimum required permissions for the IAM user/role:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name",
                "arn:aws:s3:::your-bucket-name/*"
            ]
        }
    ]
}
```

### S3 Usage Examples

#### Import from S3
```php
$processor = new DataProcessor();
$result = $processor->import($importer, 's3://data-bucket/imports/users.xlsx', [
    'storage_type' => 's3',
    'storage_config' => json_encode([
        'region' => 'us-east-1',
        'credentials' => [
            'key' => getenv('AWS_ACCESS_KEY_ID'),
            'secret' => getenv('AWS_SECRET_ACCESS_KEY')
        ]
    ])
]);
```

#### Export to S3
```php
$result = $processor->export($exporter, 's3://data-bucket/exports/users.xlsx', [
    'storage_type' => 's3',
    'storage_config' => json_encode([
        'region' => 'us-east-1'
    ])
]);
```

#### Convert Files on S3
```php
$result = $processor->convert(
    's3://data-bucket/input.xlsx',
    's3://data-bucket/output.csv',
    [
        'format' => 'csv',
        'storage_type' => 's3'
    ]
);
```

## Google Cloud Storage

### Prerequisites

Install the Google Cloud SDK:
```bash
composer require google/cloud-storage
```

### Authentication Methods

#### Service Account Key File
```php
$config = [
    'projectId' => 'my-project-id',
    'keyFilePath' => '/path/to/service-account.json'
];
```

#### Application Default Credentials
```php
$config = [
    'projectId' => 'my-project-id'
    // Uses GOOGLE_APPLICATION_CREDENTIALS environment variable
];
```

#### JSON Key Content
```php
$config = [
    'projectId' => 'my-project-id',
    'keyFile' => json_decode(file_get_contents('/path/to/key.json'), true)
];
```

### Environment Variables
```bash
export GOOGLE_APPLICATION_CREDENTIALS="/path/to/service-account.json"
export GOOGLE_CLOUD_PROJECT="my-project-id"
```

### Service Account Permissions

Required IAM roles for the service account:
- `Storage Object Admin` - For full read/write access
- `Storage Object Viewer` - For read-only access
- `Storage Object Creator` - For write-only access

### GCS Usage Examples

#### Import from Google Cloud Storage
```php
$result = $processor->import($importer, 'gs://my-bucket/data/users.xlsx', [
    'storage_type' => 'gcs',
    'storage_config' => json_encode([
        'projectId' => 'my-project-123',
        'keyFilePath' => '/path/to/service-account.json'
    ])
]);
```

#### Export to Google Cloud Storage
```php
$result = $processor->export($exporter, 'gs://my-bucket/exports/users.csv', [
    'format' => 'csv',
    'storage_type' => 'gcs',
    'storage_config' => json_encode([
        'projectId' => 'my-project-123'
    ])
]);
```

## Azure Blob Storage

### Prerequisites

Install the Azure SDK:
```bash
composer require microsoft/azure-storage-blob
```

### Configuration Options

#### Connection String Method
```php
$config = [
    'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=myaccount;AccountKey=mykey;EndpointSuffix=core.windows.net'
];
```

#### Account Name and Key Method
```php
$config = [
    'account_name' => 'mystorageaccount',
    'account_key' => 'base64-encoded-account-key'
];
```

#### SAS Token Method
```php
$config = [
    'account_name' => 'mystorageaccount',
    'sas_token' => 'your-sas-token'
];
```

### Environment Variables
```bash
export AZURE_STORAGE_ACCOUNT="mystorageaccount"
export AZURE_STORAGE_KEY="base64-encoded-key"
# OR
export AZURE_STORAGE_CONNECTION_STRING="DefaultEndpointsProtocol=https;AccountName=..."
```

### Azure Usage Examples

#### Import from Azure Blob Storage
```php
$result = $processor->import($importer, 'azure://mycontainer/data/users.xlsx', [
    'storage_type' => 'azure',
    'storage_config' => json_encode([
        'account_name' => 'mystorageaccount',
        'account_key' => getenv('AZURE_STORAGE_KEY')
    ])
]);
```

#### Export to Azure Blob Storage
```php
$result = $processor->export($exporter, 'azure://mycontainer/exports/users.xlsx', [
    'storage_type' => 'azure',
    'storage_config' => json_encode([
        'account_name' => 'mystorageaccount',
        'account_key' => getenv('AZURE_STORAGE_KEY')
    ])
]);
```

## URL Formats

Each cloud provider has a specific URL format:

### Amazon S3
```
s3://bucket-name/path/to/file.xlsx
s3://my-data-bucket/imports/2024/users.xlsx
s3://reports-bucket/monthly/january/report.csv
```

### Google Cloud Storage
```
gs://bucket-name/path/to/file.xlsx
gs://my-gcs-bucket/data/users.xlsx
gs://analytics-bucket/exports/user-data.csv
```

### Azure Blob Storage
```
azure://container-name/path/to/file.xlsx
azure://data-container/imports/users.xlsx
azure://exports-container/reports/monthly.csv
```

### Local Storage (Default)
```
/path/to/local/file.xlsx
./data/users.xlsx
../imports/data.csv
```

## Usage Examples

### Cross-Cloud Migration

#### S3 to Google Cloud Storage
```php
$result = $processor->convert(
    's3://source-bucket/data.xlsx',
    'gs://target-bucket/data.csv',
    [
        'format' => 'csv',
        'storage_type' => 's3', // Source storage type
        'storage_config' => json_encode([
            'region' => 'us-east-1'
        ])
    ]
);
```

#### Google Cloud to Azure
```php
$result = $processor->convert(
    'gs://gcp-bucket/report.ods',
    'azure://azure-container/report.xlsx',
    [
        'format' => 'xlsx',
        'storage_type' => 'gcs',
        'storage_config' => json_encode([
            'projectId' => 'my-gcp-project'
        ])
    ]
);
```

### Batch Processing from Cloud

#### Daily Import from S3
```php
$files = [
    's3://daily-imports/users-2024-01-01.xlsx',
    's3://daily-imports/users-2024-01-02.xlsx',
    's3://daily-imports/users-2024-01-03.xlsx'
];

foreach ($files as $file) {
    $result = $processor->import($importer, $file, [
        'storage_type' => 's3',
        'chunk_size' => 2000
    ]);
    
    echo "Processed {$result['rows']} rows from {$file}\n";
}
```

#### Weekly Export to Multiple Clouds
```php
$exporters = [
    ['exporter' => new UsersExport(), 'path' => 's3://backup-bucket/weekly/users.xlsx'],
    ['exporter' => new UsersExport(), 'path' => 'gs://analytics-bucket/users.csv'],
    ['exporter' => new UsersExport(), 'path' => 'azure://archive-container/users.ods']
];

foreach ($exporters as $export) {
    $storageType = parse_url($export['path'], PHP_URL_SCHEME);
    
    $result = $processor->export($export['exporter'], $export['path'], [
        'storage_type' => $storageType,
        'format' => pathinfo($export['path'], PATHINFO_EXTENSION)
    ]);
    
    echo "Exported {$result['rows']} rows to {$export['path']}\n";
}
```

### WithCloudStorage Contract Examples

#### Multi-Cloud Import Class
```php
use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithCloudStorage;

class MultiCloudImport implements Importable, WithCloudStorage {
    private string $cloudProvider;
    
    public function __construct(string $cloudProvider = 's3') {
        $this->cloudProvider = $cloudProvider;
    }
    
    public function storageConfig(): array {
        return match($this->cloudProvider) {
            's3' => [
                'type' => 's3',
                'region' => 'us-east-1',
                'credentials' => [
                    'key' => getenv('AWS_ACCESS_KEY_ID'),
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY')
                ]
            ],
            'gcs' => [
                'type' => 'gcs',
                'projectId' => getenv('GOOGLE_CLOUD_PROJECT'),
                'keyFilePath' => getenv('GOOGLE_APPLICATION_CREDENTIALS')
            ],
            'azure' => [
                'type' => 'azure',
                'account_name' => getenv('AZURE_STORAGE_ACCOUNT'),
                'account_key' => getenv('AZURE_STORAGE_KEY')
            ],
            default => throw new \InvalidArgumentException("Unsupported cloud provider: {$this->cloudProvider}")
        };
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

// Usage
$s3Import = new MultiCloudImport('s3');
$processor->import($s3Import, 's3://bucket/file.xlsx');

$gcsImport = new MultiCloudImport('gcs');
$processor->import($gcsImport, 'gs://bucket/file.xlsx');
```

## Best Practices

### Security

1. **Use Environment Variables**: Store credentials in environment variables, not in code
```bash
# Good
export AWS_ACCESS_KEY_ID="your-key"

# Bad - Don't put credentials in code
$config = ['credentials' => ['key' => 'hardcoded-key']];
```

2. **Use IAM Roles**: Use IAM roles instead of access keys when running on cloud instances
```php
// AWS EC2 instances can use IAM roles without explicit credentials
$config = [
    'region' => 'us-east-1'
    // No credentials needed - uses instance role
];
```

3. **Minimal Permissions**: Grant only the permissions necessary for your use case
```json
{
    "Effect": "Allow",
    "Action": ["s3:GetObject", "s3:PutObject"],
    "Resource": "arn:aws:s3:::specific-bucket/*"
}
```

### Performance

1. **Regional Optimization**: Use storage regions close to your application
```php
// Good - same region as application
$config = ['region' => 'us-west-2'];

// Bad - cross-region transfers are slower and cost more
$config = ['region' => 'eu-west-1']; // App in us-west-2
```

2. **Use Larger Chunk Sizes**: For cloud operations, use larger chunks to reduce API calls
```php
$result = $processor->import($importer, 's3://bucket/file.xlsx', [
    'storage_type' => 's3',
    'chunk_size' => 5000 // Larger chunks for cloud storage
]);
```

3. **Enable Parallel Processing**: Use Swoole for better cloud performance
```php
$result = $processor->import($importer, 's3://bucket/file.xlsx', [
    'storage_type' => 's3',
    'use_queue' => true, // Enables parallel processing
    'chunk_size' => 5000
]);
```

### Cost Optimization

1. **Monitor Transfer Costs**: Cloud storage charges for data transfer
2. **Use Lifecycle Policies**: Automatically move old files to cheaper storage classes
3. **Compress Files**: Use compression to reduce storage and transfer costs
4. **Clean Up Temporary Files**: The library automatically cleans up, but monitor for leaks

### Reliability

1. **Implement Retry Logic**: Handle temporary network issues
```php
$maxRetries = 3;
$retryCount = 0;

while ($retryCount < $maxRetries) {
    try {
        $result = $processor->import($importer, 's3://bucket/file.xlsx', [
            'storage_type' => 's3'
        ]);
        break; // Success
    } catch (Exception $e) {
        $retryCount++;
        if ($retryCount >= $maxRetries) {
            throw $e;
        }
        sleep(pow(2, $retryCount)); // Exponential backoff
    }
}
```

2. **Monitor Cloud Service Status**: Check cloud provider status pages during issues
3. **Have Backup Plans**: Consider multi-cloud strategies for critical data

## Error Handling

### Common Error Scenarios

#### Authentication Errors
```php
try {
    $result = $processor->import($importer, 's3://bucket/file.xlsx', [
        'storage_type' => 's3'
    ]);
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'Access Denied')) {
        // Handle authentication error
        error_log('S3 authentication failed. Check credentials.');
        // Maybe refresh credentials or use backup method
    }
}
```

#### File Not Found
```php
try {
    $result = $processor->import($importer, 's3://bucket/nonexistent.xlsx', [
        'storage_type' => 's3'
    ]);
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), 'NoSuchKey')) {
        // Handle file not found
        error_log('File not found in S3 bucket');
        // Maybe check alternative locations or notify user
    }
}
```

#### Network Issues
```php
try {
    $result = $processor->import($importer, 'gs://bucket/file.xlsx', [
        'storage_type' => 'gcs'
    ]);
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'connection')) {
        // Handle network issues
        error_log('Network issue with GCS. Retrying...');
        // Implement retry logic
    }
}
```

#### Quota/Rate Limiting
```php
try {
    $result = $processor->export($exporter, 'azure://container/file.xlsx', [
        'storage_type' => 'azure'
    ]);
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'quota')) {
        // Handle rate limiting
        error_log('Rate limited by Azure. Waiting before retry...');
        sleep(60); // Wait before retry
    }
}
```

### Error Handling Best Practices

1. **Log Detailed Information**: Include context in error logs
```php
try {
    $result = $processor->import($importer, $cloudFile, $options);
} catch (\Exception $e) {
    error_log(sprintf(
        'Cloud import failed: %s | File: %s | Provider: %s | Error: %s',
        date('Y-m-d H:i:s'),
        $cloudFile,
        $options['storage_type'],
        $e->getMessage()
    ));
    throw $e;
}
```

2. **Graceful Degradation**: Have fallback options
```php
$cloudProviders = ['s3', 'gcs', 'azure'];
$success = false;

foreach ($cloudProviders as $provider) {
    try {
        $result = $processor->import($importer, $files[$provider], [
            'storage_type' => $provider
        ]);
        $success = true;
        break;
    } catch (\Exception $e) {
        error_log("Failed with {$provider}: " . $e->getMessage());
        continue;
    }
}

if (!$success) {
    throw new \Exception('All cloud providers failed');
}
```

## Troubleshooting

### Connection Issues

#### Check Network Connectivity
```bash
# Test S3 connectivity
curl -I https://s3.amazonaws.com

# Test GCS connectivity
curl -I https://storage.googleapis.com

# Test Azure connectivity
curl -I https://myaccount.blob.core.windows.net
```

#### Verify DNS Resolution
```bash
nslookup s3.amazonaws.com
nslookup storage.googleapis.com
nslookup myaccount.blob.core.windows.net
```

### Authentication Issues

#### Test AWS Credentials
```bash
aws sts get-caller-identity
aws s3 ls s3://your-bucket
```

#### Test GCS Credentials
```bash
gcloud auth list
gsutil ls gs://your-bucket
```

#### Test Azure Credentials
```bash
az account show
az storage blob list --container-name your-container
```

### Performance Issues

#### Monitor Transfer Speeds
```php
$startTime = microtime(true);
$result = $processor->import($importer, 's3://bucket/file.xlsx', [
    'storage_type' => 's3'
]);
$endTime = microtime(true);

$transferTime = $endTime - $startTime;
$transferSpeed = $result['rows'] / $transferTime;

error_log("Transfer speed: {$transferSpeed} rows/second");
```

#### Check Regional Settings
Ensure you're using the correct region for your storage bucket:

```php
// Bad - wrong region causes slower transfers
$config = ['region' => 'us-east-1']; // Bucket is in us-west-2

// Good - correct region
$config = ['region' => 'us-west-2'];
```

### Debug Mode

Enable verbose logging for troubleshooting:

```php
// Enable debug logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use try-catch with detailed logging
try {
    $result = $processor->import($importer, $cloudFile, [
        'storage_type' => $provider,
        'storage_config' => json_encode($config)
    ]);
} catch (\Exception $e) {
    error_log('Full error details: ' . print_r($e, true));
    error_log('Stack trace: ' . $e->getTraceAsString());
    throw $e;
}
```

This comprehensive guide covers all aspects of using cloud storage with the Elysian DataProcessor. For additional support, consult the specific cloud provider documentation or contact support.
