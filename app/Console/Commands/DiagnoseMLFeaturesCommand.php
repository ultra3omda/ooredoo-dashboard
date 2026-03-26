<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLMultiOperatorFeatureService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseMLFeaturesCommand extends Command
{
    protected $signature = 'ml:diagnose-features
                            {--client-id= : Client à diagnostiquer}
                            {--date= : Date de calcul (YYYY-MM-DD, défaut: aujourd\'hui)}';

    protected $description = 'Diagnostique pourquoi les features ML sont à 0/NULL : comptes transactions et colonnes écrites';

    public function handle()
    {
        $clientId = $this->option('client-id');
        $dateStr = $this->option('date') ?: now()->toDateString();
        $calculationDate = Carbon::parse($dateStr);
        $startDate = $calculationDate->copy()->subMonths(6);

        if (!$clientId) {
            $row = DB::table('ml_client_features')
                ->where('calculation_date', $dateStr)
                ->where(function ($q) {
                    $q->whereNull('timwe_success_rate')
                      ->orWhere('timwe_total_attempts', 0);
                })
                ->first();
            if ($row) {
                $clientId = $row->client_id;
                $this->info("Client non fourni : utilisation d'un client avec features à 0/NULL : client_id = {$clientId}");
            } else {
                $row = DB::table('ml_client_features')->where('calculation_date', $dateStr)->first();
                $clientId = $row ? $row->client_id : null;
            }
        }

        if (!$clientId) {
            $this->warn('Aucun client trouvé pour cette date. Indiquez --client-id=XXX et --date=YYYY-MM-DD');
            return 1;
        }

        $clientId = (int) $clientId;
        $this->info("Diagnostic client_id = {$clientId}, calculation_date = {$dateStr}");
        $this->info("Fenêtre transactions : {$startDate->toDateString()} → {$calculationDate->toDateString()}");
        $this->newLine();

        // Comptes bruts transactions_history
        $timweCount = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('th.created_at', [$startDate, $calculationDate])
            ->whereNotNull('th.result')
            ->count();

        $eklektikCount = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', 'ORANGE_%')
                  ->orWhere('th.status', 'LIKE', 'TARAJI_%')
                  ->orWhere('th.status', 'LIKE', 'TT_%')
                  ->orWhere('th.status', 'LIKE', '%EKLEKTIK%')
                  ->orWhere('th.status', 'LIKE', 'EKLECTIC_%')
                  ->orWhere('th.status', 'LIKE', '%CLUB_PRIVILEGE%');
            })
            ->whereBetween('th.created_at', [$startDate, $calculationDate])
            ->count();

        $ooredooCount = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%OOREDOO%')
                  ->orWhere('th.status', 'LIKE', '%DGV%');
            })
            ->whereBetween('th.created_at', [$startDate, $calculationDate])
            ->count();

        $this->table(
            ['Source', 'Nombre de lignes'],
            [
                ['Timwe (RENEWED_NOTIF / CHARGE_DELIVERED)', $timweCount],
                ['Eklektik (ORANGE_ / TARAJI_ / TT_)', $eklektikCount],
                ['Ooredoo/DGV (OOREDOO_)', $ooredooCount],
            ]
        );

        if ($timweCount > 0) {
            $sample = DB::table('transactions_history')
                ->where('client_id', $clientId)
                ->where(function ($q) {
                    $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                      ->orWhere('status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                })
                ->whereBetween('created_at', [$startDate, $calculationDate])
                ->whereNotNull('result')
                ->first();
            $this->info('Exemple Timwe — status: ' . ($sample->status ?? 'N/A'));
            $result = $sample && $sample->result ? json_decode($sample->result, true) : null;
            if (is_array($result)) {
                $keys = array_keys($result);
                $this->line('Clés result (racine): ' . implode(', ', $keys));
                $hasPpid = isset($result['pricepointId']) || isset($result['response']['pricepointId']) || isset($result['data']['pricepointId']);
                $hasMno = isset($result['mnoDeliveryCode']) || isset($result['response']['mnoDeliveryCode']) || isset($result['data']['mnoDeliveryCode']);
                $this->line('pricepointId présent (racine/response/data): ' . ($hasPpid ? 'oui' : 'non'));
                $this->line('mnoDeliveryCode présent: ' . ($hasMno ? 'oui' : 'non'));
            } else {
                $this->warn('result vide ou non JSON.');
            }
            $this->newLine();
        }

        $service = app(MLMultiOperatorFeatureService::class);
        $features = $service->extractClientFeatures($clientId, $calculationDate);

        $tableColumns = Schema::getColumnListing('ml_client_features');
        $allowed = array_flip($tableColumns);
        $filtered = array_intersect_key($features, $allowed);
        $dropped = array_diff_key($features, $allowed);

        $this->info('Colonnes envoyées à l\'upsert (présentes dans ml_client_features): ' . count($filtered));
        if (!empty($dropped)) {
            $this->warn('Clés calculées mais absentes de la table (non écrites): ' . implode(', ', array_keys($dropped)));
        }

        $this->newLine();
        $this->info('Exemples de valeurs extraites:');
        foreach (['timwe_success_rate', 'timwe_total_attempts', 'timwe_has_activity', 'eklektik_success_rate', 'ooredoo_success_rate', 'daily_engagement_rate'] as $k) {
            if (array_key_exists($k, $features)) {
                $this->line("  {$k} = " . json_encode($features[$k]));
            }
        }

        return 0;
    }
}
