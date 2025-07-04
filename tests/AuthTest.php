<?php

use PHPUnit\Framework\TestCase;

/**
 * Testy klasy Auth
 */
class AuthTest extends TestCase 
{
    private $mockDb;
    private $auth;
    
    protected function setUp(): void 
    {
        // Resetuj sesję
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Mock bazy danych
        $this->mockDb = $this->createMock(Database::class);
        $this->auth = new Auth($this->mockDb);
    }
    
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    /**
     * Test poprawnej rejestracji
     */
    public function test_successful_registration()
    {
        // Arrange
        $this->mockDb->expects($this->once())
                    ->method('fetch')
                    ->willReturn(null); // Brak istniejącego użytkownika
                    
        $this->mockDb->expects($this->once())
                    ->method('query')
                    ->with($this->stringContains('INSERT INTO users'));
        
        // Act
        $result = $this->auth->register('Test User', 'test@example.com', 'password123', 'password123');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Konto zostało utworzone pomyślnie', $result['message']);
    }
    
    /**
     * Test rejestracji z niepoprawnym emailem
     */
    public function test_registration_with_invalid_email()
    {
        $result = $this->auth->register('Test User', 'invalid-email', 'password123', 'password123');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Nieprawidłowy format email', $result['message']);
    }
    
    /**
     * Test rejestracji z niezgodnymi hasłami
     */
    public function test_registration_with_mismatched_passwords()
    {
        $result = $this->auth->register('Test User', 'test@example.com', 'password123', 'different456');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Hasła nie są zgodne', $result['message']);
    }
    
    /**
     * Test poprawnego logowania
     */
    public function test_successful_login()
    {
        // Arrange
        $mockUser = createTestUser();
        
        $this->mockDb->expects($this->once())
                    ->method('fetch')
                    ->willReturn($mockUser);
        
        // Act
        $result = $this->auth->login('test@example.com', 'password123');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Test User', $result['user']['name']);
    }
    
    /**
     * Test nieudanego logowania
     */
    public function test_failed_login()
    {
        $this->mockDb->expects($this->once())
                    ->method('fetch')
                    ->willReturn(null);
        
        $result = $this->auth->login('test@example.com', 'wrongpassword');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Nieprawidłowy email lub hasło', $result['message']);
    }
}