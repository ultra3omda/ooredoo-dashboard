<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$date = '2026-01-01';

$columns = Illuminate\Support\Facades\Schema::getColumnListing('ml_client_features');
echo "Colonnes dans ml_client_features: " . count($columns) . "\n";
$hasTimweAttempts = in_array('timwe_total_attempts', $columns);
$hasEklektikAttempts = in_array('eklektik_total_attempts', $columns);
$hasOoredooAttempts = in_array('ooredoo_total_attempts', $columns);
echo "  timwe_total_attempts: " . ($hasTimweAttempts ? 'oui' : 'non') . "\n";
echo "  eklektik_total_attempts: " . ($hasEklektikAttempts ? 'oui' : 'non') . "\n";
echo "  ooredoo_total_attempts: " . ($hasOoredooAttempts ? 'oui' : 'non') . "\n\n";

$total = DB::table('ml_client_features')->where('calculation_date', $date)->count();
echo "=== calculation_date = $date ===\n\n";
echo "Total lignes: $total\n";

if ($hasTimweAttempts) {
    $withTimwe = DB::table('ml_client_features')->where('calculation_date', $date)->where('timwe_total_attempts', '>', 0)->count();
    echo "Avec timwe_total_attempts > 0: $withTimwe\n";
}
if ($hasEklektikAttempts) {
    $withEklektik = DB::table('ml_client_features')->where('calculation_date', $date)->where('eklektik_total_attempts', '>', 0)->count();
    echo "Avec eklektik_total_attempts > 0: $withEklektik\n";
}
if ($hasOoredooAttempts) {
    $withOoredoo = DB::table('ml_client_features')->where('calculation_date', $date)->where('ooredoo_total_attempts', '>', 0)->count();
    echo "Avec ooredoo_total_attempts > 0: $withOoredoo\n";
}

$select = array_intersect(['client_id', 'calculation_date', 'timwe_success_rate', 'timwe_total_attempts', 'timwe_has_activity', 'eklektik_success_rate', 'eklektik_total_attempts', 'eklektik_has_activity', 'ooredoo_success_rate', 'ooredoo_total_attempts', 'ooredoo_has_activity', 'payment_success_rate', 'total_payments'], $columns);
if (empty($select)) {
    $select = ['client_id', 'calculation_date', 'payment_success_rate', 'total_payments'];
}
$sample = DB::table('ml_client_features')->where('calculation_date', $date)->select($select)->limit(5)->get();
echo "\nExemple 5 lignes:\n";
print_r($sample->toArray());

if ($hasTimweAttempts) {
    $withData = DB::table('ml_client_features')->where('calculation_date', $date)->where('timwe_total_attempts', '>', 0)->select($select)->first();
    if ($withData) {
        echo "\nExemple ligne avec activite Timwe:\n";
        print_r((array) $withData);
    }
}
