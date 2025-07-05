<?php
/**
 * Sprawdzanie stanu logowania użytkownika
 * GET: /auth_user.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Tylko metoda GET']);
    exit;
}

try {
    session_start();
    
    $base_dir = __DIR__;
    require_once $base_dir . '/config/database.php';
    require_once $base_dir . '/classes/Auth.php';
    
    $db = new Database();
    $auth = new Auth($db);
    
    if ($auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nie zalogowany']);
    }
    
} catch (Exception $e) {
    error_log("Auth user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd serwera: ' . $e->getMessage()]);
}