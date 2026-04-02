<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLMerchantRecommendationService;

class MerchantRecommendationCommand extends Command
{
    protected $signature = 'ml:merchant-recommendations 
                            {action=status : Action: status|retrain|recommend}
                            {--client= : Client ID for recommend action}
                            {--top= : Number of recommendations (default 10)}
                            {--category= : Filter by category ID}
                            {--exclude-visited : Exclude already visited merchants}';

    protected $description = 'ML Merchant Recommendation Engine management';

    public function handle(): int
    {
        $service = new MLMerchantRecommendationService();
        $action = $this->argument('action');

        return match ($action) {
            'status' => $this->showStatus($service),
            'retrain' => $this->retrain($service),
            'recommend' => $this->recommend($service),
            default => $this->invalidAction($action),
        };
    }

    private function showStatus(MLMerchantRecommendationService $service): int
    {
        $this->info('Checking ML Merchant Recommendation Engine...');
        $health = $service->getHealth();

        $this->table(
            ['Property', 'Value'],
            collect($health)->map(fn($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->toArray()
        );

        return 0;
    }

    private function retrain(MLMerchantRecommendationService $service): int
    {
        $this->info('Triggering model retraining... (this may take ~60s)');
        $this->output->write('  ');

        $result = $service->triggerRetrain();

        if ($result['success'] ?? false) {
            $this->info('Retraining completed successfully!');
            if (isset($result['output'])) {
                $this->line($result['output']);
            }
            return 0;
        }

        $this->error('Retraining failed: ' . ($result['error'] ?? 'Unknown error'));
        return 1;
    }

    private function recommend(MLMerchantRecommendationService $service): int
    {
        $clientId = $this->option('client');
        if (!$clientId) {
            $this->error('--client option is required for recommend action');
            return 1;
        }

        $topK = (int) ($this->option('top') ?? 10);
        $categoryId = $this->option('category') ? (int) $this->option('category') : null;
        $excludeVisited = $this->option('exclude-visited');

        $this->info("Getting top {$topK} recommendations for client #{$clientId}...");
        $result = $service->getRecommendations((int) $clientId, $topK, $categoryId, $excludeVisited);

        if (!($result['success'] ?? false)) {
            $this->error('Failed to get recommendations');
            return 1;
        }

        $this->info("Source: {$result['source']} | Count: {$result['count']}");
        $this->newLine();

        $rows = [];
        foreach ($result['recommendations'] as $reco) {
            $rows[] = [
                $reco['rank'] ?? '-',
                $reco['partner_name'] ?? '-',
                $reco['category_name'] ?? '-',
                round($reco['score'] ?? 0, 2),
                $reco['active_promotions'] ?? 0,
                ($reco['already_visited'] ?? false) ? 'Oui' : 'Non',
                $reco['reason'] ?? '-',
            ];
        }

        $this->table(
            ['Rang', 'Marchand', 'Catégorie', 'Score', 'Promos', 'Déjà visité', 'Raison'],
            $rows
        );

        return 0;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}. Use: status, retrain, recommend");
        return 1;
    }
}
