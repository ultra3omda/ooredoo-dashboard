<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "        ANALYSE COMPLÈTE DE TOUS LES STATUTS DGV/OOREDOO\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Récupérer tous les statuts OOREDOO distincts
$allStatuses = DB::select("
    SELECT DISTINCT status
    FROM transactions_history
    WHERE status LIKE '%OORE%'
    ORDER BY status
");

echo "📋 STATUTS TROUVÉS: " . count($allStatuses) . "\n\n";

foreach ($allStatuses as $statusObj) {
    $status = $statusObj->status;
    
    echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║ STATUT: " . str_pad($status, 74) . "║\n";
    echo "╠═══════════════════════════════════════════════════════════════════════════════╣\n\n";
    
    // Compter les transactions pour ce statut
    $count = DB::table('transactions_history')
        ->where('status', $status)
        ->count();
    
    echo "  📊 TOTAL TRANSACTIONS: " . number_format($count) . "\n\n";
    
    // Statistiques sur le champ result
    $resultStats = DB::select("
        SELECT 
            SUM(CASE WHEN result IS NULL THEN 1 ELSE 0 END) as null_count,
            SUM(CASE WHEN result IS NOT NULL AND result != '' THEN 1 ELSE 0 END) as filled_count,
            SUM(CASE WHEN result IS NOT NULL AND JSON_VALID(result) THEN 1 ELSE 0 END) as json_valid
        FROM transactions_history
        WHERE status = ?
    ", [$status])[0];
    
    echo "  📝 CHAMP 'result':\n";
    echo "     - NULL: " . number_format($resultStats->null_count) . 
         " (" . round(($resultStats->null_count / $count) * 100, 1) . "%)\n";
    echo "     - Rempli: " . number_format($resultStats->filled_count) . 
         " (" . round(($resultStats->filled_count / $count) * 100, 1) . "%)\n";
    echo "     - JSON valide: " . number_format($resultStats->json_valid) . 
         " (" . round(($resultStats->json_valid / $count) * 100, 1) . "%)\n\n";
    
    // Si result est rempli, prendre des exemples
    if ($resultStats->filled_count > 0) {
        echo "  📄 EXEMPLES DE 'result' (3 exemples):\n";
        echo str_repeat('  ─', 39) . "\n\n";
        
        $examples = DB::table('transactions_history')
            ->where('status', $status)
            ->whereNotNull('result')
            ->where('result', '!=', '')
            ->limit(3)
            ->get(['transaction_history_id', 'result', 'created_at', 'client_id']);
        
        foreach ($examples as $i => $ex) {
            echo "  ┌─ Exemple " . ($i + 1) . " ────────────────────────────────────────────────────────┐\n";
            echo "  │ ID Transaction: {$ex->transaction_history_id}\n";
            echo "  │ Client ID: {$ex->client_id}\n";
            echo "  │ Date: {$ex->created_at}\n";
            echo "  │\n";
            
            // Afficher le result brut (tronqué si trop long)
            if (strlen($ex->result) > 500) {
                echo "  │ Result (500 premiers caractères):\n";
                $resultDisplay = substr($ex->result, 0, 500) . "...";
            } else {
                echo "  │ Result:\n";
                $resultDisplay = $ex->result;
            }
            
            // Indenter chaque ligne du result
            $lines = explode("\n", $resultDisplay);
            foreach ($lines as $line) {
                if (strlen($line) > 70) {
                    $wrapped = wordwrap($line, 70, "\n", true);
                    foreach (explode("\n", $wrapped) as $wLine) {
                        echo "  │   " . $wLine . "\n";
                    }
                } else {
                    echo "  │   " . $line . "\n";
                }
            }
            
            // Parser le JSON si possible
            $json = json_decode($ex->result, true);
            if ($json && is_array($json)) {
                echo "  │\n";
                echo "  │ 🔍 STRUCTURE JSON:\n";
                echo "  │\n";
                
                // Clés principales
                echo "  │   Clés principales: " . implode(', ', array_keys($json)) . "\n";
                
                // Valeurs importantes
                if (isset($json['status'])) 
                    echo "  │   • status: {$json['status']}\n";
                if (isset($json['type'])) 
                    echo "  │   • type: {$json['type']}\n";
                if (isset($json['code'])) 
                    echo "  │   • code: {$json['code']}\n";
                if (isset($json['message'])) 
                    echo "  │   • message: " . substr($json['message'], 0, 50) . (strlen($json['message']) > 50 ? '...' : '') . "\n";
                if (isset($json['totalCharged'])) 
                    echo "  │   • totalCharged: {$json['totalCharged']}\n";
                if (isset($json['mnoDeliveryCode'])) 
                    echo "  │   • mnoDeliveryCode: {$json['mnoDeliveryCode']}\n";
                
                // Pour les réponses d'erreur
                if (isset($json['detail'])) 
                    echo "  │   • detail: " . substr($json['detail'], 0, 50) . "\n";
                
                // Pour les données imbriquées
                if (isset($json['data']) && is_array($json['data'])) {
                    echo "  │   • data.status: " . ($json['data']['status'] ?? 'N/A') . "\n";
                    echo "  │   • data.type: " . ($json['data']['type'] ?? 'N/A') . "\n";
                }
                
                // Pour les informations de subscription
                if (isset($json['subscription']) && is_array($json['subscription'])) {
                    echo "  │   • subscription.status: " . ($json['subscription']['status'] ?? 'N/A') . "\n";
                }
                
                // Pour les informations d'offre
                if (isset($json['offer']) && is_array($json['offer'])) {
                    echo "  │   • offer.id: " . ($json['offer']['id'] ?? 'N/A') . "\n";
                    echo "  │   • offer.commercialName: " . ($json['offer']['commercialName'] ?? 'N/A') . "\n";
                }
            }
            
            echo "  └──────────────────────────────────────────────────────────────────────┘\n\n";
        }
    } else {
        echo "  ℹ️  Pas de 'result' rempli pour ce statut (100% NULL)\n\n";
    }
    
    // Statistiques par mois pour ce statut
    echo "  📅 RÉPARTITION PAR MOIS (2024):\n";
    echo str_repeat('  ─', 39) . "\n";
    
    $monthly = DB::select("
        SELECT 
            MONTH(created_at) as mois,
            COUNT(*) as count
        FROM transactions_history
        WHERE status = ?
        AND YEAR(created_at) = 2024
        GROUP BY MONTH(created_at)
        ORDER BY MONTH(created_at)
    ", [$status]);
    
    if (count($monthly) > 0) {
        foreach ($monthly as $m) {
            $moisNom = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 
                        'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'][$m->mois];
            echo sprintf("     %s 2024: %8s\n", $moisNom, number_format($m->count));
        }
    } else {
        echo "     Aucune transaction en 2024\n";
    }
    
    echo "\n╚═══════════════════════════════════════════════════════════════════════════════╝\n\n\n";
}

echo "\n\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "                          RÉSUMÉ GLOBAL\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Tableau récapitulatif
$summary = DB::select("
    SELECT 
        status,
        COUNT(*) as total,
        SUM(CASE WHEN result IS NOT NULL AND result != '' THEN 1 ELSE 0 END) as has_result
    FROM transactions_history
    WHERE status LIKE '%OORE%'
    GROUP BY status
    ORDER BY COUNT(*) DESC
");

echo "Statut                                           │ Total Trans. │ Avec Result\n";
echo str_repeat('─', 78) . "\n";

foreach ($summary as $s) {
    $pctResult = $s->total > 0 ? round(($s->has_result / $s->total) * 100) : 0;
    echo sprintf("%-47s │ %12s │ %3d%%\n", 
        substr($s->status, 0, 47), 
        number_format($s->total), 
        $pctResult
    );
}

echo "\n💡 GUIDE D'INTERPRÉTATION:\n";
echo str_repeat('═', 78) . "\n";
echo "• Statuts avec result = NULL: Transactions internes (pas de réponse API DGV)\n";
echo "• Statuts avec result JSON: Réponses de l'API DGV\n";
echo "• status = 'SUCCESS': Opération réussie\n";
echo "• status = 'ERROR' ou 'FAILED': Opération échouée\n";
echo "• type = 'SUBSCRIPTION': Opération d'abonnement\n";
echo "• type = 'INVOICE': Opération de facturation\n";
echo "• type = 'EXPIRATION': Désabonnement/expiration\n";

