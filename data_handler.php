<?php
/**
 * کلاس مدیریت داده‌های برنامه
 * این کلاس وظیفه مدیریت تمامی داده‌های برنامه را بر عهده دارد
 */
require_once 'config.php';

class DataHandler {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * گرفتن تمامی کارها
     */
    public function getTasks($filters = []) {
        $sql = "SELECT t.*, c.name as category_name, c.color as category_color 
                FROM tasks t 
                LEFT JOIN categories c ON t.category_id = c.id";
        $params = [];
        
        // اعمال فیلترها
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                if ($value !== '' && $value !== null) {
                    switch ($key) {
                        case 'category_id':
                            $conditions[] = "t.category_id = ?";
                            $params[] = $value;
                            break;
                        case 'priority':
                            $conditions[] = "t.priority = ?";
                            $params[] = $value;
                            break;
                        case 'status':
                            $conditions[] = "t.status = ?";
                            $params[] = $value;
                            break;
                        case 'search':
                            $conditions[] = "(t.title LIKE ? OR t.description LIKE ?)";
                            $searchTerm = "%{$value}%";
                            $params[] = $searchTerm;
                            $params[] = $searchTerm;
                            break;
                    }
                }
            }
            
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * گرفتن یک کار خاص
     */
    public function getTask($id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.name as category_name, c.color as category_color 
            FROM tasks t 
            LEFT JOIN categories c ON t.category_id = c.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ایجاد یا به‌روزرسانی یک کار
     */
    public function saveTask($data, $id = null) {
        if ($id) {
            // به‌روزرسانی
            $stmt = $this->pdo->prepare("
                UPDATE tasks 
                SET title = ?, description = ?, category_id = ?, priority = ?, due_date = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $data['title'] ?? '',
                $data['description'] ?? '',
                $data['category_id'] ?? null,
                $data['priority'] ?? 'medium',
                $data['due_date'] ?? null,
                $data['status'] ?? 'pending',
                $id
            ]);
            
            return $result ? $this->getTask($id) : false;
        } else {
            // ایجاد
            $stmt = $this->pdo->prepare("
                INSERT INTO tasks (title, description, category_id, priority, due_date, status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $data['category_id'] ?? null,
                $data['priority'] ?? 'medium',
                $data['due_date'] ?? null,
                $data['status'] ?? 'pending'
            ]);
            
            return $result ? $this->getTask($this->pdo->lastInsertId()) : false;
        }
    }
    
    /**
     * حذف یک کار
     */
    public function deleteTask($id) {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * گرفتن تمامی دسته‌بندی‌ها
     */
    public function getCategories() {
        $stmt = $this->pdo->prepare("SELECT * FROM categories ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * گرفتن یک دسته‌بندی خاص
     */
    public function getCategory($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ایجاد یا به‌روزرسانی یک دسته‌بندی
     */
    public function saveCategory($data, $id = null) {
        if ($id) {
            // به‌روزرسانی
            $stmt = $this->pdo->prepare("UPDATE categories SET name = ?, color = ? WHERE id = ?");
            $result = $stmt->execute([
                $data['name'] ?? '',
                $data['color'] ?? '#007bff',
                $id
            ]);
            
            return $result ? $this->getCategory($id) : false;
        } else {
            // ایجاد
            $stmt = $this->pdo->prepare("INSERT INTO categories (name, color) VALUES (?, ?)");
            $result = $stmt->execute([
                $data['name'],
                $data['color'] ?? '#007bff'
            ]);
            
            return $result ? $this->getCategory($this->pdo->lastInsertId()) : false;
        }
    }
    
    /**
     * حذف یک دسته‌بندی و کارهای مرتبط
     */
    public function deleteCategory($id) {
        try {
            $this->pdo->beginTransaction();
            
            // حذف کارهای مربوط به این دسته
            $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE category_id = ?");
            $stmt->execute([$id]);
            
            // حذف دسته
            $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            $this->pdo->commit();
            return $result;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * گرفتن تمامی یادداشت‌ها
     */
    public function getNotes() {
        $stmt = $this->pdo->prepare("SELECT * FROM notes ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * گرفتن یک یادداشت خاص
     */
    public function getNote($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM notes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ایجاد یا به‌روزرسانی یک یادداشت
     */
    public function saveNote($data, $id = null) {
        if ($id) {
            // به‌روزرسانی
            $stmt = $this->pdo->prepare("UPDATE notes SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([
                $data['title'] ?? '',
                $data['content'] ?? '',
                $id
            ]);
            
            return $result ? $this->getNote($id) : false;
        } else {
            // ایجاد
            $stmt = $this->pdo->prepare("INSERT INTO notes (title, content) VALUES (?, ?)");
            $result = $stmt->execute([
                $data['title'],
                $data['content']
            ]);
            
            return $result ? $this->getNote($this->pdo->lastInsertId()) : false;
        }
    }
    
    /**
     * حذف یک یادداشت
     */
    public function deleteNote($id) {
        $stmt = $this->pdo->prepare("DELETE FROM notes WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * گرفتن تمامی سابقه تایمر
     */
    public function getTimerEntries($filters = []) {
        $sql = "SELECT * FROM timer_entries";
        $params = [];
        
        // اعمال فیلترها
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                if ($value !== '' && $value !== null) {
                    switch ($key) {
                        case 'date_from':
                            $conditions[] = "date >= ?";
                            $params[] = $value;
                            break;
                        case 'date_to':
                            $conditions[] = "date <= ?";
                            $params[] = $value;
                            break;
                        case 'title':
                            $conditions[] = "title LIKE ?";
                            $params[] = "%{$value}%";
                            break;
                    }
                }
            }
            
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
        }
        
        $sql .= " ORDER BY date DESC, created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * گرفتن یک سابقه تایمر خاص
     */
    public function getTimerEntry($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM timer_entries WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ایجاد یا به‌روزرسانی یک سابقه تایمر
     */
    public function saveTimerEntry($data, $id = null) {
        if ($id) {
            // به‌روزرسانی
            $stmt = $this->pdo->prepare("UPDATE timer_entries SET title = ?, duration = ?, date = ? WHERE id = ?");
            $result = $stmt->execute([
                $data['title'] ?? '',
                $data['duration'] ?? '',
                $data['date'] ?? date('Y-m-d'),
                $id
            ]);
            
            return $result ? $this->getTimerEntry($id) : false;
        } else {
            // ایجاد
            $stmt = $this->pdo->prepare("INSERT INTO timer_entries (title, duration, date) VALUES (?, ?, ?)");
            $result = $stmt->execute([
                $data['title'],
                $data['duration'],
                $data['date'] ?? date('Y-m-d')
            ]);
            
            return $result ? $this->getTimerEntry($this->pdo->lastInsertId()) : false;
        }
    }
    
    /**
     * حذف یک سابقه تایمر
     */
    public function deleteTimerEntry($id) {
        $stmt = $this->pdo->prepare("DELETE FROM timer_entries WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * گرفتن تمامی فایل‌ها
     */
    public function getFiles($filters = []) {
        $sql = "SELECT * FROM files";
        $params = [];
        
        // اعمال فیلترها
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                if ($value !== '' && $value !== null) {
                    switch ($key) {
                        case 'category':
                            $conditions[] = "category = ?";
                            $params[] = $value;
                            break;
                        case 'mime_type':
                            $conditions[] = "mime_type LIKE ?";
                            $params[] = "{$value}%";
                            break;
                        case 'search':
                            $conditions[] = "(original_name LIKE ? OR category LIKE ?)";
                            $searchTerm = "%{$value}%";
                            $params[] = $searchTerm;
                            $params[] = $searchTerm;
                            break;
                    }
                }
            }
            
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
        }
        
        $sql .= " ORDER BY uploaded_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * گرفتن یک فایل خاص
     */
    public function getFile($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * آپلود یک فایل جدید
     */
    public function uploadFile($file, $category = 'general') {
        // بررسی خطا در آپلود
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading file: ' . $file['error']);
        }
        
        // بررسی اندازه فایل
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File too large');
        }
        
        // اعتبارسنجی نوع فایل
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'audio/mpeg', 'audio/wav', 'audio/mp4',
            'text/plain', 'text/html',
            'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('File type not allowed');
        }
        
        // ایجاد نام فایل منحصر به فرد
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = $this->getExtensionFromMimeType($file['type']);
        }
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $filePath = UPLOAD_DIR . $fileName;
        
        // انتقال فایل به مسیر مقصد
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        // ذخیره اطلاعات فایل در دیتابیس
        $stmt = $this->pdo->prepare("
            INSERT INTO files (original_name, file_name, file_path, file_size, mime_type, category) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $file['name'],
            $fileName,
            $filePath,
            $file['size'],
            $file['type'],
            $category
        ]);
        
        return $result ? $this->getFile($this->pdo->lastInsertId()) : false;
    }
    
    /**
     * به‌روزرسانی یک فایل
     */
    public function updateFile($id, $data) {
        $stmt = $this->pdo->prepare("UPDATE files SET category = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['category'] ?? 'general',
            $id
        ]);
        
        return $result ? $this->getFile($id) : false;
    }
    
    /**
     * حذف یک فایل
     */
    public function deleteFile($id) {
        // ابتدا اطلاعات فایل را بگیریم تا بتوانیم فایل فیزیکی را نیز حذف کنیم
        $file = $this->getFile($id);
        if (!$file) {
            return false;
        }
        
        // حذف فایل فیزیکی
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        
        // حذف رکورد از دیتابیس
        $stmt = $this->pdo->prepare("DELETE FROM files WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * دریافت گزارش آماری
     */
    public function getReport($type = 'summary', $startDate = null, $endDate = null) {
        switch ($type) {
            case 'summary':
                return $this->getSummaryReport();
            case 'tasks':
                return $this->getTasksReport($startDate, $endDate);
            case 'time':
                return $this->getTimeReport($startDate, $endDate);
            case 'categories':
                return $this->getCategoriesReport($startDate, $endDate);
            default:
                return [];
        }
    }
    
    /**
     * گزارش خلاصه
     */
    private function getSummaryReport() {
        $stats = [];
        
        // آمار کلی کارها
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM tasks");
        $stats['total_tasks'] = $stmt->fetchColumn();
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as completed FROM tasks WHERE status = 'completed'");
        $stats['completed_tasks'] = $stmt->fetchColumn();
        
        $stats['pending_tasks'] = $stats['total_tasks'] - $stats['completed_tasks'];
        $stats['completion_rate'] = $stats['total_tasks'] > 0 ? 
            round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 2) : 0;
        
        // آمار دسته‌بندی‌ها
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM categories");
        $stats['total_categories'] = $stmt->fetchColumn();
        
        // آمار یادداشت‌ها
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM notes");
        $stats['total_notes'] = $stmt->fetchColumn();
        
        // آمار فایل‌ها
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM files");
        $stats['total_files'] = $stmt->fetchColumn();
        
        // آمار تایمر
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM timer_entries");
        $stats['total_timer_entries'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * گزارش کارها
     */
    private function getTasksReport($startDate = null, $endDate = null) {
        $sql = "SELECT status, COUNT(*) as count FROM tasks";
        $params = [];
        
        if ($startDate || $endDate) {
            $sql .= " WHERE ";
            $conditions = [];
            
            if ($startDate) {
                $conditions[] = "created_at >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $conditions[] = "created_at <= ?";
                $params[] = $endDate;
            }
            
            $sql .= implode(" AND ", $conditions);
        }
        
        $sql .= " GROUP BY status";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * گزارش زمان
     */
    private function getTimeReport($startDate = null, $endDate = null) {
        $sql = "SELECT title, SUM(CASE WHEN duration LIKE '%:%:%' THEN 
                    CAST(SUBSTR(duration, 1, INSTR(duration, ':')-1) AS INTEGER) * 3600 +
                    CAST(SUBSTR(duration, INSTR(duration, ':')+1, INSTR(SUBSTR(duration, INSTR(duration, ':')+1), ':')-1) AS INTEGER) * 60 +
                    CAST(SUBSTR(duration, LENGTH(duration)-2, 2) AS INTEGER)
                 ELSE 0 END) as total_seconds
                 FROM timer_entries";
        $params = [];
        
        if ($startDate || $endDate) {
            $sql .= " WHERE ";
            $conditions = [];
            
            if ($startDate) {
                $conditions[] = "date >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $conditions[] = "date <= ?";
                $params[] = $endDate;
            }
            
            $sql .= implode(" AND ", $conditions);
        }
        
        $sql .= " GROUP BY title ORDER BY total_seconds DESC LIMIT 10";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // تبدیل ثانیه به فرمت ساعت:دقیقه:ثانیه
        foreach ($results as &$result) {
            $hours = floor($result['total_seconds'] / 3600);
            $minutes = floor(($result['total_seconds'] % 3600) / 60);
            $seconds = $result['total_seconds'] % 60;
            $result['total_duration'] = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        
        return $results;
    }
    
    /**
     * گزارش دسته‌بندی‌ها
     */
    private function getCategoriesReport($startDate = null, $endDate = null) {
        $sql = "SELECT c.name, c.color, COUNT(t.id) as task_count 
                FROM categories c 
                LEFT JOIN tasks t ON c.id = t.category_id";
        $params = [];
        
        if ($startDate || $endDate) {
            $sql .= " AND ";
            $conditions = [];
            
            if ($startDate) {
                $conditions[] = "t.created_at >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $conditions[] = "t.created_at <= ?";
                $params[] = $endDate;
            }
            
            $sql .= implode(" AND ", $conditions);
        }
        
        $sql .= " GROUP BY c.id, c.name, c.color ORDER BY task_count DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * دریافت پسوند فایل بر اساس نوع MIME
     */
    private function getExtensionFromMimeType($mimeType) {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/mp4' => 'm4a',
            'text/plain' => 'txt',
            'text/html' => 'html',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
        ];
        
        return $mimeToExt[$mimeType] ?? 'bin';
    }
}
?>