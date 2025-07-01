/**
 * ZenDo - JavaScript aplikacji
 */

class ZenDoApp {
    constructor() {
        // Automatycznie wykryj ścieżkę bazową aplikacji
        this.basePath = window.location.pathname.includes('/zendo/') ? '/zendo' : '';
        this.apiUrl = this.basePath + '/api';
        this.currentUser = null;
        this.currentList = null;
        this.editingTask = null;
        this.init();
    }

    async init() {
        this.setupEventListeners();
        await this.checkAuthStatus();
    }

    // === AUTORYZACJA ===

    async checkAuthStatus() {
        try {
            const response = await this.apiCall('GET', '/auth/user');
            if (response.success) {
                this.currentUser = response.user;
                this.showMainApp();
            } else {
                this.showAuthSection();
            }
        } catch (error) {
            console.error('Auth check error:', error);
            this.showAuthSection();
        }
    }

    async login(email, password) {
        try {
            console.log('Attempting login for:', email);
            
            const response = await this.apiCall('POST', '/auth/login', {
                email,
                password
            });

            console.log('Login response:', response);

            if (response.success) {
                this.currentUser = response.user;
                this.showMainApp();
                this.showNotification('Zalogowano pomyślnie!', 'success');
            } else {
                this.showNotification(response.message || 'Błąd logowania', 'error');
            }
        } catch (error) {
            console.error('Login error:', error);
            this.showNotification('Błąd podczas logowania: ' + error.message, 'error');
        }
    }

    async register(name, email, password, confirmPassword) {
        try {
            console.log('Attempting registration for:', email);
            
            const response = await this.apiCall('POST', '/auth/register', {
                name,
                email,
                password,
                confirmPassword
            });

            console.log('Register response:', response);

            if (response.success) {
                this.showNotification(response.message, 'success');
                this.switchAuthTab('login');
            } else {
                this.showNotification(response.message || 'Błąd rejestracji', 'error');
            }
        } catch (error) {
            console.error('Registration error:', error);
            this.showNotification('Błąd podczas rejestracji: ' + error.message, 'error');
        }
    }

    async logout() {
        try {
            await this.apiCall('POST', '/auth/logout');
            this.currentUser = null;
            this.currentList = null;
            this.showAuthSection();
            this.showNotification('Zostałeś wylogowany', 'info');
        } catch (error) {
            // Wyloguj lokalnie nawet jeśli API zwróci błąd
            this.currentUser = null;
            this.currentList = null;
            this.showAuthSection();
            this.showNotification('Zostałeś wylogowany', 'info');
        }
    }

    async changePassword(currentPassword, newPassword, confirmNewPassword) {
        try {
            console.log('Attempting password change...');
            
            const response = await this.apiCall('POST', '/auth/change-password', {
                currentPassword,
                newPassword,
                confirmNewPassword
            });

            console.log('Change password response:', response);

            if (response.success) {
                this.showNotification(response.message, 'success');
                this.closeChangePasswordModal();
            } else {
                this.showNotification(response.message || 'Błąd zmiany hasła', 'error');
            }
        } catch (error) {
            console.error('Change password error:', error);
            this.showNotification('Błąd podczas zmiany hasła: ' + error.message, 'error');
        }
    }

    // === ZARZĄDZANIE LISTAMI ===

    async loadUserLists() {
        try {
            const response = await this.apiCall('GET', '/lists');
            if (response.success) {
                this.renderLists(response.lists);
            }
        } catch (error) {
            this.showNotification('Błąd podczas ładowania list', 'error');
        }
    }

    async createList(name) {
        try {
            const response = await this.apiCall('POST', '/lists', { name });
            if (response.success) {
                this.showNotification(response.message, 'success');
                this.loadUserLists();
                this.closeNewListModal();
            } else {
                this.showNotification(response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Błąd podczas tworzenia listy', 'error');
        }
    }

    async deleteList(listId) {
        if (!confirm('Czy na pewno chcesz usunąć tę listę? Wszystkie zadania zostaną również usunięte.')) {
            return;
        }

        try {
            const response = await this.apiCall('DELETE', `/lists/${listId}`);
            if (response.success) {
                this.showNotification(response.message, 'success');
                this.loadUserLists();
                
                if (this.currentList && this.currentList.id == listId) {
                    this.currentList = null;
                    this.clearTasksView();
                }
            } else {
                this.showNotification(response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Błąd podczas usuwania listy', 'error');
        }
    }

    async selectList(listId) {
        try {
            const response = await this.apiCall('GET', `/lists/${listId}`);
            if (response.success) {
                this.currentList = response.list;
                this.updateTasksHeader();
                this.loadTasks();
                this.updateActiveList(listId);
            }
        } catch (error) {
            this.showNotification('Błąd podczas ładowania listy', 'error');
        }
    }

    // === ZARZĄDZANIE ZADANIAMI ===

    async loadTasks() {
        if (!this.currentList) return;

        try {
            const response = await this.apiCall('GET', `/tasks?list_id=${this.currentList.id}`);
            if (response.success) {
                this.renderTasks(response.tasks);
            }
        } catch (error) {
            this.showNotification('Błąd podczas ładowania zadań', 'error');
        }
    }

    async createTask(title, description, priority, deadline) {
        try {
            const response = await this.apiCall('POST', '/tasks', {
                list_id: this.currentList.id,
                title,
                description,
                priority,
                deadline
            });

            if (response.success) {
                this.showNotification(response.message, 'success');
                this.loadTasks();
                this.cancelTaskForm();
            } else {
                this.showNotification(response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Błąd podczas tworzenia zadania', 'error');
        }
    }

    async getTask(taskId) {
        try {
            console.log('Getting task details for:', taskId);
            
            const response = await this.apiCall('GET', `/get_task?id=${taskId}`);
            
            console.log('Get task response:', response);
            
            if (response.success) {
                return response.task;
            } else {
                this.showNotification(response.message || 'Błąd pobierania zadania', 'error');
                return null;
            }
        } catch (error) {
            console.error('Get task error:', error);
            this.showNotification('Błąd podczas pobierania zadania: ' + error.message, 'error');
            return null;
        }
    }

    async editTask(taskId, title, description, priority, deadline) {
        try {
            console.log('Attempting to edit task:', taskId);
            
            const response = await this.apiCall('POST', '/edit_task', {
                taskId: taskId,
                title,
                description,
                priority,
                deadline
            });

            console.log('Edit task response:', response);

            if (response.success) {
                this.showNotification(response.message, 'success');
                this.loadTasks();
                this.cancelTaskForm();
            } else {
                this.showNotification(response.message || 'Błąd edycji zadania', 'error');
            }
        } catch (error) {
            console.error('Edit task error:', error);
            this.showNotification('Błąd podczas edycji zadania: ' + error.message, 'error');
        }
    }

    async toggleTaskComplete(taskId) {
        try {
            const response = await this.apiCall('POST', '/tasks/toggle', {
                taskId: taskId
            });
            if (response.success) {
                this.showNotification(response.message, 'success');
                this.loadTasks();
            } else {
                this.showNotification(response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Błąd podczas zmiany statusu zadania', 'error');
        }
    }

    async deleteTask(taskId) {
        if (!confirm('Czy na pewno chcesz usunąć to zadanie?')) {
            return;
        }

        try {
            console.log('Attempting to delete task:', taskId);
            
            const response = await this.apiCall('POST', '/tasks/delete', {
                taskId: taskId
            });

            console.log('Delete task response:', response);

            if (response.success) {
                this.showNotification(response.message, 'success');
                this.loadTasks();
            } else {
                this.showNotification(response.message || 'Błąd usuwania zadania', 'error');
            }
        } catch (error) {
            console.error('Delete task error:', error);
            this.showNotification('Błąd podczas usuwania zadania: ' + error.message, 'error');
        }
    }

    // === EDYCJA ZADANIA ===

    async startEditTask(taskId) {
        console.log('Starting edit for task:', taskId);
        
        const task = await this.getTask(taskId);
        if (!task) {
            return;
        }

        console.log('Task data loaded:', task);

        this.editingTask = task;
        
        // Wypełnij formularz danymi zadania
        document.getElementById('taskTitle').value = task.title || '';
        document.getElementById('taskDescription').value = task.description || '';
        document.getElementById('taskPriority').value = task.priority || 'medium';
        
        // Formatuj datę dla input datetime-local
        if (task.deadline) {
            const deadlineDate = new Date(task.deadline);
            const formatted = deadlineDate.toISOString().slice(0, 16);
            document.getElementById('taskDeadline').value = formatted;
        } else {
            document.getElementById('taskDeadline').value = '';
        }

        // Zmień tytuł formularza i tekst przycisku
        document.getElementById('taskFormTitle').textContent = 'Edytuj zadanie';
        const submitBtn = document.getElementById('taskSubmitBtn');
        if (submitBtn) {
            submitBtn.textContent = '💾 Zapisz zmiany';
        }
        
        // Pokaż formularz
        document.getElementById('taskForm').classList.add('active');
    }

    // === RENDEROWANIE UI ===

    showAuthSection() {
        document.getElementById('authSection').style.display = 'block';
        document.getElementById('mainApp').style.display = 'none';
    }

    showMainApp() {
        document.getElementById('authSection').style.display = 'none';
        document.getElementById('mainApp').style.display = 'block';
        document.getElementById('currentUser').textContent = this.currentUser.name;
        this.loadUserLists();
    }

    renderLists(lists) {
        const container = document.getElementById('listsContainer');
        container.innerHTML = '';

        lists.forEach(list => {
            const listElement = document.createElement('div');
            listElement.className = 'list-item';
            listElement.onclick = () => this.selectList(list.id);

            const isOwner = list.user_id == this.currentUser.id;

            listElement.innerHTML = `
                <div>
                    <div class="list-name">${this.escapeHtml(list.name)}</div>
                    ${!isOwner ? `<div class="list-owner">Udostępniona przez ${this.escapeHtml(list.owner_name)}</div>` : ''}
                </div>
                <div class="list-actions">
                    ${isOwner ? `
                        <button onclick="event.stopPropagation(); app.deleteList(${list.id})" title="Usuń">🗑️</button>
                    ` : ''}
                </div>
            `;

            container.appendChild(listElement);
        });
    }

    renderTasks(tasks) {
        const container = document.getElementById('tasksContainer');

        if (tasks.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <h4>Brak zadań na tej liście</h4>
                    <p>Dodaj pierwsze zadanie, aby rozpocząć organizację swojej pracy</p>
                    <button class="btn" onclick="app.toggleTaskForm()">+ Dodaj zadanie</button>
                </div>
            `;
            return;
        }

        container.innerHTML = '';

        tasks.forEach(task => {
            const taskElement = document.createElement('div');
            taskElement.className = `task-item ${task.completed ? 'completed' : ''} ${task.priority}-priority`;

            const deadline = task.deadline ? 
                new Date(task.deadline).toLocaleString('pl-PL') : 'Brak terminu';
            const priorityText = { high: 'Wysoki', medium: 'Średni', low: 'Niski' }[task.priority];

            taskElement.innerHTML = `
                <div class="task-header">
                    <div>
                        <div class="task-title">${this.escapeHtml(task.title)}</div>
                        <div class="task-meta">
                            <span class="priority-badge priority-${task.priority}">${priorityText}</span>
                            <span>📅 ${deadline}</span>
                        </div>
                    </div>
                </div>
                ${task.description ? `<div class="task-description">${this.escapeHtml(task.description)}</div>` : ''}
                <div class="task-actions">
                    <button onclick="app.toggleTaskComplete(${task.id})" class="btn ${task.completed ? 'btn-secondary' : 'btn-success'} btn-small">
                        ${task.completed ? '↩️ Przywróć' : '✅ Zakończ'}
                    </button>
                    <button onclick="app.startEditTask(${task.id})" class="btn btn-secondary btn-small">
                        ✏️ Edytuj
                    </button>
                    <button onclick="app.deleteTask(${task.id})" class="btn btn-danger btn-small">
                        🗑️ Usuń
                    </button>
                </div>
            `;

            container.appendChild(taskElement);
        });
    }

    updateTasksHeader() {
        if (this.currentList) {
            document.getElementById('currentListTitle').textContent = this.currentList.name;
            document.getElementById('addTaskBtn').style.display = 'inline-block';
        }
    }

    updateActiveList(listId) {
        document.querySelectorAll('.list-item').forEach(item => item.classList.remove('active'));
        const activeItem = document.querySelector(`[onclick*="selectList(${listId})"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }
    }

    clearTasksView() {
        document.getElementById('currentListTitle').textContent = 'Wybierz listę';
        document.getElementById('addTaskBtn').style.display = 'none';
        document.getElementById('tasksContainer').innerHTML = `
            <div class="empty-state">
                <p>Wybierz listę z panelu bocznego, aby wyświetlić zadania</p>
            </div>
        `;
    }

    // === OBSŁUGA FORMULARZY ===

    switchAuthTab(tab) {
        document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));

        document.querySelector(`[onclick*="switchAuthTab('${tab}')"]`).classList.add('active');
        document.getElementById(tab + 'Form').classList.add('active');
    }

    toggleTaskForm() {
        const form = document.getElementById('taskForm');
        form.classList.toggle('active');

        if (!form.classList.contains('active')) {
            this.cancelTaskForm();
        }
    }

    cancelTaskForm() {
        document.getElementById('taskForm').classList.remove('active');
        document.getElementById('taskFormElement').reset();
        document.getElementById('taskFormTitle').textContent = 'Dodaj nowe zadanie';
        
        // Przywróć oryginalny tekst przycisku
        const submitBtn = document.getElementById('taskSubmitBtn');
        if (submitBtn) {
            submitBtn.textContent = '💾 Zapisz zadanie';
        }
        
        this.editingTask = null;
    }

    // === MODALNE OKNA ===

    showNewListModal() {
        document.getElementById('newListModal').classList.add('active');
    }

    closeNewListModal() {
        document.getElementById('newListModal').classList.remove('active');
        document.getElementById('newListForm').reset();
    }

    showChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.add('active');
    }

    closeChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.remove('active');
        document.getElementById('changePasswordForm').reset();
    }

    // === POWIADOMIENIA ===

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 4000);
    }

    // === POMOCNICZE FUNKCJE ===

    async apiCall(method, endpoint, data = null) {
        console.log(`API Call: ${method} ${this.apiUrl}${endpoint}`, data);
        
        // Użyj poprawnej ścieżki bazowej
        let url = this.apiUrl + endpoint;
        if (endpoint === '/auth/login') {
            url = this.basePath + '/login.php';
        } else if (endpoint === '/auth/register') {
            url = this.basePath + '/register.php';
        } else if (endpoint === '/auth/change-password') {
            url = this.basePath + '/change_password.php';
        } else if (endpoint === '/tasks/delete') {
            url = this.basePath + '/delete_task.php';
        } else if (endpoint === '/tasks/toggle') {
            url = this.basePath + '/toggle_task.php';
        } else if (endpoint === '/edit_task') {
            url = this.basePath + '/edit_task.php';
        } else if (endpoint.startsWith('/get_task')) {
            url = this.basePath + '/get_task.php' + endpoint.substring(9);
        }
        
        const config = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        if (data) {
            config.body = JSON.stringify(data);
        }

        try {
            console.log(`Trying URL: ${url}`);
            const response = await fetch(url, config);
            
            console.log(`API Response: ${response.status} ${response.statusText}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            console.log('API Result:', result);
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    setupEventListeners() {
        // Formularze autoryzacji
        document.getElementById('loginForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            this.login(email, password);
        });

        document.getElementById('registerForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const name = document.getElementById('registerName').value;
            const email = document.getElementById('registerEmail').value;
            const password = document.getElementById('registerPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            this.register(name, email, password, confirmPassword);
        });

        // Formularz nowej listy
        document.getElementById('newListForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const name = document.getElementById('listName').value;
            this.createList(name);
        });

        // Formularz zadania (obsługuje dodawanie i edycję)
        document.getElementById('taskFormElement').addEventListener('submit', (e) => {
            e.preventDefault();
            const title = document.getElementById('taskTitle').value;
            const description = document.getElementById('taskDescription').value;
            const priority = document.getElementById('taskPriority').value;
            const deadline = document.getElementById('taskDeadline').value;

            if (this.editingTask) {
                // Edycja istniejącego zadania
                this.editTask(this.editingTask.id, title, description, priority, deadline);
            } else {
                // Tworzenie nowego zadania
                this.createTask(title, description, priority, deadline);
            }
        });

        // Formularz zmiany hasła
        document.getElementById('changePasswordForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmNewPassword = document.getElementById('confirmNewPassword').value;
            this.changePassword(currentPassword, newPassword, confirmNewPassword);
        });

        // Zamykanie modali po kliknięciu w tło
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    }
}

// === GLOBALNE FUNKCJE DLA ONCLICK ===

// Inicjalizacja aplikacji
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new ZenDoApp();
});

// Funkcje dostępne globalnie
function switchAuthTab(tab) {
    app.switchAuthTab(tab);
}

function logout() {
    app.logout();
}

function showNewListModal() {
    app.showNewListModal();
}

function closeNewListModal() {
    app.closeNewListModal();
}

function showChangePasswordModal() {
    app.showChangePasswordModal();
}

function closeChangePasswordModal() {
    app.closeChangePasswordModal();
}

function toggleTaskForm() {
    app.toggleTaskForm();
}

function cancelTaskForm() {
    app.cancelTaskForm();
}

function deleteTask(taskId) {
    app.deleteTask(taskId);
}

function startEditTask(taskId) {
    app.startEditTask(taskId);
}