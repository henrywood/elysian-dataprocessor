<?php
namespace Elysian\DataProcessor\Contracts;

interface WithCloudStorage {
    /**
     * Get storage configuration
     */
    public function storageConfig(): array;
}
