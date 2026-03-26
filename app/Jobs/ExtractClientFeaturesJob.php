<?php

namespace App\Jobs;

use App\Services\MLFeatureExtractionService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExtractClientFeaturesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 3600; // 1 heure max par chunk
    public array $clientIds;
    public string $calculationDate;

    public function __construct(array $clientIds, Carbon $calculationDate)
    {
        $this->clientIds = $clientIds;
        $this->calculationDate = $calculationDate->toDateString();
        $this->onQueue('ml-extraction');
    }

    public function handle(MLFeatureExtractionService $service): void
    {
        $calculationDate = Carbon::parse($this->calculationDate);
        $featuresData = [];
        $processed = 0;

        foreach ($this->clientIds as $clientId) {
            try {
                $features = $service->extractClientFeatures((int) $clientId, $calculationDate);
                $featuresData[] = $features;
                $processed++;
            } catch (\Throwable $e) {
                Log::error('ExtractClientFeaturesJob - Erreur client ' . $clientId, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($featuresData)) {
            DB::table('ml_client_features')->upsert(
                $featuresData,
                ['client_id', 'calculation_date'],
                array_keys($featuresData[0])
            );
        }

        Log::info('ExtractClientFeaturesJob - Chunk terminé', [
            'processed' => $processed,
            'date' => $this->calculationDate,
        ]);
    }
}
