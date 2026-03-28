<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeeklyReportService;
use Carbon\Carbon;

class SendWeeklyReports extends Command
{
    protected $signature = 'reports:send-weekly {--dry-run : List recipients without sending}';
    protected $description = 'Envoyer les rapports hebdomadaires a tous les destinataires actifs';

    public function handle(WeeklyReportService $reportService): int
    {
        $this->info('Demarrage de l\'envoi des rapports hebdomadaires...');

        if ($this->option('dry-run')) {
            $recipients = \App\Models\ReportRecipient::where('is_active', true)->get();
            $this->table(['Nom', 'Email', 'Type', 'Partenaire'], $recipients->map(fn($r) => [
                $r->name, $r->email, $r->type, $r->partner?->partner_name ?? '-'
            ]));
            $this->info("Total: {$recipients->count()} destinataires actifs.");
            return 0;
        }

        $results = $reportService->sendAllReports(Carbon::today());

        $this->info("Envoi termine:");
        $this->info("  Envoyes: {$results['sent']}");
        $this->info("  Echoues: {$results['failed']}");
        $this->info("  Ignores: {$results['skipped']}");

        return $results['failed'] > 0 ? 1 : 0;
    }
}
