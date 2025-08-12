<?php
namespace Elysian\DataProcessor\Readers;

use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\CSV\Reader as CSVReader;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Reader\ODS\Reader as ODSReader;
use Exception;

class ReaderFactory {
    
    public function createFromFile(string $filePath): ReaderInterface {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        return match($extension) {
            'csv' => new CSVReader(),
            'xlsx', 'xls' => new XLSXReader(),
            'ods' => new ODSReader(),
            default => throw new Exception("Unsupported file format: {$extension}")
        };
    }
    
    public function create(string $format): ReaderInterface {
        return match(strtolower($format)) {
            'csv' => new CSVReader(),
            'xlsx' => new XLSXReader(),
            'ods' => new ODSReader(),
            default => throw new Exception("Unsupported format: {$format}")
        };
    }
}
