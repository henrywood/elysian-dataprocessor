<?php
// src/Queue/QueueManager.php
namespace Elysian\DataProcessor\Queue;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\ShouldQueue;
use OpenSpout\Reader\ReaderInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class QueueManager {
    
    public function processImport(Importable $importer, ReaderInterface $reader, array $options): array {
        if (!extension_loaded('swoole')) {
            throw new \Exception("Swoole extension required for queue processing");
        }
        
        $startTime = microtime(true);
        $totalRows = 0;
        $queueName = $importer instanceof ShouldQueue ? $importer->onQueue() : 'default';
        $timeout = $importer instanceof ShouldQueue ? $importer->timeout() : 300;
        
        // Create coroutine channel for job queue
        $channel = new Channel(1000);
        $workerCount = 4; // Configurable worker count
        
        // Start workers
        for ($i = 0; $i < $workerCount; $i++) {
            Coroutine::create(function() use ($channel, $importer, $i) {
                while (true) {
                    $job = $channel->pop(1.0); // 1 second timeout
                    
                    if ($job === false) {
                        continue; // Timeout, keep listening
                    }
                    
                    if ($job === 'stop') {
                        break;
                    }
                    
                    try {
                        $this->processJob($importer, $job);
                    } catch (\Exception $e) {
                        error_log("Worker {$i} error: " . $e->getMessage());
                    }
                }
            });
        }
        
        // Producer coroutine
        Coroutine::create(function() use ($channel, $reader, &$totalRows, $options) {
            $chunk = [];
            $chunkSize = $options['chunk_size'];
            
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    if ($rowIndex === 1) continue; // Skip header
                    
                    $rowData = [];
                    foreach ($row->getCells() as $cell) {
                        $rowData[] = $cell->getValue();
                    }
                    
                    $chunk[] = $rowData;
                    $totalRows++;
                    
                    if (count($chunk) >= $chunkSize) {
                        $channel->push($chunk);
                        $chunk = [];
                    }
                }
            }
            
            // Push remaining chunk
            if (!empty($chunk)) {
                $channel->push($chunk);
            }
            
            // Signal workers to stop
            for ($i = 0; $i < 4; $i++) {
                $channel->push('stop');
            }
        });
        
        // Wait for all coroutines to finish
        while (Coroutine::stats()['coroutine_num'] > 1) {
            Coroutine::sleep(0.1);
        }
        
        $endTime = microtime(true);
        return [
            'rows' => $totalRows,
            'time' => round($endTime - $startTime, 2),
            'queue' => $queueName
        ];
    }
    
    private function processJob(Importable $importer, array $chunk): void {
        $mappedChunk = [];
        foreach ($chunk as $row) {
            $mappedChunk[] = $importer->map($row);
        }
        $importer->process($mappedChunk);
    }
}

