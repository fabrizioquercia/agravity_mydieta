<?php
// api/seed.php - Popola dati di esempio dimostrativi per il primo avvio se il database è vuoto
require_once __DIR__ . '/db.php';

$pdo = get_db_connection();

// Verifica se ci sono già farmaci
$countMeds = $pdo->query("SELECT COUNT(*) FROM medications")->fetchColumn();
if ($countMeds == 0) {
    // 1. Inserimento Farmaci di default
    $meds = [
        ['Cardioaspirina', '100 mg', '08:00', '1 compressa dopo colazione'],
        ['Ramipril', '5 mg', '08:30', '1 compressa per pressione arteriosa'],
        ['Metformina', '500 mg', '13:00', '1 compressa a pranzo'],
        ['Atorvastatina', '20 mg', '21:00', '1 compressa la sera prima di dormire']
    ];
    $stmtM = $pdo->prepare("INSERT INTO medications (name, dosage, scheduled_time, notes, active) VALUES (?, ?, ?, ?, 1)");
    foreach ($meds as $m) {
        $stmtM->execute($m);
    }

    // Segna alcune assunzioni per oggi
    $today = date('Y-m-d');
    $stmtI = $pdo->prepare("INSERT INTO medication_intakes (medication_id, intake_date, taken_at, status) VALUES (?, ?, ?, 1)");
    $stmtI->execute([1, $today, $today . ' 08:05:00']);
    $stmtI->execute([2, $today, $today . ' 08:32:00']);

    // 2. Inserimento Piano Alimentare Settimanale standard
    $planData = [
        'lunedi' => [
            'prima_colazione' => "Tè verde con limone\nBiscotti integrali (4 pz)\n1 Mela",
            'seconda_colazione' => "1 Yogurt greco magro (150g)\n3 Noci",
            'pranzo' => "Riso basmati (70g) con zucchine e gamberi\nInsalata mista condita con olio EVO (1 cucchiaio)",
            'merenda' => "1 Frutto di stagione (pera o arancia)\nTisana depurativa",
            'cena' => "Petto di pollo alla piastra (150g)\nVerdure grigliate a volontà\n1 fetta di pane di segale"
        ],
        'martedi' => [
            'prima_colazione' => "Latte parzialmente scremato (200ml)\nFiocchi d'avena (40g)\nFrutti di bosco",
            'seconda_colazione' => "1 Frutto fresco\nMandorle (15g)",
            'pranzo' => "Pasta integrale (80g) al pomodoro fresco e basilico\nBresaola (60g) con rucola e scaglie di grana",
            'merenda' => "Yogurt bianco naturale",
            'cena' => "Filetto di orata al forno con pomodorini\nSpinaci al vapore con limone"
        ],
        'mercoledi' => [
            'prima_colazione' => "Caffè o orzo\nFette biscottate integrali (3 pz) con velo di marmellata light",
            'seconda_colazione' => "1 Mela verde\n2 noci",
            'pranzo' => "Zuppa di legumi (lenticchie e farro)\nFinocchi in pinzimonio leggero",
            'merenda' => "Spremuta d'arancia fresca",
            'cena' => "Frittata di 2 uova con asparagi o erbette\nInsalata di pomodori e cetrioli"
        ],
        'giovedi' => [
            'prima_colazione' => "Tè nero\nPancake d'avena fatto in casa\n1 cucchiaino di miele",
            'seconda_colazione' => "Yogurt greco magro",
            'pranzo' => "Trancio di salmone al vapore (140g)\nPatate lesse (150g)\nFagiolini al vapore",
            'merenda' => "1 Kiwi e una manciata di semi di zucca",
            'cena' => "Minestrone di verdure senza patate\nRicotta fresca vaccina (100g)\nCracker integrali"
        ],
        'venerdi' => [
            'prima_colazione' => "Cappuccino con latte di soia\nBiscotti ai cereali (4 pz)",
            'seconda_colazione' => "Frutta fresca a pezzi",
            'pranzo' => "Insalata di couscous integrale con verdure croccanti e ceci",
            'merenda' => "Barretta ai cereali senza zuccheri aggiunti",
            'cena' => "Tagliata di manzo magro (150g)\nRucola e carote julienne\nPane integrale (40g)"
        ],
        'sabato' => [
            'prima_colazione' => "Tè verde\nPane tostato integrale con ricotta e cannella",
            'seconda_colazione' => "Estratto o centrifuga di mela, carota e zenzero",
            'pranzo' => "Gnocchi di patate al pomodoro semplice\nInsalata verde mista abbondante",
            'merenda' => "1 Frutto di stagione",
            'cena' => "Pizza margherita con impasto integrale o ai cereali (pasto libero controllato)\nInsalata mista"
        ],
        'domenica' => [
            'prima_colazione' => "Caffè filtro o moka\nCiambellone light fatto in casa (1 fetta)\nSucco di pompelmo",
            'seconda_colazione' => "Frutta secca mista (20g)",
            'pranzo' => "Risotto allo zafferano leggero\nArrosto di tacchino al forno con erbe aromatiche\nInsalata mista",
            'merenda' => "Tisana rilassante con biscotto secco",
            'cena' => "Vellutata di zucca e carote\nFormaggio primo sale o fiocchi di latte (120g)\nCrostini di pane integrale"
        ]
    ];

    $stmtP = $pdo->prepare("INSERT INTO meal_plan (day_of_week, meal_type, food_items, notes) VALUES (?, ?, ?, ?)");
    foreach ($planData as $day => $mealsList) {
        foreach ($mealsList as $mealType => $items) {
            $stmtP->execute([$day, $mealType, $items, '']);
        }
    }

    // 3. Misurazioni Pressione recenti
    $sampleBP = [
        [date('Y-m-d 08:15', strtotime('-2 days')), 122, 78, 68, 'Mattina, a riposo'],
        [date('Y-m-d 19:30', strtotime('-2 days')), 125, 82, 72, 'Sera prima di cena'],
        [date('Y-m-d 08:20', strtotime('-1 days')), 119, 76, 65, 'Ottima misurazione'],
        [date('Y-m-d 19:45', strtotime('-1 days')), 126, 80, 70, 'Dopo camminata'],
        [date('Y-m-d 08:10'), 120, 77, 66, 'Misurazione mattutina standard']
    ];
    $stmtBP = $pdo->prepare("INSERT INTO blood_pressure (measured_at, systolic, diastolic, pulse, notes) VALUES (?, ?, ?, ?, ?)");
    foreach ($sampleBP as $bp) {
        $stmtBP->execute($bp);
    }

    // 4. Peso e Diario pasti recenti
    $sampleWeight = [
        [date('Y-m-d', strtotime('-3 days')), 74.8, 'Pesatura mattutina'],
        [date('Y-m-d', strtotime('-2 days')), 74.6, ''],
        [date('Y-m-d', strtotime('-1 days')), 74.4, ''],
        [date('Y-m-d'), 74.2, 'Inizio settimana con costanza']
    ];
    $stmtW = $pdo->prepare("INSERT INTO weight_logs (date, weight, notes) VALUES (?, ?, ?)");
    foreach ($sampleWeight as $sw) {
        $stmtW->execute($sw);
    }

    // Cibi di oggi
    $todayFoods = [
        [$today, 'prima_colazione', 'Tè verde con limone e 4 fette biscottate con marmellata', 210, 42, 4, 3],
        [$today, 'seconda_colazione', 'Yogurt greco 0% (150g) e 3 noci', 165, 6, 15, 9],
        [$today, 'pranzo', 'Pasta integrale al pomodoro fresco (80g) con parmigiano', 420, 68, 14, 8],
        [$today, 'merenda', '1 Mela e una tisana', 85, 20, 0.5, 0.3],
        [$today, 'cena', 'Petto di pollo alla griglia (160g) con insalata ed olio EVO', 350, 2, 38, 12]
    ];
    $stmtF = $pdo->prepare("INSERT INTO food_diary (date, meal_type, food_name, calories, carbs, proteins, fats) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($todayFoods as $tf) {
        $stmtF->execute($tf);
    }

    write_app_log('DB_SEEDED_INITIAL_SAMPLE_DATA');
}

echo "Database inizializzato con successo.";
