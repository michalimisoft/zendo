<?php

use PHPUnit\Framework\TestCase;

/**
 * Testy klasy Auth - bez problemów z sesją
 */
class AuthTest extends TestCase 
{
    private $mockDb;
    private $auth;
    
    protected function setUp(): void 
    {
        // Upewnij się że jesteśmy w trybie testowym
        if (!defined('TESTING')) {
            define('TESTING', true);
        }
        
        // Mock bazy danych
        $this->mockDb = $this->createMock(Database::class);
        $this->auth = new Auth($this->mockDb);
    }
    
    /**
     * Test poprawnej rejestracji
     */
    public function test_successful_registration()
    {
        // Arrange
        $this->mockDb->expects($this->once())
                    ->method('fetch')
                    ->with($this->stringContains('SELECT id FROM users WHERE email'))
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
        // Act
        $result = $this->auth->register('Test User', 'invalid-email', 'password123', 'password123');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Nieprawidłowy format email', $result['message']);
    }
    
    /**
     * Test rejestracji z niezgodnymi hasłami
     */
    public function test_registration_with_mismatched_passwords()
    {
        // Act
        $result = $this->auth->register('Test User', 'test@example.com', 'password123', 'different456');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Hasła nie są zgodne', $result['message']);
    }
    
    /**
     * Test rejestracji z za krótkim hasłem
     */
    public function test_registration_with_short_password()
    {
        // Act
        $result = $this->auth->register('Test User', 'test@example.com', '123', '123');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Hasło musi mieć co najmniej 6 znaków', $result['message']);
    }
    
    /**
     * Test rejestracji gdy użytkownik już istnieje
     */
    public function test_registration_when_user_exists()
    {
        // Arrange
        $existingUser = ['id' => 1, 'email' => 'test@example.com'];
        $this->mockDb->expects($this->once())
                    ->method('fetch')
                    ->willReturn($existingUser);
        
        // Act
        $result = $this->auth->register('Test User', 'test@example.com', 'password123', 'password123');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Użytkownik o tym emailu już istnieje', $result['message']);
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
                    ->with($this->stringContains('SELECT * FROM users WHERE email'))
                    ->willReturn($mockUser);
        
        // Act
        $result = $this->auth->login('test@example.com', 'password123');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Test User', $result['user']['name']);
        $this->assertEquals('test@example.com', $result['user']['email']);
        $this->assertArrayHasKey('id', $result['user']);
    }
    
    /**
     * Test nieudanego logowania - zły użytkownik
     */
    public function test_failed_login_wrong_user()
    {
        // Arrange
        $this->mockDb->expects($this->once())
                    ->method('fetch')
                    ->willReturn(null);
        
        // Act
        $result = $this->auth->login('nonexistent@example.com', 'password123');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Nieprawidłowy email lub hasło', $result['message']);
    }
    
    /**
     * Test nieudanego logowania - złe hasło
     */
    public function test_failed_login_wrong_password()
    {
        // Arrange
        $mockUser = createTestUser();
        $this->mockDb->expects($this->once())
                    ->method('fetch')
                    ->willReturn($mockUser);
        
        // Act
        $result = $this->auth->login('test@example.com', 'wrongpassword');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Nieprawidłowy email lub hasło', $result['message']);
    }
    
    /**
     * Test sprawdzania czy użytkownik jest zalogowany
     */
    public function test_is_logged_in_returns_false_in_tests()
    {
        // W trybie testowym zawsze powinno zwracać false
        $isLoggedIn = $this->auth->isLoggedIn();
        $this->assertFalse($isLoggedIn);
    }
    
    /**
     * Test pobierania obecnego użytkownika
     */
    public function test_get_current_user_returns_null_in_tests()
    {
        // W trybie testowym zawsze powinno zwracać null
        $currentUser = $this->auth->getCurrentUser();
        $this->assertNull($currentUser);
    }
}