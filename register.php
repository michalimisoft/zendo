<?php
/**
 * Bezpośrednie API rejestracji (bez routingu)
 * POST: /zendo/register.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Tylko metoda POST']);
    exit;
}

try {
    // Upewnij się że ścieżki są poprawne
    $base_dir = __DIR__;
    
    if (!file_exists($base_dir . '/config/database.php')) {
        throw new Exception('Nie można znaleźć pliku konfiguracyjnego bazy danych');
    }
    
    require_once $base_dir . '/config/database.php';
    require_once $base_dir . '/classes/Auth.php';
    
    $db = new Database();
    $auth = new Auth($db);
    
    // Pobierz dane z żądania
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $name = $input['name'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirmPassword'] ?? '';
    
    error_log("Register attempt for: $email from " . $_SERVER['REMOTE_ADDR']);
    
    $result = $auth->register($name, $email, $password, $confirmPassword);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Register error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd serwera: ' . $e->getMessage()]);
}