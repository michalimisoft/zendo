<?php
/**
 * Klasa zarządzania zadaniami
 */

class Task {
    private $db;
    private $taskList;

    public function __construct(Database $database, TaskList $taskList) {
        $this->db = $database;
        $this->taskList = $taskList;
    }

    /**
     * Pobierz wszystkie zadania z listy
     */
    public function getListTasks($listId, $userId) {
        if (!$this->taskList->hasListAccess($listId, $userId)) {
            return ['success' => false, 'message' => 'Brak dostępu do tej listy'];
        }

        $tasks = $this->db->fetchAll(
            "SELECT * FROM tasks 
             WHERE list_id = ? 
             ORDER BY 
                completed ASC,
                CASE priority 
                    WHEN 'high' THEN 3 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 1 
                END DESC,
                deadline ASC,
                created_at DESC",
            [$listId]
        );

        foreach ($tasks as &$task) {
            if ($task['deadline']) {
                $task['deadline_formatted'] = date('Y-m-d\TH:i', strtotime($task['deadline']));
            }
            $task['created_at_formatted'] = date('d.m.Y H:i', strtotime($task['created_at']));
        }

        return ['success' => true, 'tasks' => $tasks];
    }

    /**
     * Utwórz nowe zadanie
     */
    public function createTask($listId, $title, $description, $priority, $deadline, $userId) {
        if (!$this->taskList->hasListAccess($listId, $userId)) {
            return ['success' => false, 'message' => 'Brak dostępu do tej listy'];
        }

        if (empty(trim($title))) {
            return ['success' => false, 'message' => 'Tytuł zadania nie może być pusty'];
        }

        if (!in_array($priority, ['low', 'medium', 'high'])) {
            $priority = 'medium';
        }

        $deadlineFormatted = null;
        if (!empty($deadline)) {
            $deadlineFormatted = date('Y-m-d H:i:s', strtotime($deadline));
        }

        try {
            $this->db->query(
                "INSERT INTO tasks (title, description, priority, deadline, list_id) 
                 VALUES (?, ?, ?, ?, ?)",
                [trim($title), trim($description), $priority, $deadlineFormatted, $listId]
            );

            $taskId = $this->db->lastInsertId();

            return [
                'success' => true,
                'message' => 'Zadanie zostało utworzone',
                'task_id' => $taskId
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas tworzenia zadania'];
        }
    }

    /**
     * Aktualizuj zadanie
     */
    public function updateTask($taskId, $title, $description, $priority, $deadline, $userId) {
        $task = $this->getTaskWithAccess($taskId, $userId);
        if (!$task['success']) {
            return $task;
        }

        if (empty(trim($title))) {
            return ['success' => false, 'message' => 'Tytuł zadania nie może być pusty'];
        }

        if (!in_array($priority, ['low', 'medium', 'high'])) {
            $priority = 'medium';
        }

        $deadlineFormatted = null;
        if (!empty($deadline)) {
            $deadlineFormatted = date('Y-m-d H:i:s', strtotime($deadline));
        }

        try {
            $this->db->query(
                "UPDATE tasks 
                 SET title = ?, description = ?, priority = ?, deadline = ? 
                 WHERE id = ?",
                [trim($title), trim($description), $priority, $deadlineFormatted, $taskId]
            );

            return ['success' => true, 'message' => 'Zadanie zostało zaktualizowane'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas aktualizacji zadania'];
        }
    }

    /**
     * Zmień status zadania
     */
    public function toggleTaskComplete($taskId, $userId) {
        $taskResult = $this->getTaskWithAccess($taskId, $userId);
        if (!$taskResult['success']) {
            return $taskResult;
        }

        $task = $taskResult['task'];
        $newStatus = !$task['completed'];

        try {
            $this->db->query(
                "UPDATE tasks SET completed = ? WHERE id = ?",
                [$newStatus, $taskId]
            );

            $message = $newStatus ? 'Zadanie zostało oznaczone jako ukończone' : 'Zadanie zostało przywrócone';
            return ['success' => true, 'message' => $message, 'completed' => $newStatus];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas zmiany statusu zadania'];
        }
    }

    /**
     * Usuń zadanie
     */
    public function deleteTask($taskId, $userId) {
        $taskResult = $this->getTaskWithAccess($taskId, $userId);
        if (!$taskResult['success']) {
            return $taskResult;
        }

        try {
            $this->db->query("DELETE FROM tasks WHERE id = ?", [$taskId]);
            return ['success' => true, 'message' => 'Zadanie zostało usunięte'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas usuwania zadania'];
        }
    }

    /**
     * Pobierz zadanie z sprawdzeniem dostępu
     */
    public function getTaskWithAccess($taskId, $userId) {
        $task = $this->db->fetch(
            "SELECT t.*, tl.user_id as list_owner_id
             FROM tasks t 
             JOIN task_lists tl ON t.list_id = tl.id 
             WHERE t.id = ?",
            [$taskId]
        );

        if (!$task) {
            return ['success' => false, 'message' => 'Zadanie nie zostało znalezione'];
        }

        if (!$this->taskList->hasListAccess($task['list_id'], $userId)) {
            return ['success' => false, 'message' => 'Brak dostępu do tego zadania'];
        }

        return ['success' => true, 'task' => $task];
    }

    /**
     * Pobierz zadania z nadchodzącymi terminami
     */
    public function getUpcomingTasks($userId, $days = 7) {
        $sql = "
            SELECT t.*, tl.name as list_name
            FROM tasks t
            JOIN task_lists tl ON t.list_id = tl.id
            WHERE t.completed = 0 
            AND t.deadline IS NOT NULL
            AND t.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
            AND (
                tl.user_id = ? 
                OR tl.id IN (SELECT list_id FROM list_shares WHERE user_id = ?)
            )
            ORDER BY t.deadline ASC
        ";

        $tasks = $this->db->fetchAll($sql, [$days, $userId, $userId]);

        foreach ($tasks as &$task) {
            $task['deadline_formatted'] = date('d.m.Y H:i', strtotime($task['deadline']));
            $task['days_until'] = ceil((strtotime($task['deadline']) - time()) / (60 * 60 * 24));
        }

        return ['success' => true, 'tasks' => $tasks];
    }

    /**
     * Pobierz przeterminowane zadania
     */
    public function getOverdueTasks($userId) {
        $sql = "
            SELECT t.*, tl.name as list_name
            FROM tasks t
            JOIN task_lists tl ON t.list_id = tl.id
            WHERE t.completed = 0 
            AND t.deadline IS NOT NULL
            AND t.deadline < NOW()
            AND (
                tl.user_id = ? 
                OR tl.id IN (SELECT list_id FROM list_shares WHERE user_id = ?)
            )
            ORDER BY t.deadline ASC
        ";

        $tasks = $this->db->fetchAll($sql, [$userId, $userId]);

        foreach ($tasks as &$task) {
            $task['deadline_formatted'] = date('d.m.Y H:i', strtotime($task['deadline']));
            $task['days_overdue'] = floor((time() - strtotime($task['deadline'])) / (60 * 60 * 24));
        }

        return ['success' => true, 'tasks' => $tasks];
    }

    /**
     * Pobierz statystyki zadań użytkownika
     */
    public function getUserTaskStats($userId) {
        $sql = "
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN completed = 0 AND deadline < NOW() THEN 1 ELSE 0 END) as overdue_tasks,
                SUM(CASE WHEN completed = 0 AND deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as upcoming_tasks
            FROM tasks t
            JOIN task_lists tl ON t.list_id = tl.id
            WHERE tl.user_id = ? 
            OR tl.id IN (SELECT list_id FROM list_shares WHERE user_id = ?)
        ";

        $stats = $this->db->fetch($sql, [$userId, $userId]);

        return ['success' => true, 'stats' => $stats];
    }
}
?>