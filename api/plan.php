<?php
// api/plan.php - Gestione Piano Alimentare Settimanale
require_once __DIR__ . '/db.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        // Ritorna tutto il piano settimanale indicizzato per giorno e pasto
        $stmt = $pdo->query("SELECT * FROM meal_plan");
        $rows = $stmt->fetchAll();
        
        $plan = [];
        $days = ['lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'];
        $meals = ['prima_colazione', 'seconda_colazione', 'pranzo', 'merenda', 'cena'];

        foreach ($days as $d) {
            $plan[$d] = [];
            foreach ($meals as $m) {
                $plan[$d][$m] = ['food_items' => '', 'notes' => ''];
            }
        }

        foreach ($rows as $row) {
            $d = strtolower($row['day_of_week']);
            $m = strtolower($row['meal_type']);
            if (isset($plan[$d][$m])) {
                $plan[$d][$m] = [
                    'id' => $row['id'],
                    'food_items' => $row['food_items'],
                    'notes' => $row['notes']
                ];
            }
        }

        write_app_log('PLAN_GET_ALL');
        send_json(['success' => true, 'plan' => $plan]);
    } elseif ($method === 'POST') {
        $data = get_json_input();
        
        if ($action === 'save_cell') {
            $day = strtolower($data['day_of_week'] ?? '');
            $meal = strtolower($data['meal_type'] ?? '');
            $food_items = trim($data['food_items'] ?? '');
            $notes = trim($data['notes'] ?? '');

            $validDays = ['lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'];
            $validMeals = ['prima_colazione', 'seconda_colazione', 'pranzo', 'merenda', 'cena'];

            if (!in_array($day, $validDays) || !in_array($meal, $validMeals)) {
                send_json(['success' => false, 'error' => 'Giorno o tipo pasto non valido'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO meal_plan (day_of_week, meal_type, food_items, notes) 
                VALUES (?, ?, ?, ?)
                ON CONFLICT(day_of_week, meal_type) 
                DO UPDATE SET food_items = excluded.food_items, notes = excluded.notes");
            $stmt->execute([$day, $meal, $food_items, $notes]);

            write_app_log('PLAN_SAVE_CELL', ['day' => $day, 'meal' => $meal]);
            send_json(['success' => true, 'message' => 'Piano pasto aggiornato']);
        } elseif ($action === 'save_all') {
            // Bulk save entire week plan
            $items = $data['items'] ?? [];
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO meal_plan (day_of_week, meal_type, food_items, notes) 
                VALUES (?, ?, ?, ?)
                ON CONFLICT(day_of_week, meal_type) 
                DO UPDATE SET food_items = excluded.food_items, notes = excluded.notes");
            
            foreach ($items as $item) {
                $day = strtolower($item['day_of_week'] ?? '');
                $meal = strtolower($item['meal_type'] ?? '');
                $food_items = trim($item['food_items'] ?? '');
                $notes = trim($item['notes'] ?? '');
                if ($day && $meal) {
                    $stmt->execute([$day, $meal, $food_items, $notes]);
                }
            }
            $pdo->commit();
            write_app_log('PLAN_SAVE_BULK', ['count' => count($items)]);
            send_json(['success' => true, 'message' => 'Piano settimanale salvato con successo']);
        } else {
            send_json(['success' => false, 'error' => 'Azione non valida'], 400);
        }
    } else {
        send_json(['success' => false, 'error' => 'Metodo non consentito'], 405);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    write_app_log('PLAN_ERROR', ['error' => $e->getMessage()]);
    send_json(['success' => false, 'error' => $e->getMessage()], 500);
}
