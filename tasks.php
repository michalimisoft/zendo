<?php
/**
 * Bezpośrednie API zadań
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    session_start();
    
    $base_dir = __DIR__;
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
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Pobierz dane z żądania
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = array_merge($_POST, $_GET);
    }
    
    switch ($method) {
        case 'GET':
            // Pobierz zadania z listy
            $listId = $_GET['list_id'] ?? $input['list_id'] ?? '';
            
            if (empty($listId)) {
                echo json_encode(['success' => false, 'message' => 'ID listy jest wymagane']);
                exit;
            }
            
            $result = $task->getListTasks($listId, $user['id']);
            echo json_encode($result);
            break;
            
        case 'POST':
            // Sprawdź czy to toggle zadania
            if (isset($input['toggle']) && $input['toggle']) {
                $taskId = $input['taskId'] ?? '';
                if (empty($taskId)) {
                    echo json_encode(['success' => false, 'message' => 'ID zadania jest wymagane']);
                    exit;
                }
                $result = $task->toggleTaskComplete($taskId, $user['id']);
                echo json_encode($result);
                break;
            }
            
            // Utwórz nowe zadanie
            $listId = $input['list_id'] ?? '';
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            $priority = $input['priority'] ?? 'medium';
            $deadline = $input['deadline'] ?? '';
            
            if (empty($listId) || empty(trim($title))) {
                echo json_encode(['success' => false, 'message' => 'ID listy i tytuł zadania są wymagane']);
                exit;
            }
            
            $result = $task->createTask($listId, $title, $description, $priority, $deadline, $user['id']);
            echo json_encode($result);
            break;
            
        case 'PUT':
            // Aktualizuj zadanie
            $taskId = $input['taskId'] ?? '';
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            $priority = $input['priority'] ?? 'medium';
            $deadline = $input['deadline'] ?? '';
            
            if (empty($taskId) || empty(trim($title))) {
                echo json_encode(['success' => false, 'message' => 'ID zadania i tytuł są wymagane']);
                exit;
            }
            
            $result = $task->updateTask($taskId, $title, $description, $priority, $deadline, $user['id']);
            echo json_encode($result);
            break;
            
        case 'DELETE':
            // Usuń zadanie
            $taskId = $input['taskId'] ?? '';
            
            if (empty($taskId)) {
                echo json_encode(['success' => false, 'message' => 'ID zadania jest wymagane']);
                exit;
            }
            
            $result = $task->deleteTask($taskId, $user['id']);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
    }
    
} catch (Exception $e) {
    error_log("Tasks API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd serwera: ' . $e->getMessage()]);
}