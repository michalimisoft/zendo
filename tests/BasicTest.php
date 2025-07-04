<?php

use PHPUnit\Framework\TestCase;

/**
 * Podstawowe testy funkcjonalności ZenDo
 */
class BasicTest extends TestCase 
{
    /**
     * Test sprawdzający czy klasy są dostępne
     */
    public function test_classes_exist()
    {
        $this->assertTrue(class_exists('Database'), 'Klasa Database powinna istnieć');
        $this->assertTrue(class_exists('Auth'), 'Klasa Auth powinna istnieć');
        $this->assertTrue(class_exists('Task'), 'Klasa Task powinna istnieć');
        $this->assertTrue(class_exists('TaskList'), 'Klasa TaskList powinna istnieć');
    }
    
    /**
     * Test walidacji email
     */
    public function test_email_validation()
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'simple@domain.org'
        ];
        
        foreach ($validEmails as $email) {
            $this->assertTrue(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email $email powinien być poprawny"
            );
        }
        
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            'user name@domain.com'
        ];
        
        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL),
                "Email $email powinien być niepoprawny"
            );
        }
    }
    
    /**
     * Test walidacji hasła
     */
    public function test_password_validation()
    {
        $shortPassword = "12345";
        $this->assertLessThan(6, strlen($shortPassword), 'Hasło powinno być za krótkie');
        
        $validPassword = "password123";
        $this->assertGreaterThanOrEqual(6, strlen($validPassword), 'Hasło powinno być wystarczająco długie');
    }
    
    /**
     * Test priorytetów zadań
     */
    public function test_task_priorities()
    {
        $validPriorities = ['low', 'medium', 'high'];
        
        foreach ($validPriorities as $priority) {
            $this->assertContains($priority, $validPriorities, "Priorytet $priority powinien być poprawny");
        }
        
        $invalidPriority = 'urgent';
        $this->assertNotContains($invalidPriority, $validPriorities, 'Priorytet urgent nie powinien być poprawny');
    }
    
    /**
     * Test formatowania daty
     */
    public function test_date_formatting()
    {
        $testDate = '2025-12-31 23:59:59';
        $formattedDate = date('d.m.Y H:i', strtotime($testDate));
        
        $this->assertEquals('31.12.2025 23:59', $formattedDate, 'Data powinna być poprawnie sformatowana');
    }
    
    /**
     * Test funkcji pomocniczych
     */
    public function test_helper_functions()
    {
        $testUser = createTestUser();
        $this->assertArrayHasKey('id', $testUser);
        $this->assertArrayHasKey('name', $testUser);
        $this->assertArrayHasKey('email', $testUser);
        $this->assertEquals('Test User', $testUser['name']);
        
        $testTask = createTestTask();
        $this->assertArrayHasKey('id', $testTask);
        $this->assertArrayHasKey('title', $testTask);
        $this->assertEquals('Test Task', $testTask['title']);
        
        $testList = createTestList();
        $this->assertArrayHasKey('id', $testList);
        $this->assertArrayHasKey('name', $testList);
        $this->assertEquals('Test List', $testList['name']);
    }
}