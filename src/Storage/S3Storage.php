<?php
namespace Elysian\DataProcessor\Storage;

use Exception;

class S3Storage implements StorageInterface {
    private array $config;
    private $s3Client;
    
    public function __construct(array $config) {
        $this->config = array_merge([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => []
        ], $config);
        
        if (!class_exists('Aws\S3\S3Client')) {
            throw new Exception("AWS SDK not installed. Run: composer require aws/aws-sdk-php");
        }
        
        $this->s3Client = new \Aws\S3\S3Client($this->config);
    }
    
    public function download(string $remotePath): string {
        $parsed = $this->parseS3Url($remotePath);
        $tempPath = tempnam(sys_get_temp_dir(), 's3_download_');
        
        try {
            $this->s3Client->getObject([
                'Bucket' => $parsed['bucket'],
                'Key' => $parsed['key'],
                'SaveAs' => $tempPath
            ]);
            return $tempPath;
        } catch (Exception $e) {
            throw new Exception("Failed to download from S3: " . $e->getMessage());
        }
    }
    
    public function upload(string $localPath, string $remotePath): void {
        $parsed = $this->parseS3Url($remotePath);
        
        try {
            $this->s3Client->putObject([
                'Bucket' => $parsed['bucket'],
                'Key' => $parsed['key'],
                'SourceFile' => $localPath
            ]);
        } catch (Exception $e) {
            throw new Exception("Failed to upload to S3: " . $e->getMessage());
        }
    }
    
    public function exists(string $path): bool {
        $parsed = $this->parseS3Url($path);
        
        try {
            $this->s3Client->headObject([
                'Bucket' => $parsed['bucket'],
                'Key' => $parsed['key']
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function delete(string $path): void {
        $parsed = $this->parseS3Url($path);
        
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $parsed['bucket'],
                'Key' => $parsed['key']
            ]);
        } catch (Exception $e) {
            throw new Exception("Failed to delete from S3: " . $e->getMessage());
        }
    }
    
    private function parseS3Url(string $url): array {
        if (!preg_match('/^s3:\/\/([^\/]+)\/(.+)$/', $url, $matches)) {
            throw new Exception("Invalid S3 URL format: {$url}");
        }
        
        return [
            'bucket' => $matches[1],
            'key' => $matches[2]
        ];
    }
}

