<?php
// src/Contracts/WithValidation.php
namespace Elysian\DataProcessor\Contracts;

interface WithValidation {
    /**
     * Get validation rules
     */
    public function rules(): array;
}
