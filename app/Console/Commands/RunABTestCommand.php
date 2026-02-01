<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RunABTestCommand extends Command
{
    protected $signature = 'ml:ab-test
                            {--name= : Nom du test A/B}
                            {--participants=100 : Nombre de participants à assigner (control + treatment)}
                            {--days=14 : Durée du test en jours}
                            {--segment= : Segment client (ex: high_risk, low_risk) - filtre ml_client_features}
                            {--price= : Prix traitement en TND (ex: 0.3 pour quotidien)}
                            {--frequency=daily : Fréquence traitement (daily|monthly)}
                            {--list : Afficher les tests A/B existants sans en créer}';

    protected $description = 'Crée et lance un test A/B (remplit ml_ab_tests et ml_ab_test_participants)';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listTests();
        }

        $name = $this->option('name') ?: 'Test facturation optimisée ' . now()->format('Y-m-d H:i');
        $participants = (int) $this->option('participants');
        $days = (int) $this->option('days');
        $segment = $this->option('segment');
        $price = $this->option('price');
        $frequency = $this->option('frequency') ?: 'daily';

        if ($participants < 2) {
            $this->error('Le nombre de participants doit être au moins 2.');
            return 1;
        }

        $this->info('🧪 Création d\'un test A/B...');
        $this->line("   Nom: {$name}");
        $this->line("   Participants: {$participants}");
        $this->line("   Durée: {$days} jours");
        if ($segment) {
            $this->line("   Segment: {$segment}");
        }
        if ($price !== null && $price !== '') {
            $this->line("   Prix traitement: {$price} TND ({$frequency})");
        }

        $testId = 'ab_' . str_replace([' ', ':'], ['_', ''], now()->format('Y-m-d_His'));

        $treatmentPrice = $price !== null && $price !== '' ? (float) $price : 'recommended';
        $treatmentStrategy = [
            'billing_time' => 'optimized',
            'price' => $treatmentPrice,
            'frequency' => $frequency,
        ];

        try {
            $startDate = Carbon::today();
            $endDate = Carbon::today()->addDays($days);

            DB::table('ml_ab_tests')->insert([
                'test_id' => $testId,
                'test_name' => $name,
                'test_description' => "Test A/B créé via ml:ab-test. Contrôle vs traitement sur stratégie de facturation."
                    . ($segment ? " Segment: {$segment}." : '')
                    . ($treatmentPrice !== 'recommended' ? " Traitement: {$treatmentPrice} TND ({$frequency})." : ''),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'running',
                'total_participants' => 0,
                'traffic_allocation' => 0.5,
                'control_strategy' => json_encode([
                    'billing_time' => 'current',
                    'price' => 'current',
                    'frequency' => 'monthly',
                ]),
                'treatment_strategy' => json_encode($treatmentStrategy),
                'primary_metric' => 'success_rate',
                'secondary_metrics' => json_encode(['revenue', 'churn']),
                'minimum_detectable_effect' => 0.05,
                'significance_level' => 0.05,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->line("   Test créé: {$testId}");

            // Récupérer des client_id (filtrer par segment si --segment=)
            $query = DB::table('ml_client_features')->distinct('client_id');
            if ($segment) {
                $query->where('client_segment', $segment);
            }
            $clientIds = $query->limit($participants * 2)
                ->pluck('client_id')
                ->shuffle()
                ->take($participants)
                ->values()
                ->all();

            if (empty($clientIds)) {
                $clientIds = DB::table('client')
                    ->limit($participants * 2)
                    ->pluck('client_id')
                    ->shuffle()
                    ->take($participants)
                    ->values()
                    ->all();
            }

            if (count($clientIds) < 2) {
                $this->warn('Pas assez de clients en base pour assigner des participants.');
                return 0;
            }

            $half = (int) ceil(count($clientIds) / 2);
            $assignedAt = now();

            $rows = [];
            foreach (array_slice($clientIds, 0, $half) as $clientId) {
                $rows[] = [
                    'test_id' => $testId,
                    'client_id' => $clientId,
                    'test_group' => 'control',
                    'assigned_at' => $assignedAt,
                    'created_at' => $assignedAt,
                    'updated_at' => $assignedAt,
                ];
            }
            foreach (array_slice($clientIds, $half) as $clientId) {
                $rows[] = [
                    'test_id' => $testId,
                    'client_id' => $clientId,
                    'test_group' => 'treatment',
                    'assigned_at' => $assignedAt,
                    'created_at' => $assignedAt,
                    'updated_at' => $assignedAt,
                ];
            }

            DB::table('ml_ab_test_participants')->insert($rows);
            DB::table('ml_ab_tests')->where('test_id', $testId)->update([
                'total_participants' => count($rows),
                'updated_at' => now(),
            ]);

            $this->info('✅ Test A/B lancé.');
            $this->line("   - Control: {$half} participants");
            $this->line("   - Treatment: " . (count($rows) - $half) . " participants");
            $this->line("   - Tables: ml_ab_tests, ml_ab_test_participants");

            return 0;
        } catch (\Throwable $e) {
            $this->error('Erreur: ' . $e->getMessage());
            return 1;
        }
    }

    private function listTests(): int
    {
        $tests = DB::table('ml_ab_tests')->orderBy('created_at', 'desc')->limit(20)->get();

        if ($tests->isEmpty()) {
            $this->info('Aucun test A/B en base. Lancez: php artisan ml:ab-test');
            return 0;
        }

        $this->table(
            ['test_id', 'Nom', 'Statut', 'Début', 'Fin', 'Participants'],
            $tests->map(fn ($t) => [
                $t->test_id,
                \Str::limit($t->test_name, 30),
                $t->status,
                $t->start_date,
                $t->end_date,
                $t->total_participants,
            ])
        );

        return 0;
    }
}
