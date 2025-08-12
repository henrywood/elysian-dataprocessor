<?php
namespace Elysian\DataProcessor\Contracts;

interface WithChunking {
    /**
     * Get chunk size for processing
     */
    public function chunkSize(): int;

    /**
     * Get maximum file size in bytes
     */
    public function maxFileSize(): int;

    /**
     * Get chunk rows
     */
    public function chunkRows(): int;
}
