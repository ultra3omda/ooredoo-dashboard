<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Analyse Eklektik : match client_abonnement (expiration NULL vs NOT NULL) avec transactions_history
 * pour classer les clients "facturation activée", voir les types de notifications associés,
 * et comparer avec eklektik_stats_daily (écart = hypothèse USSD).
 */
class AnalyzeEklektikBillingVsSubscriptionsCommand extends Command
{
    protected $signature = 'eklektik:analyze-billing-vs-subscriptions
                            {--days=90 : Période (jours) pour comparaison avec eklektik_stats_daily}
                            {--sample-null=500 : Max abonnements expiration NULL à analyser (0 = tous)}
                            {--sample-expired=500 : Max abonnements avec expiration à analyser (0 = tous)}
                            {--window-days=60 : Jours après création pour fenêtre transactions (expiration NULL)}
                            {--export= : Fichier optionnel pour export markdown}';

    protected $description = 'Analyse Eklektik : abonnements expiration NULL vs NOT NULL, types de notifications, écart vs eklektik_stats_daily (USSD)';

    private function eklektikStatusFilter($q): void
    {
        $q->where('th.status', 'LIKE', 'ORANGE_%')
            ->orWhere('th.status', 'LIKE', 'TARAJI_%')
            ->orWhere('th.status', 'LIKE', 'TT_%')
            ->orWhere('th.status', 'LIKE', '%EKLEKTIK%')
            ->orWhere('th.status', 'LIKE', 'EKLECTIC_%')
            ->orWhere('th.status', 'LIKE', '%CLUB_PRIVILEGE%');
    }

    private function isEklektikSuccess($status, $result): bool
    {
        if (str_contains((string) $status, 'CHARGE_DELIVERED') || str_contains((string) $status, 'RENEWED')) {
            return true;
        }
        $arr = is_string($result) ? json_decode($result, true) : ($result ?? []);
        if (! is_array($arr)) {
            return false;
        }
        if (! empty($arr['success'])) {
            return true;
        }
        $mno = $arr['mnoDeliveryCode'] ?? $arr['response']['mnoDeliveryCode'] ?? $arr['data']['mnoDeliveryCode'] ?? null;
        if ($mno === 'DELIVERED') {
            return true;
        }
        if (isset($arr['message']) && (string) $arr['message'] === 'OK') {
            return true;
        }
        if (array_key_exists('status', $arr) && (int) $arr['status'] === 0) {
            return true;
        }
        if (isset($arr['confirm']) && (string) $arr['confirm'] === 'ok') {
            return true;
        }
        return false;
    }

    public function handle(): int
    {
        @ini_set('memory_limit', '512M');

        $days = (int) $this->option('days');
        $sampleNull = (int) $this->option('sample-null');
        $sampleExpired = (int) $this->option('sample-expired');
        $windowDays = (int) $this->option('window-days');
        $exportPath = $this->option('export');

        $end = now()->endOfDay();
        $start = now()->copy()->subDays($days)->startOfDay();

        $out = [];
        $line = function (string $s) use (&$out) {
            $this->line($s);
            $out[] = $s;
        };
        $empty = function () use (&$out) {
            $this->newLine();
            $out[] = '';
        };

        $line('══════════════════════════════════════════════════════════════════════════════');
        $line('  ANALYSE EKLEKTIK : FACTURATION vs ABONNEMENTS (expiration NULL / NOT NULL)');
        $line('  Période référence : ' . $start->toDateString() . ' → ' . $end->toDateString());
        $line('══════════════════════════════════════════════════════════════════════════════');
        $empty();

        // ─── 1) CPM Eklektik (nom ou fallback par statuts transactions) ───
        $eklektikCpmIds = DB::table('country_payments_methods')
            ->where(function ($q) {
                $q->whereRaw("TRIM(LOWER(COALESCE(country_payments_methods_name,''))) LIKE '%eklektik%'")
                    ->orWhereRaw("TRIM(LOWER(COALESCE(country_payments_methods_name,''))) LIKE '%eklectic%'")
                    ->orWhereRaw("TRIM(LOWER(COALESCE(country_payments_methods_name,''))) LIKE '%club privilege%'")
                    ->orWhereRaw("TRIM(LOWER(COALESCE(country_payments_methods_name,''))) LIKE '%club privilège%'");
            })
            ->pluck('country_payments_methods_id')
            ->all();

        if (empty($eklektikCpmIds)) {
            // Fallback : CPM des abonnements dont le client a des tx Eklektik, en excluant Timwe/Ooredoo par nom
            $timweOoredooCpmIds = DB::table('country_payments_methods')
                ->where(function ($q) {
                    $q->whereRaw("TRIM(LOWER(COALESCE(country_payments_methods_name,''))) LIKE '%timwe%'")
                        ->orWhereRaw("TRIM(LOWER(COALESCE(country_payments_methods_name,''))) LIKE '%ooredoo%'")
                        ->orWhereRaw("TRIM(LOWER(COALESCE(country_payments_methods_name,''))) LIKE '%dgv%'");
                })
                ->pluck('country_payments_methods_id')
                ->all();

            $eklektikCpmIds = DB::table('client_abonnement as ca')
                ->join('transactions_history as th', 'th.client_id', '=', 'ca.client_id')
                ->where(function ($q) {
                    $q->where('th.status', 'LIKE', 'ORANGE_%')
                        ->orWhere('th.status', 'LIKE', 'TARAJI_%')
                        ->orWhere('th.status', 'LIKE', 'TT_%')
                        ->orWhere('th.status', 'LIKE', '%EKLEKTIK%')
                        ->orWhere('th.status', 'LIKE', 'EKLECTIC_%')
                        ->orWhere('th.status', 'LIKE', '%CLUB_PRIVILEGE%');
                })
                ->when(! empty($timweOoredooCpmIds), fn ($q) => $q->whereNotIn('ca.country_payments_methods_id', $timweOoredooCpmIds))
                ->distinct()
                ->pluck('ca.country_payments_methods_id')
                ->all();
        }

        if (empty($eklektikCpmIds)) {
            $this->warn('Aucun country_payments_methods Eklektik trouvé (ni par nom ni par transactions ORANGE/TARAJI/TT/EKLEKTIK).');
            $this->line('  Vérifiez les libellés dans country_payments_methods :');
            $allCpm = DB::table('country_payments_methods')
                ->select('country_payments_methods_id', 'country_payments_methods_name')
                ->get();
            foreach ($allCpm->take(30) as $r) {
                $this->line('    id=' . $r->country_payments_methods_id . ' name=' . ($r->country_payments_methods_name ?? '(null)'));
            }
            if ($allCpm->count() > 30) {
                $this->line('    ... et ' . ($allCpm->count() - 30) . ' autres.');
            }
            return self::FAILURE;
        }

        $line('  CPM Eklektik utilisés : ' . count($eklektikCpmIds) . ' (ids: ' . implode(', ', array_slice($eklektikCpmIds, 0, 10)) . (count($eklektikCpmIds) > 10 ? '...' : '') . ')');
        $empty();

        // ─── 2) Abonnements Eklektik expiration NULL (facturation activée) ───
        $line('── 1) CLIENTS EKLEKTIK AVEC FACTURATION ACTIVÉE (client_abonnement_expiration = NULL) ──');
        $qNull = DB::table('client_abonnement as ca')
            ->join('client as c', 'c.client_id', '=', 'ca.client_id')
            ->whereIn('ca.country_payments_methods_id', $eklektikCpmIds)
            ->whereNull('ca.client_abonnement_expiration')
            ->where('ca.client_abonnement_creation', '<=', $end)
            ->select('ca.client_abonnement_id', 'ca.client_id', 'ca.client_abonnement_creation', 'c.client_telephone')
            ->orderBy('ca.client_abonnement_creation', 'desc');
        if ($sampleNull > 0) {
            $qNull->limit($sampleNull);
        }
        $abosNull = $qNull->get();
        $countNull = $abosNull->count();
        $line('  Nombre d\'abonnements Eklektik avec expiration NULL (échantillon) : ' . number_format($countNull));
        if ($countNull === 0) {
            $line('  Aucun abonnement avec expiration NULL dans la période.');
        } else {
            $clientIdsNull = $abosNull->pluck('client_id')->unique()->values()->all();
            // Transactions Eklektik de ces clients sur la période d'analyse (types de notification reçus)
            $txNull = DB::table('transactions_history as th')
                ->whereIn('th.client_id', $clientIdsNull)
                ->where(function ($q) {
                    $this->eklektikStatusFilter($q);
                })
                ->whereBetween('th.created_at', [$start, $end])
                ->select('th.status', 'th.result', 'th.client_id', 'th.created_at')
                ->get();

            $byStatusNull = [];
            $successCountNull = 0;
            foreach ($txNull as $t) {
                $s = $t->status ?? '';
                $byStatusNull[$s] = ($byStatusNull[$s] ?? 0) + 1;
                if ($this->isEklektikSuccess($t->status, $t->result)) {
                    $successCountNull++;
                }
            }
            ksort($byStatusNull);
            $line('  Transactions Eklektik de ces clients sur la période : ' . number_format($txNull->count()));
            $line('  Dont considérées succès (critères ML) : ' . number_format($successCountNull));
            $empty();
            $line('  Répartition par type de notification (statut) :');
            $rows = [];
            foreach ($byStatusNull as $status => $cnt) {
                $rows[] = [$status, number_format($cnt)];
            }
            $this->table(['Statut', 'Nombre'], $rows);
            foreach ($rows as $r) {
                $out[] = '  - ' . $r[0] . ' : ' . $r[1];
            }
        }
        $empty();

        // ─── 3) Abonnements Eklektik avec expiration NOT NULL (unsub) ───
        $line('── 2) ÉCHANTILLON EKLEKTIK AVEC DATE D\'EXPIRATION (unsub) ──');
        $qExpired = DB::table('client_abonnement as ca')
            ->join('client as c', 'c.client_id', '=', 'ca.client_id')
            ->whereIn('ca.country_payments_methods_id', $eklektikCpmIds)
            ->whereNotNull('ca.client_abonnement_expiration')
            ->whereBetween('ca.client_abonnement_creation', [$start, $end])
            ->select('ca.client_abonnement_id', 'ca.client_id', 'ca.client_abonnement_creation', 'ca.client_abonnement_expiration', 'c.client_telephone')
            ->orderBy('ca.client_abonnement_creation', 'desc');
        if ($sampleExpired > 0) {
            $qExpired->limit($sampleExpired);
        }
        $abosExpired = $qExpired->get();
        $countExpired = $abosExpired->count();
        $line('  Nombre d\'abonnements Eklektik avec expiration renseignée (échantillon) : ' . number_format($countExpired));
        if ($countExpired === 0) {
            $line('  Aucun abonnement avec expiration dans la période.');
        } else {
            $byStatusExpired = [];
            $successCountExpired = 0;
            $durations = [];
            foreach ($abosExpired as $abo) {
                $cre = \Carbon\Carbon::parse($abo->client_abonnement_creation);
                $exp = \Carbon\Carbon::parse($abo->client_abonnement_expiration);
                $durations[] = $cre->diffInDays($exp);

                $txBetween = DB::table('transactions_history as th')
                    ->where('th.client_id', $abo->client_id)
                    ->where(function ($q) {
                        $this->eklektikStatusFilter($q);
                    })
                    ->whereBetween('th.created_at', [$abo->client_abonnement_creation, $abo->client_abonnement_expiration])
                    ->select('th.status', 'th.result')
                    ->get();
                foreach ($txBetween as $t) {
                    $s = $t->status ?? '';
                    $byStatusExpired[$s] = ($byStatusExpired[$s] ?? 0) + 1;
                    if ($this->isEklektikSuccess($t->status, $t->result)) {
                        $successCountExpired++;
                    }
                }
            }
            $line('  Durée moyenne (création → expiration) : ' . round(array_sum($durations) / count($durations), 1) . ' jours');
            $line('  Total transactions Eklektik entre création et expiration : ' . number_format(array_sum($byStatusExpired)));
            $line('  Dont succès (critères ML) : ' . number_format($successCountExpired));
            ksort($byStatusExpired);
            $line('  Répartition par statut (entre création et expiration) :');
            $rowsExp = [];
            foreach ($byStatusExpired as $status => $cnt) {
                $rowsExp[] = [$status, number_format($cnt)];
            }
            $this->table(['Statut', 'Nombre'], $rowsExp);
            foreach ($rowsExp as $r) {
                $out[] = '  - ' . $r[0] . ' : ' . $r[1];
            }
        }
        $empty();

        // ─── 4) Comparaison avec eklektik_stats_daily (charges) et hypothèse USSD ───
        $line('── 3) COMPARAISON AVEC EKLEKTIK_STATS_DAILY (charges officielles) ──');
        $officialCharges = (int) DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->sum('charges');
        $ourSuccessCount = 0;
        DB::table('transactions_history as th')
            ->whereBetween('th.created_at', [$start, $end])
            ->where(function ($q) {
                $this->eklektikStatusFilter($q);
            })
            ->select('th.status', 'th.result')
            ->orderBy('th.transaction_history_id')
            ->chunkById(5000, function ($rows) use (&$ourSuccessCount) {
                foreach ($rows as $t) {
                    if ($this->isEklektikSuccess($t->status, $t->result)) {
                        $ourSuccessCount++;
                    }
                }
            }, 'transaction_history_id');
        $line('  Somme eklektik_stats_daily.charges (période) : ' . number_format($officialCharges));
        $line('  Succès déduits de transactions_history (mêmes critères ML) : ' . number_format($ourSuccessCount));
        $diff = $officialCharges - $ourSuccessCount;
        if ($officialCharges > 0 || $ourSuccessCount > 0) {
            $line('  Écart (officiel - nous) : ' . ($diff >= 0 ? '+' : '') . number_format($diff));
            if ($diff > 0) {
                $line('');
                $line('  Hypothèse : les facturations en plus côté officiel peuvent correspondre à des');
                $line('  abonnements inscrits via USSD (ou autre canal) pour lesquels nous n\'avons pas');
                $line('  de réponse/transaction dans transactions_history.');
            }
        }
        $empty();

        $line('── Résumé ──');
        $line('  - Expiration NULL = abonnement resté actif après facturation (indicateur facturation activée).');
        $line('  - Les types de notification (statuts) ci-dessus montrent ce qui est envoyé/reçu pour ces clients.');
        $line('  - Expiration NOT NULL = unsub ; les transactions entre création et expiration décrivent le parcours.');
        $line('  - Écart positif officiel vs nous = possible part USSD (sans trace dans transactions_history).');

        if ($exportPath !== null && $exportPath !== '') {
            $md = implode("\n", $out);
            file_put_contents($exportPath, $md);
            $this->info('Export écrit dans : ' . $exportPath);
        }

        return self::SUCCESS;
    }
}
