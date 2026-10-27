<?php
// api/pressure.php - Gestione Pressione Arteriosa (PA)
require_once __DIR__ . '/db.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        if ($action === 'list') {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $stmt = $pdo->prepare("SELECT * FROM blood_pressure ORDER BY measured_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $list = $stmt->fetchAll();

            // Calcolo medie
            $stats = ['avg_systolic' => 0, 'avg_diastolic' => 0, 'avg_pulse' => 0, 'total' => count($list)];
            if ($stats['total'] > 0) {
                $sumSys = 0; $sumDia = 0; $sumPulse = 0;
                foreach ($list as $row) {
                    $sumSys += $row['systolic'];
                    $sumDia += $row['diastolic'];
                    $sumPulse += $row['pulse'];
                }
                $stats['avg_systolic'] = round($sumSys / $stats['total'], 1);
                $stats['avg_diastolic'] = round($sumDia / $stats['total'], 1);
                $stats['avg_pulse'] = round($sumPulse / $stats['total'], 1);
            }

            write_app_log('BP_LIST_FETCH', ['count' => count($list)]);
            send_json(['success' => true, 'data' => $list, 'stats' => $stats]);
        } else {
            send_json(['success' => false, 'error' => 'Azione non valida'], 400);
        }
    } elseif ($method === 'POST') {
        $data = get_json_input();

        if ($action === 'add') {
            $measured_at = trim($data['measured_at'] ?? date('Y-m-d H:i'));
            $systolic = (int)($data['systolic'] ?? 0);
            $diastolic = (int)($data['diastolic'] ?? 0);
            $pulse = (int)($data['pulse'] ?? 0);
            $notes = trim($data['notes'] ?? '');

            if ($systolic <= 40 || $systolic >= 300 || $diastolic <= 30 || $diastolic >= 200) {
                send_json(['success' => false, 'error' => 'Valori pressori non realistici (es. Sistolica 120, Diastolica 80)'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO blood_pressure (measured_at, systolic, diastolic, pulse, notes) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$measured_at, $systolic, $diastolic, $pulse, $notes]);
            $newId = $pdo->lastInsertId();

            write_app_log('BP_ADD', ['id' => $newId, 'sys' => $systolic, 'dia' => $diastolic, 'pulse' => $pulse]);
            send_json(['success' => true, 'id' => $newId, 'message' => 'Misurazione registrata']);
        } elseif ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                send_json(['success' => false, 'error' => 'ID non valido'], 400);
            }
            $stmt = $pdo->prepare("DELETE FROM blood_pressure WHERE id = ?");
            $stmt->execute([$id]);
            write_app_log('BP_DELETE', ['id' => $id]);
            send_json(['success' => true, 'message' => 'Misurazione rimossa']);
        } else {
            send_json(['success' => false, 'error' => 'Azione non valida'], 400);
        }
    } else {
        send_json(['success' => false, 'error' => 'Metodo non consentito'], 405);
    }
} catch (Exception $e) {
    write_app_log('BP_ERROR', ['error' => $e->getMessage()]);
    send_json(['success' => false, 'error' => $e->getMessage()], 500);
}
