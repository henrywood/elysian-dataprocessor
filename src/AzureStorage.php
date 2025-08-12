<?php
// src/Storage/AzureStorage.php
namespace Elysian\DataProcessor\Storage;

use Exception;

class AzureStorage implements StorageInterface {
    private array $config;
    private $blobClient;
    
    public function __construct(array $config) {
        $this->config = $config;
        
        if (!class_exists('MicrosoftAzure\Storage\Blob\BlobRestProxy')) {
            throw new Exception("Azure SDK not installed. Run: composer require microsoft/azure-storage-blob");
        }
        
        $connectionString = "DefaultEndpointsProtocol=https;AccountName={$config['account_name']};AccountKey={$config['account_key']};EndpointSuffix=core.windows.net";
        $this->blobClient = \MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService($connectionString);
    }
    
    public function download(string $remotePath): string {
        $parsed = $this->parseAzureUrl($remotePath);
        $tempPath = tempnam(sys_get_temp_dir(), 'azure_download_');
        
        try {
            $blob = $this->blobClient->getBlob($parsed['container'], $parsed['blob']);
            file_put_contents($tempPath, stream_get_contents($blob->getContentStream()));
            return $tempPath;
        } catch (Exception $e) {
            throw new Exception("Failed to download from Azure: " . $e->getMessage());
        }
    }
    
    public function upload(string $localPath, string $remotePath): void {
        $parsed = $this->parseAzureUrl($remotePath);
        
        try {
            $content = fopen($localPath, 'r');
            $this->blobClient->createBlockBlob($parsed['container'], $parsed['blob'], $content);
        } catch (Exception $e) {
            throw new Exception("Failed to upload to Azure: " . $e->getMessage());
        }
    }
    
    public function exists(string $path): bool {
        $parsed = $this->parseAzureUrl($path);
        
        try {
            $this->blobClient->getBlobProperties($parsed['container'], $parsed['blob']);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function delete(string $path): void {
        $parsed = $this->parseAzureUrl($path);
        
        try {
            $this->blobClient->deleteBlob($parsed['container'], $parsed['blob']);
        } catch (Exception $e) {
            throw new Exception("Failed to delete from Azure: " . $e->getMessage());
        }
    }
    
    private function parseAzureUrl(string $url): array {
        if (!preg_match('/^azure:\/\/([^\/]+)\/(.+)$/', $url, $matches)) {
            throw new Exception("Invalid Azure URL format: {$url}");
        }
        
        return [
            'container' => $matches[1],
            'blob' => $matches[2]
        ];
    }
}
