<?php
// API endpoints for the Advanced Task Manager

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$database = new Database();
$pdo = $database->getPDO();

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the requested endpoint
$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$endpoint = isset($request[0]) ? $request[0] : '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($endpoint, $request, $pdo);
            break;
        case 'POST':
            handlePostRequest($endpoint, $request, $pdo);
            break;
        case 'PUT':
            handlePutRequest($endpoint, $request, $pdo);
            break;
        case 'DELETE':
            handleDeleteRequest($endpoint, $request, $pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetRequest($endpoint, $request, $pdo) {
    switch ($endpoint) {
        case 'tasks':
            if (isset($request[1])) {
                getTask($request[1], $pdo);
            } else {
                getAllTasks($pdo);
            }
            break;
        case 'categories':
            if (isset($request[1])) {
                getCategory($request[1], $pdo);
            } else {
                getAllCategories($pdo);
            }
            break;
        case 'time-records':
            if (isset($request[1])) {
                getTimeRecord($request[1], $pdo);
            } else {
                getAllTimeRecords($pdo);
            }
            break;
        case 'notes':
            if (isset($request[1])) {
                getNote($request[1], $pdo);
            } else {
                getAllNotes($pdo);
            }
            break;
        case 'reports':
            generateReport($request, $pdo);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePostRequest($endpoint, $request, $pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($endpoint) {
        case 'tasks':
            createTask($input, $pdo);
            break;
        case 'categories':
            createCategory($input, $pdo);
            break;
        case 'time-records':
            createTimeRecord($input, $pdo);
            break;
        case 'notes':
            createNote($input, $pdo);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePutRequest($endpoint, $request, $pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($endpoint) {
        case 'tasks':
            if (isset($request[1])) {
                updateTask($request[1], $input, $pdo);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID required']);
            }
            break;
        case 'categories':
            if (isset($request[1])) {
                updateCategory($request[1], $input, $pdo);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Category ID required']);
            }
            break;
        case 'time-records':
            if (isset($request[1])) {
                updateTimeRecord($request[1], $input, $pdo);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Time record ID required']);
            }
            break;
        case 'notes':
            if (isset($request[1])) {
                updateNote($request[1], $input, $pdo);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Note ID required']);
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handleDeleteRequest($endpoint, $request, $pdo) {
    switch ($endpoint) {
        case 'tasks':
            if (isset($request[1])) {
                deleteTask($request[1], $pdo);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID required']);
            }
            break;
        case 'categories':
            if (isset($request[1])) {
                deleteCategory($request[1], $pdo);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Category ID required']);
            }
            break;
        case 'time-records':
            if (isset($request[1])) {
                deleteTimeRecord($request[1], $pdo);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Time record ID required']);
            }
            break;
        case 'notes':
            if (isset($request[1])) {
                deleteNote($request[1], $pdo);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Note ID required']);
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

// Tasks CRUD operations
function getAllTasks($pdo) {
    $stmt = $pdo->query("
        SELECT t.*, c.name as category_name 
        FROM tasks t 
        LEFT JOIN categories c ON t.category_id = c.id 
        ORDER BY t.created_at DESC
    ");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($tasks);
}

function getTask($id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name 
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
}

function createTask($data, $pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO tasks (name, category_id, priority, due_date, description, completed) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $data['name'],
        $data['category_id'] ?? null,
        $data['priority'] ?? 'medium',
        $data['due_date'] ?? null,
        $data['description'] ?? '',
        $data['completed'] ?? 0
    ]);
    
    if ($result) {
        $taskId = $pdo->lastInsertId();
        echo json_encode(['id' => $taskId, 'message' => 'Task created successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create task']);
    }
}

function updateTask($id, $data, $pdo) {
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET name = ?, category_id = ?, priority = ?, due_date = ?, description = ?, completed = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $data['name'],
        $data['category_id'] ?? null,
        $data['priority'] ?? 'medium',
        $data['due_date'] ?? null,
        $data['description'] ?? '',
        $data['completed'] ?? 0,
        $id
    ]);
    
    if ($result) {
        echo json_encode(['message' => 'Task updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update task']);
    }
}

function deleteTask($id, $pdo) {
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['message' => 'Task deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete task']);
    }
}

// Categories CRUD operations
function getAllCategories($pdo) {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY created_at DESC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($categories);
}

function getCategory($id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($category) {
        echo json_encode($category);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Category not found']);
    }
}

function createCategory($data, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO categories (name, description, goal) VALUES (?, ?, ?)");
    
    $result = $stmt->execute([
        $data['name'],
        $data['description'] ?? '',
        $data['goal'] ?? ''
    ]);
    
    if ($result) {
        $categoryId = $pdo->lastInsertId();
        echo json_encode(['id' => $categoryId, 'message' => 'Category created successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create category']);
    }
}

function updateCategory($id, $data, $pdo) {
    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, goal = ? WHERE id = ?");
    
    $result = $stmt->execute([
        $data['name'],
        $data['description'] ?? '',
        $data['goal'] ?? '',
        $id
    ]);
    
    if ($result) {
        echo json_encode(['message' => 'Category updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update category']);
    }
}

function deleteCategory($id, $pdo) {
    // First, remove association from tasks
    $stmt = $pdo->prepare("UPDATE tasks SET category_id = NULL WHERE category_id = ?");
    $stmt->execute([$id]);
    
    // Then delete the category
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['message' => 'Category deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete category']);
    }
}

// Time Records CRUD operations
function getAllTimeRecords($pdo) {
    $stmt = $pdo->query("
        SELECT tr.*, c.name as category_name 
        FROM time_records tr 
        LEFT JOIN categories c ON tr.category_id = c.id 
        ORDER BY tr.date DESC
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($records);
}

function getTimeRecord($id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT tr.*, c.name as category_name 
        FROM time_records tr 
        LEFT JOIN categories c ON tr.category_id = c.id 
        WHERE tr.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode($record);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Time record not found']);
    }
}

function createTimeRecord($data, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO time_records (title, category_id, duration) VALUES (?, ?, ?)");
    
    $result = $stmt->execute([
        $data['title'],
        $data['category_id'] ?? null,
        $data['duration']
    ]);
    
    if ($result) {
        $recordId = $pdo->lastInsertId();
        echo json_encode(['id' => $recordId, 'message' => 'Time record created successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create time record']);
    }
}

function updateTimeRecord($id, $data, $pdo) {
    $stmt = $pdo->prepare("UPDATE time_records SET title = ?, category_id = ?, duration = ? WHERE id = ?");
    
    $result = $stmt->execute([
        $data['title'],
        $data['category_id'] ?? null,
        $data['duration'],
        $id
    ]);
    
    if ($result) {
        echo json_encode(['message' => 'Time record updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update time record']);
    }
}

function deleteTimeRecord($id, $pdo) {
    $stmt = $pdo->prepare("DELETE FROM time_records WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['message' => 'Time record deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete time record']);
    }
}

// Notes CRUD operations
function getAllNotes($pdo) {
    $stmt = $pdo->query("SELECT * FROM notes ORDER BY created_at DESC");
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($notes);
}

function getNote($id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->execute([$id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($note) {
        echo json_encode($note);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Note not found']);
    }
}

function createNote($data, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO notes (title, content) VALUES (?, ?)");
    
    $result = $stmt->execute([
        $data['title'],
        $data['content'] ?? ''
    ]);
    
    if ($result) {
        $noteId = $pdo->lastInsertId();
        echo json_encode(['id' => $noteId, 'message' => 'Note created successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create note']);
    }
}

function updateNote($id, $data, $pdo) {
    $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    
    $result = $stmt->execute([
        $data['title'],
        $data['content'] ?? '',
        $id
    ]);
    
    if ($result) {
        echo json_encode(['message' => 'Note updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update note']);
    }
}

function deleteNote($id, $pdo) {
    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['message' => 'Note deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete note']);
    }
}

// Reports generation
function generateReport($request, $pdo) {
    $type = isset($request[1]) ? $request[1] : 'daily';
    
    // Calculate date ranges based on report type
    $endDate = date('Y-m-d');
    
    switch ($type) {
        case 'daily':
            $startDate = $endDate;
            $period = 'Daily';
            break;
        case 'weekly':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $period = 'Weekly';
            break;
        case 'monthly':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $period = 'Monthly';
            break;
        default:
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $period = 'Weekly';
    }
    
    // Get tasks for the period
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name 
        FROM tasks t 
        LEFT JOIN categories c ON t.category_id = c.id 
        WHERE t.created_at BETWEEN ? AND ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get time records for the period
    $stmt = $pdo->prepare("
        SELECT tr.*, c.name as category_name 
        FROM time_records tr 
        LEFT JOIN categories c ON tr.category_id = c.id 
        WHERE tr.date BETWEEN ? AND ?
        ORDER BY tr.date DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    $completedTasks = array_filter($tasks, function($task) {
        return $task['completed'] == 1;
    });
    
    $totalTime = array_sum(array_column($records, 'duration'));
    
    // Format total time
    $hours = floor($totalTime / 3600000);
    $minutes = floor(($totalTime % 3600000) / 60000);
    $formattedTime = "{$hours}h {$minutes}m";
    
    $report = [
        'type' => $type,
        'period' => $period,
        'date_generated' => date('Y-m-d H:i:s'),
        'date_range' => [
            'start' => $startDate,
            'end' => $endDate
        ],
        'stats' => [
            'total_tasks' => count($tasks),
            'completed_tasks' => count($completedTasks),
            'pending_tasks' => count($tasks) - count($completedTasks),
            'total_time_tracked' => $formattedTime,
            'total_time_minutes' => round($totalTime / 60000)
        ],
        'tasks' => $tasks,
        'time_records' => $records
    ];
    
    echo json_encode($report);
}
?>