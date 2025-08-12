<?php
namespace Elysian\DataProcessor\Examples\Exports;

use Elysian\DataProcessor\Contracts\Exportable;
use Elysian\DataProcessor\Contracts\WithChunking;
use Generator;

/**
 * Example Users Export Class
 * 
 * This class demonstrates how to create an export processor
 * that efficiently streams data from the database
 */
class UsersExport implements Exportable, WithChunking {
    
    private ?array $filters;
    
    public function __construct(?array $filters = null) {
        $this->filters = $filters;
    }
    
    /**
     * Generator that yields data efficiently
     */
    public function query(): Generator {
        $chunkSize = $this->chunkSize();
        $offset = 0;
        
        do {
            // Simulate database query - replace with actual database code
            $users = $this->getUsers($offset, $chunkSize);
            
            foreach ($users as $user) {
                // Apply filters if provided
                if ($this->filters) {
                    if (isset($this->filters['min_age']) && $user['age'] < $this->filters['min_age']) {
                        continue;
                    }
                    
                    if (isset($this->filters['email_domain']) && !str_contains($user['email'], '@' . $this->filters['email_domain'])) {
                        continue;
                    }
                }
                
                yield $user;
            }
            
            $offset += $chunkSize;
            
        } while (count($users) === $chunkSize);
    }
    
    /**
     * Define column headings for the export
     */
    public function headings(): array {
        return ['ID', 'Name', 'Email', 'Age', 'Phone', 'Created At'];
    }
    
    /**
     * Map database record to export format
     */
    public function map($user): array {
        return [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['age'],
            $user['phone'] ?: 'N/A',
            $user['created_at']
        ];
    }
    
    public function chunkSize(): int {
        return 1000;
    }
    
    public function maxFileSize(): int {
        return 100 * 1024 * 1024; // 100MB
    }
    
    public function chunkRows(): int {
        return 5000;
    }
    
    /**
     * Simulate database query - replace with actual implementation
     */
    private function getUsers(int $offset, int $limit): array {
        // This is a simulation - replace with actual database query
        $users = [];
        for ($i = 0; $i < $limit; $i++) {
            $id = $offset + $i + 1;
            $users[] = [
                'id' => $id,
                'name' => "User {$id}",
                'email' => "user{$id}@example.com",
                'age' => 20 + ($id % 50),
                'phone' => "555-" . str_pad($id, 4, '0', STR_PAD_LEFT),
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$id} days"))
            ];
        }
        return $users;
    }
}
