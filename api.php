<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

// مدیریت درخواست‌های OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_REQUEST['request'], '/'));

// Route handler
$table = isset($request[0]) ? $request[0] : null;
$id = isset($request[1]) ? $request[1] : null;

switch ($method) {
    case 'GET':
        switch ($table) {
            case 'tasks':
                if ($id) {
                    getTask($id);
                } else {
                    getTasks();
                }
                break;
            case 'categories':
                if ($id) {
                    getCategory($id);
                } else {
                    getCategories();
                }
                break;
            case 'notes':
                if ($id) {
                    getNote($id);
                } else {
                    getNotes();
                }
                break;
            case 'timer_entries':
                if ($id) {
                    getTimerEntry($id);
                } else {
                    getTimerEntries();
                }
                break;
            case 'files':
                if ($id) {
                    getFile($id);
                } else {
                    getFiles();
                }
                break;
            default:
                echo json_encode(['error' => 'Invalid endpoint']);
                break;
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($table) {
            case 'tasks':
                createTask($input);
                break;
            case 'categories':
                createCategory($input);
                break;
            case 'notes':
                createNote($input);
                break;
            case 'timer_entries':
                createTimerEntry($input);
                break;
            case 'files':
                uploadFile();
                break;
            default:
                echo json_encode(['error' => 'Invalid endpoint']);
                break;
        }
        break;
        
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($table) {
            case 'tasks':
                updateTask($id, $input);
                break;
            case 'categories':
                updateCategory($id, $input);
                break;
            case 'notes':
                updateNote($id, $input);
                break;
            case 'timer_entries':
                updateTimerEntry($id, $input);
                break;
            case 'files':
                updateFile($id, $input);
                break;
            default:
                echo json_encode(['error' => 'Invalid endpoint']);
                break;
        }
        break;
        
    case 'DELETE':
        switch ($table) {
            case 'tasks':
                deleteTask($id);
                break;
            case 'categories':
                deleteCategory($id);
                break;
            case 'notes':
                deleteNote($id);
                break;
            case 'timer_entries':
                deleteTimerEntry($id);
                break;
            case 'files':
                deleteFile($id);
                break;
            default:
                echo json_encode(['error' => 'Invalid endpoint']);
                break;
        }
        break;
        
    default:
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// توابع کمکی برای کار با داده‌ها

// کارها
function getTasks() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, c.name as category_name, c.color as category_color 
            FROM tasks t 
            LEFT JOIN categories c ON t.category_id = c.id 
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($tasks);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getTask($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, c.name as category_name, c.color as category_color 
            FROM tasks t 
            LEFT JOIN categories c ON t.category_id = c.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            echo json_encode($task);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createTask($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
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
        
        if ($result) {
            $taskId = $pdo->lastInsertId();
            getTask($taskId);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create task']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateTask($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
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
        
        if ($result) {
            getTask($id);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update task']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteTask($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete task']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// دسته‌بندی‌ها
function getCategories() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY created_at DESC");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($categories);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getCategory($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            echo json_encode($category);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Category not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createCategory($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name, color) VALUES (?, ?)");
        $result = $stmt->execute([
            $data['name'],
            $data['color'] ?? '#007bff'
        ]);
        
        if ($result) {
            $categoryId = $pdo->lastInsertId();
            getCategory($categoryId);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create category']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateCategory($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, color = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['name'] ?? '',
            $data['color'] ?? '#007bff',
            $id
        ]);
        
        if ($result) {
            getCategory($id);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update category']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteCategory($id) {
    global $pdo;
    
    try {
        // حذف کارهای مربوط به این دسته
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE category_id = ?");
        $stmt->execute([$id]);
        
        // حذف دسته
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete category']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// یادداشت‌ها
function getNotes() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM notes ORDER BY created_at DESC");
        $stmt->execute();
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($notes);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getNote($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
        $stmt->execute([$id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($note) {
            echo json_encode($note);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Note not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createNote($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notes (title, content) VALUES (?, ?)");
        $result = $stmt->execute([
            $data['title'],
            $data['content']
        ]);
        
        if ($result) {
            $noteId = $pdo->lastInsertId();
            getNote($noteId);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create note']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateNote($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([
            $data['title'] ?? '',
            $data['content'] ?? '',
            $id
        ]);
        
        if ($result) {
            getNote($id);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update note']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteNote($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete note']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// سابقه تایمر
function getTimerEntries() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM timer_entries ORDER BY date DESC, created_at DESC");
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($entries);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getTimerEntry($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM timer_entries WHERE id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($entry) {
            echo json_encode($entry);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Timer entry not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createTimerEntry($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO timer_entries (title, duration, date) VALUES (?, ?, ?)");
        $result = $stmt->execute([
            $data['title'],
            $data['duration'],
            $data['date'] ?? date('Y-m-d')
        ]);
        
        if ($result) {
            $entryId = $pdo->lastInsertId();
            getTimerEntry($entryId);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create timer entry']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateTimerEntry($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE timer_entries SET title = ?, duration = ?, date = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['title'] ?? '',
            $data['duration'] ?? '',
            $data['date'] ?? date('Y-m-d'),
            $id
        ]);
        
        if ($result) {
            getTimerEntry($id);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update timer entry']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteTimerEntry($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM timer_entries WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete timer entry']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// فایل‌ها
function getFiles() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM files ORDER BY uploaded_at DESC");
        $stmt->execute();
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($files);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getFile($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            echo json_encode($file);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function uploadFile() {
    global $pdo;
    
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['file'];
    
    // بررسی خطا در آپلود
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Error uploading file']);
        return;
    }
    
    // بررسی اندازه فایل
    if ($file['size'] > MAX_FILE_SIZE) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large']);
        return;
    }
    
    // ایجاد نام فایل منحصر به فرد
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = time() . '_' . uniqid() . '.' . $extension;
    $filePath = UPLOAD_DIR . $fileName;
    
    // انتقال فایل به مسیر مقصد
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO files (original_name, file_name, file_path, file_size, mime_type, category) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $file['name'],
                $fileName,
                $filePath,
                $file['size'],
                $file['type'],
                $_POST['category'] ?? 'general'
            ]);
            
            if ($result) {
                $fileId = $pdo->lastInsertId();
                getFile($fileId);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save file record']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file']);
    }
}

function updateFile($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE files SET category = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['category'] ?? 'general',
            $id
        ]);
        
        if ($result) {
            getFile($id);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update file']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteFile($id) {
    global $pdo;
    
    try {
        // ابتدا اطلاعات فایل را بگیریم تا بتوانیم فایل فیزیکی را نیز حذف کنیم
        $stmt = $pdo->prepare("SELECT file_path FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            // حذف فایل فیزیکی
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
            
            // حذف رکورد از دیتابیس
            $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete file record']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>