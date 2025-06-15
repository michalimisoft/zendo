<?php
/**
 * Klasa zarządzania listami zadań
 */

class TaskList {
    private $db;

    public function __construct(Database $database) {
        $this->db = $database;
    }

    /**
     * Pobierz wszystkie listy użytkownika (własne i udostępnione)
     */
    public function getUserLists($userId) {
        $sql = "
            SELECT 
                tl.*,
                u.name as owner_name,
                CASE WHEN tl.user_id = ? THEN 1 ELSE 0 END as is_owner
            FROM task_lists tl
            JOIN users u ON tl.user_id = u.id
            WHERE tl.user_id = ? 
            OR tl.id IN (
                SELECT list_id FROM list_shares WHERE user_id = ?
            )
            ORDER BY is_owner DESC, tl.name ASC
        ";

        return $this->db->fetchAll($sql, [$userId, $userId, $userId]);
    }

    /**
     * Utwórz nową listę
     */
    public function createList($name, $userId) {
        if (empty(trim($name))) {
            return ['success' => false, 'message' => 'Nazwa listy nie może być pusta'];
        }

        try {
            $this->db->query(
                "INSERT INTO task_lists (name, user_id) VALUES (?, ?)",
                [trim($name), $userId]
            );

            $listId = $this->db->lastInsertId();
            
            return [
                'success' => true, 
                'message' => 'Lista została utworzona',
                'list_id' => $listId
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas tworzenia listy'];
        }
    }

    /**
     * Aktualizuj listę
     */
    public function updateList($listId, $name, $userId) {
        if (empty(trim($name))) {
            return ['success' => false, 'message' => 'Nazwa listy nie może być pusta'];
        }

        // Sprawdź czy użytkownik jest właścicielem listy
        if (!$this->isListOwner($listId, $userId)) {
            return ['success' => false, 'message' => 'Brak uprawnień do edycji tej listy'];
        }

        try {
            $this->db->query(
                "UPDATE task_lists SET name = ? WHERE id = ? AND user_id = ?",
                [trim($name), $listId, $userId]
            );

            return ['success' => true, 'message' => 'Lista została zaktualizowana'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas aktualizacji listy'];
        }
    }

    /**
     * Usuń listę
     */
    public function deleteList($listId, $userId) {
        // Sprawdź czy użytkownik jest właścicielem listy
        if (!$this->isListOwner($listId, $userId)) {
            return ['success' => false, 'message' => 'Brak uprawnień do usunięcia tej listy'];
        }

        try {
            $this->db->beginTransaction();

            // Usuń udostępnienia
            $this->db->query(
                "DELETE FROM list_shares WHERE list_id = ?",
                [$listId]
            );

            // Usuń zadania (CASCADE powinno to zrobić automatycznie)
            $this->db->query(
                "DELETE FROM tasks WHERE list_id = ?",
                [$listId]
            );

            // Usuń listę
            $this->db->query(
                "DELETE FROM task_lists WHERE id = ? AND user_id = ?",
                [$listId, $userId]
            );

            $this->db->commit();

            return ['success' => true, 'message' => 'Lista została usunięta'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Błąd podczas usuwania listy'];
        }
    }

    /**
     * Udostępnij listę użytkownikowi
     */
    public function shareList($listId, $email, $ownerId) {
        // Sprawdź czy użytkownik jest właścicielem listy
        if (!$this->isListOwner($listId, $ownerId)) {
            return ['success' => false, 'message' => 'Brak uprawnień do udostępnienia tej listy'];
        }

        // Znajdź użytkownika po emailu
        $user = $this->db->fetch(
            "SELECT id, name FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Nie znaleziono użytkownika o tym emailu'];
        }

        if ($user['id'] == $ownerId) {
            return ['success' => false, 'message' => 'Nie możesz udostępnić listy samemu sobie'];
        }

        // Sprawdź czy lista nie jest już udostępniona temu użytkownikowi
        $existingShare = $this->db->fetch(
            "SELECT id FROM list_shares WHERE list_id = ? AND user_id = ?",
            [$listId, $user['id']]
        );

        if ($existingShare) {
            return ['success' => false, 'message' => 'Lista jest już udostępniona temu użytkownikowi'];
        }

        try {
            $this->db->query(
                "INSERT INTO list_shares (list_id, user_id) VALUES (?, ?)",
                [$listId, $user['id']]
            );

            return [
                'success' => true, 
                'message' => "Lista została udostępniona użytkownikowi {$user['name']}",
                'user' => $user
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas udostępniania listy'];
        }
    }

    /**
     * Usuń udostępnienie listy
     */
    public function removeShare($listId, $sharedUserId, $ownerId) {
        // Sprawdź czy użytkownik jest właścicielem listy
        if (!$this->isListOwner($listId, $ownerId)) {
            return ['success' => false, 'message' => 'Brak uprawnień'];
        }

        try {
            $this->db->query(
                "DELETE FROM list_shares WHERE list_id = ? AND user_id = ?",
                [$listId, $sharedUserId]
            );

            return ['success' => true, 'message' => 'Udostępnienie zostało usunięte'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas usuwania udostępnienia'];
        }
    }

    /**
     * Pobierz użytkowników z dostępem do listy
     */
    public function getListShares($listId, $userId) {
        // Sprawdź czy użytkownik ma dostęp do listy
        if (!$this->hasListAccess($listId, $userId)) {
            return ['success' => false, 'message' => 'Brak dostępu do tej listy'];
        }

        $shares = $this->db->fetchAll(
            "SELECT u.id, u.name, u.email 
             FROM list_shares ls 
             JOIN users u ON ls.user_id = u.id 
             WHERE ls.list_id = ?
             ORDER BY u.name",
            [$listId]
        );

        return ['success' => true, 'shares' => $shares];
    }

    /**
     * Sprawdź czy użytkownik jest właścicielem listy
     */
    public function isListOwner($listId, $userId) {
        $list = $this->db->fetch(
            "SELECT user_id FROM task_lists WHERE id = ?",
            [$listId]
        );

        return $list && $list['user_id'] == $userId;
    }

    /**
     * Sprawdź czy użytkownik ma dostęp do listy (właściciel lub udostępniona)
     */
    public function hasListAccess($listId, $userId) {
        $sql = "
            SELECT COUNT(*) as count FROM task_lists 
            WHERE id = ? AND (
                user_id = ? 
                OR id IN (SELECT list_id FROM list_shares WHERE user_id = ?)
            )
        ";

        $result = $this->db->fetch($sql, [$listId, $userId, $userId]);
        return $result['count'] > 0;
    }

    /**
     * Pobierz szczegóły listy
     */
    public function getListDetails($listId, $userId) {
        if (!$this->hasListAccess($listId, $userId)) {
            return ['success' => false, 'message' => 'Brak dostępu do tej listy'];
        }

        $list = $this->db->fetch(
            "SELECT tl.*, u.name as owner_name 
             FROM task_lists tl 
             JOIN users u ON tl.user_id = u.id 
             WHERE tl.id = ?",
            [$listId]
        );

        if (!$list) {
            return ['success' => false, 'message' => 'Lista nie została znaleziona'];
        }

        return [
            'success' => true, 
            'list' => $list,
            'is_owner' => $list['user_id'] == $userId
        ];
    }
}