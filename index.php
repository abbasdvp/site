<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Task Manager & Personal Analytics</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header>
            <h1>Advanced Task Manager & Personal Analytics</h1>
            <div id="clock">--:--:--</div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="tabs">
            <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
            <button class="tab-btn" data-tab="tasks">Tasks</button>
            <button class="tab-btn" data-tab="categories">Categories</button>
            <button class="tab-btn" data-tab="timer">Timer</button>
            <button class="tab-btn" data-tab="notes">Notes</button>
            <button class="tab-btn" data-tab="calculator">Calculator</button>
            <button class="tab-btn" data-tab="reports">Reports</button>
        </nav>

        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            <div class="dashboard-grid">
                <div class="card">
                    <h3>Today's Tasks</h3>
                    <div id="today-tasks-list"></div>
                </div>
                <div class="card">
                    <h3>Progress Overview</h3>
                    <canvas id="progress-chart"></canvas>
                </div>
                <div class="card">
                    <h3>Time Tracker Summary</h3>
                    <div id="time-summary"></div>
                </div>
                <div class="card">
                    <h3>Quick Stats</h3>
                    <ul>
                        <li>Total Tasks: <span id="total-tasks-count">0</span></li>
                        <li>Completed: <span id="completed-tasks-count">0</span></li>
                        <li>Pending: <span id="pending-tasks-count">0</span></li>
                        <li>Categories: <span id="categories-count">0</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tasks Tab -->
        <div id="tasks" class="tab-content">
            <div class="section-header">
                <h2>Task Management</h2>
                <button id="add-task-btn" class="btn-primary">Add New Task</button>
            </div>
            
            <div class="filters">
                <select id="category-filter">
                    <option value="">All Categories</option>
                </select>
                <select id="priority-filter">
                    <option value="">All Priorities</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
                <input type="text" id="search-tasks" placeholder="Search tasks...">
            </div>
            
            <div class="tasks-container">
                <table id="tasks-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tasks-list"></tbody>
                </table>
            </div>
        </div>

        <!-- Categories Tab -->
        <div id="categories" class="tab-content">
            <div class="section-header">
                <h2>Category Management</h2>
                <button id="add-category-btn" class="btn-primary">Add New Category</button>
            </div>
            
            <div class="categories-container">
                <table id="categories-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Goal</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categories-list"></tbody>
                </table>
            </div>
        </div>

        <!-- Timer Tab -->
        <div id="timer" class="tab-content">
            <div class="section-header">
                <h2>Time Tracker</h2>
            </div>
            
            <div class="timer-section">
                <div class="timer-display">
                    <span id="timer-hours">00</span>:<span id="timer-minutes">00</span>:<span id="timer-seconds">00</span>
                </div>
                
                <div class="timer-controls">
                    <button id="start-timer" class="btn-success">Start</button>
                    <button id="pause-timer" class="btn-warning">Pause</button>
                    <button id="stop-timer" class="btn-danger">Stop</button>
                    <button id="record-time" class="btn-primary">Record Time</button>
                </div>
                
                <div class="timer-form">
                    <label for="task-title">Title:</label>
                    <input type="text" id="task-title" placeholder="Enter task title">
                    
                    <label for="time-category">Category:</label>
                    <select id="time-category">
                        <option value="">Select Category</option>
                    </select>
                    
                    <button id="save-record" class="btn-primary">Save Record</button>
                </div>
                
                <div class="records-container">
                    <h3>Time Records</h3>
                    <table id="records-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Duration</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="records-list"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Notes Tab -->
        <div id="notes" class="tab-content">
            <div class="section-header">
                <h2>Notes</h2>
                <button id="add-note-btn" class="btn-primary">Add New Note</button>
            </div>
            
            <div class="notes-container">
                <div id="notes-list"></div>
            </div>
        </div>

        <!-- Calculator Tab -->
        <div id="calculator" class="tab-content">
            <div class="section-header">
                <h2>Calculator</h2>
            </div>
            
            <div class="calculator">
                <input type="text" id="calc-display" readonly>
                <div class="calc-buttons">
                    <button class="calc-btn" onclick="appendNumber('7')">7</button>
                    <button class="calc-btn" onclick="appendNumber('8')">8</button>
                    <button class="calc-btn" onclick="appendNumber('9')">9</button>
                    <button class="calc-btn calc-op" onclick="appendOperator('/')">/</button>
                    
                    <button class="calc-btn" onclick="appendNumber('4')">4</button>
                    <button class="calc-btn" onclick="appendNumber('5')">5</button>
                    <button class="calc-btn" onclick="appendNumber('6')">6</button>
                    <button class="calc-btn calc-op" onclick="appendOperator('*')">×</button>
                    
                    <button class="calc-btn" onclick="appendNumber('1')">1</button>
                    <button class="calc-btn" onclick="appendNumber('2')">2</button>
                    <button class="calc-btn" onclick="appendNumber('3')">3</button>
                    <button class="calc-btn calc-op" onclick="appendOperator('-')">-</button>
                    
                    <button class="calc-btn" onclick="appendNumber('0')">0</button>
                    <button class="calc-btn" onclick="appendNumber('.')">.</button>
                    <button class="calc-btn calc-equals" onclick="calculate()">=</button>
                    <button class="calc-btn calc-op" onclick="appendOperator('+')">+</button>
                    
                    <button class="calc-btn calc-clear" onclick="clearDisplay()">C</button>
                    <button class="calc-btn calc-delete" onclick="deleteLastChar()">⌫</button>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div id="reports" class="tab-content">
            <div class="section-header">
                <h2>Reports</h2>
                <div class="report-actions">
                    <select id="report-type">
                        <option value="daily">Daily Report</option>
                        <option value="weekly">Weekly Report</option>
                        <option value="monthly">Monthly Report</option>
                    </select>
                    <button id="generate-report" class="btn-primary">Generate Report</button>
                    <button id="export-pdf" class="btn-secondary">Export PDF</button>
                    <button id="export-excel" class="btn-secondary">Export Excel</button>
                </div>
            </div>
            
            <div class="reports-container">
                <div id="report-content">
                    <p>Select report type and click Generate Report to see analytics.</p>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <div id="task-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>Add/Edit Task</h3>
                <form id="task-form">
                    <input type="hidden" id="task-id">
                    <div class="form-group">
                        <label for="task-name">Task Name:</label>
                        <input type="text" id="task-name" required>
                    </div>
                    <div class="form-group">
                        <label for="task-category">Category:</label>
                        <select id="task-category" required>
                            <option value="">Select Category</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="task-priority">Priority:</label>
                        <select id="task-priority" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="task-due-date">Due Date:</label>
                        <input type="date" id="task-due-date">
                    </div>
                    <div class="form-group">
                        <label for="task-description">Description:</label>
                        <textarea id="task-description"></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Save Task</button>
                </form>
            </div>
        </div>

        <div id="category-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>Add/Edit Category</h3>
                <form id="category-form">
                    <input type="hidden" id="category-id">
                    <div class="form-group">
                        <label for="category-name">Name:</label>
                        <input type="text" id="category-name" required>
                    </div>
                    <div class="form-group">
                        <label for="category-description">Description:</label>
                        <textarea id="category-description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="category-goal">Goal:</label>
                        <input type="text" id="category-goal">
                    </div>
                    <button type="submit" class="btn-primary">Save Category</button>
                </form>
            </div>
        </div>

        <div id="note-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>Add/Edit Note</h3>
                <form id="note-form">
                    <input type="hidden" id="note-id">
                    <div class="form-group">
                        <label for="note-title">Title:</label>
                        <input type="text" id="note-title" required>
                    </div>
                    <div class="form-group">
                        <label for="note-content">Content:</label>
                        <textarea id="note-content" rows="10"></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Save Note</button>
                </form>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>