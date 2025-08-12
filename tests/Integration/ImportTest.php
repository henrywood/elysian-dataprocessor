<?php
// tests/Integration/ImportTest.php
namespace Elysian\DataProcessor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Elysian\DataProcessor\DataProcessor;
use Elysian\DataProcessor\Examples\Imports\ProductsImport;

class ImportTest extends TestCase {
    
    private DataProcessor $processor;
    
    protected function setUp(): void {
        $this->processor = new DataProcessor();
    }
    
    public function testProductImportIntegration(): void {
        // Create test CSV file
        $testFile = tempnam(sys_get_temp_dir(), 'products_test_') . '.csv';
        $csvContent = "Product Name,SKU,Price,Stock\n";
        $csvContent .= "Test Product 1,TEST001,19.99,100\n";
        $csvContent .= "Test Product 2,TEST002,29.99,50\n";
        $csvContent .= "Test Product 3,TEST003,39.99,25\n";
        
        file_put_contents($testFile, $csvContent);
        
        $importer = new ProductsImport();
        $result = $this->processor->import($importer, $testFile);
        
        $this->assertEquals(3, $result['rows']);
        $this->assertArrayHasKey('time', $result);
        
        $products = $importer->getProducts();
        $this->assertCount(3, $products);
        
        $firstProduct = $products[0];
        $this->assertEquals('Test Product 1', $firstProduct['name']);
        $this->assertEquals('TEST001', $firstProduct['sku']);
        $this->assertEquals(19.99, $firstProduct['price']);
        $this->assertEquals(100, $firstProduct['stock_quantity']);
        
        unlink($testFile);
    }
}
