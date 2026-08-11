<?php

namespace App\Traits;

use Carbon\Carbon;

/**
 * Décide si une table matérialisée peut servir une période donnée.
 *
 * Le seul critère de volume ne suffit pas : une table figée depuis des semaines
 * garde un taux de remplissage élevé sur une longue période, passe le seuil, et
 * les jours manquants sont alors rendus à zéro au lieu de basculer sur la
 * requête live. Le graphe affiche une courbe normale puis un plat à zéro.
 *
 * On exige donc en plus que la FIN de la fenêtre soit matérialisée.
 */
trait MaterializedCoverage
{
    /**
     * @param \Illuminate\Database\Query\Builder $scopedQuery Requête déjà restreinte
     *        à la table, à la fenêtre de dates et à l'opérateur voulus.
     */
    protected function hasFreshMaterializedCoverage(
        $scopedQuery,
        Carbon $startBound,
        Carbon $endExclusive,
        float $minRatio = 0.8
    ): bool {
        try {
            $expectedDays = $startBound->diffInDays($endExclusive);
            if ($expectedDays <= 0) {
                return false;
            }

            // 1. Volume : assez de jours présents dans la fenêtre ?
            if (((clone $scopedQuery)->count() / $expectedDays) < $minRatio) {
                return false;
            }

            // 2. Fraîcheur : le dernier jour attendu est-il présent ?
            // Le batch tourne de nuit, "aujourd'hui" n'est donc pas exigible.
            $lastDayOfWindow = $endExclusive->copy()->subDay()->startOfDay();
            $lastMaterializable = Carbon::yesterday()->startOfDay();
            $requiredDay = $lastDayOfWindow->lte($lastMaterializable)
                ? $lastDayOfWindow
                : $lastMaterializable;

            $lastCovered = (clone $scopedQuery)->max('stat_date');
            if (!$lastCovered) {
                return false;
            }

            return Carbon::parse($lastCovered)->startOfDay()->gte($requiredDay);
        } catch (\Exception $e) {
            return false;
        }
    }
}
