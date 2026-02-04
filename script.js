// Global variables
let tasks = JSON.parse(localStorage.getItem('tasks')) || [];
let categories = JSON.parse(localStorage.getItem('categories')) || [];
let timeRecords = JSON.parse(localStorage.getItem('timeRecords')) || [];
let notes = JSON.parse(localStorage.getItem('notes')) || [];

// Timer variables
let timerInterval = null;
let startTime = null;
let elapsedTime = 0;
let isRunning = false;

// DOM Elements
const tabBtns = document.querySelectorAll('.tab-btn');
const tabContents = document.querySelectorAll('.tab-content');
const clockElement = document.getElementById('clock');

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    setupEventListeners();
    updateClock();
    setInterval(updateClock, 1000);
    renderDashboard();
    renderTasks();
    renderCategories();
    renderTimeRecords();
    renderNotes();
    populateCategorySelectors();
    updateStats();
}

function setupEventListeners() {
    // Tab navigation
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    // Task management
    document.getElementById('add-task-btn').addEventListener('click', openTaskModal);
    document.getElementById('task-form').addEventListener('submit', saveTask);
    document.getElementById('category-filter').addEventListener('change', renderTasks);
    document.getElementById('priority-filter').addEventListener('change', renderTasks);
    document.getElementById('search-tasks').addEventListener('input', renderTasks);

    // Category management
    document.getElementById('add-category-btn').addEventListener('click', openCategoryModal);
    document.getElementById('category-form').addEventListener('submit', saveCategory);

    // Timer controls
    document.getElementById('start-timer').addEventListener('click', startTimer);
    document.getElementById('pause-timer').addEventListener('click', pauseTimer);
    document.getElementById('stop-timer').addEventListener('click', stopTimer);
    document.getElementById('record-time').addEventListener('click', recordTime);
    document.getElementById('save-record').addEventListener('click', saveTimeRecord);

    // Notes management
    document.getElementById('add-note-btn').addEventListener('click', openNoteModal);
    document.getElementById('note-form').addEventListener('submit', saveNote);

    // Reports
    document.getElementById('generate-report').addEventListener('click', generateReport);
    document.getElementById('export-pdf').addEventListener('click', exportPDF);
    document.getElementById('export-excel').addEventListener('click', exportExcel);

    // Modal close buttons
    document.querySelectorAll('.close').forEach(closeBtn => {
        closeBtn.addEventListener('click', closeModal);
    });
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target);
        }
    });
}

// Clock functionality
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    clockElement.textContent = timeString;
}

// Tab switching
function switchTab(tabName) {
    // Update active tab button
    tabBtns.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });

    // Show active tab content
    tabContents.forEach(content => {
        content.classList.toggle('active', content.id === tabName);
    });

    // Refresh content if needed
    switch(tabName) {
        case 'dashboard':
            renderDashboard();
            break;
        case 'tasks':
            renderTasks();
            break;
        case 'categories':
            renderCategories();
            break;
        case 'timer':
            renderTimeRecords();
            break;
        case 'notes':
            renderNotes();
            break;
    }
}

// Task management functions
function renderTasks() {
    const tasksList = document.getElementById('tasks-list');
    const categoryFilter = document.getElementById('category-filter').value;
    const priorityFilter = document.getElementById('priority-filter').value;
    const searchQuery = document.getElementById('search-tasks').value.toLowerCase();

    tasksList.innerHTML = '';

    let filteredTasks = tasks.filter(task => {
        const matchesCategory = !categoryFilter || task.categoryId === categoryFilter;
        const matchesPriority = !priorityFilter || task.priority === priorityFilter;
        const matchesSearch = task.name.toLowerCase().includes(searchQuery) || 
                             task.description.toLowerCase().includes(searchQuery);
        
        return matchesCategory && matchesPriority && matchesSearch;
    });

    filteredTasks.forEach(task => {
        const category = categories.find(cat => cat.id === task.categoryId);
        const row = document.createElement('tr');
        
        row.innerHTML = `
            <td>
                <input type="checkbox" ${task.completed ? 'checked' : ''} 
                       onchange="toggleTaskStatus(${task.id})" class="task-checkbox">
                <span class="${task.completed ? 'status-completed' : 'status-pending'}">${task.name}</span>
            </td>
            <td>${category ? category.name : 'Uncategorized'}</td>
            <td><span class="priority-${task.priority}">${task.priority.charAt(0).toUpperCase() + task.priority.slice(1)}</span></td>
            <td>${task.dueDate || 'No due date'}</td>
            <td>${task.completed ? 'Completed' : 'Pending'}</td>
            <td class="actions-cell">
                <button class="btn-primary" onclick="openTaskModal(${task.id})">Edit</button>
                <button class="btn-danger" onclick="deleteTask(${task.id})">Delete</button>
            </td>
        `;
        
        tasksList.appendChild(row);
    });
}

function openTaskModal(taskId = null) {
    const modal = document.getElementById('task-modal');
    const form = document.getElementById('task-form');
    
    if (taskId) {
        const task = tasks.find(t => t.id === taskId);
        if (task) {
            document.getElementById('task-id').value = task.id;
            document.getElementById('task-name').value = task.name;
            document.getElementById('task-category').value = task.categoryId;
            document.getElementById('task-priority').value = task.priority;
            document.getElementById('task-due-date').value = task.dueDate || '';
            document.getElementById('task-description').value = task.description || '';
        }
    } else {
        form.reset();
        document.getElementById('task-id').value = '';
    }
    
    modal.style.display = 'block';
}

function saveTask(e) {
    e.preventDefault();
    
    const taskId = document.getElementById('task-id').value;
    const taskData = {
        id: taskId ? parseInt(taskId) : Date.now(),
        name: document.getElementById('task-name').value,
        categoryId: document.getElementById('task-category').value,
        priority: document.getElementById('task-priority').value,
        dueDate: document.getElementById('task-due-date').value,
        description: document.getElementById('task-description').value,
        completed: taskId ? tasks.find(t => t.id === parseInt(taskId)).completed : false
    };
    
    if (taskId) {
        // Update existing task
        const index = tasks.findIndex(t => t.id === parseInt(taskId));
        if (index !== -1) {
            tasks[index] = taskData;
        }
    } else {
        // Add new task
        tasks.push(taskData);
    }
    
    localStorage.setItem('tasks', JSON.stringify(tasks));
    closeModal(document.getElementById('task-modal'));
    renderTasks();
    updateStats();
}

function toggleTaskStatus(taskId) {
    const task = tasks.find(t => t.id === taskId);
    if (task) {
        task.completed = !task.completed;
        localStorage.setItem('tasks', JSON.stringify(tasks));
        renderTasks();
        updateStats();
    }
}

function deleteTask(taskId) {
    if (confirm('Are you sure you want to delete this task?')) {
        tasks = tasks.filter(task => task.id !== taskId);
        localStorage.setItem('tasks', JSON.stringify(tasks));
        renderTasks();
        updateStats();
    }
}

// Category management functions
function renderCategories() {
    const categoriesList = document.getElementById('categories-list');
    
    categoriesList.innerHTML = '';

    categories.forEach(category => {
        const row = document.createElement('tr');
        
        row.innerHTML = `
            <td>${category.name}</td>
            <td>${category.description || ''}</td>
            <td>${category.goal || ''}</td>
            <td>${new Date(category.createdAt).toLocaleDateString()}</td>
            <td class="actions-cell">
                <button class="btn-primary" onclick="openCategoryModal(${category.id})">Edit</button>
                <button class="btn-danger" onclick="deleteCategory(${category.id})">Delete</button>
            </td>
        `;
        
        categoriesList.appendChild(row);
    });
}

function openCategoryModal(categoryId = null) {
    const modal = document.getElementById('category-modal');
    const form = document.getElementById('category-form');
    
    if (categoryId) {
        const category = categories.find(c => c.id === categoryId);
        if (category) {
            document.getElementById('category-id').value = category.id;
            document.getElementById('category-name').value = category.name;
            document.getElementById('category-description').value = category.description || '';
            document.getElementById('category-goal').value = category.goal || '';
        }
    } else {
        form.reset();
        document.getElementById('category-id').value = '';
    }
    
    modal.style.display = 'block';
}

function saveCategory(e) {
    e.preventDefault();
    
    const categoryId = document.getElementById('category-id').value;
    const categoryData = {
        id: categoryId ? parseInt(categoryId) : Date.now(),
        name: document.getElementById('category-name').value,
        description: document.getElementById('category-description').value,
        goal: document.getElementById('category-goal').value,
        createdAt: categoryId ? categories.find(c => c.id === parseInt(categoryId)).createdAt : new Date().toISOString()
    };
    
    if (categoryId) {
        // Update existing category
        const index = categories.findIndex(c => c.id === parseInt(categoryId));
        if (index !== -1) {
            categories[index] = categoryData;
        }
    } else {
        // Add new category
        categories.push(categoryData);
    }
    
    localStorage.setItem('categories', JSON.stringify(categories));
    closeModal(document.getElementById('category-modal'));
    renderCategories();
    populateCategorySelectors();
    updateStats();
    renderTasks(); // Re-render tasks since categories changed
}

function deleteCategory(categoryId) {
    if (confirm('Are you sure you want to delete this category? All associated tasks will remain but become uncategorized.')) {
        // Remove the category
        categories = categories.filter(category => category.id !== categoryId);
        
        // Update tasks that used this category to have no category
        tasks = tasks.map(task => {
            if (task.categoryId == categoryId) {
                return {...task, categoryId: ''};
            }
            return task;
        });
        
        localStorage.setItem('categories', JSON.stringify(categories));
        localStorage.setItem('tasks', JSON.stringify(tasks));
        renderCategories();
        populateCategorySelectors();
        updateStats();
        renderTasks();
    }
}

function populateCategorySelectors() {
    const categorySelects = [
        document.getElementById('task-category'),
        document.getElementById('time-category'),
        document.getElementById('category-filter')
    ];
    
    categorySelects.forEach(select => {
        if (!select) return;
        
        // Save current selection if possible
        const currentSelection = select.value;
        
        // Clear options except the first one (placeholder)
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        // Add new options
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            select.appendChild(option);
        });
        
        // Restore selection if it still exists
        if (currentSelection) {
            select.value = currentSelection;
        }
    });
}

// Timer functions
function startTimer() {
    if (isRunning) return;
    
    isRunning = true;
    startTime = new Date().getTime() - elapsedTime;
    
    timerInterval = setInterval(function() {
        const currentTime = new Date().getTime();
        elapsedTime = currentTime - startTime;
        
        updateTimerDisplay();
    }, 10);
}

function pauseTimer() {
    if (!isRunning) return;
    
    isRunning = false;
    clearInterval(timerInterval);
}

function stopTimer() {
    isRunning = false;
    clearInterval(timerInterval);
    elapsedTime = 0;
    updateTimerDisplay();
}

function updateTimerDisplay() {
    const totalSeconds = Math.floor(elapsedTime / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    document.getElementById('timer-hours').textContent = hours.toString().padStart(2, '0');
    document.getElementById('timer-minutes').textContent = minutes.toString().padStart(2, '0');
    document.getElementById('timer-seconds').textContent = seconds.toString().padStart(2, '0');
}

function recordTime() {
    if (elapsedTime === 0) return;
    
    const hours = Math.floor(elapsedTime / 3600000);
    const minutes = Math.floor((elapsedTime % 3600000) / 60000);
    const seconds = Math.floor((elapsedTime % 60000) / 1000);
    
    const durationString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    document.getElementById('task-title').value = `Recorded: ${durationString}`;
    document.getElementById('record-time').disabled = true;
}

function saveTimeRecord() {
    const title = document.getElementById('task-title').value.trim();
    const categoryId = document.getElementById('time-category').value;
    
    if (!title) {
        alert('Please enter a title for this time record');
        return;
    }
    
    const record = {
        id: Date.now(),
        title: title,
        categoryId: categoryId,
        duration: elapsedTime,
        date: new Date().toISOString()
    };
    
    timeRecords.push(record);
    localStorage.setItem('timeRecords', JSON.stringify(timeRecords));
    
    // Reset form and timer
    document.getElementById('task-title').value = '';
    document.getElementById('time-category').value = '';
    stopTimer();
    document.getElementById('record-time').disabled = false;
    
    renderTimeRecords();
}

function renderTimeRecords() {
    const recordsList = document.getElementById('records-list');
    
    recordsList.innerHTML = '';

    // Sort records by date (newest first)
    const sortedRecords = [...timeRecords].sort((a, b) => new Date(b.date) - new Date(a.date));
    
    sortedRecords.forEach(record => {
        const category = categories.find(cat => cat.id === record.categoryId);
        const durationInMs = record.duration;
        const hours = Math.floor(durationInMs / 3600000);
        const minutes = Math.floor((durationInMs % 3600000) / 60000);
        const seconds = Math.floor((durationInMs % 60000) / 1000);
        
        const durationString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        const dateString = new Date(record.date).toLocaleDateString();
        
        const row = document.createElement('tr');
        
        row.innerHTML = `
            <td>${record.title}</td>
            <td>${category ? category.name : 'Uncategorized'}</td>
            <td>${durationString}</td>
            <td>${dateString}</td>
            <td class="actions-cell">
                <button class="btn-danger" onclick="deleteTimeRecord(${record.id})">Delete</button>
            </td>
        `;
        
        recordsList.appendChild(row);
    });
}

function deleteTimeRecord(recordId) {
    if (confirm('Are you sure you want to delete this time record?')) {
        timeRecords = timeRecords.filter(record => record.id !== recordId);
        localStorage.setItem('timeRecords', JSON.stringify(timeRecords));
        renderTimeRecords();
    }
}

// Notes functions
function renderNotes() {
    const notesList = document.getElementById('notes-list');
    
    notesList.innerHTML = '';

    // Sort notes by date (newest first)
    const sortedNotes = [...notes].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    
    sortedNotes.forEach(note => {
        const noteCard = document.createElement('div');
        noteCard.className = 'note-card';
        
        noteCard.innerHTML = `
            <h4>${note.title}</h4>
            <div class="note-content">${note.content.replace(/\n/g, '<br>')}</div>
            <div class="note-meta">
                Created: ${new Date(note.createdAt).toLocaleString()}
                <button class="btn-primary" onclick="openNoteModal(${note.id})" style="margin-left: 10px;">Edit</button>
                <button class="btn-danger" onclick="deleteNote(${note.id})">Delete</button>
            </div>
        `;
        
        notesList.appendChild(noteCard);
    });
}

function openNoteModal(noteId = null) {
    const modal = document.getElementById('note-modal');
    const form = document.getElementById('note-form');
    
    if (noteId) {
        const note = notes.find(n => n.id === noteId);
        if (note) {
            document.getElementById('note-id').value = note.id;
            document.getElementById('note-title').value = note.title;
            document.getElementById('note-content').value = note.content;
        }
    } else {
        form.reset();
        document.getElementById('note-id').value = '';
    }
    
    modal.style.display = 'block';
}

function saveNote(e) {
    e.preventDefault();
    
    const noteId = document.getElementById('note-id').value;
    const noteData = {
        id: noteId ? parseInt(noteId) : Date.now(),
        title: document.getElementById('note-title').value,
        content: document.getElementById('note-content').value,
        createdAt: noteId ? notes.find(n => n.id === parseInt(noteId)).createdAt : new Date().toISOString()
    };
    
    if (noteId) {
        // Update existing note
        const index = notes.findIndex(n => n.id === parseInt(noteId));
        if (index !== -1) {
            notes[index] = noteData;
        }
    } else {
        // Add new note
        notes.push(noteData);
    }
    
    localStorage.setItem('notes', JSON.stringify(notes));
    closeModal(document.getElementById('note-modal'));
    renderNotes();
}

function deleteNote(noteId) {
    if (confirm('Are you sure you want to delete this note?')) {
        notes = notes.filter(note => note.id !== noteId);
        localStorage.setItem('notes', JSON.stringify(notes));
        renderNotes();
    }
}

// Dashboard functions
function renderDashboard() {
    renderTodayTasks();
    renderProgressChart();
    renderTimeSummary();
    updateStats();
}

function renderTodayTasks() {
    const todayTasksList = document.getElementById('today-tasks-list');
    const today = new Date().toISOString().split('T')[0];
    
    const todayTasks = tasks.filter(task => task.dueDate === today);
    
    if (todayTasks.length === 0) {
        todayTasksList.innerHTML = '<p>No tasks scheduled for today.</p>';
        return;
    }
    
    const ul = document.createElement('ul');
    
    todayTasks.forEach(task => {
        const li = document.createElement('li');
        li.innerHTML = `
            <input type="checkbox" ${task.completed ? 'checked' : ''} 
                   onchange="toggleTaskStatus(${task.id})">
            <span class="${task.completed ? 'status-completed' : 'status-pending'}">${task.name}</span>
        `;
        ul.appendChild(li);
    });
    
    todayTasksList.innerHTML = '';
    todayTasksList.appendChild(ul);
}

function renderProgressChart() {
    const ctx = document.getElementById('progress-chart').getContext('2d');
    
    // Count completed vs pending tasks
    const completedCount = tasks.filter(task => task.completed).length;
    const pendingCount = tasks.length - completedCount;
    
    // Destroy previous chart if it exists
    if (window.progressChart) {
        window.progressChart.destroy();
    }
    
    window.progressChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Pending'],
            datasets: [{
                data: [completedCount, pendingCount],
                backgroundColor: [
                    '#2ecc71',
                    '#e74c3c'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function renderTimeSummary() {
    const timeSummaryDiv = document.getElementById('time-summary');
    
    // Calculate total time spent
    const totalTimeMs = timeRecords.reduce((sum, record) => sum + record.duration, 0);
    
    const hours = Math.floor(totalTimeMs / 3600000);
    const minutes = Math.floor((totalTimeMs % 3600000) / 60000);
    const seconds = Math.floor((totalTimeMs % 60000) / 1000);
    
    timeSummaryDiv.innerHTML = `
        <p><strong>Total Time Tracked:</strong></p>
        <p>${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}</p>
    `;
}

function updateStats() {
    document.getElementById('total-tasks-count').textContent = tasks.length;
    document.getElementById('completed-tasks-count').textContent = tasks.filter(t => t.completed).length;
    document.getElementById('pending-tasks-count').textContent = tasks.filter(t => !t.completed).length;
    document.getElementById('categories-count').textContent = categories.length;
}

// Reports functions
function generateReport() {
    const reportType = document.getElementById('report-type').value;
    const reportContent = document.getElementById('report-content');
    
    let content = '';
    
    switch(reportType) {
        case 'daily':
            content = generateDailyReport();
            break;
        case 'weekly':
            content = generateWeeklyReport();
            break;
        case 'monthly':
            content = generateMonthlyReport();
            break;
    }
    
    reportContent.innerHTML = content;
}

function generateDailyReport() {
    const today = new Date().toISOString().split('T')[0];
    
    const todayTasks = tasks.filter(task => task.dueDate === today);
    const todayRecords = timeRecords.filter(record => record.date.split('T')[0] === today);
    
    let content = `<h3>Daily Report - ${today}</h3>`;
    
    content += `<h4>Tasks Due Today: ${todayTasks.length}</h4>`;
    content += '<ul>';
    todayTasks.forEach(task => {
        const status = task.completed ? '✓ Completed' : '○ Pending';
        content += `<li>${status}: ${task.name}</li>`;
    });
    content += '</ul>';
    
    content += `<h4>Time Tracked Today: ${todayRecords.length} sessions</h4>`;
    content += '<ul>';
    todayRecords.forEach(record => {
        const durationInMs = record.duration;
        const hours = Math.floor(durationInMs / 3600000);
        const minutes = Math.floor((durationInMs % 3600000) / 60000);
        const seconds = Math.floor((durationInMs % 60000) / 1000);
        const durationString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        const category = categories.find(cat => cat.id === record.categoryId);
        content += `<li>${record.title} (${category ? category.name : 'Uncategorized'}): ${durationString}</li>`;
    });
    content += '</ul>';
    
    return content;
}

function generateWeeklyReport() {
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    
    const weekTasks = tasks.filter(task => new Date(task.dueDate) >= weekAgo);
    const weekRecords = timeRecords.filter(record => new Date(record.date) >= weekAgo);
    
    let content = `<h3>Weekly Report (Last 7 Days)</h3>`;
    
    content += `<h4>Tasks Due in Last 7 Days: ${weekTasks.length}</h4>`;
    content += '<ul>';
    weekTasks.forEach(task => {
        const status = task.completed ? '✓ Completed' : '○ Pending';
        content += `<li>${status}: ${task.name}</li>`;
    });
    content += '</ul>';
    
    content += `<h4>Time Tracked in Last 7 Days: ${weekRecords.length} sessions</h4>`;
    content += '<ul>';
    weekRecords.forEach(record => {
        const durationInMs = record.duration;
        const hours = Math.floor(durationInMs / 3600000);
        const minutes = Math.floor((durationInMs % 3600000) / 60000);
        const seconds = Math.floor((durationInMs % 60000) / 1000);
        const durationString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        const category = categories.find(cat => cat.id === record.categoryId);
        content += `<li>${record.title} (${category ? category.name : 'Uncategorized'}): ${durationString}</li>`;
    });
    content += '</ul>';
    
    return content;
}

function generateMonthlyReport() {
    const monthAgo = new Date();
    monthAgo.setMonth(monthAgo.getMonth() - 1);
    
    const monthTasks = tasks.filter(task => new Date(task.dueDate) >= monthAgo);
    const monthRecords = timeRecords.filter(record => new Date(record.date) >= monthAgo);
    
    let content = `<h3>Monthly Report (Last 30 Days)</h3>`;
    
    content += `<h4>Tasks Due in Last 30 Days: ${monthTasks.length}</h4>`;
    content += '<ul>';
    monthTasks.forEach(task => {
        const status = task.completed ? '✓ Completed' : '○ Pending';
        content += `<li>${status}: ${task.name}</li>`;
    });
    content += '</ul>';
    
    content += `<h4>Time Tracked in Last 30 Days: ${monthRecords.length} sessions</h4>`;
    content += '<ul>';
    monthRecords.forEach(record => {
        const durationInMs = record.duration;
        const hours = Math.floor(durationInMs / 3600000);
        const minutes = Math.floor((durationInMs % 3600000) / 60000);
        const seconds = Math.floor((durationInMs % 60000) / 1000);
        const durationString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        const category = categories.find(cat => cat.id === record.categoryId);
        content += `<li>${record.title} (${category ? category.name : 'Uncategorized'}): ${durationString}</li>`;
    });
    content += '</ul>';
    
    return content;
}

function exportPDF() {
    alert('PDF export functionality would be implemented here. This requires additional libraries like jsPDF.');
}

function exportExcel() {
    alert('Excel export functionality would be implemented here. This requires additional libraries like SheetJS.');
}

// Calculator functions
function appendNumber(number) {
    const display = document.getElementById('calc-display');
    display.value += number;
}

function appendOperator(operator) {
    const display = document.getElementById('calc-display');
    const lastChar = display.value.slice(-1);
    
    // Don't append operator if last character is also an operator (except minus)
    if(['+', '-', '*', '/'].includes(lastChar) && lastChar !== '-' && operator !== '-') {
        display.value = display.value.slice(0, -1) + operator;
    } else {
        display.value += operator;
    }
}

function calculate() {
    const display = document.getElementById('calc-display');
    try {
        // Replace × with * for evaluation
        const expression = display.value.replace(/×/g, '*');
        const result = eval(expression);
        display.value = result;
    } catch (error) {
        display.value = 'Error';
    }
}

function clearDisplay() {
    document.getElementById('calc-display').value = '';
}

function deleteLastChar() {
    const display = document.getElementById('calc-display');
    display.value = display.value.slice(0, -1);
}

// Utility functions
function closeModal(modal) {
    modal.style.display = 'none';
}

// Close modals when clicking outside of them
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}