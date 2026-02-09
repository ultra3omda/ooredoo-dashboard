<?php
/**
 * Jointure client_abonnement + abonnement_tarifs + abonnement + client + stores (CPM 9)
 * pour affiner la répartition Orange / TT / Ooredoo.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== 1) Jointure CPM 9 : répartition par tarif_id, abonnement_nom, abonnement_store ===\n\n";

$rows = \Illuminate\Support\Facades\DB::select("
    SELECT
        ca.tarif_id,
        at.abonnement_tarifs_prix AS prix,
        at.abonnement_id,
        a.abonnement_nom,
        a.abonnement_store,
        COUNT(DISTINCT ca.client_abonnement_id) AS nb_abo
    FROM client_abonnement ca
    JOIN abonnement_tarifs at ON at.abonnement_tarifs_id = ca.tarif_id
    JOIN abonnement a ON a.abonnement_id = at.abonnement_id
    WHERE ca.country_payments_methods_id = 9
    GROUP BY ca.tarif_id, at.abonnement_id, a.abonnement_nom, a.abonnement_store, at.abonnement_tarifs_prix
    ORDER BY nb_abo DESC
");

echo "tarif_id | prix | abonnement_id | abonnement_nom                    | abonnement_store | nb_abo\n";
echo "---------|------|---------------|-----------------------------------|------------------|--------\n";
foreach ($rows as $r) {
    $nom = mb_substr($r->abonnement_nom ?? '-', 0, 35);
    echo sprintf("%7d | %4s | %13s | %-34s | %16s | %s\n",
        $r->tarif_id,
        $r->prix,
        $r->abonnement_id,
        $nom,
        $r->abonnement_store ?? '-',
        number_format($r->nb_abo)
    );
}

echo "\n=== 2) Par tarif_id : statuts transactions (ORANGE / TT / TARAJI / OOREDOO) ===\n\n";

$tarifIds = array_unique(array_column($rows, 'tarif_id'));
if (empty($tarifIds)) {
    echo "Aucun tarif_id pour CPM 9.\n";
    exit(0);
}

$statsSimple = \Illuminate\Support\Facades\DB::select("
    SELECT
        ca.tarif_id,
        COUNT(DISTINCT CASE WHEN th.status LIKE 'ORANGE_%' THEN ca.client_abonnement_id END) AS orange,
        COUNT(DISTINCT CASE WHEN th.status LIKE 'TT_%' THEN ca.client_abonnement_id END) AS tt,
        COUNT(DISTINCT CASE WHEN th.status LIKE 'TARAJI_%' THEN ca.client_abonnement_id END) AS taraji,
        COUNT(DISTINCT CASE WHEN th.status LIKE '%OOREDOO%' OR th.status LIKE '%DGV%' THEN ca.client_abonnement_id END) AS ooredoo
    FROM client_abonnement ca
    LEFT JOIN transactions_history th ON th.client_id = ca.client_id
      AND (th.status LIKE 'ORANGE_%' OR th.status LIKE 'TT_%' OR th.status LIKE 'TARAJI_%' OR th.status LIKE '%OOREDOO%' OR th.status LIKE '%DGV%')
    WHERE ca.country_payments_methods_id = 9 AND ca.tarif_id IN (" . implode(',', array_map('intval', $tarifIds)) . ")
    GROUP BY ca.tarif_id
    ORDER BY ca.tarif_id
");

echo "tarif_id | Orange | TT    | Taraji | Ooredoo | opérateur dominant\n";
echo "---------|--------|-------|--------|---------|--------------------\n";
foreach ($statsSimple as $s) {
    $o = (int)($s->orange ?? 0);
    $t = (int)($s->tt ?? 0);
    $ta = (int)($s->taraji ?? 0);
    $oo = (int)($s->ooredoo ?? 0);
    $max = max($o, $t, $ta, $oo);
    if ($max == 0) {
        $op = '?';
    } elseif ($oo >= $o && $oo >= $t && $oo >= $ta) {
        $op = 'Ooredoo';
    } elseif ($ta >= $o && $ta >= $t && $ta >= $oo) {
        $op = 'Taraji';
    } elseif ($o >= $t && $o >= $ta && $o >= $oo) {
        $op = 'Orange';
    } else {
        $op = 'TT';
    }
    echo sprintf("%7d | %6s | %5s | %6s | %7s | %s\n",
        $s->tarif_id,
        number_format($o),
        number_format($t),
        number_format($ta),
        number_format($oo),
        $op
    );
}

echo "\n=== 3) Résumé : tarif_id → opérateur (CPM 9) ===\n\n";
$byOp = ['Orange' => [], 'TT' => [], 'Taraji' => [], 'Ooredoo' => []];
foreach ($statsSimple as $s) {
    $o = (int)($s->orange ?? 0);
    $t = (int)($s->tt ?? 0);
    $ta = (int)($s->taraji ?? 0);
    $oo = (int)($s->ooredoo ?? 0);
    $max = max($o, $t, $ta, $oo);
    if ($max == 0) {
        continue;
    }
    if ($oo >= $o && $oo >= $t && $oo >= $ta) {
        $byOp['Ooredoo'][] = $s->tarif_id;
    } elseif ($ta >= $o && $ta >= $t && $ta >= $oo) {
        $byOp['Taraji'][] = $s->tarif_id;
    } elseif ($o >= $t && $o >= $ta && $o >= $oo) {
        $byOp['Orange'][] = $s->tarif_id;
    } else {
        $byOp['TT'][] = $s->tarif_id;
    }
}
foreach ($byOp as $op => $ids) {
    if (!empty($ids)) {
        echo "  " . $op . " : tarif_id " . implode(', ', $ids) . "\n";
    }
}

echo "\n=== 4) Stores (sub_store) pour CPM 9 — échantillon abonnement_store / store_name ===\n\n";
$storeSample = \Illuminate\Support\Facades\DB::select("
    SELECT
        a.abonnement_store,
        s.store_id,
        s.store_name,
        COUNT(DISTINCT ca.client_abonnement_id) AS nb
    FROM client_abonnement ca
    JOIN abonnement_tarifs at ON at.abonnement_tarifs_id = ca.tarif_id
    JOIN abonnement a ON a.abonnement_id = at.abonnement_id
    JOIN client c ON c.client_id = ca.client_id
    LEFT JOIN stores s ON s.store_id = c.sub_store
    WHERE ca.country_payments_methods_id = 9
    GROUP BY a.abonnement_store, s.store_id, s.store_name
    ORDER BY nb DESC
    LIMIT 25
");
echo "abonnement_store | store_id | store_name          | nb_abo\n";
echo "-----------------|---------|---------------------|--------\n";
foreach ($storeSample as $row) {
    echo sprintf("%17s | %7s | %-20s | %s\n",
        $row->abonnement_store ?? '-',
        $row->store_id ?? '-',
        mb_substr($row->store_name ?? '-', 0, 20),
        number_format($row->nb)
    );
}

echo "\nDone.\n";
