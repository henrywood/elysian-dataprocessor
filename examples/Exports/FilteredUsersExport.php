<?php
/ examples/Exports/FilteredUsersExport.php
namespace Elysian\DataProcessor\Examples\Exports;

use Elysian\DataProcessor\Contracts\Exportable;
use Elysian\DataProcessor\Contracts\WithChunking;
use Generator;

/**
 * Example Filtered Users Export
 * 
 * Demonstrates filtering and advanced querying
 */
class FilteredUsersExport implements Exportable, WithChunking {
    
    private array $filters;
    
    public function __construct(array $filters = []) {
        $this->filters = $filters;
    }
    
    public function query(): Generator {
        $chunkSize = $this->chunkSize();
        $offset = 0;
        
        do {
            $users = $this->getFilteredUsers($offset, $chunkSize);
            
            foreach ($users as $user) {
                yield $user;
            }
            
            $offset += $chunkSize;
            
        } while (count($users) === $chunkSize);
    }
    
    public function headings(): array {
        return ['ID', 'Name', 'Email', 'Age', 'Status', 'Last Login'];
    }
    
    public function map($user): array {
        return [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['age'],
            $user['status'],
            $user['last_login'] ?: 'Never'
        ];
    }
    
    public function chunkSize(): int {
        return 2000;
    }
    
    public function maxFileSize(): int {
        return 200 * 1024 * 1024; // 200MB
    }
    
    public function chunkRows(): int {
        return 10000;
    }
    
    private function getFilteredUsers(int $offset, int $limit): array {
        // Simulate filtered database query
        $users = [];
        $generated = 0;
        $id = $offset + 1;
        
        while ($generated < $limit && $id <= 10000) {
            $user = [
                'id' => $id,
                'name' => "User {$id}",
                'email' => "user{$id}@example.com",
                'age' => 18 + ($id % 65),
                'status' => ($id % 3 === 0) ? 'inactive' : 'active',
                'last_login' => ($id % 5 === 0) ? null : date('Y-m-d H:i:s', strtotime("-" . ($id % 30) . " days"))
            ];
            
            // Apply filters
            if ($this->passesFilters($user)) {
                $users[] = $user;
                $generated++;
            }
            
            $id++;
        }
        
        return $users;
    }
    
    private function passesFilters(array $user): bool {
        if (isset($this->filters['min_age']) && $user['age'] < $this->filters['min_age']) {
            return false;
        }
        
        if (isset($this->filters['status']) && $user['status'] !== $this->filters['status']) {
            return false;
        }
        
        if (isset($this->filters['has_logged_in']) && $this->filters['has_logged_in'] && !$user['last_login']) {
            return false;
        }
        
        return true;
    }
}
