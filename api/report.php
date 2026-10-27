<?php
// api/report.php - Dati aggregati per il Report Medico Settimanale
require_once __DIR__ . '/db.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        // 1. Dati Peso nell'intervallo
        $stmtW = $pdo->prepare("SELECT * FROM weight_logs WHERE date BETWEEN ? AND ? ORDER BY date ASC");
        $stmtW->execute([$startDate, $endDate]);
        $weights = $stmtW->fetchAll();

        // 2. Dati Dieta raggruppati per data nell'intervallo
        $stmtD = $pdo->prepare("
            SELECT date, 
                   COUNT(*) as items_count, 
                   SUM(CASE WHEN grams IS NOT NULL THEN grams ELSE 0 END) as total_grams
            FROM food_diary 
            WHERE date BETWEEN ? AND ? 
            GROUP BY date 
            ORDER BY date ASC
        ");
        $stmtD->execute([$startDate, $endDate]);
        $dietSummary = $stmtD->fetchAll();

        // Dettaglio cibi registrati nella settimana
        $stmtFoods = $pdo->prepare("SELECT * FROM food_diary WHERE date BETWEEN ? AND ? ORDER BY date ASC, meal_type ASC");
        $stmtFoods->execute([$startDate, $endDate]);
        $allFoods = $stmtFoods->fetchAll();

        // 3. Pressione Arteriosa nell'intervallo
        $stmtP = $pdo->prepare("
            SELECT * FROM blood_pressure 
            WHERE substr(measured_at, 1, 10) BETWEEN ? AND ? 
            ORDER BY measured_at ASC
        ");
        $stmtP->execute([$startDate, $endDate]);
        $pressures = $stmtP->fetchAll();

        // Statistiche PA
        $bpStats = [
            'count' => count($pressures),
            'avg_sys' => 0,
            'avg_dia' => 0,
            'avg_pulse' => 0,
            'max_sys' => 0,
            'min_sys' => 999
        ];
        if (count($pressures) > 0) {
            $sumS = 0; $sumD = 0; $sumP = 0;
            foreach ($pressures as $p) {
                $sumS += $p['systolic'];
                $sumD += $p['diastolic'];
                $sumP += $p['pulse'];
                if ($p['systolic'] > $bpStats['max_sys']) $bpStats['max_sys'] = $p['systolic'];
                if ($p['systolic'] < $bpStats['min_sys']) $bpStats['min_sys'] = $p['systolic'];
            }
            $bpStats['avg_sys'] = round($sumS / count($pressures), 1);
            $bpStats['avg_dia'] = round($sumD / count($pressures), 1);
            $bpStats['avg_pulse'] = round($sumP / count($pressures), 1);
        } else {
            $bpStats['min_sys'] = 0;
        }

        // 4. Farmaci e Somministrazioni della settimana
        $stmtMeds = $pdo->query("SELECT * FROM medications WHERE active = 1 ORDER BY scheduled_time ASC");
        $medications = $stmtMeds->fetchAll();

        $stmtIntakes = $pdo->prepare("
            SELECT medication_id, intake_date, status, taken_at 
            FROM medication_intakes 
            WHERE intake_date BETWEEN ? AND ?
        ");
        $stmtIntakes->execute([$startDate, $endDate]);
        $rawIntakes = $stmtIntakes->fetchAll();

        $intakeMap = [];
        foreach ($rawIntakes as $in) {
            $intakeMap[$in['medication_id'] . '_' . $in['intake_date']] = $in;
        }

        // 5. Piano alimentare di riferimento
        $stmtPlan = $pdo->query("SELECT * FROM meal_plan");
        $planRows = $stmtPlan->fetchAll();

        write_app_log('REPORT_GENERATED', ['start' => $startDate, 'end' => $endDate]);

        send_json([
            'success' => true,
            'range' => ['start' => $startDate, 'end' => $endDate],
            'weights' => $weights,
            'diet_summary' => $dietSummary,
            'all_foods' => $allFoods,
            'pressures' => $pressures,
            'bp_stats' => $bpStats,
            'medications' => $medications,
            'intake_map' => $intakeMap,
            'meal_plan' => $planRows
        ]);
    } else {
        send_json(['success' => false, 'error' => 'Metodo non supportato'], 405);
    }
} catch (Exception $e) {
    write_app_log('REPORT_ERROR', ['error' => $e->getMessage()]);
    send_json(['success' => false, 'error' => $e->getMessage()], 500);
}
