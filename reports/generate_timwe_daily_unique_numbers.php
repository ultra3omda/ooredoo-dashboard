<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$start = Carbon::parse('2025-10-01')->startOfDay();
$end = Carbon::parse('2025-10-31')->endOfDay();
$billingPpid = env('TIMWE_BILLING_PPID', '63980');

$outDir = base_path('reports/timwe_billing_oct_2025_by_day_unique_numbers');
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$byDay = [];
$seen = [];

DB::table('transactions_history as th')
    ->join('client as c', 'th.client_id', '=', 'c.client_id')
    ->whereBetween('th.created_at', [$start, $end])
    ->where(function($q) {
        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
    })
    ->orderBy('th.transaction_history_id')
    ->select('th.transaction_history_id','th.client_id','th.result','th.created_at','c.client_telephone')
    ->chunk(1000, function($rows) use (&$byDay, &$seen, $billingPpid) {
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

            $date = Carbon::parse($row->created_at)->format('Y-m-d');
            $phone = trim((string)($row->client_telephone ?? ''));
            if ($phone === '') {
                $phone = 'client_id:' . $row->client_id;
            }

            if (!isset($seen[$date][$phone][$row->transaction_history_id])) {
                $seen[$date][$phone][$row->transaction_history_id] = true;

                if (!isset($byDay[$date][$phone])) {
                    $byDay[$date][$phone] = [
                        'date' => $date,
                        'phone' => $phone,
                        'client_id' => $row->client_id,
                        'billings_count' => 0,
                        'total_charged_millimes' => 0,
                        'first_tx_at' => $row->created_at,
                        'last_tx_at' => $row->created_at,
                    ];
                }

                $byDay[$date][$phone]['billings_count'] += 1;
                $byDay[$date][$phone]['total_charged_millimes'] += $totalCharged;
                if ($row->created_at < $byDay[$date][$phone]['first_tx_at']) {
                    $byDay[$date][$phone]['first_tx_at'] = $row->created_at;
                }
                if ($row->created_at > $byDay[$date][$phone]['last_tx_at']) {
                    $byDay[$date][$phone]['last_tx_at'] = $row->created_at;
                }
            }
        }
    });

ksort($byDay);
foreach ($byDay as $date => $rows) {
    $path = $outDir . DIRECTORY_SEPARATOR . $date . '.csv';
    $fh = fopen($path, 'w');
    fputcsv($fh, ['date','phone','client_id','billings_count','total_charged_millimes','total_charged_tnd','first_tx_at','last_tx_at']);
    ksort($rows);
    foreach ($rows as $row) {
        $totalTnd = $row['total_charged_millimes'] / 1000;
        fputcsv($fh, [
            $row['date'],
            $row['phone'],
            $row['client_id'],
            $row['billings_count'],
            $row['total_charged_millimes'],
            number_format($totalTnd, 3, '.', ''),
            $row['first_tx_at'],
            $row['last_tx_at'],
        ]);
    }
    fclose($fh);
}

file_put_contents(base_path('reports/timwe_billing_oct_2025_by_day_unique_numbers/README.txt'),
    "Fichiers par jour (octobre 2025) avec numeros uniques.\n" .
    "Filtres: pricepointId=billing, mnoDeliveryCode=DELIVERED, totalCharged>0.\n" .
    "Groupement par numero (1 ligne par numero et par jour).\n"
);

echo $outDir;
