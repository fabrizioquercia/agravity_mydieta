<?php
// api/medications.php - Gestione Farmaci e Spunte Somministrazione Giornaliera
require_once __DIR__ . '/db.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        if ($action === 'daily_status') {
            $date = $_GET['date'] ?? date('Y-m-d');
            
            // Preleva tutti i farmaci attivi con eventuale stato di assunzione per la data richiesta
            $stmt = $pdo->prepare("
                SELECT m.*, 
                       mi.id as intake_id, 
                       mi.status as intake_status,
                       mi.taken_at
                FROM medications m
                LEFT JOIN medication_intakes mi 
                       ON m.id = mi.medication_id AND mi.intake_date = ?
                WHERE m.active = 1
                ORDER BY m.scheduled_time ASC, m.name ASC
            ");
            $stmt->execute([$date]);
            $meds = $stmt->fetchAll();

            write_app_log('MEDS_DAILY_STATUS', ['date' => $date, 'count' => count($meds)]);
            send_json(['success' => true, 'date' => $date, 'data' => $meds]);
        } elseif ($action === 'list_all') {
            $stmt = $pdo->query("SELECT * FROM medications ORDER BY active DESC, scheduled_time ASC");
            $meds = $stmt->fetchAll();
            send_json(['success' => true, 'data' => $meds]);
        } else {
            send_json(['success' => false, 'error' => 'Azione non valida'], 400);
        }
    } elseif ($method === 'POST') {
        $data = get_json_input();

        if ($action === 'toggle_intake') {
            $medication_id = (int)($data['medication_id'] ?? 0);
            $date = trim($data['date'] ?? date('Y-m-d'));
            $status = isset($data['status']) ? (int)$data['status'] : 1; // 1 o 0

            if ($medication_id <= 0) {
                send_json(['success' => false, 'error' => 'ID farmaco non valido'], 422);
            }

            if ($status === 1) {
                $now = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO medication_intakes (medication_id, intake_date, taken_at, status)
                    VALUES (?, ?, ?, 1)
                    ON CONFLICT(medication_id, intake_date)
                    DO UPDATE SET status = 1, taken_at = ?");
                $stmt->execute([$medication_id, $date, $now, $now]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM medication_intakes WHERE medication_id = ? AND intake_date = ?");
                $stmt->execute([$medication_id, $date]);
            }

            write_app_log('MED_TOGGLE_INTAKE', ['medication_id' => $medication_id, 'date' => $date, 'status' => $status]);
            send_json(['success' => true, 'status' => $status, 'message' => 'Stato somministrazione aggiornato']);
        } elseif ($action === 'add') {
            $name = trim($data['name'] ?? '');
            $dosage = trim($data['dosage'] ?? '');
            $scheduled_time = trim($data['scheduled_time'] ?? '08:00');
            $notes = trim($data['notes'] ?? '');

            if (empty($name)) {
                send_json(['success' => false, 'error' => 'Il nome del farmaco è obbligatorio'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO medications (name, dosage, scheduled_time, notes, active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$name, $dosage, $scheduled_time, $notes]);
            $newId = $pdo->lastInsertId();

            write_app_log('MED_ADD', ['id' => $newId, 'name' => $name]);
            send_json(['success' => true, 'id' => $newId, 'message' => 'Farmaco aggiunto']);
        } elseif ($action === 'update') {
            $id = (int)($data['id'] ?? 0);
            $name = trim($data['name'] ?? '');
            $dosage = trim($data['dosage'] ?? '');
            $scheduled_time = trim($data['scheduled_time'] ?? '08:00');
            $notes = trim($data['notes'] ?? '');
            $active = isset($data['active']) ? (int)$data['active'] : 1;

            if ($id <= 0 || empty($name)) {
                send_json(['success' => false, 'error' => 'Dati farmaco non validi'], 422);
            }

            $stmt = $pdo->prepare("UPDATE medications SET name = ?, dosage = ?, scheduled_time = ?, notes = ?, active = ? WHERE id = ?");
            $stmt->execute([$name, $dosage, $scheduled_time, $notes, $active, $id]);

            write_app_log('MED_UPDATE', ['id' => $id, 'name' => $name]);
            send_json(['success' => true, 'message' => 'Farmaco aggiornato']);
        } elseif ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                send_json(['success' => false, 'error' => 'ID non valido'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM medications WHERE id = ?");
            $stmt->execute([$id]);

            write_app_log('MED_DELETE', ['id' => $id]);
            send_json(['success' => true, 'message' => 'Farmaco eliminato']);
        } else {
            send_json(['success' => false, 'error' => 'Azione non valida'], 400);
        }
    } else {
        send_json(['success' => false, 'error' => 'Metodo non consentito'], 405);
    }
} catch (Exception $e) {
    write_app_log('MED_ERROR', ['error' => $e->getMessage()]);
    send_json(['success' => false, 'error' => $e->getMessage()], 500);
}
