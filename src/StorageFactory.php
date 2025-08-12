<?php
// src/Storage/StorageFactory.php
namespace Elysian\DataProcessor\Storage;

use Exception;

class StorageFactory {
    
    public function create(string $type, ?string $config = null): StorageInterface {
        $configArray = $config ? json_decode($config, true) : [];
        
        return match(strtolower($type)) {
            'local' => new LocalStorage(),
            's3' => new S3Storage($configArray),
            'gcs' => new GCSStorage($configArray),
            'azure' => new AzureStorage($configArray),
            default => throw new Exception("Unsupported storage type: {$type}")
        };
    }
}
