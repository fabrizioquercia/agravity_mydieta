<?php
// api/db.php - Database connection & Logger helper

function get_db_path() {
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    return $dataDir . '/salute_dieta.sqlite';
}

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $dbPath = get_db_path();
        $isNew = !file_exists($dbPath);
        
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Optimize SQLite for concurrent reading/writing
        $pdo->exec("PRAGMA journal_mode = WAL;");
        $pdo->exec("PRAGMA foreign_keys = ON;");
        
        init_db_schema($pdo);
    }
    return $pdo;
}

function write_app_log($action, $details = []) {
    $logDir = __DIR__ . '/../log';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $endpoint = $_SERVER['REQUEST_URI'] ?? 'local';
    
    $payloadStr = is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $entry = sprintf("[%s] [%s] [%s %s] ACTION: %s | DETAILS: %s%s", $timestamp, $ip, $method, $endpoint, $action, $payloadStr, PHP_EOL);
    
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function init_db_schema($pdo) {
    // 1. Weight & Nutrition Daily Logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS weight_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL UNIQUE,
        weight REAL NOT NULL,
        measured_time TEXT DEFAULT '',
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS food_diary (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL,
        meal_type TEXT NOT NULL, -- 'prima_colazione', 'seconda_colazione', 'pranzo', 'merenda', 'cena', 'altro'
        food_name TEXT NOT NULL,
        grams REAL DEFAULT NULL,
        notes TEXT DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration helper for existing databases
    $colsWeight = $pdo->query("PRAGMA table_info(weight_logs)")->fetchAll();
    $weightColNames = array_column($colsWeight, 'name');
    if (!in_array('measured_time', $weightColNames)) {
        $pdo->exec("ALTER TABLE weight_logs ADD COLUMN measured_time TEXT DEFAULT ''");
    }

    $colsFood = $pdo->query("PRAGMA table_info(food_diary)")->fetchAll();
    $foodColNames = array_column($colsFood, 'name');
    if (!in_array('grams', $foodColNames)) {
        $pdo->exec("ALTER TABLE food_diary ADD COLUMN grams REAL DEFAULT NULL");
    }
    if (!in_array('notes', $foodColNames)) {
        $pdo->exec("ALTER TABLE food_diary ADD COLUMN notes TEXT DEFAULT ''");
    }

    // 2. Weekly Meal Plan (Piano Alimentare Settimanale)
    // Days: lunedi, martedi, mercoledi, giovedi, venerdi, sabato, domenica
    // Meals: prima_colazione, seconda_colazione, pranzo, merenda, cena
    $pdo->exec("CREATE TABLE IF NOT EXISTS meal_plan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        day_of_week TEXT NOT NULL,
        meal_type TEXT NOT NULL,
        food_items TEXT NOT NULL, -- elenco alimenti (testo con newline o formattato)
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(day_of_week, meal_type)
    )");

    // 3. Blood Pressure Monitoring (PA)
    $pdo->exec("CREATE TABLE IF NOT EXISTS blood_pressure (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        measured_at TEXT NOT NULL, -- YYYY-MM-DD HH:MM
        systolic INTEGER NOT NULL, -- Massima (mmHg)
        diastolic INTEGER NOT NULL, -- Minima (mmHg)
        pulse INTEGER NOT NULL,    -- Pulsazioni (bpm)
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Medications & Intake Tracker
    $pdo->exec("CREATE TABLE IF NOT EXISTS medications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        dosage TEXT NOT NULL,
        scheduled_time TEXT NOT NULL, -- es. '08:00', '13:00', '20:00'
        notes TEXT,
        active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS medication_intakes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        medication_id INTEGER NOT NULL,
        intake_date TEXT NOT NULL, -- YYYY-MM-DD
        taken_at TEXT DEFAULT CURRENT_TIMESTAMP,
        status INTEGER DEFAULT 1, -- 1 = preso, 0 = non preso
        FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE CASCADE,
        UNIQUE(medication_id, intake_date)
    )");

    // 5. Sport Activities Log
    $pdo->exec("CREATE TABLE IF NOT EXISTS sport_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL,
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        activity TEXT NOT NULL,
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
}

function send_json($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function get_json_input() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
