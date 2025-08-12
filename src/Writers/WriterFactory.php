<?php
// src/Writers/WriterFactory.php
namespace Elysian\DataProcessor\Writers;

use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\CSV\Writer as CSVWriter;
use OpenSpout\Writer\XLSX\Writer as XLSXWriter;
use OpenSpout\Writer\ODS\Writer as ODSWriter;
use Exception;

class WriterFactory {
    
    public function create(string $format): WriterInterface {
        return match(strtolower($format)) {
            'csv' => new CSVWriter(),
            'xlsx' => new XLSXWriter(),
            'ods' => new ODSWriter(),
            default => throw new Exception("Unsupported format: {$format}")
        };
    }
}
