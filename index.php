<?php
/**
 * ZenDo - Główny plik aplikacji
 */

session_start();

// Konfiguracja
define('APP_NAME', 'ZenDo');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/');

// Poprawiony autoloader klas
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/classes/' . $class . '.php',
        __DIR__ . '/classes/' . strtolower($class) . '.php',
        __DIR__ . '/config/' . $class . '.php',
        __DIR__ . '/config/' . strtolower($class) . '.php'
    ];
    
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    // Debug - pokaż co próbujemy załadować
    error_log("Autoloader: Nie można znaleźć klasy '$class'. Sprawdzono ścieżki: " . implode(', ', $paths));
});

// Test autoloadera - sprawdź czy klasy są dostępne
if (!class_exists('Database')) {
    die('Błąd: Klasa Database nie została znaleziona. Sprawdź czy plik config/database.php istnieje.');
}

// Inicjalizacja bazy danych i klas
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    die('Błąd inicjalizacji aplikacji: ' . $e->getMessage());
}

// Sprawdź połączenie z bazą danych
try {
    $db->query("SELECT 1");
} catch (Exception $e) {
    die('Błąd połączenia z bazą danych. Sprawdź konfigurację w config/database.php. Błąd: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ZenDo - Twoje narzędzie do zarządzania zadaniami i listami TODO">
    <meta name="keywords" content="todo, zadania, organizacja, produktywność, lista zadań">
    <meta name="author" content="Diana Holubieva, Michał Kościelniak, Dawid Ćwiok">
    
    <title><?= APP_NAME ?> - Zarządzanie Zadaniami</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#667eea">
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ZenDo">
    
    <!-- Open Graph -->
    <meta property="og:title" content="ZenDo - Zarządzanie Zadaniami">
    <meta property="og:description" content="Funkcjonalna aplikacja do zarządzania zadaniami i listami TODO">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= BASE_URL ?>">
    
    <!-- Preload ważnych zasobów -->
    <link rel="preload" href="assets/js/app.js" as="script">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>ZenDo</h1>
            <p>Twoje narzędzie do zarządzania zadaniami - v1.0</p>
        </div>

        <!-- Sekcja autoryzacji -->
        <div class="auth-section" id="authSection">
            <div class="auth-tabs">
                <button class="auth-tab active" onclick="switchAuthTab('login')">Logowanie</button>
                <button class="auth-tab" onclick="switchAuthTab('register')">Rejestracja</button>
                <button class="auth-tab" onclick="switchAuthTab('reset')">Resetuj hasło</button>
            </div>

            <!-- Formularz logowania -->
            <form class="auth-form active" id="loginForm">
                <div class="form-group">
                    <label for="loginEmail">Email:</label>
                    <input type="email" id="loginEmail" required autocomplete="email" 
                           placeholder="Wprowadź swój email">
                </div>
                <div class="form-group">
                    <label for="loginPassword">Hasło:</label>
                    <input type="password" id="loginPassword" required autocomplete="current-password"
                           placeholder="Wprowadź hasło">
                </div>
                <button type="submit" class="btn">Zaloguj się</button>
                
                <!-- Przykładowe dane testowe -->
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 0.9rem;">
                    <strong>Przykładowe konta testowe:</strong><br>
                    <code>diana@example.com</code> / <code>haslo123</code><br>
                    <code>michal@example.com</code> / <code>haslo123</code><br>
                    <code>dawid@example.com</code> / <code>haslo123</code>
                </div>
            </form>

            <!-- Formularz rejestracji -->
            <form class="auth-form" id="registerForm">
                <div class="form-group">
                    <label for="registerName">Imię i nazwisko:</label>
                    <input type="text" id="registerName" required autocomplete="name"
                           placeholder="Wprowadź imię i nazwisko">
                </div>
                <div class="form-group">
                    <label for="registerEmail">Email:</label>
                    <input type="email" id="registerEmail" required autocomplete="email"
                           placeholder="Wprowadź adres email">
                </div>
                <div class="form-group">
                    <label for="registerPassword">Hasło:</label>
                    <input type="password" id="registerPassword" required autocomplete="new-password"
                           placeholder="Wprowadź hasło (min. 6 znaków)">
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Potwierdź hasło:</label>
                    <input type="password" id="confirmPassword" required autocomplete="new-password"
                           placeholder="Potwierdź hasło">
                </div>
                <button type="submit" class="btn">Zarejestruj się</button>
            </form>

            <!-- Formularz resetowania hasła -->
            <form class="auth-form" id="resetForm">
                <div class="form-group">
                    <label for="resetEmail">Email:</label>
                    <input type="email" id="resetEmail" required autocomplete="email"
                           placeholder="Wprowadź adres email">
                </div>
                <button type="submit" class="btn">Wyślij link resetujący</button>
                <p style="margin-top: 15px; font-size: 0.9rem; color: #666;">
                    Otrzymasz email z instrukcjami resetowania hasła.
                </p>
            </form>
        </div>

        <!-- Główna aplikacja -->
        <div class="main-app" id="mainApp">
            <!-- Header aplikacji -->
            <div class="app-header">
                <div class="user-info">
                    <h2>Witaj, <span id="currentUser">Użytkowniku</span>!</h2>
                </div>
                <div class="user-actions">
                    <button class="btn btn-secondary" onclick="showChangePasswordModal()">
                        🔒 Zmień hasło
                    </button>
                    <button class="btn btn-danger" onclick="logout()">
                        🚪 Wyloguj
                    </button>
                </div>
            </div>

            <!-- Sekcja list i zadań -->
            <div class="lists-section">
                <!-- Sidebar z listami -->
                <div class="lists-sidebar">
                    <h3>📋 Moje listy</h3>
                    <div id="listsContainer">
                        <!-- Listy będą ładowane dynamicznie -->
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Ładowanie list...</p>
                        </div>
                    </div>
                    <button class="btn" onclick="showNewListModal()" style="width: 100%; margin-top: 20px;">
                        ➕ Nowa lista
                    </button>
                </div>

                <!-- Sekcja zadań -->
                <div class="tasks-section">
                    <div class="tasks-header">
                        <h3 id="currentListTitle">Wybierz listę</h3>
                        <div class="header-actions">
                            <button class="btn" onclick="toggleTaskForm()" id="addTaskBtn" style="display: none;">
                                ➕ Dodaj zadanie
                            </button>
                            <button class="btn btn-secondary" onclick="toggleShareSection()" id="shareListBtn" style="display: none;">
                                👥 Udostępnij listę
                            </button>
                        </div>
                    </div>

                    <!-- Formularz dodawania/edycji zadania -->
                    <div class="task-form" id="taskForm">
                        <h4 id="taskFormTitle">Dodaj nowe zadanie</h4>
                        <form id="taskFormElement">
                            <div class="task-form-row">
                                <div class="form-group">
                                    <label for="taskTitle">Tytuł zadania:</label>
                                    <input type="text" id="taskTitle" required 
                                           placeholder="Wprowadź tytuł zadania">
                                </div>
                                <div class="form-group">
                                    <label for="taskPriority">Priorytet:</label>
                                    <select id="taskPriority">
                                        <option value="low">🟢 Niski</option>
                                        <option value="medium" selected>🟡 Średni</option>
                                        <option value="high">🔴 Wysoki</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="taskDeadline">Termin wykonania:</label>
                                    <input type="datetime-local" id="taskDeadline">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="taskDescription">Opis zadania:</label>
                                <textarea id="taskDescription" rows="3" 
                                         placeholder="Dodaj szczegóły zadania (opcjonalne)"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success" id="taskSubmitBtn">💾 Zapisz zadanie</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelTaskForm()">
                                    ❌ Anuluj
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Sekcja udostępniania -->
                    <div class="share-section" id="shareSection">
                        <h4>👥 Udostępnij listę</h4>
                        <div class="form-group">
                            <label for="shareEmail">Email użytkownika:</label>
                            <input type="email" id="shareEmail" 
                                   placeholder="wprowadź email użytkownika...">
                        </div>
                        <button class="btn" onclick="shareList()">📤 Udostępnij</button>
                        
                        <h5 style="margin-top: 20px; margin-bottom: 10px;">Udostępniona dla:</h5>
                        <div class="shared-users" id="sharedUsers">
                            <!-- Udostępnieni użytkownicy będą wyświetlani tutaj -->
                        </div>
                    </div>

                    <!-- Kontener na zadania -->
                    <div id="tasksContainer" class="tasks-container">
                        <div class="empty-state">
                            <h4>🎯 Rozpocznij organizację</h4>
                            <p>Wybierz listę z panelu bocznego, aby wyświetlić zadania, lub utwórz nową listę.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal dla nowej listy -->
    <div class="modal" id="newListModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeNewListModal()">&times;</span>
            <h3>📋 Utwórz nową listę</h3>
            <form id="newListForm">
                <div class="form-group">
                    <label for="listName">Nazwa listy:</label>
                    <input type="text" id="listName" required 
                           placeholder="np. Praca, Dom, Zakupy..." maxlength="100">
                </div>
                <button type="submit" class="btn">✅ Utwórz listę</button>
            </form>
        </div>
    </div>

    <!-- Modal zmiany hasła -->
    <div class="modal" id="changePasswordModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeChangePasswordModal()">&times;</span>
            <h3>🔒 Zmień hasło</h3>
            <form id="changePasswordForm">
                <div class="form-group">
                    <label for="currentPassword">Obecne hasło:</label>
                    <input type="password" id="currentPassword" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="newPassword">Nowe hasło:</label>
                    <input type="password" id="newPassword" required autocomplete="new-password"
                           placeholder="Min. 6 znaków">
                </div>
                <div class="form-group">
                    <label for="confirmNewPassword">Potwierdź nowe hasło:</label>
                    <input type="password" id="confirmNewPassword" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn">💾 Zmień hasło</button>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
    
    <!-- Dodatkowe informacje o aplikacji -->
    <script>
        // Informacje o wersji i autorach
        console.log('%cZenDo v<?= APP_VERSION ?>', 'color: #667eea; font-size: 16px; font-weight: bold;');
        console.log('Autorzy: Diana Holubieva, Michał Kościelniak, Dawid Ćwiok');
        console.log('Technologie: PHP 8+, MySQL, HTML5, CSS3, JavaScript');
        
        // Sprawdź wsparcie dla Service Worker
        if ('serviceWorker' in navigator) {
            console.log('Service Worker jest wspierany');
        }
        
        // Informacje o przeglądarce dla debugowania
        console.log('User Agent:', navigator.userAgent);
        console.log('Online:', navigator.onLine);
    </script>
    
    <!-- Google Analytics lub inne narzędzia analityczne (opcjonalne) -->
    <!-- 
    <script async src="https://www.googletagmanager.com/gtag/js?id=GA_TRACKING_ID"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'GA_TRACKING_ID');
    </script>
    -->
</body>
</html>