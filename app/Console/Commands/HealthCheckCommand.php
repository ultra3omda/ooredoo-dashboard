<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AlertService;

class HealthCheckCommand extends Command
{
    protected $signature = 'monitoring:health-check {--json : Output as JSON}';
    protected $description = 'Exécute tous les health checks du système et crée des alertes si nécessaire';

    public function handle(AlertService $alertService): int
    {
        $this->info('Démarrage des health checks...');
        $results = $alertService->runHealthChecks();

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $results['overall_status'] === 'healthy' ? 0 : 1;
        }

        $statusColors = ['healthy' => 'green', 'warning' => 'yellow', 'critical' => 'red', 'info' => 'blue'];

        foreach ($results['checks'] as $component => $check) {
            $color = $statusColors[$check['status']] ?? 'white';
            $icon = match ($check['status']) {
                'healthy' => 'OK',
                'warning' => 'WARN',
                'critical' => 'CRIT',
                default => 'INFO',
            };
            $this->line("<fg={$color}>[{$icon}]</> {$component}: {$check['message']}");
        }

        $overallColor = $statusColors[$results['overall_status']] ?? 'white';
        $this->newLine();
        $this->line("<fg={$overallColor};options=bold>Statut global: " . strtoupper($results['overall_status']) . "</>");

        $alertStats = $alertService->getAlertStats();
        if ($alertStats['unacknowledged'] > 0) {
            $this->warn("{$alertStats['unacknowledged']} alerte(s) non acquittée(s)");
        }

        return $results['overall_status'] === 'healthy' ? 0 : 1;
    }
}
