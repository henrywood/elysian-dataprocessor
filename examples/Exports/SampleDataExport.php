<?php
// examples/Exports/SampleDataExport.php
namespace Elysian\DataProcessor\Examples\Exports;

use Elysian\DataProcessor\Contracts\Exportable;
use Generator;

/**
 * Example Simple Export Class
 * 
 * Basic export that generates sample data
 */
class SampleDataExport implements Exportable {
    
    private int $recordCount;
    
    public function __construct(int $recordCount = 1000) {
        $this->recordCount = $recordCount;
    }
    
    public function query(): Generator {
        for ($i = 1; $i <= $this->recordCount; $i++) {
            yield [
                'id' => $i,
                'name' => "Sample User {$i}",
                'email' => "user{$i}@example.com",
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} days"))
            ];
        }
    }
    
    public function headings(): array {
        return ['ID', 'Name', 'Email', 'Created Date'];
    }
    
    public function map($data): array {
        return [
            $data['id'],
            $data['name'],
            $data['email'],
            $data['created_at']
        ];
    }
}
