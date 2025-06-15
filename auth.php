<?php
/**
 * Klasa zarządzania autoryzacją użytkowników
 */

class Auth {
    private $db;

    public function __construct(Database $database) {
        $this->db = $database;
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Logowanie użytkownika
     */
    public function login($email, $password) {
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE email = ?", 
            [$email]
        );

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email']
                ]
            ];
        }

        return ['success' => false, 'message' => 'Nieprawidłowy email lub hasło'];
    }

    /**
     * Rejestracja nowego użytkownika
     */
    public function register($name, $email, $password, $confirmPassword) {
        // Walidacja danych
        if (empty($name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Wszystkie pola są wymagane'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Nieprawidłowy format email'];
        }

        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Hasła nie są zgodne'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Hasło musi mieć co najmniej 6 znaków'];
        }

        // Sprawdź czy użytkownik już istnieje
        $existingUser = $this->db->fetch(
            "SELECT id FROM users WHERE email = ?", 
            [$email]
        );

        if ($existingUser) {
            return ['success' => false, 'message' => 'Użytkownik o tym emailu już istnieje'];
        }

        // Utworz nowego użytkownika
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $this->db->query(
                "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
                [$name, $email, $hashedPassword]
            );

            return ['success' => true, 'message' => 'Konto zostało utworzone pomyślnie'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd podczas tworzenia konta'];
        }
    }

    /**
     * Resetowanie hasła
     */
    public function resetPassword($email) {
        $user = $this->db->fetch(
            "SELECT id FROM users WHERE email = ?", 
            [$email]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Nie znaleziono użytkownika o tym emailu'];
        }

        // Generuj token resetowania
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->db->query(
            "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?",
            [$token, $expires, $email]
        );

        // W rzeczywistej aplikacji tutaj wysłałbyś email z linkiem
        // Dla demonstracji zwracamy token
        return [
            'success' => true, 
            'message' => 'Link do resetowania hasła został wysłany na email',
            'token' => $token // Tylko dla demonstracji
        ];
    }

    /**
     * Zmiana hasła
     */
    public function changePassword($userId, $currentPassword, $newPassword, $confirmNewPassword) {
        if ($newPassword !== $confirmNewPassword) {
            return ['success' => false, 'message' => 'Nowe hasła nie są zgodne'];
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Nowe hasło musi mieć co najmniej 6 znaków'];
        }

        $user = $this->db->fetch(
            "SELECT password FROM users WHERE id = ?", 
            [$userId]
        );

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Obecne hasło jest nieprawidłowe'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $this->db->query(
            "UPDATE users SET password = ? WHERE id = ?",
            [$hashedPassword, $userId]
        );

        return ['success' => true, 'message' => 'Hasło zostało zmienione pomyślnie'];
    }

    /**
     * Sprawdź czy użytkownik jest zalogowany
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Pobierz dane zalogowanego użytkownika
     */
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email']
            ];
        }
        return null;
    }

    /**
     * Wylogowanie użytkownika
     */
    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Zostałeś wylogowany'];
    }

    /**
     * Middleware sprawdzający autoryzację
     */
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Wymagane logowanie']);
            exit();
        }
    }
}