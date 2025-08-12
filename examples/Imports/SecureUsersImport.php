<?php
// examples/Imports/SecureUsersImport.php
namespace Elysian\DataProcessor\Examples\Imports;

use Elysian\DataProcessor\Contracts\Importable;
use Elysian\DataProcessor\Contracts\WithValidation;

/**
 * Example secure UsersImport with data sanitization
 */
class SecureUsersImport implements Importable, WithValidation {
    
    public function rules(): array {
        return [
            0 => 'required|max:255|regex:/^[a-zA-Z\s\-\'\.]+$/', // Name: letters, spaces, hyphens, apostrophes, dots only
            1 => 'required|email|max:255',                       // Email: proper email validation
            2 => 'required|numeric|min:13|max:120',              // Age: reasonable bounds
            3 => 'nullable|regex:/^[\+]?[1-9][\d\s\-\(\)]+$/'   // Phone: international format
        ];
    }
    
    public function map(array $row): array {
        return [
            'name' => $this->sanitizeName(trim($row[0] ?? '')),
            'email' => $this->sanitizeEmail(trim($row[1] ?? '')),
            'age' => $this->sanitizeAge((int)($row[2] ?? 0)),
            'phone' => $this->sanitizePhone(trim($row[3] ?? '')),
        ];
    }
    
    private function sanitizeName(string $name): string {
        // Remove any potentially dangerous characters
        $name = preg_replace('/[^a-zA-Z\s\-\'\.]/u', '', $name);
        // Normalize whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        // Proper case
        return ucwords(strtolower($name));
    }
    
    private function sanitizeEmail(string $email): string {
        // Convert to lowercase and validate
        $email = strtolower($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
    
    private function sanitizeAge(int $age): int {
        // Ensure reasonable bounds
        return max(13, min(120, $age));
    }
    
    private function sanitizePhone(string $phone): ?string {
        if (empty($phone)) return null;
        
        // Remove all non-digit characters except + - ( ) and spaces
        $phone = preg_replace('/[^\d\+\-\(\)\s]/', '', $phone);
        // Normalize spacing
        $phone = preg_replace('/\s+/', ' ', trim($phone));
        
        return strlen($phone) >= 10 ? $phone : null;
    }
    
    public function process(array $data): void {
        foreach ($data as $userData) {
            try {
                // In real implementation, save to database
                error_log("Processing secure user: " . json_encode($userData));
            } catch (\Exception $e) {
                error_log("Failed to create user: " . $e->getMessage());
            }
        }
    }
}
