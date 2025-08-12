<?php
// tests/Integration/ExportTest.php
namespace Elysian\DataProcessor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Elysian\DataProcessor\DataProcessor;
use Elysian\DataProcessor\Examples\Exports\SampleDataExport;

class ExportTest extends TestCase {
    
    private DataProcessor $processor;
    
    protected function setUp(): void {
        $this->processor = new DataProcessor();
    }
    
    public function testSampleDataExportIntegration(): void {
        $exporter = new SampleDataExport(10); // Export 10 sample records
        $testFile = tempnam(sys_get_temp_dir(), 'export_test_') . '.xlsx';
        
        $result = $this->processor->export($exporter, $testFile);
        
        $this->assertEquals(10, $result['rows']);
        $this->assertArrayHasKey('time', $result);
        $this->assertFileExists($testFile);
        
        // Verify file is not empty
        $this->assertGreaterThan(0, filesize($testFile));
        
        unlink($testFile);
    }
}
