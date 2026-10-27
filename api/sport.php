<?php
// api/sport.php - Gestione Attività Sportive Giornaliere
require_once __DIR__ . '/db.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        if ($action === 'list') {
            $date = $_GET['date'] ?? '';
            if (!empty($date)) {
                $stmt = $pdo->prepare("SELECT * FROM sport_logs WHERE date = ? ORDER BY start_time ASC, id ASC");
                $stmt->execute([$date]);
            } else {
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                $stmt = $pdo->prepare("SELECT * FROM sport_logs ORDER BY date DESC, start_time DESC LIMIT ?");
                $stmt->execute([$limit]);
            }
            $logs = $stmt->fetchAll();
            write_app_log('SPORT_GET_LIST', ['date' => $date, 'count' => count($logs)]);
            send_json(['success' => true, 'data' => $logs]);
        } else {
            send_json(['success' => false, 'error' => 'Azione non valida'], 400);
        }
    } elseif ($method === 'POST') {
        $data = get_json_input();

        if ($action === 'add') {
            $date = trim($data['date'] ?? date('Y-m-d'));
            $start_time = trim($data['start_time'] ?? '');
            $end_time = trim($data['end_time'] ?? '');
            $activity = trim($data['activity'] ?? '');
            $notes = trim($data['notes'] ?? '');

            if (empty($date) || empty($activity)) {
                send_json(['success' => false, 'error' => 'Data e attività svolta sono obbligatori'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO sport_logs (date, start_time, end_time, activity, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$date, $start_time, $end_time, $activity, $notes]);
            $newId = $pdo->lastInsertId();

            write_app_log('SPORT_ADD', ['id' => $newId, 'date' => $date, 'activity' => $activity]);
            send_json(['success' => true, 'id' => $newId, 'message' => 'Attività sportiva registrata con successo']);
        } elseif ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                send_json(['success' => false, 'error' => 'ID non valido'], 400);
            }
            $stmt = $pdo->prepare("DELETE FROM sport_logs WHERE id = ?");
            $stmt->execute([$id]);

            write_app_log('SPORT_DELETE', ['id' => $id]);
            send_json(['success' => true, 'message' => 'Attività sportiva eliminata']);
        } else {
            send_json(['success' => false, 'error' => 'Azione non valida'], 400);
        }
    } else {
        send_json(['success' => false, 'error' => 'Metodo non supportato'], 405);
    }
} catch (Exception $e) {
    write_app_log('SPORT_ERROR', ['error' => $e->getMessage()]);
    send_json(['success' => false, 'error' => $e->getMessage()], 500);
}
