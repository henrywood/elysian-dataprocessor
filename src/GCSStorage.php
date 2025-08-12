<?php
// src/Storage/GCSStorage.php
namespace Elysian\DataProcessor\Storage;

use Exception;

class GCSStorage implements StorageInterface {
    private array $config;
    private $gcsClient;
    
    public function __construct(array $config) {
        $this->config = $config;
        
        if (!class_exists('Google\Cloud\Storage\StorageClient')) {
            throw new Exception("Google Cloud SDK not installed. Run: composer require google/cloud-storage");
        }
        
        $this->gcsClient = new \Google\Cloud\Storage\StorageClient($this->config);
    }
    
    public function download(string $remotePath): string {
        $parsed = $this->parseGCSUrl($remotePath);
        $tempPath = tempnam(sys_get_temp_dir(), 'gcs_download_');
        
        try {
            $bucket = $this->gcsClient->bucket($parsed['bucket']);
            $object = $bucket->object($parsed['key']);
            $object->downloadToFile($tempPath);
            return $tempPath;
        } catch (Exception $e) {
            throw new Exception("Failed to download from GCS: " . $e->getMessage());
        }
    }
    
    public function upload(string $localPath, string $remotePath): void {
        $parsed = $this->parseGCSUrl($remotePath);
        
        try {
            $bucket = $this->gcsClient->bucket($parsed['bucket']);
            $bucket->upload(fopen($localPath, 'r'), [
                'name' => $parsed['key']
            ]);
        } catch (Exception $e) {
            throw new Exception("Failed to upload to GCS: " . $e->getMessage());
        }
    }
    
    public function exists(string $path): bool {
        $parsed = $this->parseGCSUrl($path);
        
        try {
            $bucket = $this->gcsClient->bucket($parsed['bucket']);
            $object = $bucket->object($parsed['key']);
            return $object->exists();
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function delete(string $path): void {
        $parsed = $this->parseGCSUrl($path);
        
        try {
            $bucket = $this->gcsClient->bucket($parsed['bucket']);
            $object = $bucket->object($parsed['key']);
            $object->delete();
        } catch (Exception $e) {
            throw new Exception("Failed to delete from GCS: " . $e->getMessage());
        }
    }
    
    private function parseGCSUrl(string $url): array {
        if (!preg_match('/^gs:\/\/([^\/]+)\/(.+)$/', $url, $matches)) {
            throw new Exception("Invalid GCS URL format: {$url}");
        }
        
        return [
            'bucket' => $matches[1],
            'key' => $matches[2]
        ];
    }
}
