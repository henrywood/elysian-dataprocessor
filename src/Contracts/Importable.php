<?php
// src/Contracts/Importable.php
namespace Elysian\DataProcessor\Contracts;

interface Importable {
    /**
     * Map row data to desired format
     */
    public function map(array $row): array;

    /**
     * Process chunk of mapped data
     */
    public function process(array $data): void;
}
