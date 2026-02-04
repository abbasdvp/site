// مدیریت تم‌ها
document.addEventListener('DOMContentLoaded', function() {
    // بارگذاری تم ذخیره شده
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', savedTheme);
    
    // انتخاب تم
    document.querySelectorAll('.theme-option').forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });
    });
    
    // نمایش ساعت
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('fa-IR');
        document.getElementById('clock').textContent = timeString;
    }
    setInterval(updateClock, 1000);
    updateClock();
    
    // مدیریت ناوبری
    document.querySelectorAll('[data-section]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const sectionId = this.getAttribute('data-section');
            
            // مخفی کردن تمام بخش‌ها
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // نمایش بخش مورد نظر
            document.getElementById(sectionId).classList.add('active');
            
            // به‌روزرسانی لینک فعال
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            this.classList.add('active');
        });
    });
    
    // بارگذاری داده‌ها
    loadData();
    
    // اضافه کردن کار جدید
    document.getElementById('taskForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const task = {
            id: Date.now(),
            title: document.getElementById('taskTitle').value,
            description: document.getElementById('taskDescription').value,
            category: document.getElementById('taskCategory').value,
            priority: document.getElementById('taskPriority').value,
            dueDate: document.getElementById('taskDueDate').value,
            status: 'pending',
            createdAt: new Date().toISOString()
        };
        
        addTask(task);
        document.getElementById('taskForm').reset();
        document.getElementById('addTaskForm').style.display = 'none';
        loadTasks();
    });
    
    // اضافه کردن دسته‌بندی جدید
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const category = {
            id: Date.now(),
            name: document.getElementById('categoryName').value,
            color: document.getElementById('categoryColor').value
        };
        
        addCategory(category);
        document.getElementById('categoryForm').reset();
        document.getElementById('addCategoryForm').style.display = 'none';
        loadCategories();
    });
    
    // اضافه کردن یادداشت جدید
    document.getElementById('noteForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const note = {
            id: Date.now(),
            title: document.getElementById('noteTitle').value,
            content: document.getElementById('noteContent').value,
            createdAt: new Date().toISOString()
        };
        
        addNote(note);
        document.getElementById('noteForm').reset();
        document.getElementById('addNoteForm').style.display = 'none';
        loadNotes();
    });
    
    // مدیریت تایمر
    let timerInterval;
    let startTime;
    let elapsedTime = 0;
    let isRunning = false;
    
    document.getElementById('startTimer').addEventListener('click', startTimer);
    document.getElementById('pauseTimer').addEventListener('click', pauseTimer);
    document.getElementById('stopTimer').addEventListener('click', stopTimer);
    document.getElementById('resetTimer').addEventListener('click', resetTimer);
    
    document.getElementById('saveTimeEntry').addEventListener('click', saveTimeEntry);
    
    // مدیریت فایل‌ها
    document.getElementById('uploadFileBtn').addEventListener('click', () => {
        document.getElementById('fileInput').click();
    });
    
    document.getElementById('fileInput').addEventListener('change', handleFileUpload);
    
    // تولید گزارش
    document.getElementById('generateReportBtn').addEventListener('click', generateReport);
    document.getElementById('exportReportBtn').addEventListener('click', exportReport);
});

// داده‌های برنامه
let tasks = JSON.parse(localStorage.getItem('tasks')) || [];
let categories = JSON.parse(localStorage.getItem('categories')) || [];
let notes = JSON.parse(localStorage.getItem('notes')) || [];
let timerEntries = JSON.parse(localStorage.getItem('timerEntries')) || [];
let files = JSON.parse(localStorage.getItem('files')) || [];

// توابع مدیریت کارها
function loadData() {
    loadCategories();
    loadTasks();
    loadNotes();
    loadTimerHistory();
    loadFiles();
    updateStats();
}

function addTask(task) {
    tasks.push(task);
    localStorage.setItem('tasks', JSON.stringify(tasks));
}

function updateTask(id, updates) {
    tasks = tasks.map(task => task.id == id ? {...task, ...updates} : task);
    localStorage.setItem('tasks', JSON.stringify(tasks));
}

function deleteTask(id) {
    tasks = tasks.filter(task => task.id != id);
    localStorage.setItem('tasks', JSON.stringify(tasks));
}

function loadTasks() {
    const filterCategory = document.getElementById('filterCategory').value;
    const filterPriority = document.getElementById('filterPriority').value;
    const filterStatus = document.getElementById('filterStatus').value;
    
    let filteredTasks = tasks;
    
    if (filterCategory && filterCategory !== 'all') {
        filteredTasks = filteredTasks.filter(task => task.category === filterCategory);
    }
    
    if (filterPriority && filterPriority !== 'all') {
        filteredTasks = filteredTasks.filter(task => task.priority === filterPriority);
    }
    
    if (filterStatus && filterStatus !== 'all') {
        filteredTasks = filteredTasks.filter(task => task.status === filterStatus);
    }
    
    const tasksList = document.getElementById('tasksList');
    tasksList.innerHTML = '';
    
    filteredTasks.forEach(task => {
        const category = categories.find(cat => cat.id == task.category) || {name: 'بدون دسته', color: '#ccc'};
        const taskElement = document.createElement('div');
        taskElement.className = `task-item card ${task.status === 'completed' ? 'completed' : ''} ${task.priority}-priority`;
        taskElement.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title">${task.title}</h6>
                        <p class="card-text">${task.description || ''}</p>
                        <div class="d-flex gap-2">
                            <span class="badge" style="background-color: ${category.color};">${category.name}</span>
                            <span class="badge bg-${task.priority === 'high' ? 'danger' : task.priority === 'medium' ? 'warning' : 'success'}">
                                ${task.priority === 'high' ? 'بالا' : task.priority === 'medium' ? 'متوسط' : 'کم'}
                            </span>
                            ${task.dueDate ? `<span class="badge bg-info">سررسید: ${task.dueDate}</span>` : ''}
                            ${task.status === 'completed' ? '<span class="badge bg-success">انجام شده</span>' : '<span class="badge bg-warning">در انتظار</span>'}
                        </div>
                    </div>
                    <div class="task-actions">
                        <button class="btn btn-sm btn-success" onclick="toggleTaskStatus(${task.id})">
                            <i class="fas fa-${task.status === 'completed' ? 'undo' : 'check'}"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="editTask(${task.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteTaskUI(${task.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        tasksList.appendChild(taskElement);
    });
    
    updateStats();
}

function toggleTaskStatus(id) {
    const task = tasks.find(t => t.id == id);
    if (task) {
        task.status = task.status === 'completed' ? 'pending' : 'completed';
        localStorage.setItem('tasks', JSON.stringify(tasks));
        loadTasks();
    }
}

function editTask(id) {
    // پیاده‌سازی ویرایش کار
    alert('ویرایش کار: ' + id);
}

function deleteTaskUI(id) {
    if (confirm('آیا از حذف این کار اطمینان دارید؟')) {
        deleteTask(id);
        loadTasks();
    }
}

function updateStats() {
    const totalTasks = tasks.length;
    const completedTasks = tasks.filter(t => t.status === 'completed').length;
    const pendingTasks = totalTasks - completedTasks;
    const completionRate = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;
    
    document.getElementById('totalTasks').textContent = totalTasks;
    document.getElementById('completedTasks').textContent = completedTasks;
    document.getElementById('pendingTasks').textContent = pendingTasks;
    
    document.getElementById('totalTasksCard').textContent = totalTasks;
    document.getElementById('completedTasksCard').textContent = completedTasks;
    document.getElementById('pendingTasksCard').textContent = pendingTasks;
    document.getElementById('completionRateCard').textContent = completionRate + '%';
    
    const overallProgress = document.getElementById('overallProgress');
    overallProgress.style.width = completionRate + '%';
    overallProgress.textContent = completionRate + '%';
    
    // به‌روزرسانی آمار گزارش
    document.getElementById('reportCompletedTasks').textContent = completedTasks;
    document.getElementById('reportPendingTasks').textContent = pendingTasks;
    
    // نمایش آخرین کارها
    const recentTasksList = document.getElementById('recentTasksList');
    recentTasksList.innerHTML = '';
    const recentTasks = [...tasks].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)).slice(0, 5);
    
    recentTasks.forEach(task => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = `
            <span>${task.title}</span>
            <span class="badge bg-${task.status === 'completed' ? 'success' : 'warning'}">${task.status === 'completed' ? 'انجام شده' : 'در انتظار'}</span>
        `;
        recentTasksList.appendChild(li);
    });
}

// توابع مدیریت دسته‌بندی‌ها
function addCategory(category) {
    categories.push(category);
    localStorage.setItem('categories', JSON.stringify(categories));
}

function updateCategory(id, updates) {
    categories = categories.map(cat => cat.id == id ? {...cat, ...updates} : cat);
    localStorage.setItem('categories', JSON.stringify(categories));
}

function deleteCategory(id) {
    categories = categories.filter(cat => cat.id != id);
    // حذف کارهای مربوط به این دسته
    tasks = tasks.filter(task => task.category != id);
    localStorage.setItem('categories', JSON.stringify(categories));
    localStorage.setItem('tasks', JSON.stringify(tasks));
}

function loadCategories() {
    const categoryList = document.getElementById('categoryList');
    const taskCategorySelect = document.getElementById('taskCategory');
    const filterCategorySelect = document.getElementById('filterCategory');
    
    categoryList.innerHTML = '';
    taskCategorySelect.innerHTML = '<option value="">انتخاب دسته بندی</option>';
    filterCategorySelect.innerHTML = '<option value="all">همه دسته بندی ها</option>';
    
    categories.forEach(category => {
        // لیست دسته‌بندی‌ها در سایدبار
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center mb-1';
        div.innerHTML = `
            <div>
                <span class="badge" style="background-color: ${category.color};">${category.name}</span>
            </div>
            <div>
                <button class="btn btn-sm btn-warning" onclick="editCategory(${category.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteCategoryUI(${category.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        categoryList.appendChild(div);
        
        // گزینه‌های انتخاب دسته برای کار جدید
        const option1 = document.createElement('option');
        option1.value = category.id;
        option1.textContent = category.name;
        taskCategorySelect.appendChild(option1);
        
        // گزینه‌های فیلتر دسته
        const option2 = document.createElement('option');
        option2.value = category.id;
        option2.textContent = category.name;
        filterCategorySelect.appendChild(option2);
    });
    
    loadTasks();
}

function editCategory(id) {
    // پیاده‌سازی ویرایش دسته
    alert('ویرایش دسته: ' + id);
}

function deleteCategoryUI(id) {
    if (confirm('آیا از حذف این دسته و تمام کارهای مرتبط اطمینان دارید؟')) {
        deleteCategory(id);
        loadCategories();
        loadTasks();
    }
}

// توابع مدیریت یادداشت‌ها
function addNote(note) {
    notes.push(note);
    localStorage.setItem('notes', JSON.stringify(notes));
}

function updateNote(id, updates) {
    notes = notes.map(note => note.id == id ? {...note, ...updates} : note);
    localStorage.setItem('notes', JSON.stringify(notes));
}

function deleteNote(id) {
    notes = notes.filter(note => note.id != id);
    localStorage.setItem('notes', JSON.stringify(notes));
}

function loadNotes() {
    const notesList = document.getElementById('notesList');
    notesList.innerHTML = '';
    
    notes.forEach(note => {
        const noteElement = document.createElement('div');
        noteElement.className = 'card mb-3';
        noteElement.innerHTML = `
            <div class="card-body">
                <h5 class="card-title">${note.title}</h5>
                <p class="card-text">${note.content}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">${new Date(note.createdAt).toLocaleString('fa-IR')}</small>
                    <div>
                        <button class="btn btn-sm btn-warning" onclick="editNote(${note.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteNoteUI(${note.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        notesList.appendChild(noteElement);
    });
}

function editNote(id) {
    // پیاده‌سازی ویرایش یادداشت
    alert('ویرایش یادداشت: ' + id);
}

function deleteNoteUI(id) {
    if (confirm('آیا از حذف این یادداشت اطمینان دارید؟')) {
        deleteNote(id);
        loadNotes();
    }
}

// توابع مدیریت تایمر
function startTimer() {
    if (!isRunning) {
        isRunning = true;
        startTime = new Date().getTime() - elapsedTime;
        timerInterval = setInterval(function() {
            elapsedTime = new Date().getTime() - startTime;
            updateTimerDisplay();
        }, 10);
    }
}

function pauseTimer() {
    if (isRunning) {
        isRunning = false;
        clearInterval(timerInterval);
    }
}

function stopTimer() {
    if (isRunning) {
        isRunning = false;
        clearInterval(timerInterval);
        saveTimeEntry();
    }
}

function resetTimer() {
    pauseTimer();
    elapsedTime = 0;
    updateTimerDisplay();
}

function updateTimerDisplay() {
    const totalSeconds = Math.floor(elapsedTime / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    const display = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    document.getElementById('timerDisplay').textContent = display;
}

function saveTimeEntry() {
    const activityTitle = document.getElementById('activityTitle').value || 
                         document.getElementById('predefinedActivity').value;
    
    if (!activityTitle) {
        alert('لطفاً عنوان فعالیت را وارد کنید');
        return;
    }
    
    const timerEntry = {
        id: Date.now(),
        title: activityTitle,
        time: document.getElementById('timerDisplay').textContent,
        date: new Date().toLocaleDateString('fa-IR')
    };
    
    timerEntries.push(timerEntry);
    localStorage.setItem('timerEntries', JSON.stringify(timerEntries));
    loadTimerHistory();
    
    // بازنشانی فیلدها
    document.getElementById('activityTitle').value = '';
    document.getElementById('predefinedActivity').value = '';
    resetTimer();
}

function loadTimerHistory() {
    const timerHistory = document.getElementById('timerHistory');
    timerHistory.innerHTML = '';
    
    timerEntries.slice().reverse().forEach(entry => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${entry.title}</td>
            <td>${entry.time}</td>
            <td>${entry.date}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="deleteTimerEntry(${entry.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        timerHistory.appendChild(tr);
    });
}

function deleteTimerEntry(id) {
    timerEntries = timerEntries.filter(entry => entry.id != id);
    localStorage.setItem('timerEntries', JSON.stringify(timerEntries));
    loadTimerHistory();
}

// توابع مدیریت فایل‌ها
function handleFileUpload(event) {
    const files = Array.from(event.target.files);
    
    files.forEach(file => {
        const fileId = Date.now() + '_' + file.name.replace(/[^a-zA-Z0-9]/g, '_');
        
        const fileObj = {
            id: fileId,
            name: file.name,
            size: file.size,
            type: file.type,
            uploadDate: new Date().toISOString()
        };
        
        // ذخیره فایل در localStorage به صورت base64 (برای نمونه کار)
        const reader = new FileReader();
        reader.onload = function(e) {
            fileObj.data = e.target.result;
            files.push(fileObj);
            localStorage.setItem('files', JSON.stringify(files));
            loadFiles();
        };
        
        // برای فایل‌های تصویری، PDF و صوتی از readAsDataURL استفاده می‌کنیم
        if (file.type.startsWith('image/')) {
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            reader.readAsDataURL(file);
        } else if (file.type.startsWith('audio/')) {
            reader.readAsDataURL(file);
        } else {
            // برای سایر فایل‌ها می‌توانیم محتوای متنی را بخوانیم یا فقط اطلاعات را ذخیره کنیم
            reader.readAsDataURL(file);
        }
    });
    
    // بازنشانی input فایل
    event.target.value = '';
}

function loadFiles() {
    // بارگذاری فایل‌ها در تب‌های مختلف
    loadImages();
    loadPDFs();
    loadAudio();
    loadAllFiles();
}

function loadImages() {
    const images = files.filter(file => file.type.startsWith('image/'));
    const gallery = document.getElementById('imagesGallery');
    gallery.innerHTML = '';
    
    images.forEach(file => {
        const col = document.createElement('div');
        col.className = 'col-md-3';
        col.innerHTML = `
            <div class="image-card">
                <img src="${file.data}" class="image-preview" alt="${file.name}">
                <div class="p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">${formatFileSize(file.size)}</small>
                        <button class="btn btn-sm btn-danger" onclick="deleteFile('${file.id}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        gallery.appendChild(col);
    });
}

function loadPDFs() {
    const pdfs = files.filter(file => file.type === 'application/pdf');
    const list = document.getElementById('pdfsList');
    list.innerHTML = '';
    
    pdfs.forEach(file => {
        const item = document.createElement('a');
        item.className = 'list-group-item list-group-item-action';
        item.href = '#';
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-file-pdf text-danger me-2"></i>
                    <span>${file.name}</span>
                </div>
                <div class="d-flex align-items-center">
                    <small class="text-muted me-2">${formatFileSize(file.size)}</small>
                    <button class="btn btn-sm btn-primary me-1" onclick="openPDF('${file.data}')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-success me-1" onclick="downloadFile('${file.id}')">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteFile('${file.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        list.appendChild(item);
    });
}

function loadAudio() {
    const audioFiles = files.filter(file => file.type.startsWith('audio/'));
    const list = document.getElementById('audioList');
    list.innerHTML = '';
    
    audioFiles.forEach(file => {
        const item = document.createElement('a');
        item.className = 'list-group-item list-group-item-action';
        item.href = '#';
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-file-audio text-warning me-2"></i>
                    <span>${file.name}</span>
                </div>
                <div class="d-flex align-items-center">
                    <small class="text-muted me-2">${formatFileSize(file.size)}</small>
                    <audio controls class="audio-player">
                        <source src="${file.data}" type="${file.type}">
                        مرورگر شما از پخش صوت پشتیبانی نمی‌کند.
                    </audio>
                    <button class="btn btn-sm btn-success me-1" onclick="downloadFile('${file.id}')">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteFile('${file.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        list.appendChild(item);
    });
}

function loadAllFiles() {
    const list = document.getElementById('allFilesList');
    list.innerHTML = '';
    
    files.forEach(file => {
        const item = document.createElement('a');
        item.className = 'list-group-item list-group-item-action';
        item.href = '#';
        let icon = '<i class="fas fa-file me-2"></i>';
        if (file.type.startsWith('image/')) icon = '<i class="fas fa-file-image text-info me-2"></i>';
        else if (file.type === 'application/pdf') icon = '<i class="fas fa-file-pdf text-danger me-2"></i>';
        else if (file.type.startsWith('audio/')) icon = '<i class="fas fa-file-audio text-warning me-2"></i>';
        else if (file.type.startsWith('video/')) icon = '<i class="fas fa-file-video text-primary me-2"></i>';
        
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    ${icon}
                    <span>${file.name}</span>
                </div>
                <div class="d-flex align-items-center">
                    <small class="text-muted me-2">${formatFileSize(file.size)}</small>
                    <small class="text-muted me-2">${new Date(file.uploadDate).toLocaleDateString('fa-IR')}</small>
                    <button class="btn btn-sm btn-success me-1" onclick="downloadFile('${file.id}')">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteFile('${file.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        list.appendChild(item);
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function openPDF(dataUrl) {
    // باز کردن PDF در یک پنجره جدید
    window.open(dataUrl, '_blank');
}

function downloadFile(fileId) {
    const file = files.find(f => f.id === fileId);
    if (file) {
        // ایجاد یک لینک موقت برای دانلود
        const link = document.createElement('a');
        link.href = file.data;
        link.download = file.name;
        link.click();
    }
}

function deleteFile(fileId) {
    if (confirm('آیا از حذف این فایل اطمینان دارید؟')) {
        files = files.filter(file => file.id !== fileId);
        localStorage.setItem('files', JSON.stringify(files));
        loadFiles();
    }
}

// توابع گزارش‌گیری
function generateReport() {
    // محاسبه گزارش بر اساس تاریخ و نوع گزارش
    const reportType = document.getElementById('reportType').value;
    const startDate = document.getElementById('reportStartDate').value;
    const endDate = document.getElementById('reportEndDate').value;
    
    // برای نمونه، گزارش کلی را نمایش می‌دهیم
    // در عمل باید بر اساس تاریخ‌های مشخص شده فیلتر کنیم
    
    // محاسبه بیشترین فعالیت
    const activityCounts = {};
    timerEntries.forEach(entry => {
        activityCounts[entry.title] = (activityCounts[entry.title] || 0) + 1;
    });
    
    const topActivity = Object.keys(activityCounts).reduce((a, b) => 
        activityCounts[a] > activityCounts[b] ? a : b, '');
    
    document.getElementById('reportTopActivity').textContent = topActivity || '-';
    
    // محاسبه مجموع زمان کار
    let totalTime = 0;
    timerEntries.forEach(entry => {
        const [h, m, s] = entry.time.split(':').map(Number);
        totalTime += h * 3600 + m * 60 + s;
    });
    
    const totalHours = Math.floor(totalTime / 3600);
    document.getElementById('reportTotalTime').textContent = totalHours;
    
    // نمایش نمودار
    showReportChart();
}

function showReportChart() {
    // ایجاد نمودار با Chart.js
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    // پاک کردن نمودار قبلی در صورت وجود
    if (window.reportChart) {
        window.reportChart.destroy();
    }
    
    window.reportChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['کارهای انجام شده', 'کارهای باقیمانده', 'مجموع کارها'],
            datasets: [{
                label: 'تعداد کارها',
                data: [
                    tasks.filter(t => t.status === 'completed').length,
                    tasks.filter(t => t.status === 'pending').length,
                    tasks.length
                ],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(54, 162, 235, 0.6)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(54, 162, 235, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            indexAxis: 'y'
        }
    });
}

function exportReport() {
    // صادرات گزارش به صورت PDF یا Excel
    alert('قابلیت صادرات گزارش در نسخه کامل پیاده‌سازی خواهد شد.');
}

// مدیریت نمایش فرم‌ها
document.getElementById('showAddTaskFormBtn').addEventListener('click', function() {
    document.getElementById('addTaskForm').style.display = 'block';
});
document.getElementById('cancelTaskBtn').addEventListener('click', function() {
    document.getElementById('addTaskForm').style.display = 'none';
});

document.getElementById('showAddCategoryFormBtn').addEventListener('click', function() {
    document.getElementById('addCategoryForm').style.display = 'block';
});
document.getElementById('cancelCategoryBtn').addEventListener('click', function() {
    document.getElementById('addCategoryForm').style.display = 'none';
});

document.getElementById('showAddNoteFormBtn').addEventListener('click', function() {
    document.getElementById('addNoteForm').style.display = 'block';
});
document.getElementById('cancelNoteBtn').addEventListener('click', function() {
    document.getElementById('addNoteForm').style.display = 'none';
});

// نمایش نمودار پیشرفت در داشبورد
function showDashboardChart() {
    const ctx = document.getElementById('progressChart').getContext('2d');
    
    // پاک کردن نمودار قبلی در صورت وجود
    if (window.dashboardChart) {
        window.dashboardChart.destroy();
    }
    
    window.dashboardChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['هفته گذشته', '۴ روز گذشته', '۳ روز گذشته', 'دیروز', 'امروز'],
            datasets: [{
                label: 'کارهای انجام شده',
                data: [2, 4, 3, 5, 7],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'کارهای باقیمانده',
                data: [8, 6, 7, 5, 3],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// فراخوانی نمایش نمودار داشبورد
document.addEventListener('DOMContentLoaded', function() {
    showDashboardChart();
});