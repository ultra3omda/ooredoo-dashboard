<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== 1) Prix distincts dans abonnement_tarifs (Eklektik/DGV ~ 0.3, 0.45, 0.5) ===\n\n";
$prixList = \Illuminate\Support\Facades\DB::table('abonnement_tarifs')
    ->selectRaw('DISTINCT abonnement_tarifs_prix')
    ->orderBy('abonnement_tarifs_prix')
    ->pluck('abonnement_tarifs_prix');
foreach ($prixList as $p) {
    echo "  prix = " . $p . "\n";
}

echo "\n=== 2) Tarifs à 0.3 (daily Eklektik/DGV), 0.45 et 0.5 (Taraji Privileges?) ===\n\n";
$tarifs03 = \Illuminate\Support\Facades\DB::table('abonnement_tarifs')
    ->where('abonnement_tarifs_prix', 0.3)
    ->get(['abonnement_tarifs_id', 'abonnement_tarifs_prix']);
$tarifs045 = \Illuminate\Support\Facades\DB::table('abonnement_tarifs')
    ->whereRaw('ROUND(abonnement_tarifs_prix, 2) = 0.45 OR abonnement_tarifs_prix = 0.450')
    ->get(['abonnement_tarifs_id', 'abonnement_tarifs_prix']);
$tarifs05 = \Illuminate\Support\Facades\DB::table('abonnement_tarifs')
    ->where('abonnement_tarifs_prix', 0.5)
    ->get(['abonnement_tarifs_id', 'abonnement_tarifs_prix']);

echo "Prix 0.3   : " . $tarifs03->pluck('abonnement_tarifs_id')->implode(', ') . "\n";
echo "Prix 0.45  : " . $tarifs045->pluck('abonnement_tarifs_id')->implode(', ') . "\n";
echo "Prix 0.5   : " . $tarifs05->pluck('abonnement_tarifs_id')->implode(', ') . "\n";

$allEklektikTarifIds = $tarifs03->pluck('abonnement_tarifs_id')
    ->merge($tarifs045->pluck('abonnement_tarifs_id'))
    ->merge($tarifs05->pluck('abonnement_tarifs_id'))
    ->unique()->values()->all();

echo "\n=== 3) Répartition par opérateur (ORANGE / TT / TARAJI) par tarif_id ===\n";
echo "Requête agrégée (ca.tarif_id + statuts th)...\n\n";

// Sous-requête : un enregistrement par (client_id, opérateur) pour limiter la jointure
$sql = "
SELECT
    ca.tarif_id,
    at.abonnement_tarifs_prix AS prix,
    COUNT(DISTINCT CASE WHEN op.op_type = 'ORANGE' THEN ca.client_abonnement_id END) AS orange,
    COUNT(DISTINCT CASE WHEN op.op_type = 'TT' THEN ca.client_abonnement_id END) AS tt,
    COUNT(DISTINCT CASE WHEN op.op_type = 'TARAJI' THEN ca.client_abonnement_id END) AS taraji
FROM client_abonnement ca
LEFT JOIN abonnement_tarifs at ON at.abonnement_tarifs_id = ca.tarif_id
LEFT JOIN (
    SELECT DISTINCT th.client_id,
      CASE
        WHEN th.status LIKE 'ORANGE_%' THEN 'ORANGE'
        WHEN th.status LIKE 'TT_%' THEN 'TT'
        WHEN th.status LIKE 'TARAJI_%' THEN 'TARAJI'
      END AS op_type
    FROM transactions_history th
    WHERE th.status LIKE 'ORANGE_%' OR th.status LIKE 'TT_%' OR th.status LIKE 'TARAJI_%'
) op ON op.client_id = ca.client_id AND op.op_type IS NOT NULL
WHERE ca.tarif_id IN (" . implode(',', array_map('intval', $allEklektikTarifIds)) . ")
GROUP BY ca.tarif_id, at.abonnement_tarifs_prix
ORDER BY at.abonnement_tarifs_prix, ca.tarif_id
";

$rows = \Illuminate\Support\Facades\DB::select($sql);
echo "tarif_id | prix   | Orange | TT  | Taraji | opérateur dominant\n";
echo "---------|--------|--------|-----|--------|--------------------\n";
foreach ($rows as $r) {
    $max = max($r->orange ?? 0, $r->tt ?? 0, $r->taraji ?? 0);
    if ($max == 0) {
        $op = '?';
    } elseif ($r->taraji >= $r->orange && $r->taraji >= $r->tt) {
        $op = 'Taraji';
    } elseif ($r->orange >= $r->tt) {
        $op = 'Orange';
    } else {
        $op = 'TT';
    }
    echo sprintf("%7d | %6s | %6s | %3s | %6s | %s\n",
        $r->tarif_id,
        $r->prix,
        number_format($r->orange ?? 0),
        number_format($r->tt ?? 0),
        number_format($r->taraji ?? 0),
        $op
    );
}

echo "\n=== Résumé ===\n";
echo "- Offres 0.3 DT : daily Eklektik (Orange, TT) ou DGV (tarif 39).\n";
echo "- Offres 0.45 / 0.5 DT : Taraji Privileges (prix différent).\n";
echo "- Répartition opérateur = selon statuts ORANGE_*, TT_*, TARAJI_* dans transactions_history.\n";
