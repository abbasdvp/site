<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت ربات فیلم</title>
    <style>
        * {
            direction: rtl;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .card h3 {
            margin-bottom: 10px;
            color: #667eea;
        }
        
        .card .count {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            background: #eee;
            transition: background 0.3s;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        form {
            margin: 20px 0;
        }
        
        input, select, textarea, button {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        
        button:hover {
            opacity: 0.9;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        
        .channel-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
            margin: 5px 0;
        }
        
        .media-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
            margin: 5px 0;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📱 پنل مدیریت ربات فیلم و سریال</h1>
            <p>مدیریت کامل ربات تلگرامی شما</p>
        </header>
        
        <div class="dashboard">
            <div class="card">
                <h3>👥 کاربران</h3>
                <div class="count" id="user-count">0</div>
                <p>کل کاربران ربات</p>
            </div>
            <div class="card">
                <h3>🎬 فیلم‌ها</h3>
                <div class="count" id="media-count">0</div>
                <p>کل فیلم‌ها و سریال‌ها</p>
            </div>
            <div class="card">
                <h3>👑 ادمین‌ها</h3>
                <div class="count" id="admin-count">0</div>
                <p>کل ادمین‌های ربات</p>
            </div>
            <div class="card">
                <h3>📡 چنل‌ها</h3>
                <div class="count" id="channel-count">0</div>
                <p>کل چنل‌های مدیریتی</p>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('dashboard')">📅 داشبورد</div>
            <div class="tab" onclick="showTab('media')">🎬 مدیریت فیلم‌ها</div>
            <div class="tab" onclick="showTab('channels')">📡 تنظیمات چنل‌ها</div>
            <div class="tab" onclick="showTab('admins')">👑 مدیریت ادمین‌ها</div>
            <div class="tab" onclick="showTab('users')">👥 لیست کاربران</div>
        </div>
        
        <div id="dashboard" class="tab-content active">
            <h2>📊 وضعیت کلی ربات</h2>
            <div style="margin-top: 20px;">
                <h3>آخرین فعالیت‌ها</h3>
                <ul id="activity-log">
                    <!-- Activity log will be populated here -->
                </ul>
            </div>
        </div>
        
        <div id="media" class="tab-content">
            <h2>➕ افزودن فیلم جدید</h2>
            <form id="add-media-form">
                <input type="text" id="media-title" placeholder="عنوان فیلم یا سریال" required>
                <textarea id="media-description" placeholder="توضیحات فیلم" rows="4" required></textarea>
                <input type="text" id="media-cover" placeholder="آیدی فایل کاور (عکس)" required>
                <input type="text" id="media-file" placeholder="آیدی فایل ویدیو" required>
                <button type="submit" class="btn-success">➕ افزودن فیلم</button>
            </form>
            
            <h2 style="margin-top: 30px;">📚 لیست فیلم‌ها</h2>
            <div id="media-list">
                <!-- Media list will be populated here -->
            </div>
        </div>
        
        <div id="channels" class="tab-content">
            <h2>⚙️ تنظیمات چنل‌ها</h2>
            <form id="channel-settings-form">
                <input type="text" id="archive-channel" placeholder="آیدی چنل بایگانی (مثال: @channel_name)">
                <input type="text" id="main-channel" placeholder="آیدی چنل اصلی (مثال: @channel_name)">
                <input type="text" id="backup-channel" placeholder="آیدی چنل پشتیبان (مثال: @channel_name)">
                <input type="text" id="notification-channel" placeholder="آیدی چنل اعلانات (مثال: @channel_name)">
                <input type="text" id="default-cover" placeholder="آیدی کاور پیش‌فرض">
                <button type="submit" class="btn-success">💾 ذخیره تنظیمات</button>
            </form>
            
            <h2 style="margin-top: 30px;">➕ افزودن چنل عضویت اجباری</h2>
            <form id="add-join-channel-form">
                <input type="text" id="join-channel-id" placeholder="آیدی چنل (مثال: @channel_name)" required>
                <input type="text" id="join-channel-title" placeholder="عنوان چنل" required>
                <button type="submit" class="btn-success">➕ افزودن چنل</button>
            </form>
            
            <h2 style="margin-top: 30px;">📋 چنل‌های عضویت اجباری</h2>
            <div id="join-channels-list">
                <!-- Join channels list will be populated here -->
            </div>
        </div>
        
        <div id="admins" class="tab-content">
            <h2>➕ افزودن ادمین جدید</h2>
            <form id="add-admin-form">
                <input type="number" id="admin-user-id" placeholder="آیدی عددی کاربر" required>
                <input type="text" id="admin-username" placeholder="نام کاربری (اختیاری)">
                <button type="submit" class="btn-success">➕ افزودن ادمین</button>
            </form>
            
            <h2 style="margin-top: 30px;">👥 لیست ادمین‌ها</h2>
            <div id="admin-list">
                <!-- Admin list will be populated here -->
            </div>
        </div>
        
        <div id="users" class="tab-content">
            <h2>👥 لیست کاربران</h2>
            <div id="user-list">
                <!-- User list will be populated here -->
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Load data based on tab
            loadData(tabName);
        }
        
        // Load data for specific tab
        function loadData(tabName) {
            fetch('telegram_media_bot.php')
                .then(response => response.text())
                .then(data => {
                    if (tabName === 'dashboard') {
                        loadDashboardData();
                    } else if (tabName === 'media') {
                        loadMediaData();
                    } else if (tabName === 'channels') {
                        loadChannelData();
                    } else if (tabName === 'admins') {
                        loadAdminData();
                    } else if (tabName === 'users') {
                        loadUserData();
                    }
                })
                .catch(error => console.error('Error loading data:', error));
        }
        
        // Load dashboard data
        function loadDashboardData() {
            // This would normally fetch data from backend
            document.getElementById('user-count').textContent = '0';
            document.getElementById('media-count').textContent = '0';
            document.getElementById('admin-count').textContent = '0';
            document.getElementById('channel-count').textContent = '0';
            
            // Mock activity log
            const activityLog = document.getElementById('activity-log');
            activityLog.innerHTML = `
                <li>۲۰۲۶/۰۲/۰۵ - ربات با موفقیت راه‌اندازی شد</li>
                <li>۲۰۲۶/۰۲/۰۵ - پنل مدیریت ایجاد شد</li>
            `;
        }
        
        // Load media data
        function loadMediaData() {
            // Mock data - in real implementation, fetch from JSON file
            const mediaList = document.getElementById('media-list');
            mediaList.innerHTML = '<p>در حال حاضر فیلمی موجود نیست</p>';
        }
        
        // Load channel data
        function loadChannelData() {
            // Mock data - in real implementation, fetch from settings JSON
            document.getElementById('archive-channel').value = '';
            document.getElementById('main-channel').value = '';
            document.getElementById('backup-channel').value = '';
            document.getElementById('notification-channel').value = '';
            document.getElementById('default-cover').value = '';
        }
        
        // Load admin data
        function loadAdminData() {
            // Mock data - in real implementation, fetch from admins JSON
            const adminList = document.getElementById('admin-list');
            adminList.innerHTML = '<p>در حال حاضر ادمینی موجود نیست</p>';
        }
        
        // Load user data
        function loadUserData() {
            // Mock data - in real implementation, fetch from users JSON
            const userList = document.getElementById('user-list');
            userList.innerHTML = '<p>در حال حاضر کاربری موجود نیست</p>';
        }
        
        // Form submissions would go here in a real implementation
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            
            // Form submission handlers (would connect to backend in real implementation)
            document.getElementById('add-media-form').addEventListener('submit', function(e) {
                e.preventDefault();
                alert('فیلم با موفقیت اضافه شد!');
                this.reset();
            });
            
            document.getElementById('channel-settings-form').addEventListener('submit', function(e) {
                e.preventDefault();
                alert('تنظیمات چنل‌ها با موفقیت ذخیره شد!');
            });
            
            document.getElementById('add-join-channel-form').addEventListener('submit', function(e) {
                e.preventDefault();
                alert('چنل عضویت اجباری با موفقیت اضافه شد!');
                this.reset();
            });
            
            document.getElementById('add-admin-form').addEventListener('submit', function(e) {
                e.preventDefault();
                alert('ادمین جدید با موفقیت اضافه شد!');
                this.reset();
            });
        });
    </script>
</body>
</html>