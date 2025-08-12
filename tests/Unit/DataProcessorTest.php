<?php
// tests/Unit/DataProcessorTest.php
namespace Elysian\DataProcessor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Elysian\DataProcessor\DataProcessor;
use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\Exportable;

class DataProcessorTest extends TestCase {
    
    private DataProcessor $processor;
    
    protected function setUp(): void {
        $this->processor = new DataProcessor();
    }
    
    public function testImportBasicFunctionality(): void {
        $importer = new class implements Importable {
            public $processedData = [];
            
            public function map(array $row): array {
                return [
                    'name' => $row[0] ?? '',
                    'email' => $row[1] ?? ''
                ];
            }
            
            public function process(array $data): void {
                $this->processedData = array_merge($this->processedData, $data);
            }
        };
        
        // Create a test CSV file
        $testFile = tempnam(sys_get_temp_dir(), 'test_') . '.csv';
        file_put_contents($testFile, "Name,Email\nJohn Doe,john@example.com\nJane Smith,jane@example.com");
        
        $result = $this->processor->import($importer, $testFile);
        
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('time', $result);
        $this->assertEquals(2, $result['rows']);
        $this->assertCount(2, $importer->processedData);
        
        unlink($testFile);
    }
    
    public function testExportBasicFunctionality(): void {
        $exporter = new class implements Exportable {
            public function query(): \Generator {
                yield ['id' => 1, 'name' => 'John Doe'];
                yield ['id' => 2, 'name' => 'Jane Smith'];
            }
            
            public function headings(): array {
                return ['ID', 'Name'];
            }
            
            public function map($data): array {
                return [$data['id'], $data['name']];
            }
        };
        
        $testFile = tempnam(sys_get_temp_dir(), 'test_export_') . '.csv';
        
        $result = $this->processor->export($exporter, $testFile, ['format' => 'csv']);
        
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('time', $result);
        $this->assertEquals(2, $result['rows']);
        $this->assertFileExists($testFile);
        
        $content = file_get_contents($testFile);
        $this->assertStringContains('ID,Name', $content);
        $this->assertStringContains('John Doe', $content);
        
        unlink($testFile);
    }
}

