<?php
// src/Contracts/Exportable.php
namespace Elysian\DataProcessor\Contracts;

use Generator;

interface Exportable {
    /**
     * Get data generator
     */
    public function query(): Generator;

    /**
     * Map data to export format
     */
    public function map($data): array;
}
