<?php
// examples/Imports/ProductsImport.php
namespace Elysian\DataProcessor\Examples\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithValidation;

/**
 * Example Product Import Class
 * 
 * Simpler import class for product data without queue processing
 */
class ProductsImport implements Importable, WithValidation {
    
    private array $products = [];
    
    public function rules(): array {
        return [
            0 => 'required|max:100',  // product_name
            1 => 'required|max:50',   // sku
            2 => 'required|numeric',  // price
            3 => 'numeric',           // stock_quantity
        ];
    }
    
    public function map(array $row): array {
        return [
            'name' => trim($row[0] ?? ''),
            'sku' => strtoupper(trim($row[1] ?? '')),
            'price' => (float)($row[2] ?? 0),
            'stock_quantity' => (int)($row[3] ?? 0),
            'category' => trim($row[4] ?? 'General'),
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    public function process(array $data): void {
        // For this example, we'll just store in memory
        // In real usage, you'd insert into database
        $this->products = array_merge($this->products, $data);
        
        // Log progress
        error_log("Processed " . count($data) . " products. Total: " . count($this->products));
    }
    
    /**
     * Get all processed products (for testing)
     */
    public function getProducts(): array {
        return $this->products;
    }
}
