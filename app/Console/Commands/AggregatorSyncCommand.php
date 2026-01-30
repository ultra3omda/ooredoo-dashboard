<?php

namespace App\Console\Commands;

use App\Models\AggregatorOfferMap;
use App\Models\AggregatorSubscription;
use App\Models\ClientAbonnement;
use App\Models\Client;
use App\Services\AggregatorClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AggregatorSyncCommand extends Command
{
    protected $signature = 'aggregator:sync {--start=} {--end=} {--limit=1000}';
    protected $description = 'Synchronise les abonnements depuis l\'agrégateur dans la table de staging';

    public function handle(AggregatorClient $client): int
    {
        $start = $this->option('start') ? Carbon::parse($this->option('start')) : now()->subDays(7);
        $end = $this->option('end') ? Carbon::parse($this->option('end')) : now();
        $limit = (int) $this->option('limit');

        $this->info("Sync agrégateur du {$start} au {$end} (limit={$limit})");

        $query = ClientAbonnement::query()
            ->select('client_abonnement.client_id', 'client_abonnement.tarif_id', 'client_abonnement.client_abonnement_id')
            ->join('client', 'client.client_id', '=', 'client_abonnement.client_id')
            ->whereBetween('client_abonnement.client_abonnement_creation', [$start, $end])
            ->limit($limit);

        $rows = $query->get();
        $this->info('Candidates: ' . $rows->count());

        $map = AggregatorOfferMap::query()->get()->keyBy('abonnement_id');

        $synced = 0;
        $skipped = 0;
        
        foreach ($rows as $row) {
            /** @var Client $clientModel */
            $clientModel = Client::find($row->client_id);
            if (!$clientModel) {
                $skipped++;
                continue;
            }

            $msisdn = $clientModel->client_telephone;

            // Determine abonnement_id from tarif_id via join
            $abonnementId = DB::table('abonnement_tarifs')
                ->where('abonnement_tarifs_id', $row->tarif_id)
                ->value('abonnement_id');

            if (!$abonnementId) {
                $skipped++;
                continue;
            }

            $mapRow = $map->get($abonnementId);
            if (!$mapRow || empty($mapRow->aggregator_offre_id)) {
                $skipped++;
                continue;
            }

            $offreId = $mapRow->aggregator_offre_id;
            $subscriptionId = $client->findSubscriptionId($msisdn, (string) $offreId);
            if (!$subscriptionId) {
                $skipped++;
                continue;
            }

            $payload = $client->getSubscription($subscriptionId);
            if (!$payload || empty($payload['user'])) {
                $skipped++;
                continue;
            }

            $u = $payload['user'];
            AggregatorSubscription::updateOrCreate(
                [
                    'msisdn' => $u['msisdn'] ?? $msisdn,
                    'subscription_id' => (string) ($u['id'] ?? $subscriptionId),
                ],
                [
                    'offre_id' => (string) ($u['offre_id'] ?? $offreId),
                    'service_id' => (string) ($u['service_id'] ?? null),
                    'subscription_date' => self::toNullableDate($u['subscription_date'] ?? null),
                    'unsubscription_date' => self::toNullableDate($u['unsubscription_date'] ?? null),
                    'expire_date' => self::toNullableDate($u['expire_date'] ?? null),
                    'status' => (string) ($u['status'] ?? null),
                    'state' => (string) ($u['state'] ?? null),
                    'first_successbilling' => self::toNullableDate($u['first_successbilling'] ?? null),
                    'last_successbilling' => self::toNullableDate($u['last_successbilling'] ?? null),
                    'success_billing' => isset($u['success_billing']) ? (int) $u['success_billing'] : null,
                    'last_status_update' => self::toNullableDate($u['last_status_update'] ?? null),
                ]
            );

            $synced++;
        }

        $this->info("✅ Sync terminée - {$synced} synchro, {$skipped} ignorés");
        return self::SUCCESS;
    }

    private static function toNullableDate($value): ?string
    {
        if (!$value || $value === '0000-00-00 00:00:00') {
            return null;
        }
        return $value;
    }
}




