<?php
namespace Elysian\DataProcessor\Storage;

interface StorageInterface {
    public function download(string $remotePath): string;
    public function upload(string $localPath, string $remotePath): void;
    public function exists(string $path): bool;
    public function delete(string $path): void;
}
