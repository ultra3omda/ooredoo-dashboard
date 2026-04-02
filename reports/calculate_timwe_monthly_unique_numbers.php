<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$start = Carbon::parse('2025-10-01')->startOfDay();
$end = Carbon::parse('2025-10-31')->endOfDay();
$billingPpid = env('TIMWE_BILLING_PPID', '63980');

$unique = [];
DB::table('transactions_history as th')
    ->join('client as c', 'th.client_id', '=', 'c.client_id')
    ->whereBetween('th.created_at', [$start, $end])
    ->where(function($q) {
        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
    })
    ->orderBy('th.transaction_history_id')
    ->select('th.transaction_history_id','th.client_id','th.result','c.client_telephone')
    ->chunk(1000, function($rows) use (&$unique, $billingPpid) {
        foreach ($rows as $row) {
            if (empty($row->result)) {
                continue;
            }
            $result = json_decode($row->result, true);
            if (!is_array($result)) {
                continue;
            }
            $ppid = $result['pricepointId'] ?? null;
            $delivery = $result['mnoDeliveryCode'] ?? null;
            $totalCharged = (int)($result['totalCharged'] ?? 0);

            if ((string)$ppid !== (string)$billingPpid || $delivery !== 'DELIVERED' || $totalCharged <= 0) {
                continue;
            }

            $phone = trim((string)($row->client_telephone ?? ''));
            if ($phone === '') {
                $phone = 'client_id:' . $row->client_id;
            }
            $unique[$phone] = true;
        }
    });

echo 'unique_numbers_month=' . count($unique);
