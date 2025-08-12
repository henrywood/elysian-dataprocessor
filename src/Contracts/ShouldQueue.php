<?php
namespace Elysian\DataProcessor\Contracts;

interface ShouldQueue {
    /**
     * Get queue name
     */
    public function onQueue(): ?string;

    /**
     * Get timeout in seconds
     */
    public function timeout(): int;

    /**
     * Get memory limit in MB
     */
    public function memory(): int;
}
