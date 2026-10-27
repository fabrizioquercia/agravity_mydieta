<?php
// api/diet.php - Gestione Dieta, Alimenti e Peso
require_once __DIR__ . '/db.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        if ($action === 'day_summary') {
            $date = $_GET['date'] ?? date('Y-m-d');
            
            // Get weight for this day
            $stmtW = $pdo->prepare("SELECT * FROM weight_logs WHERE date = ?");
            $stmtW->execute([$date]);
            $weightLog = $stmtW->fetch();

            // Get food entries for this day
            $stmtF = $pdo->prepare("SELECT * FROM food_diary WHERE date = ? ORDER BY id ASC");
            $stmtF->execute([$date]);
            $foods = $stmtF->fetchAll();

            // Calculate totals
            $totals = [
                'total_grams' => 0,
                'items_count' => count($foods)
            ];
            foreach ($foods as $f) {
                if (isset($f['grams']) && is_numeric($f['grams'])) {
                    $totals['total_grams'] += (float)$f['grams'];
                }
            }

            write_app_log('DIET_GET_DAY_SUMMARY', ['date' => $date]);
            send_json([
                'success' => true,
                'date' => $date,
                'weight' => $weightLog,
                'foods' => $foods,
                'totals' => $totals
            ]);
        } elseif ($action === 'weight_history') {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
            $stmt = $pdo->prepare("SELECT * FROM weight_logs ORDER BY date DESC LIMIT ?");
            $stmt->execute([$limit]);
            $history = array_reverse($stmt->fetchAll());
            write_app_log('WEIGHT_GET_HISTORY', ['count' => count($history)]);
            send_json(['success' => true, 'data' => $history]);
        } elseif ($action === 'food_suggestions') {
            $query = trim($_GET['q'] ?? '');
            
            // Cerca alimenti univoci sia dal diario sia dalle righe del piano alimentare
            $sql = "
                SELECT DISTINCT food_name FROM (
                    SELECT TRIM(food_name) as food_name FROM food_diary WHERE food_name IS NOT NULL AND TRIM(food_name) != ''
                )
            ";
            
            if ($query !== '') {
                $stmt = $pdo->prepare($sql . " WHERE food_name LIKE ? ORDER BY food_name ASC LIMIT 25");
                $stmt->execute(['%' . $query . '%']);
            } else {
                $stmt = $pdo->query($sql . " ORDER BY food_name ASC LIMIT 25");
            }
            $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Se il diario non ha ancora alimenti o se ce ne sono pochi, arricchiamo anche con le singole linee del piano alimentare
            if (count($suggestions) < 15) {
                $planStmt = $pdo->query("SELECT food_items FROM meal_plan WHERE food_items IS NOT NULL");
                $planLines = [];
                while ($row = $planStmt->fetch(PDO::FETCH_ASSOC)) {
                    $lines = preg_split("/\r\n|\n|\r/", $row['food_items']);
                    foreach ($lines as $line) {
                        $cleaned = trim($line);
                        if (!empty($cleaned)) {
                            $planLines[$cleaned] = true;
                        }
                    }
                }
                foreach (array_keys($planLines) as $item) {
                    if ($query === '' || stripos($item, $query) !== false) {
                        if (!in_array($item, $suggestions)) {
                            $suggestions[] = $item;
                        }
                    }
                    if (count($suggestions) >= 25) break;
                }
                sort($suggestions, SORT_NATURAL | SORT_FLAG_CASE);
            }

            send_json(['success' => true, 'data' => $suggestions]);
        } else {
            send_json(['success' => false, 'error' => 'Azione non valida'], 400);
        }
    } elseif ($method === 'POST') {
        $data = get_json_input();

        if ($action === 'save_weight') {
            $date = $data['date'] ?? date('Y-m-d');
            $weight = (float)($data['weight'] ?? 0);
            $measured_time = trim($data['measured_time'] ?? '');
            $notes = trim($data['notes'] ?? '');

            if ($weight <= 0) {
                send_json(['success' => false, 'error' => 'Inserisci un peso valido'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO weight_logs (date, weight, measured_time, notes) VALUES (?, ?, ?, ?) 
                ON CONFLICT(date) DO UPDATE SET weight = excluded.weight, measured_time = excluded.measured_time, notes = excluded.notes");
            $stmt->execute([$date, $weight, $measured_time, $notes]);

            write_app_log('WEIGHT_SAVE', ['date' => $date, 'weight' => $weight, 'measured_time' => $measured_time]);
            send_json(['success' => true, 'message' => 'Peso registrato con successo']);
        } elseif ($action === 'add_food') {
            $date = $data['date'] ?? date('Y-m-d');
            $meal_type = $data['meal_type'] ?? 'prima_colazione';
            $food_name = trim($data['food_name'] ?? '');
            $grams = isset($data['grams']) && $data['grams'] !== '' ? (float)$data['grams'] : null;
            $notes = trim($data['notes'] ?? '');

            if (empty($food_name)) {
                send_json(['success' => false, 'error' => 'Il nome dell\'alimento è obbligatorio'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO food_diary (date, meal_type, food_name, grams, notes) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$date, $meal_type, $food_name, $grams, $notes]);
            $newId = $pdo->lastInsertId();

            write_app_log('FOOD_ADD', ['id' => $newId, 'date' => $date, 'food' => $food_name, 'grams' => $grams]);
            send_json(['success' => true, 'id' => $newId, 'message' => 'Alimento aggiunto']);
        } elseif ($action === 'delete_food') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                send_json(['success' => false, 'error' => 'ID non valido'], 400);
            }
            $stmt = $pdo->prepare("DELETE FROM food_diary WHERE id = ?");
            $stmt->execute([$id]);
            write_app_log('FOOD_DELETE', ['id' => $id]);
            send_json(['success' => true, 'message' => 'Alimento rimosso']);
        } elseif ($action === 'delete_weight') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                send_json(['success' => false, 'error' => 'ID non valido'], 400);
            }
            $stmt = $pdo->prepare("DELETE FROM weight_logs WHERE id = ?");
            $stmt->execute([$id]);
            write_app_log('WEIGHT_DELETE', ['id' => $id]);
            send_json(['success' => true, 'message' => 'Misurazione peso rimossa']);
        } else {
            send_json(['success' => false, 'error' => 'Azione POST non valida'], 400);
        }
    } else {
        send_json(['success' => false, 'error' => 'Metodo non supportato'], 405);
    }
} catch (Exception $e) {
    write_app_log('DIET_ERROR', ['error' => $e->getMessage()]);
    send_json(['success' => false, 'error' => $e->getMessage()], 500);
}
