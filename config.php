<?php
// Database configuration for advanced task manager
// Using SQLite as requested for internal database

class Database {
    private $pdo;
    
    public function __construct() {
        try {
            // Create SQLite database file
            $this->pdo = new PDO('sqlite:task_manager.db');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initDatabase();
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    private function initDatabase() {
        // Create tasks table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category_id INTEGER,
            priority TEXT DEFAULT 'medium',
            due_date DATE,
            description TEXT,
            completed BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create categories table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            goal TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create time_records table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS time_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            category_id INTEGER,
            duration INTEGER NOT NULL, -- Duration in milliseconds
            date DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create notes table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create reports table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL, -- daily, weekly, monthly
            content TEXT,
            generated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    public function getPDO() {
        return $this->pdo;
    }
}
?>