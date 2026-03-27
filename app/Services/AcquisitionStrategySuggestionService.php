<?php

namespace App\Services;

use App\Models\MLClientFeature;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Suggestions alignées sur la stratégie d'acquisition :
 * - Canaux : bulk SMS, campagnes digitales, USSD (Eklektik)
 * - Agrégateurs : Eklektik, DGV/Ooredoo, Timwe
 * - Objectifs : acquisition, conversion (trial → payant), taux de facturation, CA
 */
class AcquisitionStrategySuggestionService
{
    /**
     * Retourne des suggestions pour acquisition, conversion et taux de facturation,
     * au format attendu par MLRecommendationService::storeRecommendations().
     *
     * @return array Liste de tableaux [ type, current_strategy, recommended_strategy, reasoning, priority, expected_impact_percentage ]
     */
    public function getStrategySuggestions(Carbon $analysisDate = null): array
    {
        if (!$analysisDate) {
            $analysisDate = Carbon::today();
        }

        $suggestions = [];

        try {
            $operatorStats = $this->getOperatorStats($analysisDate);
            $segmentStats = MLClientFeature::getSegmentStats($analysisDate);

            // --- Acquisition ---
            $suggestions = array_merge(
                $suggestions,
                $this->suggestAcquisition($operatorStats, $segmentStats, $analysisDate)
            );

            // --- Conversion (trial → payant) ---
            $suggestions = array_merge(
                $suggestions,
                $this->suggestConversion($operatorStats, $segmentStats, $analysisDate)
            );

            // --- Taux de facturation (timing, daily vs mensuel, tentatives) ---
            $suggestions = array_merge(
                $suggestions,
                $this->suggestBillingRate($operatorStats, $segmentStats, $analysisDate)
            );

            // --- Eklektik USSD (CA hors base) ---
            $suggestions = array_merge(
                $suggestions,
                $this->suggestEklektikUSSD($operatorStats, $analysisDate)
            );
        } catch (\Throwable $e) {
            Log::warning('AcquisitionStrategySuggestionService - Erreur génération suggestions', [
                'error' => $e->getMessage(),
            ]);
        }

        return $suggestions;
    }

    /**
     * Statistiques par opérateur (agrégateur) sur la date d'analyse.
     */
    private function getOperatorStats(Carbon $date): array
    {
        $latestDate = MLClientFeature::where('calculation_date', '<=', $date->toDateString())
            ->max('calculation_date');
        if (!$latestDate) {
            return [];
        }

        $rows = MLClientFeature::where('calculation_date', $latestDate)
            ->selectRaw('
                AVG(timwe_success_rate) as timwe_success_rate,
                SUM(timwe_total_attempts) as timwe_total_attempts,
                SUM(timwe_total_successes) as timwe_total_successes,
                AVG(eklektik_success_rate) as eklektik_success_rate,
                SUM(eklektik_total_attempts) as eklektik_total_attempts,
                SUM(eklektik_total_subscriptions) as eklektik_total_subscriptions,
                AVG(ooredoo_success_rate) as ooredoo_success_rate,
                SUM(ooredoo_total_attempts) as ooredoo_total_attempts,
                SUM(ooredoo_total_subscriptions) as ooredoo_total_subscriptions,
                AVG(payment_success_rate) as portfolio_success_rate,
                COUNT(*) as total_clients
            ')
            ->first();

        if (!$rows || $rows->total_clients < 1) {
            return [];
        }

        return [
            'timwe' => [
                'success_rate' => (float) ($rows->timwe_success_rate ?? 0),
                'total_attempts' => (int) ($rows->timwe_total_attempts ?? 0),
                'total_successes' => (int) ($rows->timwe_total_successes ?? 0),
            ],
            'eklektik' => [
                'success_rate' => (float) ($rows->eklektik_success_rate ?? 0),
                'total_attempts' => (int) ($rows->eklektik_total_attempts ?? 0),
                'total_subscriptions' => (int) ($rows->eklektik_total_subscriptions ?? 0),
            ],
            'ooredoo' => [
                'success_rate' => (float) ($rows->ooredoo_success_rate ?? 0),
                'total_attempts' => (int) ($rows->ooredoo_total_attempts ?? 0),
                'total_subscriptions' => (int) ($rows->ooredoo_total_subscriptions ?? 0),
            ],
            'portfolio_success_rate' => (float) ($rows->portfolio_success_rate ?? 0),
            'total_clients' => (int) $rows->total_clients,
        ];
    }

    /**
     * Suggestions pour améliorer l'acquisition (bulk SMS, digital, par agrégateur).
     */
    private function suggestAcquisition(array $operatorStats, array $segmentStats, Carbon $analysisDate): array
    {
        $suggestions = [];

        if (empty($operatorStats)) {
            return $suggestions;
        }

        $bestOperator = null;
        $bestRate = 0;
        foreach (['timwe', 'eklektik', 'ooredoo'] as $op) {
            $rate = $operatorStats[$op]['success_rate'] ?? 0;
            if ($rate > $bestRate) {
                $bestRate = $rate;
                $bestOperator = $op;
            }
        }

        $worstOperator = null;
        $worstRate = 1;
        foreach (['timwe', 'eklektik', 'ooredoo'] as $op) {
            $rate = $operatorStats[$op]['success_rate'] ?? 0;
            if ($rate < $worstRate && ($operatorStats[$op]['total_attempts'] ?? 0) > 0) {
                $worstRate = $rate;
                $worstOperator = $op;
            }
        }

        // Cibler les canaux (SMS vs digital) selon le meilleur opérateur
        $bestName = $this->operatorLabel($bestOperator);
        $suggestions[] = [
            'type' => 'global_strategy',
            'current_strategy' => 'Acquisition uniforme bulk SMS / digital',
            'recommended_strategy' => "Renforcer l'acquisition bulk SMS et digital via {$bestName} (taux succès " . round($bestRate * 100, 1) . "%)",
            'reasoning' => "[Acquisition] Agrégateur {$bestName} affiche le meilleur taux de succès. Prioriser les campagnes (SMS + digital) sur ce partenaire tout en gardant la période gratuite puis prélèvement selon type d'abonnement.",
            'priority' => $bestRate > 0.25 ? 'high' : 'medium',
            'expected_impact_percentage' => max(5, min(30, (int) (($bestRate - ($operatorStats['portfolio_success_rate'] ?? 0)) * 100))),
        ];

        // Segment qui convertit le mieux → cibler l'acquisition vers des profils similaires
        $bestSegment = null;
        $bestSegRate = 0;
        foreach ($segmentStats as $seg) {
            if (($seg['avg_success_rate'] ?? 0) > $bestSegRate && ($seg['count'] ?? 0) >= 50) {
                $bestSegRate = $seg['avg_success_rate'];
                $bestSegment = $seg['segment'] ?? null;
            }
        }
        if ($bestSegment && $bestSegRate > 15) {
            $suggestions[] = [
                'type' => 'global_strategy',
                'current_strategy' => 'Acquisition tous segments',
                'recommended_strategy' => "Cibler l'acquisition vers des profils type « {$bestSegment} » (taux succès " . round($bestSegRate, 1) . "%)",
                'reasoning' => "[Acquisition] Le segment {$bestSegment} convertit le mieux. Adapter les messages bulk SMS et campagnes digitales pour attirer des profils similaires (âge, usage, opérateur).",
                'priority' => 'medium',
                'expected_impact_percentage' => 15,
            ];
        }

        // Si un agrégateur est en retard
        if ($worstOperator && $worstRate < 0.2 && ($operatorStats[$worstOperator]['total_attempts'] ?? 0) > 100) {
            $worstName = $this->operatorLabel($worstOperator);
            $suggestions[] = [
                'type' => 'global_strategy',
                'current_strategy' => "Acquisition {$worstName} inchangée",
                'recommended_strategy' => "Revoir offre et timing de prélèvement {$worstName} (taux " . round($worstRate * 100, 1) . "%)",
                'reasoning' => "[Acquisition] {$worstName} a un taux de succès faible. Vérifier la durée de la période gratuite, le type d'abonnement proposé (daily 0,3 DT vs mensuel) et le nombre de tentatives par jour pour le daily.",
                'priority' => 'high',
                'expected_impact_percentage' => 25,
            ];
        }

        return $suggestions;
    }

    /**
     * Suggestions pour améliorer la conversion (période gratuite → premier prélèvement).
     */
    private function suggestConversion(array $operatorStats, array $segmentStats, Carbon $analysisDate): array
    {
        $suggestions = [];

        if (empty($operatorStats)) {
            return $suggestions;
        }

        $portfolioRate = $operatorStats['portfolio_success_rate'] ?? 0;
        $portfolioPct = round($portfolioRate * 100, 1);

        if ($portfolioPct < 25) {
            $suggestions[] = [
                'type' => 'global_strategy',
                'current_strategy' => 'Conversion trial → payant actuelle',
                'recommended_strategy' => 'Rappel SMS/notification en fin de période gratuite + première tentative au créneau optimal',
                'reasoning' => "[Conversion] Taux de succès global {$portfolioPct}%. Améliorer la conversion en : (1) rappel avant fin de gratuité (bulk SMS ou in-app), (2) lancer la 1ère tentative de prélèvement au meilleur créneau horaire identifié par le modèle.",
                'priority' => 'critical',
                'expected_impact_percentage' => 35,
            ];
        }

        // Proposer offre daily 0,3 DT pour les segments à faible conversion
        $lowSegments = array_filter($segmentStats, fn($s) => ($s['avg_success_rate'] ?? 0) < 20 && ($s['count'] ?? 0) > 80);
        if (count($lowSegments) > 0) {
            $segNames = implode(', ', array_map(fn($s) => $s['segment'], $lowSegments));
            $suggestions[] = [
                'type' => 'global_strategy',
                'current_strategy' => 'Offre unique ou majoritairement mensuelle',
                'recommended_strategy' => "Proposer offre daily 0,3 DT en option pour segments à faible conversion ({$segNames})",
                'reasoning' => "[Conversion] Ces segments convertissent mal en mensuel. Le daily 0,3 DT permet plusieurs tentatives par jour selon l'agrégateur et peut augmenter la conversion trial → premier paiement.",
                'priority' => 'high',
                'expected_impact_percentage' => 20,
            ];
        }

        return $suggestions;
    }

    /**
     * Suggestions pour améliorer le taux de facturation (timing, daily vs mensuel, tentatives).
     */
    private function suggestBillingRate(array $operatorStats, array $segmentStats, Carbon $analysisDate): array
    {
        $suggestions = [];

        if (empty($operatorStats)) {
            return $suggestions;
        }

        // Concentrer les tentatives aux heures où le taux de succès est le plus élevé
        $suggestions[] = [
            'type' => 'global_strategy',
            'current_strategy' => 'Tentatives de prélèvement réparties',
            'recommended_strategy' => 'Concentrer les tentatives (daily et mensuel) sur les créneaux à fort taux de succès (voir prédictions ML)',
            'reasoning' => "[Taux de facturation] Pour le daily 0,3 DT (une ou plusieurs tentatives/jour selon agrégateur) et le mensuel : utiliser le meilleur créneau horaire par segment pour maximiser le taux de facturation.",
            'priority' => 'high',
            'expected_impact_percentage' => 22,
        ];

        // Équilibre daily vs mensuel
        $suggestions[] = [
            'type' => 'global_strategy',
            'current_strategy' => 'Mix offre daily / mensuel selon agrégateur',
            'recommended_strategy' => 'Analyser par agrégateur le ratio tentatives/succès daily vs mensuel et ajuster le mix',
            'reasoning' => "[Taux de facturation] Chaque agrégateur prélève à sa façon (daily 0,3 DT : une ou plusieurs tentatives/jour). Comparer les taux par type d'offre et favoriser le type le plus performant par partenaire.",
            'priority' => 'medium',
            'expected_impact_percentage' => 15,
        ];

        return $suggestions;
    }

    /**
     * Suggestions spécifiques Eklektik (CA facturé hors base, USSD).
     */
    private function suggestEklektikUSSD(array $operatorStats, Carbon $analysisDate): array
    {
        $suggestions = [];

        $eklektik = $operatorStats['eklektik'] ?? null;
        if (!$eklektik || ($eklektik['total_subscriptions'] ?? 0) == 0) {
            return $suggestions;
        }

        $suggestions[] = [
            'type' => 'global_strategy',
            'current_strategy' => 'KPIs Eklektik basés uniquement sur la base clients',
            'recommended_strategy' => 'Suivre séparément le CA Eklektik USSD (clients non en base) pour avoir une vue complète',
            'reasoning' => "[Eklektik USSD] Une partie du CA Eklektik provient d'utilisateurs ayant activé par USSD sans télécharger l'app (non présents en base). Pour améliorer acquisition et conversion : tracer le CA facturé USSD vs in-app et aligner les objectifs.",
            'priority' => 'high',
            'expected_impact_percentage' => 10,
        ];

        return $suggestions;
    }

    private function operatorLabel(string $key): string
    {
        return match ($key) {
            'timwe' => 'Timwe',
            'eklektik' => 'Eklektik',
            'ooredoo' => 'DGV/Ooredoo',
            default => $key,
        };
    }
}
