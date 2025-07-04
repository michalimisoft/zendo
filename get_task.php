<?php
/**
 * Bezpośrednie API pobierania szczegółów zadania
 * GET: /zendo/get_task.php?id={taskId}
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
    
    // Upewnij się że ścieżki są poprawne
    $base_dir = __DIR__;
    
    if (!file_exists($base_dir . '/config/database.php')) {
        throw new Exception('Nie można znaleźć pliku konfiguracyjnego bazy danych');
    }
    
    require_once $base_dir . '/config/database.php';
    require_once $base_dir . '/classes/Auth.php';
    require_once $base_dir . '/classes/TaskList.php';
    require_once $base_dir . '/classes/Task.php';
    
    $db = new Database();
    $auth = new Auth($db);
    $taskList = new TaskList($db);
    $task = new Task($db, $taskList);
    
    // Sprawdź czy użytkownik jest zalogowany
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Musisz być zalogowany']);
        exit;
    }
    
    $user = $auth->getCurrentUser();
    
    $taskId = $_GET['id'] ?? '';
    
    if (empty($taskId)) {
        echo json_encode(['success' => false, 'message' => 'ID zadania jest wymagane']);
        exit;
    }
    
    error_log("Get task attempt - Task ID: $taskId, User: " . $user['email']);
    
    $result = $task->getTaskWithAccess($taskId, $user['id']);
    
    // Dodaj formatowanie daty dla formularza
    if ($result['success'] && isset($result['task']['deadline']) && $result['task']['deadline']) {
        $result['task']['deadline_formatted'] = date('Y-m-d\TH:i', strtotime($result['task']['deadline']));
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Get task error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd serwera: ' . $e->getMessage()]);
}