<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReportRecipient;
use App\Models\ReportLog;
use App\Services\WeeklyReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    protected WeeklyReportService $reportService;

    public function __construct(WeeklyReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function getRecipients(Request $request)
    {
        $recipients = ReportRecipient::with(['partner:partner_id,partner_name', 'lastLog'])
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'email' => $r->email,
                    'type' => $r->type,
                    'partner_id' => $r->partner_id,
                    'partner_name' => $r->partner?->partner_name,
                    'is_active' => $r->is_active,
                    'schedule_day' => $r->schedule_day,
                    'schedule_time' => $r->schedule_time,
                    'last_sent' => $r->lastLog?->sent_at?->format('d/m/Y H:i'),
                    'last_status' => $r->lastLog?->status,
                ];
            });

        return response()->json(['recipients' => $recipients]);
    }

    public function storeRecipient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'type' => 'required|in:ceo,marketing,partner',
            'partner_id' => 'required_if:type,partner|nullable|integer|exists:partner,partner_id',
            'is_active' => 'boolean',
            'schedule_day' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedule_time' => 'string|regex:/^\d{2}:\d{2}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        if ($data['type'] !== 'partner') {
            $data['partner_id'] = null;
        }

        $exists = ReportRecipient::where('email', $data['email'])
            ->where('type', $data['type'])
            ->where('partner_id', $data['partner_id'] ?? null)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Ce destinataire existe deja pour ce type de rapport.'], 422);
        }

        $recipient = ReportRecipient::create($data);

        return response()->json(['recipient' => $recipient, 'message' => 'Destinataire ajoute avec succes.'], 201);
    }

    public function updateRecipient(Request $request, $id)
    {
        $recipient = ReportRecipient::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'email|max:255',
            'type' => 'in:ceo,marketing,partner',
            'partner_id' => 'nullable|integer|exists:partner,partner_id',
            'is_active' => 'boolean',
            'schedule_day' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedule_time' => 'string|regex:/^\d{2}:\d{2}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $recipient->update($validator->validated());

        return response()->json(['recipient' => $recipient->fresh(), 'message' => 'Destinataire mis a jour.']);
    }

    public function deleteRecipient($id)
    {
        ReportRecipient::findOrFail($id)->delete();
        return response()->json(['message' => 'Destinataire supprime.']);
    }

    public function toggleRecipient($id)
    {
        $recipient = ReportRecipient::findOrFail($id);
        $recipient->update(['is_active' => !$recipient->is_active]);
        return response()->json(['recipient' => $recipient->fresh(), 'message' => 'Statut mis a jour.']);
    }

    public function sendNow(Request $request)
    {
        $recipientId = $request->input('recipient_id');
        $periodEnd = Carbon::today();
        $periodStart = $periodEnd->copy()->subDays(7);
        $compStart = $periodStart->copy()->subDays(7);
        $compEnd = $periodStart->copy();

        try {
            if ($recipientId) {
                $recipient = ReportRecipient::findOrFail($recipientId);
                $this->reportService->sendReportToRecipient($recipient, $periodStart, $periodEnd, $compStart, $compEnd);
                return response()->json(['message' => "Rapport envoye a {$recipient->email}."]);
            }

            $results = $this->reportService->sendAllReports($periodEnd);
            return response()->json([
                'message' => "Envoi termine: {$results['sent']} envoyes, {$results['failed']} echoues.",
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error("Manual report send failed: " . $e->getMessage());
            return response()->json(['error' => "Echec d'envoi: " . $e->getMessage()], 500);
        }
    }

    public function previewReport($id)
    {
        $recipient = ReportRecipient::with('partner')->findOrFail($id);
        $periodEnd = Carbon::today();
        $periodStart = $periodEnd->copy()->subDays(7);
        $compStart = $periodStart->copy()->subDays(7);
        $compEnd = $periodStart->copy();

        try {
            $reportData = $this->reportService->buildPreviewData($recipient, $periodStart, $periodEnd, $compStart, $compEnd);
            $emailView = "reports.email.{$recipient->type}";
            $html = view($emailView, $reportData)->render();
            return response()->json(['html' => $html, 'recipient' => $recipient->name, 'type' => $recipient->type]);
        } catch (\Exception $e) {
            Log::error("Report preview failed for {$recipient->email}: " . $e->getMessage());
            return response()->json(['error' => "Erreur de generation: " . $e->getMessage()], 500);
        }
    }

    public function getLogs(Request $request)
    {
        $logs = ReportLog::with('recipient:id,name,email,type')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'recipient_name' => $log->recipient?->name,
                    'recipient_email' => $log->recipient?->email,
                    'report_type' => $log->report_type,
                    'status' => $log->status,
                    'period' => $log->period_start->format('d/m') . ' - ' . $log->period_end->format('d/m/Y'),
                    'sent_at' => $log->sent_at?->format('d/m/Y H:i'),
                    'error' => $log->error_message,
                    'has_ai' => !empty($log->ai_suggestions),
                    'created_at' => $log->created_at->format('d/m/Y H:i'),
                ];
            });

        return response()->json(['logs' => $logs]);
    }

    public function getPartners(Request $request)
    {
        $search = $request->input('q', '');
        $query = DB::table('partner')
            ->where('partener_active', 1)
            ->whereNotNull('partner_mail')
            ->where('partner_mail', '!=', '')
            ->select('partner_id', 'partner_name', 'partner_mail');

        if ($search) {
            $query->where('partner_name', 'like', "%{$search}%");
        }

        $partners = $query->orderBy('partner_name')->limit(50)->get();

        return response()->json(['partners' => $partners]);
    }

    public function getScheduleConfig()
    {
        $config = [
            'global_day' => config('reporting.schedule_day', 'monday'),
            'global_time' => config('reporting.schedule_time', '08:00'),
            'recipients_count' => ReportRecipient::where('is_active', true)->count(),
            'last_run' => ReportLog::orderByDesc('created_at')->first()?->created_at?->format('d/m/Y H:i'),
        ];

        return response()->json($config);
    }
}
