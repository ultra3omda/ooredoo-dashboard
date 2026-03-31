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

    public function buildPreviewData(ReportRecipient $recipient, Carbon $periodStart, Carbon $periodEnd, Carbon $compStart, Carbon $compEnd): array
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
        return $reportData;
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
        $mlData = $this->gatherMLData();

        switch ($recipient->type) {
            case 'ceo':
                return array_merge($this->gatherCEOReport($start, $end, $compStart, $compEnd), ['ml' => $mlData]);
            case 'marketing':
                return array_merge($this->gatherMarketingReport($start, $end, $compStart, $compEnd), ['ml' => $mlData]);
            case 'partner':
                return array_merge($this->gatherPartnerReport($recipient, $start, $end, $compStart, $compEnd), ['ml' => $mlData]);
            case 'associe':
                return array_merge($this->gatherAssocieReport($start, $end, $compStart, $compEnd), ['ml' => $mlData]);
            case 'store':
                return array_merge($this->gatherStoreReport($recipient, $start, $end, $compStart, $compEnd), ['ml' => $mlData]);
            case 'sub-store':
                return array_merge($this->gatherSubStoreReport($recipient, $start, $end, $compStart, $compEnd), ['ml' => $mlData]);
            default:
                return ['ml' => $mlData];
        }
    }

    protected function gatherMLData(): array
    {
        try {
            $latestDate = DB::table('ml_client_features')->max('calculation_date');
            if (!$latestDate) return ['available' => false];

            $segments = DB::table('ml_client_features')
                ->where('calculation_date', $latestDate)
                ->selectRaw("client_segment, COUNT(*) as cnt, AVG(payment_success_rate) as avg_success, AVG(churn_probability) as avg_churn, AVG(engagement_score) as avg_engagement, AVG(lifetime_value_score) as avg_ltv")
                ->groupBy('client_segment')
                ->orderByDesc('cnt')
                ->get()->map(fn($s) => [
                    'segment' => $s->client_segment,
                    'count' => (int)$s->cnt,
                    'avg_success' => round((float)$s->avg_success * 100, 1),
                    'avg_churn' => round((float)$s->avg_churn * 100, 1),
                    'avg_engagement' => round((float)$s->avg_engagement * 100, 1),
                    'avg_ltv' => round((float)$s->avg_ltv * 100, 1),
                ])->toArray();

            $overall = DB::table('ml_client_features')
                ->where('calculation_date', $latestDate)
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN churn_probability > 0.5 THEN 1 ELSE 0 END) as high_churn, SUM(CASE WHEN is_high_value_client = 1 THEN 1 ELSE 0 END) as high_value, AVG(payment_success_rate) as avg_success, AVG(failure_streak) as avg_failure_streak")
                ->first();

            $modelMetrics = [];
            $modelPath = base_path('ml_models/model_metrics.json');
            if (file_exists($modelPath)) {
                $modelMetrics = json_decode(file_get_contents($modelPath), true) ?? [];
            }

            return [
                'available' => true,
                'date' => $latestDate,
                'segments' => $segments,
                'total_clients' => (int)($overall->total ?? 0),
                'high_churn_clients' => (int)($overall->high_churn ?? 0),
                'high_value_clients' => (int)($overall->high_value ?? 0),
                'avg_success_rate' => round((float)($overall->avg_success ?? 0) * 100, 1),
                'avg_failure_streak' => round((float)($overall->avg_failure_streak ?? 0), 1),
                'model' => $modelMetrics,
            ];
        } catch (\Exception $e) {
            Log::warning("ML data unavailable for report: " . $e->getMessage());
            return ['available' => false];
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
            ->select('stat_date', 'activated_count', 'deactivated_count', 'active_snapshot')
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

        $channelAcquisition = DB::table('subscription_daily_stats')
            ->where('stat_date', '>=', $start->toDateString())
            ->where('stat_date', '<', $end->toDateString())
            ->whereNull('operator_id')
            ->selectRaw("SUM(channel_cb) as cb, SUM(channel_recharge) as recharge, SUM(channel_phone_balance) as phone_balance, SUM(channel_other) as other")
            ->first();

        $channels = [];
        if ($channelAcquisition) {
            if ($channelAcquisition->cb > 0) $channels[] = (object)['channel' => 'CB', 'count' => $channelAcquisition->cb];
            if ($channelAcquisition->recharge > 0) $channels[] = (object)['channel' => 'Recharge', 'count' => $channelAcquisition->recharge];
            if ($channelAcquisition->phone_balance > 0) $channels[] = (object)['channel' => 'Solde Tel', 'count' => $channelAcquisition->phone_balance];
            if ($channelAcquisition->other > 0) $channels[] = (object)['channel' => 'Autre', 'count' => $channelAcquisition->other];
            usort($channels, fn($a, $b) => $b->count - $a->count);
        }
        $channelAcquisition = $channels;

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
            ->selectRaw('p.promotion_id, COALESCE(p.promotion_title, CONCAT("Offre #", p.promotion_id)) as title, COUNT(*) as uses')
            ->groupBy('p.promotion_id', 'p.promotion_title')
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

    protected function gatherAssocieReport(Carbon $start, Carbon $end, Carbon $compStart, Carbon $compEnd): array
    {
        $globalKpis = $this->dashboardService->getKPIsOptimizedPublic($start, $end, $compStart, $compEnd, 'ALL');

        $eklektikStats = $this->getEklektikSummary($start, $end);
        $eklektikComp = $this->getEklektikSummary($compStart, $compEnd);

        $topCategories = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->join('partner_category as pc', 'pt.partner_category_id', '=', 'pc.partner_category_id')
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->whereNotNull('h.promotion_id')
            ->selectRaw('pc.partner_category_name as category, COUNT(*) as transactions, COUNT(DISTINCT pt.partner_id) as partners')
            ->groupBy('pc.partner_category_name')
            ->orderByDesc('transactions')
            ->limit(10)
            ->get()->toArray();

        $partnerGrowth = DB::table('partner')
            ->where('partener_active', 1)
            ->selectRaw('COUNT(*) as total_active')
            ->first();

        return [
            'report_type' => 'associe',
            'global_kpis' => $globalKpis,
            'eklektik_stats' => $eklektikStats,
            'eklektik_comp' => $eklektikComp,
            'top_categories' => $topCategories,
            'total_active_partners' => (int)($partnerGrowth->total_active ?? 0),
        ];
    }

    protected function gatherStoreReport(ReportRecipient $recipient, Carbon $start, Carbon $end, Carbon $compStart, Carbon $compEnd): array
    {
        $partnerId = $recipient->partner_id;
        if (!$partnerId) {
            throw new \Exception("Aucun partenaire associe au destinataire {$recipient->email}");
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

        $hourlyDistribution = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->where('p.partner_id', $partnerId)
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->selectRaw('HOUR(h.time) as hour, COUNT(*) as count')
            ->groupByRaw('HOUR(h.time)')
            ->orderBy('hour')
            ->get()->toArray();

        $dailyTransactions = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->where('p.partner_id', $partnerId)
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->selectRaw('DATE(h.time) as date, COUNT(*) as count')
            ->groupByRaw('DATE(h.time)')
            ->orderBy('date')
            ->get()->toArray();

        $topPromotions = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->where('p.partner_id', $partnerId)
            ->where('h.time', '>=', $start)
            ->where('h.time', '<', $end)
            ->selectRaw('p.promotion_id, COALESCE(p.promotion_title, CONCAT("Offre #", p.promotion_id)) as title, COUNT(*) as uses')
            ->groupBy('p.promotion_id', 'p.promotion_title')
            ->orderByDesc('uses')
            ->limit(10)
            ->get()->toArray();

        $peakHour = collect($hourlyDistribution)->sortByDesc('count')->first();

        return [
            'report_type' => 'store',
            'partner_info' => $partnerInfo,
            'partner_id' => $partnerId,
            'transactions' => $transactions,
            'transactions_comp' => $transactionsComp,
            'unique_clients' => $uniqueClients,
            'daily_transactions' => $dailyTransactions,
            'hourly_distribution' => $hourlyDistribution,
            'top_promotions' => $topPromotions,
            'peak_hour' => $peakHour ? $peakHour->hour : null,
            'avg_daily_transactions' => count($dailyTransactions) > 0 ? round(array_sum(array_column($dailyTransactions, 'count')) / count($dailyTransactions), 1) : 0,
        ];
    }

    protected function gatherSubStoreReport(ReportRecipient $recipient, Carbon $start, Carbon $end, Carbon $compStart, Carbon $compEnd): array
    {
        return $this->gatherStoreReport($recipient, $start, $end, $compStart, $compEnd);
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
        $base = "Tu es un analyste business expert du programme de fidelite Club Privileges en Tunisie. Analyse les KPIs suivants et donne 3 a 5 recommandations actionables, concretes et prioritisees. Reponds en francais.\n\n";

        $ml = $data['ml'] ?? [];
        $mlContext = '';
        if (!empty($ml['available'])) {
            $mlContext = "\n--- PREDICTIONS ML (date: {$ml['date']}) ---\n";
            $mlContext .= "- Total clients analyses: {$ml['total_clients']}\n";
            $mlContext .= "- Clients a haut risque churn: {$ml['high_churn_clients']}\n";
            $mlContext .= "- Clients haute valeur: {$ml['high_value_clients']}\n";
            $mlContext .= "- Taux succes paiement moyen: {$ml['avg_success_rate']}%\n";
            $mlContext .= "- Streak echec moyen: {$ml['avg_failure_streak']}\n";
            if (!empty($ml['segments'])) {
                $mlContext .= "- Segments:\n";
                foreach ($ml['segments'] as $s) {
                    $mlContext .= "  * {$s['segment']}: {$s['count']} clients, succes {$s['avg_success']}%, churn {$s['avg_churn']}%, engagement {$s['avg_engagement']}%\n";
                }
            }
            if (!empty($ml['model'])) {
                $acc = $ml['model']['accuracy'] ?? 'N/A';
                $mlContext .= "- Precision modele ML: {$acc}\n";
            }
        }

        switch ($reportType) {
            case 'ceo':
                $kpis = $data['global_kpis'] ?? [];
                $base .= "RAPPORT CEO - Vue strategique globale tous operateurs :\n";
                $base .= "- Abonnements actives: " . ($kpis['activatedSubscriptions']['current'] ?? 'N/A') . " (variation: " . ($kpis['activatedSubscriptions']['change'] ?? 'N/A') . "%)\n";
                $base .= "- Abonnements actifs (cohorte): " . ($kpis['activeSubscriptions']['current'] ?? 'N/A') . "\n";
                $base .= "- Taux de retention: " . ($kpis['retentionRate']['current'] ?? 'N/A') . "%\n";
                $base .= "- Taux de conversion: " . ($kpis['conversionRate']['current'] ?? 'N/A') . "%\n";
                $base .= "- Transactions totales: " . ($kpis['totalTransactions']['current'] ?? 'N/A') . " (variation: " . ($kpis['totalTransactions']['change'] ?? 'N/A') . "%)\n";
                $base .= "- Taux de churn: " . ($kpis['churnRate']['current'] ?? 'N/A') . "%\n";
                $ek = $data['eklektik_stats'] ?? [];
                $base .= "- Eklektik Revenu TTC: " . ($ek['revenue_ttc'] ?? 'N/A') . " TND\n";
                $base .= "- Top marchands: " . json_encode(array_map(fn($m) => $m->name . '(' . $m->transactions . ')', $data['top_merchants'] ?? [])) . "\n";
                $base .= $mlContext;
                $base .= "\nPour le CEO, focus sur: ROI global, risques strategiques (churn), opportunites de croissance basees sur les predictions ML, et recommandations d'investissement. Inclure des projections basees sur les donnees ML.";
                break;

            case 'marketing':
                $kpis = $data['kpis'] ?? [];
                $base .= "RAPPORT MARKETING - Acquisition, Retention & Comportement :\n";
                $base .= "- Nouveaux abonnes: " . ($kpis['activatedSubscriptions']['current'] ?? 'N/A') . " (variation: " . ($kpis['activatedSubscriptions']['change'] ?? 'N/A') . "%)\n";
                $base .= "- Cohorte active: " . ($kpis['activeSubscriptions']['current'] ?? 'N/A') . "\n";
                $base .= "- Retention: " . ($kpis['retentionRate']['current'] ?? 'N/A') . "%\n";
                $base .= "- Desactivations: " . ($kpis['periodDeactivated']['current'] ?? $kpis['deactivatedSubscriptions']['current'] ?? 'N/A') . "\n";
                $base .= "- Churn: " . ($kpis['churnRate']['current'] ?? 'N/A') . "%\n";
                $base .= "- Conversion: " . ($kpis['conversionRate'] ?? 'N/A') . "%\n";
                $channels = $data['channel_acquisition'] ?? [];
                $base .= "- Canaux d'acquisition: " . json_encode(array_map(fn($c) => $c->channel . ':' . $c->count, $channels)) . "\n";
                $base .= $mlContext;
                $base .= "\nPour le Marketing, focus sur: campagnes de reactivation pour les segments 'high_risk' et 'struggling_payers', optimisation des canaux d'acquisition, et actions de retention basees sur les predictions ML de churn.";
                break;

            case 'partner':
                $base .= "RAPPORT PARTENAIRE - " . ($data['partner_info']->partner_name ?? 'Inconnu') . " :\n";
                $base .= "- Transactions cette semaine: " . ($data['transactions'] ?? 0) . "\n";
                $base .= "- Transactions semaine precedente: " . ($data['transactions_comp'] ?? 0) . "\n";
                $delta = ($data['transactions_comp'] ?? 0) > 0
                    ? round((($data['transactions'] - $data['transactions_comp']) / $data['transactions_comp']) * 100, 1)
                    : 0;
                $base .= "- Evolution: " . ($delta > 0 ? '+' : '') . $delta . "%\n";
                $base .= "- Clients uniques: " . ($data['unique_clients'] ?? 0) . "\n";
                $tops = $data['top_promotions'] ?? [];
                $base .= "- Top offres: " . json_encode(array_map(fn($p) => $p->title . '(' . $p->uses . ')', $tops)) . "\n";
                $base .= "\nPour le Partenaire, focus sur: optimisation des offres existantes, augmentation du trafic client, et strategie de fidelisation specifique a ce commerce.";
                break;

            case 'associe':
                $kpis = $data['global_kpis'] ?? [];
                $ek = $data['eklektik_stats'] ?? [];
                $ekComp = $data['eklektik_comp'] ?? [];
                $base .= "RAPPORT ASSOCIE - Vue financiere et reseau :\n";
                $base .= "- Abonnes actifs: " . ($kpis['activeSubscriptions']['current'] ?? 'N/A') . "\n";
                $base .= "- Retention: " . ($kpis['retentionRate']['current'] ?? 'N/A') . "%\n";
                $base .= "- Churn: " . ($kpis['churnRate']['current'] ?? 'N/A') . "%\n";
                $base .= "- Revenu TTC: " . ($ek['revenue_ttc'] ?? 0) . " TND (prec: " . ($ekComp['revenue_ttc'] ?? 0) . " TND)\n";
                $base .= "- Partenaires actifs: " . ($data['total_active_partners'] ?? 0) . "\n";
                $cats = $data['top_categories'] ?? [];
                $base .= "- Top categories: " . json_encode(array_map(fn($c) => $c->category . '(' . $c->transactions . ' trans, ' . $c->partners . ' partenaires)', $cats)) . "\n";
                $base .= $mlContext;
                $base .= "\nPour l'Associe, focus sur: sante financiere du programme, diversification du reseau partenaire, et risques lies au churn identifies par le ML.";
                break;

            case 'store':
            case 'sub-store':
                $label = $reportType === 'store' ? 'STORE' : 'SUB-STORE';
                $base .= "RAPPORT {$label} - " . ($data['partner_info']->partner_name ?? 'Inconnu') . " :\n";
                $base .= "- Transactions: " . ($data['transactions'] ?? 0) . " (prec: " . ($data['transactions_comp'] ?? 0) . ")\n";
                $base .= "- Clients uniques: " . ($data['unique_clients'] ?? 0) . "\n";
                $base .= "- Moyenne quotidienne: " . ($data['avg_daily_transactions'] ?? 0) . "\n";
                $base .= "- Heure de pointe: " . ($data['peak_hour'] !== null ? $data['peak_hour'] . 'h' : 'N/A') . "\n";
                $tops = $data['top_promotions'] ?? [];
                $base .= "- Top offres: " . json_encode(array_map(fn($p) => $p->title . '(' . $p->uses . ')', $tops)) . "\n";
                $base .= "\nPour le {$label}, focus sur: optimisation des heures de pointe, promotion des offres les moins utilisees, et strategies pour augmenter la frequentation client.";
                break;
        }

        $base .= "\nDonne des recommandations strategiques precises et actionables. Format: liste numerotee.";
        return $base;
    }

    protected function getPdfView(string $type): string
    {
        $map = ['ceo' => 'ceo', 'marketing' => 'marketing', 'partner' => 'partner', 'associe' => 'associe', 'store' => 'store', 'sub-store' => 'store'];
        return "reports.pdf." . ($map[$type] ?? 'partner');
    }

    protected function getEmailView(string $type): string
    {
        $map = ['ceo' => 'ceo', 'marketing' => 'marketing', 'partner' => 'partner', 'associe' => 'associe', 'store' => 'store', 'sub-store' => 'store'];
        return "reports.email." . ($map[$type] ?? 'partner');
    }

    protected function getSubject(string $type, Carbon $start, Carbon $end, ReportRecipient $recipient): string
    {
        $period = $start->format('d/m') . ' - ' . $end->format('d/m/Y');
        switch ($type) {
            case 'ceo':
                return "Club Privileges - Rapport Strategique Hebdomadaire ({$period})";
            case 'marketing':
                return "Club Privileges - Rapport Marketing & ML Insights ({$period})";
            case 'partner':
                $name = $recipient->partner?->partner_name ?? 'Partenaire';
                return "Club Privileges - Rapport Transactions {$name} ({$period})";
            case 'associe':
                return "Club Privileges - Rapport Associe - Performance Reseau ({$period})";
            case 'store':
                $name = $recipient->partner?->partner_name ?? 'Store';
                return "Club Privileges - Rapport Store {$name} ({$period})";
            case 'sub-store':
                $name = $recipient->partner?->partner_name ?? 'Sub-Store';
                return "Club Privileges - Rapport Sub-Store {$name} ({$period})";
            default:
                return "Club Privileges - Rapport ({$period})";
        }
    }
}
