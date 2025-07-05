<?php
/**
 * Bezpośrednie API list zadań
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
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Pobierz dane z żądania
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    switch ($method) {
        case 'GET':
            // Pobierz wszystkie listy użytkownika
            $lists = $taskList->getUserLists($user['id']);
            echo json_encode(['success' => true, 'lists' => $lists]);
            break;
            
        case 'POST':
            // Utwórz nową listę
            $name = $input['name'] ?? '';
            if (empty(trim($name))) {
                echo json_encode(['success' => false, 'message' => 'Nazwa listy jest wymagana']);
                exit;
            }
            
            $result = $taskList->createList($name, $user['id']);
            echo json_encode($result);
            break;
            
        case 'PUT':
            // Aktualizuj listę
            $listId = $input['listId'] ?? '';
            $name = $input['name'] ?? '';
            
            if (empty($listId) || empty(trim($name))) {
                echo json_encode(['success' => false, 'message' => 'ID listy i nazwa są wymagane']);
                exit;
            }
            
            $result = $taskList->updateList($listId, $name, $user['id']);
            echo json_encode($result);
            break;
            
        case 'DELETE':
            // Usuń listę
            $listId = $input['listId'] ?? '';
            
            if (empty($listId)) {
                echo json_encode(['success' => false, 'message' => 'ID listy jest wymagane']);
                exit;
            }
            
            $result = $taskList->deleteList($listId, $user['id']);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
    }
    
} catch (Exception $e) {
    error_log("Lists API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd serwera: ' . $e->getMessage()]);
}