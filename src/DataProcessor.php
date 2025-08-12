namespace Elysian\DataProcessor;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\Exportable;
use Elysian\DataProcessor\Contracts\ShouldQueue;
use Elysian\DataProcessor\Contracts\WithChunking;
use Elysian\DataProcessor\Contracts\WithValidation;
use Elysian\DataProcessor\Contracts\WithCloudStorage;
use Elysian\DataProcessor\Readers\ReaderFactory;
use Elysian\DataProcessor\Writers\WriterFactory;
use Elysian\DataProcessor\Storage\StorageFactory;
use Elysian\DataProcessor\Queue\QueueManager;
use Elysian\DataProcessor\Validation\Validator;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Common\Entity\Row;
use Generator;
use Exception;
use Swoole\Coroutine;

class DataProcessor {
    private ReaderFactory $readerFactory;
    private WriterFactory $writerFactory;
    private StorageFactory $storageFactory;
    private QueueManager $queueManager;
    private Validator $validator;
    
    public function __construct() {
        $this->readerFactory = new ReaderFactory();
        $this->writerFactory = new WriterFactory();
        $this->storageFactory = new StorageFactory();
        $this->queueManager = new QueueManager();
        $this->validator = new Validator();
    }

    /**
     * Import data from file using the provided import class
     *
     * @param Importable $importer
     * @param string $filePath
     * @param array $options
     * @return array
     */
    public function import(Importable $importer, string $filePath, array $options = []): array {
        $startTime = microtime(true);
        $totalRows = 0;
        
        $options = array_merge([
            'chunk_size' => 1000,
            'storage_type' => 'local',
            'storage_config' => null,
            'use_queue' => false,
            'validate' => true,
            'max_memory' => 512
        ], $options);

        // Set memory limit
        ini_set('memory_limit', $options['max_memory'] . 'M');

        // Get storage adapter
        $storage = $this->storageFactory->create($options['storage_type'], $options['storage_config']);
        
        // Download file if using cloud storage
        $localPath = $storage->download($filePath);
        
        try {
            // Get reader
            $reader = $this->readerFactory->createFromFile($localPath);
            $reader->open($localPath);

            // Check if should use queue processing
            if ($options['use_queue'] && $importer instanceof ShouldQueue) {
                return $this->processImportWithQueue($importer, $reader, $options);
            }

            // Process directly
            $chunkSize = $this->getChunkSize($importer, $options['chunk_size']);
            $chunk = [];
            $chunkNumber = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    // Skip header row
                    if ($rowIndex === 1) {
                        continue;
                    }

                    $rowData = [];
                    foreach ($row->getCells() as $cell) {
                        $rowData[] = $cell->getValue();
                    }

                    // Validate if required
                    if ($options['validate'] && $importer instanceof WithValidation) {
                        $validation = $this->validator->validate($rowData, $importer->rules());
                        if (!$validation['valid']) {
                            error_log("Row {$rowIndex} validation failed: " . implode(', ', $validation['errors']));
                            continue;
                        }
                    }

                    // Map data
                    $mappedData = $importer->map($rowData);
                    $chunk[] = $mappedData;
                    $totalRows++;

                    // Process chunk when full
                    if (count($chunk) >= $chunkSize) {
                        $this->processChunk($importer, $chunk, ++$chunkNumber);
                        $chunk = [];
                        
                        // Use Swoole coroutine for better performance if available
                        if (extension_loaded('swoole') && method_exists(Coroutine::class, 'yield')) {
                            Coroutine::yield();
                        }
                    }
                }
            }

            // Process remaining chunk
            if (!empty($chunk)) {
                $this->processChunk($importer, $chunk, ++$chunkNumber);
            }

            $reader->close();
        } finally {
            // Cleanup temporary file if downloaded
            if ($localPath !== $filePath) {
                unlink($localPath);
            }
        }

        $endTime = microtime(true);
        return [
            'rows' => $totalRows,
            'time' => round($endTime - $startTime, 2),
            'chunks' => $chunkNumber
        ];
    }

    /**
     * Export data to file using the provided export class
     *
     * @param Exportable $exporter
     * @param string $filePath
     * @param array $options
     * @return array
     */
    public function export(Exportable $exporter, string $filePath, array $options = []): array {
        $startTime = microtime(true);
        $totalRows = 0;
        
        $options = array_merge([
            'format' => 'xlsx',
            'chunk_size' => 1000,
            'storage_type' => 'local',
            'storage_config' => null,
            'use_queue' => false,
            'max_memory' => 512
        ], $options);

        // Set memory limit
        ini_set('memory_limit', $options['max_memory'] . 'M');

        // Create temporary local file
        $tempPath = tempnam(sys_get_temp_dir(), 'export_') . '.' . $options['format'];
        
        try {
            // Get writer
            $writer = $this->writerFactory->create($options['format']);
            $writer->openToFile($tempPath);

            // Write headers if available
            if (method_exists($exporter, 'headings')) {
                $headers = $exporter->headings();
                $writer->addRow(Row::fromValues($headers));
            }

            // Get chunk size
            $chunkSize = $this->getChunkSize($exporter, $options['chunk_size']);
            
            // Process data in chunks
            $generator = $exporter->query();
            $chunk = [];
            $chunkNumber = 0;

            foreach ($generator as $item) {
                // Map data
                $mappedData = $exporter->map($item);
                $chunk[] = $mappedData;
                $totalRows++;

                // Write chunk when full
                if (count($chunk) >= $chunkSize) {
                    $this->writeChunk($writer, $chunk, ++$chunkNumber);
                    $chunk = [];
                    
                    // Use Swoole coroutine for better performance if available
                    if (extension_loaded('swoole') && method_exists(Coroutine::class, 'yield')) {
                        Coroutine::yield();
                    }
                }
            }

            // Write remaining chunk
            if (!empty($chunk)) {
                $this->writeChunk($writer, $chunk, ++$chunkNumber);
            }

            $writer->close();

            // Upload file if using cloud storage
            $storage = $this->storageFactory->create($options['storage_type'], $options['storage_config']);
            $storage->upload($tempPath, $filePath);

        } finally {
            // Cleanup temporary file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        $endTime = microtime(true);
        return [
            'rows' => $totalRows,
            'time' => round($endTime - $startTime, 2),
            'chunks' => $chunkNumber
        ];
    }

    /**
     * Convert file from one format to another
     *
     * @param string $inputPath
     * @param string $outputPath
     * @param array $options
     * @return array
     */
    public function convert(string $inputPath, string $outputPath, array $options = []): array {
        $startTime = microtime(true);
        $totalRows = 0;
        
        $options = array_merge([
            'format' => 'xlsx',
            'chunk_size' => 1000,
            'storage_type' => 'local',
            'storage_config' => null,
            'max_memory' => 512
        ], $options);

        // Set memory limit
        ini_set('memory_limit', $options['max_memory'] . 'M');

        // Get storage adapter
        $storage = $this->storageFactory->create($options['storage_type'], $options['storage_config']);
        
        // Download input file if using cloud storage
        $localInputPath = $storage->download($inputPath);
        $tempOutputPath = tempnam(sys_get_temp_dir(), 'convert_') . '.' . $options['format'];
        
        try {
            // Get reader and writer
            $reader = $this->readerFactory->createFromFile($localInputPath);
            $writer = $this->writerFactory->create($options['format']);
            
            $reader->open($localInputPath);
            $writer->openToFile($tempOutputPath);

            $chunkSize = $options['chunk_size'];
            $chunk = [];
            $chunkNumber = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    $rowData = [];
                    foreach ($row->getCells() as $cell) {
                        $rowData[] = $cell->getValue();
                    }

                    $chunk[] = $rowData;
                    $totalRows++;

                    // Write chunk when full
                    if (count($chunk) >= $chunkSize) {
                        $this->writeChunk($writer, $chunk, ++$chunkNumber);
                        $chunk = [];
                        
                        // Use Swoole coroutine for better performance if available
                        if (extension_loaded('swoole') && method_exists(Coroutine::class, 'yield')) {
                            Coroutine::yield();
                        }
                    }
                }
            }

            // Write remaining chunk
            if (!empty($chunk)) {
                $this->writeChunk($writer, $chunk, ++$chunkNumber);
            }

            $reader->close();
            $writer->close();

            // Upload output file if using cloud storage
            $storage->upload($tempOutputPath, $outputPath);

        } finally {
            // Cleanup temporary files
            if ($localInputPath !== $inputPath) {
                unlink($localInputPath);
            }
            if (file_exists($tempOutputPath)) {
                unlink($tempOutputPath);
            }
        }

        $endTime = microtime(true);
        return [
            'rows' => $totalRows,
            'time' => round($endTime - $startTime, 2),
            'chunks' => $chunkNumber
        ];
    }

    /**
     * Process chunk of data
     */
    private function processChunk(Importable $importer, array $chunk, int $chunkNumber): void {
        $importer->process($chunk);
    }

    /**
     * Write chunk of data
     */
    private function writeChunk(WriterInterface $writer, array $chunk, int $chunkNumber): void {
        foreach ($chunk as $row) {
            $writer->addRow(Row::fromValues($row));
        }
    }

    /**
     * Get chunk size from importer/exporter or use default
     */
    private function getChunkSize($processor, int $default): int {
        if ($processor instanceof WithChunking) {
            return $processor->chunkSize();
        }
        return $default;
    }

    /**
     * Process import with queue
     */
    private function processImportWithQueue(Importable $importer, ReaderInterface $reader, array $options): array {
        return $this->queueManager->processImport($importer, $reader, $options);
    }
}
