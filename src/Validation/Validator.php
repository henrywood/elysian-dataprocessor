<?php
// src/Validation/Validator.php
namespace Elysian\DataProcessor\Validation;

class Validator {
    
    public function validate(array $data, array $rules): array {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $ruleList = is_string($rule) ? explode('|', $rule) : $rule;
            
            foreach ($ruleList as $singleRule) {
                $error = $this->validateSingleRule($field, $value, $singleRule);
                if ($error) {
                    $errors[] = $error;
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function validateSingleRule(string $field, $value, string $rule): ?string {
        if ($rule === 'required' && empty($value)) {
            return "Field {$field} is required";
        }
        
        if (str_starts_with($rule, 'max:')) {
            $max = (int)substr($rule, 4);
            if (strlen((string)$value) > $max) {
                return "Field {$field} exceeds maximum length of {$max}";
            }
        }
        
        if (str_starts_with($rule, 'min:')) {
            $min = (int)substr($rule, 4);
            if (strlen((string)$value) < $min) {
                return "Field {$field} is below minimum length of {$min}";
            }
        }
        
        if ($rule === 'email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "Field {$field} must be a valid email address";
        }
        
        if ($rule === 'numeric' && $value && !is_numeric($value)) {
            return "Field {$field} must be numeric";
        }
        
        if (str_starts_with($rule, 'regex:')) {
            $pattern = substr($rule, 6);
            if ($value && !preg_match($pattern, $value)) {
                return "Field {$field} format is invalid";
            }
        }
        
        return null;
    }
}
