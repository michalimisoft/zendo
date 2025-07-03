<?php
/**
 * Bezpośrednie API edycji zadań
 * POST: /zendo/edit_task.php
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
    
    // Pobierz dane z żądania
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $taskId = $input['taskId'] ?? '';
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $priority = $input['priority'] ?? 'medium';
    $deadline = $input['deadline'] ?? '';
    
    if (empty($taskId)) {
        echo json_encode(['success' => false, 'message' => 'ID zadania jest wymagane']);
        exit;
    }
    
    if (empty(trim($title))) {
        echo json_encode(['success' => false, 'message' => 'Tytuł zadania nie może być pusty']);
        exit;
    }
    
    error_log("Edit task attempt - Task ID: $taskId, User: " . $user['email']);
    
    $result = $task->updateTask($taskId, $title, $description, $priority, $deadline, $user['id']);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Edit task error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd serwera: ' . $e->getMessage()]);
}