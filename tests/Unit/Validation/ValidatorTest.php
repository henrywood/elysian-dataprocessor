<?php
// tests/Unit/Validation/ValidatorTest.php
namespace Elysian\DataProcessor\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Elysian\DataProcessor\Validation\Validator;

class ValidatorTest extends TestCase {
    
    private Validator $validator;
    
    protected function setUp(): void {
        $this->validator = new Validator();
    }
    
    public function testRequiredValidation(): void {
        $data = ['', 'test@example.com'];
        $rules = [0 => 'required', 1 => 'required'];
        
        $result = $this->validator->validate($data, $rules);
        
        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContains('Field 0 is required', $result['errors'][0]);
    }
    
    public function testEmailValidation(): void {
        $data = ['invalid-email', 'valid@example.com'];
        $rules = [0 => 'email', 1 => 'email'];
        
        $result = $this->validator->validate($data, $rules);
        
        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContains('valid email', $result['errors'][0]);
    }
    
    public function testMaxLengthValidation(): void {
        $data = ['toolongname', 'ok'];
        $rules = [0 => 'max:5', 1 => 'max:5'];
        
        $result = $this->validator->validate($data, $rules);
        
        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContains('maximum length', $result['errors'][0]);
    }
    
    public function testValidData(): void {
        $data = ['John Doe', 'john@example.com', '30'];
        $rules = [
            0 => 'required|max:255',
            1 => 'required|email',
            2 => 'numeric'
        ];
        
        $result = $this->validator->validate($data, $rules);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
}
