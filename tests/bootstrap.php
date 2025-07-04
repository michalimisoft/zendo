<?php
/**
 * Bootstrap dla testów ZenDo
 */

// WAŻNE: Zdefiniuj flagę testową przed autoloadem
define('TESTING', true);

// Autoload Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Autoload klas aplikacji
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../classes/' . $class . '.php',
        __DIR__ . '/../config/' . $class . '.php'
    ];
    
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Konfiguracja dla testów - wyłącz output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Konfiguracja error reporting dla testów
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// Klasa testowej bazy danych
class TestDatabase extends Database {
    protected $host = '127.0.0.1';
    protected $db_name = 'zendo_test';
    protected $username = 'root';
    protected $password = 'root';
}

// Funkcje pomocnicze dla testów
function createTestUser($id = 1, $name = 'Test User', $email = 'test@example.com') {
    return [
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ];
}

function createTestTask($id = 1, $title = 'Test Task', $listId = 1) {
    return [
        'id' => $id,
        'title' => $title,
        'description' => 'Test description',
        'priority' => 'medium',
        'deadline' => '2025-12-31 23:59:59',
        'completed' => false,
        'list_id' => $listId,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

function createTestList($id = 1, $name = 'Test List', $userId = 1) {
    return [
        'id' => $id,
        'name' => $name,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s')
    ];
}