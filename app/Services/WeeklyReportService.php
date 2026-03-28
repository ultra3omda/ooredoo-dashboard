<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Models\ReportRecipient;
use App\Models\ReportLog;
use App\Services\DashboardService;
use Barryvdh\DomPDF\Facade\Pdf;

class WeeklyReportService
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function sendAllReports(?Carbon $periodEnd = null): array
    {
        $periodEnd = $periodEnd ?? Carbon::today();
        $periodStart = $periodEnd->copy()->subDays(7);
        $compStart = $periodStart->copy()->subDays(7);
        $compEnd = $periodStart->copy();

        $recipients = ReportRecipient::where('is_active', true)->get();
        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($recipients as $recipient) {
            try {
                $this->sendReportToRecipient($recipient, $periodStart, $periodEnd, $compStart, $compEnd);
                $results['sent']++;
            } catch (\Exception $e) {
                Log::error("Report failed for {$recipient->email}: " . $e->getMessage());
                ReportLog::create([
                    'recipient_id' => $recipient->id,
                    'report_type' => $recipient->type,
                    'status' => 'failed',
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'error_message' => $e->getMessage(),
                ]);
                $results['failed']++;
            }
        }

        return $results;
    }

    public function sendReportToRecipient(ReportRecipient $recipient, Carbon $periodStart, Carbon $periodEnd, Carbon $compStart, Carbon $compEnd): void
    {
        $reportData = $this->gatherReportData($recipient, $periodStart, $periodEnd, $compStart, $compEnd);
        $aiSuggestions = $this->getAISuggestions($recipient->type, $reportData);
        $reportData['ai_suggestions'] = $aiSuggestions;
        $reportData['period_start'] = $periodStart->format('d/m/Y');
        $reportData['period_end'] = $periodEnd->format('d/m/Y');
        $reportData['comp_start'] = $compStart->format('d/m/Y');
        $reportData['comp_end'] = $compEnd->format('d/m/Y');
        $reportData['recipient'] = $recipient;
        $reportData['generated_at'] = Carbon::now()->format('d/m/Y H:i');

        $pdfView = $this->getPdfView($recipient->type);
        $pdf = Pdf::loadView($pdfView, $reportData)
            ->setPaper('a4', 'portrait')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false]);

        $emailView = $this->getEmailView($recipient->type);
        $subject = $this->getSubject($recipient->type, $periodStart, $periodEnd, $recipient);

        Mail::send($emailView, $reportData, function ($message) use ($recipient, $subject, $pdf) {
            $message->to($recipient->email, $recipient->name)
                    ->subject($subject)
                    ->attachData($pdf->output(), 'rapport-hebdomadaire.pdf', [
                        'mime' => 'application/pdf',
                    ]);
        });

        ReportLog::create([
            'recipient_id' => $recipient->id,
            'report_type' => $recipient->type,
            'status' => 'sent',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'ai_suggestions' => $aiSuggestions,
            'sent_at' => Carbon::now(),
        ]);
    }

    protected function gatherReportData(ReportRecipient $recipient, Carbon $start, Carbon $end, Carbon $compStart, Carbon $compEnd): array
    {
        switch ($recipient->type) {
            case 'ceo':
                return $this->gatherCEOReport($start, $end, $compStart, $compEnd);
            case 'marketing':
                return $this->gatherMarketingReport($start, $end, $compStart, $compEnd);
            case 'partner':
                return $this->gatherPartnerReport($recipient, $start, $end, $compStart, $compEnd);
            default:
                return [];
        }
    }

    protected function gatherCEOReport(Carbon $start, Carbon $end, Carbon $compStart, Carbon $compEnd): array
    {
        $globalKpis = $this->dashboardService->getKPIsOptimizedPublic($start, $end, $compStart, $compEnd, 'ALL');

        $timweStats = [];
        try {
            $timweStats = $this->dashboardService->getDailyStatistics($start, $end, 'ALL');
        } catch (\Exception $e) {
            Log::warning("Timwe stats unavailable for report: " . $e->getMessage());
        }

        $ooredooStats = [];
        try {
            $ooredooStats = $this->dashboardService->getOoredooDailyStatisticsPublic($start, $end);
        } catch (\Exception $e) {
            Log::warning("Ooredoo stats unavailable for report: " . $e->getMessage());
        }

        $eklektikStats = $this->getEklektikSummary($start, $end);

        $topMerchants = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->whereNotNull('h.promotion_id')
            ->selectRaw('pt.partner_name as name, COUNT(*) as transactions')
            ->groupBy('pt.partner_name')
            ->orderByDesc('transactions')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'report_type' => 'ceo',
            'global_kpis' => $globalKpis,
            'timwe_stats' => $timweStats,
            'ooredoo_stats' => $ooredooStats,
            'eklektik_stats' => $eklektikStats,
            'top_merchants' => $topMerchants,
        ];
    }

    protected function gatherMarketingReport(Carbon $start, Carbon $end, Carbon $compStart, Carbon $compEnd): array
    {
        $kpis = $this->dashboardService->getKPIsOptimizedPublic($start, $end, $compStart, $compEnd, 'ALL');

        $dailySubs = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $start->toDateString())
            ->where('stat_date', '<', $end->toDateString())
            ->whereNull('operator_id')
            ->select('stat_date', 'activated_count', 'deactivated_count', 'active_end_count')
            ->orderBy('stat_date')
            ->get()
            ->toArray();

        $operatorBreakdown = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $start->toDateString())
            ->where('stat_date', '<', $end->toDateString())
            ->whereNotNull('operator_id')
            ->selectRaw('operator_id, SUM(activated_count) as activated, SUM(deactivated_count) as deactivated')
            ->groupBy('operator_id')
            ->get()
            ->toArray();

        $channelAcquisition = DB::table('client_abonnement as ca')
            ->where('ca.client_abonnement_creation', '>=', $start)
            ->where('ca.client_abonnement_creation', '<', $end)
            ->selectRaw("COALESCE(ca.entry_by, 'direct') as channel, COUNT(*) as count")
            ->groupBy('channel')
            ->orderByDesc('count')
            ->get()
            ->toArray();

        return [
            'report_type' => 'marketing',
            'kpis' => $kpis,
            'daily_subs' => $dailySubs,
            'operator_breakdown' => $operatorBreakdown,
            'channel_acquisition' => $channelAcquisition,
        ];
    }

    protected function gatherPartnerReport(ReportRecipient $recipient, Carbon $start, Carbon $end, Carbon $compStart, Carbon $compEnd): array
    {
        $partnerId = $recipient->partner_id;
        if (!$partnerId) {
            throw new \Exception("Aucun partenaire associé au destinataire {$recipient->email}");
        }

        $partnerInfo = DB::table('partner')
            ->where('partner_id', $partnerId)
            ->select('partner_name', 'partner_mail', 'partner_category_id')
            ->first();

        $transactions = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->where('p.partner_id', $partnerId)
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->count();

        $transactionsComp = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->where('p.partner_id', $partnerId)
            ->where('h.time', '>=', $compStart)
            ->where('h.time', '<', $compEnd)
            ->count();

        $uniqueClients = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->where('p.partner_id', $partnerId)
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->distinct('ca.client_id')
            ->count('ca.client_id');

        $dailyTransactions = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->where('p.partner_id', $partnerId)
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->selectRaw('DATE(h.time) as date, COUNT(*) as count')
            ->groupByRaw('DATE(h.time)')
            ->orderBy('date')
            ->get()
            ->toArray();

        $topPromotions = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->where('p.partner_id', $partnerId)
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->selectRaw('p.promotion_id, COALESCE(p.promotion_titre, CONCAT("Offre #", p.promotion_id)) as title, COUNT(*) as uses')
            ->groupBy('p.promotion_id', 'p.promotion_titre')
            ->orderByDesc('uses')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'report_type' => 'partner',
            'partner_info' => $partnerInfo,
            'partner_id' => $partnerId,
            'transactions' => $transactions,
            'transactions_comp' => $transactionsComp,
            'unique_clients' => $uniqueClients,
            'daily_transactions' => $dailyTransactions,
            'top_promotions' => $topPromotions,
        ];
    }

    protected function getEklektikSummary(Carbon $start, Carbon $end): array
    {
        try {
            $stats = DB::table('eklektik_stats_daily')
                ->where('date', '>=', $start->toDateString())
                ->where('date', '<', $end->toDateString())
                ->selectRaw('SUM(revenue_ttc) as revenue_ttc, SUM(revenue_ht) as revenue_ht, SUM(ca_bigdeal) as ca_bigdeal, MAX(active_subscriptions) as active_subs')
                ->first();
            return [
                'revenue_ttc' => round($stats->revenue_ttc ?? 0, 2),
                'revenue_ht' => round($stats->revenue_ht ?? 0, 2),
                'ca_bigdeal' => round($stats->ca_bigdeal ?? 0, 2),
                'active_subs' => (int)($stats->active_subs ?? 0),
            ];
        } catch (\Exception $e) {
            return ['revenue_ttc' => 0, 'revenue_ht' => 0, 'ca_bigdeal' => 0, 'active_subs' => 0];
        }
    }

    protected function getAISuggestions(string $reportType, array $data): string
    {
        try {
            $prompt = $this->buildAIPrompt($reportType, $data);
            $response = Http::timeout(30)->post('http://127.0.0.1:8001/api/report-ai-suggestions', [
                'report_type' => $reportType,
                'prompt' => $prompt,
            ]);

            if ($response->successful()) {
                return $response->json('suggestions', '');
            }

            Log::warning("AI suggestions request failed: " . $response->status());
            return '';
        } catch (\Exception $e) {
            Log::warning("AI suggestions unavailable: " . $e->getMessage());
            return '';
        }
    }

    protected function buildAIPrompt(string $reportType, array $data): string
    {
        $base = "Tu es un analyste business expert du programme de fidélité Club Privilèges en Tunisie. Analyse les KPIs suivants et donne 3 à 5 recommandations actionables, concrètes et prioritisées. Réponds en français.\n\n";

        switch ($reportType) {
            case 'ceo':
                $kpis = $data['global_kpis'] ?? [];
                $base .= "RAPPORT CEO - Vue globale tous opérateurs :\n";
                $base .= "- Abonnements activés: " . ($kpis['activatedSubscriptions'] ?? 'N/A') . "\n";
                $base .= "- Abonnements actifs: " . ($kpis['activeSubscriptions'] ?? 'N/A') . "\n";
                $base .= "- Taux de rétention: " . ($kpis['retentionRate'] ?? 'N/A') . "%\n";
                $base .= "- Taux de conversion: " . ($kpis['conversionRate'] ?? 'N/A') . "%\n";
                $base .= "- Transactions totales: " . ($kpis['totalTransactions'] ?? 'N/A') . "\n";
                $ek = $data['eklektik_stats'] ?? [];
                $base .= "- Eklektik Revenu TTC: " . ($ek['revenue_ttc'] ?? 'N/A') . " TND\n";
                $base .= "- Top marchands: " . json_encode(array_map(fn($m) => $m->name . '(' . $m->transactions . ')', $data['top_merchants'] ?? [])) . "\n";
                break;

            case 'marketing':
                $kpis = $data['kpis'] ?? [];
                $base .= "RAPPORT MARKETING - Acquisition & Rétention :\n";
                $base .= "- Nouveaux abonnés: " . ($kpis['activatedSubscriptions'] ?? 'N/A') . "\n";
                $base .= "- Cohorte active: " . ($kpis['activeSubscriptions'] ?? 'N/A') . "\n";
                $base .= "- Rétention: " . ($kpis['retentionRate'] ?? 'N/A') . "%\n";
                $base .= "- Désactivations: " . ($kpis['deactivated'] ?? 'N/A') . "\n";
                $base .= "- Churn: " . ($kpis['churnRate'] ?? 'N/A') . "%\n";
                $base .= "- Conversion: " . ($kpis['conversionRate'] ?? 'N/A') . "%\n";
                $channels = $data['channel_acquisition'] ?? [];
                $base .= "- Canaux d'acquisition: " . json_encode(array_map(fn($c) => $c->channel . ':' . $c->count, $channels)) . "\n";
                break;

            case 'partner':
                $base .= "RAPPORT PARTENAIRE - " . ($data['partner_info']->partner_name ?? 'Inconnu') . " :\n";
                $base .= "- Transactions cette semaine: " . ($data['transactions'] ?? 0) . "\n";
                $base .= "- Transactions semaine précédente: " . ($data['transactions_comp'] ?? 0) . "\n";
                $delta = ($data['transactions_comp'] ?? 0) > 0
                    ? round((($data['transactions'] - $data['transactions_comp']) / $data['transactions_comp']) * 100, 1)
                    : 0;
                $base .= "- Evolution: " . ($delta > 0 ? '+' : '') . $delta . "%\n";
                $base .= "- Clients uniques: " . ($data['unique_clients'] ?? 0) . "\n";
                $tops = $data['top_promotions'] ?? [];
                $base .= "- Top offres: " . json_encode(array_map(fn($p) => $p->title . '(' . $p->uses . ')', $tops)) . "\n";
                break;
        }

        $base .= "\nDonne des recommandations stratégiques précises et actionables. Format: liste numérotée.";
        return $base;
    }

    protected function getPdfView(string $type): string
    {
        return "reports.pdf.{$type}";
    }

    protected function getEmailView(string $type): string
    {
        return "reports.email.{$type}";
    }

    protected function getSubject(string $type, Carbon $start, Carbon $end, ReportRecipient $recipient): string
    {
        $period = $start->format('d/m') . ' - ' . $end->format('d/m/Y');
        switch ($type) {
            case 'ceo':
                return "Club Privileges - Rapport Hebdomadaire Complet ({$period})";
            case 'marketing':
                return "Club Privileges - Rapport Marketing ({$period})";
            case 'partner':
                $name = $recipient->partner?->partner_name ?? 'Partenaire';
                return "Club Privileges - Rapport Transactions {$name} ({$period})";
            default:
                return "Club Privileges - Rapport ({$period})";
        }
    }
}
