<?php
// examples/Imports/UsersImport.php
namespace Elysian\DataProcessor\Examples\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\ShouldQueue;
use Elysian\DataProcessor\Contracts\WithChunking;
use Elysian\DataProcessor\Contracts\WithValidation;
use Elysian\DataProcessor\Contracts\WithCloudStorage;
use PDO;

/**
 * Example Users Import Class
 * 
 * This class demonstrates how to create an import processor
 * that implements multiple contracts for different features
 */
class UsersImport implements Importable, ShouldQueue, WithChunking, WithValidation, WithCloudStorage {
    
    private PDO $pdo;
    
    public function __construct() {
        // Initialize database connection
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=test',
            'username',
            'password',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    /**
     * Define validation rules for imported data
     */
    public function rules(): array {
        return [
            0 => 'required|max:255',  // name
            1 => 'required|email',    // email
            2 => 'numeric',           // age
            3 => 'max:20'             // phone
        ];
    }
    
    /**
     * Map raw row data to structured array
     */
    public function map(array $row): array {
        return [
            'name' => trim($row[0] ?? ''),
            'email' => strtolower(trim($row[1] ?? '')),
            'age' => (int)($row[2] ?? 0),
            'phone' => trim($row[3] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Process a chunk of mapped data
     */
    public function process(array $data): void {
        if (empty($data)) {
            return;
        }
        
        // Prepare bulk insert statement
        $placeholders = str_repeat('(?,?,?,?,?,?),', count($data));
        $placeholders = rtrim($placeholders, ',');
        
        $sql = "INSERT INTO users (name, email, age, phone, created_at, updated_at) 
                VALUES {$placeholders}
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                age = VALUES(age),
                phone = VALUES(phone),
                updated_at = VALUES(updated_at)";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Flatten the data array for binding
        $values = [];
        foreach ($data as $row) {
            $values = array_merge($values, array_values($row));
        }
        
        $stmt->execute($values);
    }
    
    // Queue configuration
    public function onQueue(): ?string {
        return 'imports';
    }
    
    public function timeout(): int {
        return 300; // 5 minutes
    }
    
    public function memory(): int {
        return 512; // 512MB
    }
    
    // Chunking configuration
    public function chunkSize(): int {
        return 1000;
    }
    
    public function maxFileSize(): int {
        return 50 * 1024 * 1024; // 50MB
    }
    
    public function chunkRows(): int {
        return 10000;
    }
    
    // Cloud storage configuration
    public function storageConfig(): array {
        return [
            'type' => 's3',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY')
            ]
        ];
    }
}

