<?php
/**
 * Pobieranie szczegółów konkretnej listy
 * GET: /get_list.php?id={listId}
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
    require_once $base_dir . '/classes/TaskList.php';
    
    $db = new Database();
    $auth = new Auth($db);
    $taskList = new TaskList($db);
    
    // Sprawdź czy użytkownik jest zalogowany
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Musisz być zalogowany']);
        exit;
    }
    
    $user = $auth->getCurrentUser();
    $listId = $_GET['id'] ?? '';
    
    if (empty($listId)) {
        echo json_encode(['success' => false, 'message' => 'ID listy jest wymagane']);
        exit;
    }
    
    $result = $taskList->getListDetails($listId, $user['id']);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Get list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd serwera: ' . $e->getMessage()]);
}