<?php
/**
 * API REST dla aplikacji ZenDo
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Autoload klas
$base_dir = dirname(__DIR__);
require_once $base_dir . '/config/database.php';
require_once $base_dir . '/classes/Auth.php';
require_once $base_dir . '/classes/TaskList.php';
require_once $base_dir . '/classes/Task.php';

// Inicjalizacja z obsługą błędów
try {
    $db = new Database();
    $auth = new Auth($db);
    $taskList = new TaskList($db);
    $task = new Task($db, $taskList);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Błąd inicjalizacji serwera',
        'error' => $e->getMessage()
    ]);
    exit();
}

// Routing
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Usuń nazwę folderu aplikacji jeśli istnieje
if (count($pathParts) > 0 && $pathParts[0] === 'zendo') {
    array_shift($pathParts);
}

// Usuń 'api' jeśli istnieje
if (count($pathParts) > 0 && $pathParts[0] === 'api') {
    array_shift($pathParts);
}

$endpoint = $pathParts[0] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Pobierz dane z żądania
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

// Routing endpointów
switch ($endpoint) {
    case 'auth':
        handleAuth($auth, $method, $input, $pathParts);
        break;
    
    case 'lists':
        handleLists($auth, $taskList, $method, $input, $pathParts);
        break;
    
    case 'tasks':
        handleTasks($auth, $task, $taskList, $method, $input, $pathParts);
        break;
    
    case 'dashboard':
        handleDashboard($auth, $task, $method);
        break;
    
    case 'test':
    case 'test-rewrite':
        echo json_encode([
            'success' => true,
            'message' => 'API działa poprawnie',
            'endpoint' => $endpoint,
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
    
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'message' => 'Endpoint nie został znaleziony: ' . $endpoint,
            'available' => ['auth', 'lists', 'tasks', 'dashboard']
        ]);
}

// FUNKCJA: Obsługa autoryzacji
function handleAuth($auth, $method, $input, $pathParts) {
    $action = $pathParts[1] ?? '';
    
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
                return;
            }
            
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Email i hasło są wymagane']);
                return;
            }
            
            $result = $auth->login($email, $password);
            echo json_encode($result);
            break;
        
        case 'register':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
                return;
            }
            
            $name = $input['name'] ?? '';
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            $confirmPassword = $input['confirmPassword'] ?? '';
            
            $result = $auth->register($name, $email, $password, $confirmPassword);
            echo json_encode($result);
            break;
        
        case 'reset-password':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
                return;
            }
            
            $email = $input['email'] ?? '';
            $result = $auth->resetPassword($email);
            echo json_encode($result);
            break;
        
        case 'change-password':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
                return;
            }
            
            $auth->requireAuth();
            $user = $auth->getCurrentUser();
            
            $currentPassword = $input['currentPassword'] ?? '';
            $newPassword = $input['newPassword'] ?? '';
            $confirmNewPassword = $input['confirmNewPassword'] ?? '';
            
            $result = $auth->changePassword($user['id'], $currentPassword, $newPassword, $confirmNewPassword);
            echo json_encode($result);
            break;
        
        case 'logout':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
                return;
            }
            
            $result = $auth->logout();
            echo json_encode($result);
            break;
        
        case 'user':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
                return;
            }
            
            if ($auth->isLoggedIn()) {
                $user = $auth->getCurrentUser();
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Nie zalogowany']);
            }
            break;
        
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Akcja nie została znaleziona: ' . $action]);
    }
}

// FUNKCJA: Obsługa list
function handleLists($auth, $taskList, $method, $input, $pathParts) {
    $auth->requireAuth();
    $user = $auth->getCurrentUser();
    
    $listId = $pathParts[1] ?? null;
    $action = $pathParts[2] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($listId) {
                if ($action === 'shares') {
                    $result = $taskList->getListShares($listId, $user['id']);
                    echo json_encode($result);
                } else {
                    $result = $taskList->getListDetails($listId, $user['id']);
                    echo json_encode($result);
                }
            } else {
                $lists = $taskList->getUserLists($user['id']);
                echo json_encode(['success' => true, 'lists' => $lists]);
            }
            break;
        
        case 'POST':
            if ($listId && $action === 'share') {
                $email = $input['email'] ?? '';
                $result = $taskList->shareList($listId, $email, $user['id']);
                echo json_encode($result);
            } else {
                $name = $input['name'] ?? '';
                $result = $taskList->createList($name, $user['id']);
                echo json_encode($result);
            }
            break;
        
        case 'PUT':
            if (!$listId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID listy jest wymagane']);
                return;
            }
            
            $name = $input['name'] ?? '';
            $result = $taskList->updateList($listId, $name, $user['id']);
            echo json_encode($result);
            break;
        
        case 'DELETE':
            if (!$listId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID listy jest wymagane']);
                return;
            }
            
            if ($action === 'share') {
                $sharedUserId = $input['userId'] ?? '';
                $result = $taskList->removeShare($listId, $sharedUserId, $user['id']);
                echo json_encode($result);
            } else {
                $result = $taskList->deleteList($listId, $user['id']);
                echo json_encode($result);
            }
            break;
        
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
    }
}

// FUNKCJA: Obsługa zadań
function handleTasks($auth, $task, $taskList, $method, $input, $pathParts) {
    $auth->requireAuth();
    $user = $auth->getCurrentUser();
    
    $taskId = $pathParts[1] ?? null;
    $action = $pathParts[2] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($taskId) {
                $result = $task->getTaskWithAccess($taskId, $user['id']);
                echo json_encode($result);
            } else {
                $listId = $_GET['list_id'] ?? null;
                if (!$listId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID listy jest wymagane']);
                    return;
                }
                
                $result = $task->getListTasks($listId, $user['id']);
                echo json_encode($result);
            }
            break;
        
        case 'POST':
            if ($taskId && $action === 'toggle') {
                $result = $task->toggleTaskComplete($taskId, $user['id']);
                echo json_encode($result);
            } else {
                $listId = $input['list_id'] ?? '';
                $title = $input['title'] ?? '';
                $description = $input['description'] ?? '';
                $priority = $input['priority'] ?? 'medium';
                $deadline = $input['deadline'] ?? '';
                
                $result = $task->createTask($listId, $title, $description, $priority, $deadline, $user['id']);
                echo json_encode($result);
            }
            break;
        
        case 'PUT':
            if (!$taskId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID zadania jest wymagane']);
                return;
            }
            
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            $priority = $input['priority'] ?? 'medium';
            $deadline = $input['deadline'] ?? '';
            
            $result = $task->updateTask($taskId, $title, $description, $priority, $deadline, $user['id']);
            echo json_encode($result);
            break;
        
        case 'DELETE':
            if (!$taskId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID zadania jest wymagane']);
                return;
            }
            
            $result = $task->deleteTask($taskId, $user['id']);
            echo json_encode($result);
            break;
        
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
    }
}

// FUNKCJA: Obsługa dashboardu
function handleDashboard($auth, $task, $method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona']);
        return;
    }
    
    $auth->requireAuth();
    $user = $auth->getCurrentUser();
    
    $stats = $task->getUserTaskStats($user['id']);
    $upcoming = $task->getUpcomingTasks($user['id'], 7);
    $overdue = $task->getOverdueTasks($user['id']);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats['stats'],
        'upcoming' => $upcoming['tasks'],
        'overdue' => $overdue['tasks']
    ]);
}
?>