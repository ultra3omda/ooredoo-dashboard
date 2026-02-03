<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TimweDiagnosticApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API rapide diagnostic Timwe — endpoints séparés pour chargement &lt; 200 ms.
 * Summary, delivery, phones (paginated), phone delivery-codes (lazy), recent, lifetime.
 */
class TimweDiagnosticApiController extends Controller
{
    private const MAX_DAYS = 365;

    private function normalizePeriod(Request $request): array
    {
        $start = $request->input('start', Carbon::now()->subDays(7)->format('Y-m-d'));
        $end = $request->input('end', Carbon::now()->format('Y-m-d'));
        $startCarbon = Carbon::parse($start)->startOfDay();
        $endCarbon = Carbon::parse($end)->endOfDay();
        if ($startCarbon->diffInDays($endCarbon) + 1 > self::MAX_DAYS) {
            $endCarbon = $startCarbon->copy()->addDays(self::MAX_DAYS - 1);
        }
        return [$startCarbon->format('Y-m-d'), $endCarbon->format('Y-m-d')];
    }

    /**
     * GET /api/timwe-diagnostic/summary?start=&end=&delivery_code=
     */
    public function summary(Request $request): JsonResponse
    {
        [$start, $end] = $this->normalizePeriod($request);
        $deliveryCode = $request->input('delivery_code');
        $data = (new TimweDiagnosticApiService())->getSummary($start, $end, $deliveryCode);
        return response()->json($data);
    }

    /**
     * GET /api/timwe-diagnostic/delivery?start=&end=
     */
    public function delivery(Request $request): JsonResponse
    {
        [$start, $end] = $this->normalizePeriod($request);
        $data = (new TimweDiagnosticApiService())->getDelivery($start, $end);
        return response()->json($data);
    }

    /**
     * GET /api/timwe-diagnostic/phones?start=&end=&page=&per_page=&search_phone=&delivery_code=&sort_by=&sort_dir=
     */
    public function phones(Request $request): JsonResponse
    {
        [$start, $end] = $this->normalizePeriod($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(500, max(1, (int) $request->input('per_page', 200)));
        $searchPhone = $request->input('search_phone');
        $deliveryCode = $request->input('delivery_code');
        $sortBy = $request->input('sort_by', 'total_attempts');
        $sortDir = $request->input('sort_dir', 'desc');
        if (!in_array($sortBy, ['total_attempts', 'total_charged_tnd'], true)) {
            $sortBy = 'total_attempts';
        }
        $data = (new TimweDiagnosticApiService())->getPhones($start, $end, $page, $perPage, $searchPhone, $deliveryCode, $sortBy, $sortDir);
        return response()->json($data);
    }

    /**
     * GET /api/timwe-diagnostic/phones/{phone}/delivery-codes?start=&end=
     */
    public function phoneDeliveryCodes(Request $request, string $phone): JsonResponse
    {
        [$start, $end] = $this->normalizePeriod($request);
        $data = (new TimweDiagnosticApiService())->getPhoneDeliveryCodes(trim($phone), $start, $end);
        return response()->json($data);
    }

    /**
     * GET /api/timwe-diagnostic/recent?start=&end=&limit=
     */
    public function recent(Request $request): JsonResponse
    {
        [$start, $end] = $this->normalizePeriod($request);
        $limit = min(500, max(1, (int) $request->input('limit', 200)));
        $data = (new TimweDiagnosticApiService())->getRecent($start, $end, $limit);
        return response()->json($data);
    }

    /**
     * GET /api/timwe-diagnostic/lifetime?phones[]=... (batch, page courante uniquement)
     */
    public function lifetime(Request $request): JsonResponse
    {
        $phones = $request->input('phones', []);
        if (is_string($phones)) {
            $phones = array_filter(explode(',', $phones));
        }
        $data = (new TimweDiagnosticApiService())->getLifetime($phones);
        return response()->json($data);
    }

    /**
     * GET /api/timwe-diagnostic/billing-rate-evolution?start=&end=
     */
    public function billingRateEvolution(Request $request): JsonResponse
    {
        [$start, $end] = $this->normalizePeriod($request);
        $data = (new TimweDiagnosticApiService())->getBillingRateEvolution($start, $end);
        return response()->json($data);
    }
}
