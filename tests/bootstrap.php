<?php
/**
 * Bootstrap dla testów ZenDo
 */

// Autoload klas (dla testów w chmurze)
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',  // Composer autoload
    __DIR__ . '/../classes/',             // Nasze klasy
    __DIR__ . '/../config/'               // Konfiguracja
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path) && is_file($path)) {
        require_once $path;
    }
}

// Autoload manualny dla klas aplikacji
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

// Konfiguracja dla testów
if (!defined('TESTING')) {
    define('TESTING', true);
}

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