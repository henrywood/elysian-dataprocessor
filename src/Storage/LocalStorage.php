<?php
// src/Storage/LocalStorage.php
namespace Elysian\DataProcessor\Storage;

use Exception;

class LocalStorage implements StorageInterface {
    
    public function download(string $remotePath): string {
        if (!file_exists($remotePath)) {
            throw new Exception("File not found: {$remotePath}");
        }
        return $remotePath; // Already local
    }
    
    public function upload(string $localPath, string $remotePath): void {
        $dir = dirname($remotePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (!copy($localPath, $remotePath)) {
            throw new Exception("Failed to copy file to: {$remotePath}");
        }
    }
    
    public function exists(string $path): bool {
        return file_exists($path);
    }
    
    public function delete(string $path): void {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
