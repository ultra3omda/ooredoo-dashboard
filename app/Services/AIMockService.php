<?php

namespace App\Services;

use App\Services\AIContextProvider;
use Illuminate\Support\Str;

class AIMockService
{
    private AIContextProvider $contextProvider;

    public function __construct(AIContextProvider $contextProvider)
    {
        $this->contextProvider = $contextProvider;
    }

    /**
     * Simule une réponse d'agent IA basée sur des patterns pré-définis
     */
    public function generateMockResponse(string $question): array
    {
        $questionLower = strtolower($question);
        $context = $this->contextProvider->getSystemContext();
        
        // Patterns de questions et réponses
        if (preg_match('/(taux|succès|performance|actuel)/i', $question)) {
            return [
                'message' => $this->getSuccessRateResponse($context),
                'tokens_used' => 156
            ];
        }
        
        if (preg_match('/(quotidien|mensuel|compare|roi|stratégie)/i', $question)) {
            return [
                'message' => $this->getStrategyComparisonResponse(),
                'tokens_used' => 245
            ];
        }
        
        if (preg_match('/(features?|ml|machine|important)/i', $question)) {
            return [
                'message' => $this->getFeaturesResponse(),
                'tokens_used' => 189
            ];
        }
        
        if (preg_match('/(high.risk|29k|struggling)/i', $question)) {
            return [
                'message' => $this->getHighRiskResponse(),
                'tokens_used' => 198
            ];
        }
        
        if (preg_match('/(client|id|analyse|\d+)/i', $question)) {
            return [
                'message' => $this->getClientAnalysisResponse($question),
                'tokens_used' => 167
            ];
        }
        
        if (preg_match('/(timwe|eklektik|ooredoo|opérateur)/i', $question)) {
            return [
                'message' => $this->getOperatorComparisonResponse(),
                'tokens_used' => 223
            ];
        }
        
        // Réponse générale
        return [
            'message' => $this->getGeneralResponse($question, $context),
            'tokens_used' => 134
        ];
    }

    private function getSuccessRateResponse($context): string
    {
        $currentRate = $context['current_performance']['global_success_rate'] ?? 0.0909;
        $currentPercent = round($currentRate * 100, 2);
        
        return "📊 **Taux de Succès Global Actuel : {$currentPercent}%**

**Répartition par segment :**
- **Premium payers** : 91.3% (591 clients) ✅
- **Regular payers** : 54.3% (3,852 clients) 
- **Struggling payers** : 24.6% (2,726 clients) ⚠️
- **High risk** : 0.2% (29,439 clients) ❌
- **Churn risk** : 0.5% (252 clients) ❌

**💡 Analyse :**
78.5% des clients ont 0% de succès - c'est le **défi principal**. La stratégie quotidienne 0.3 TND peut réactiver ces 67k+ clients perdus.

**🎯 Recommandation :** Migration vers quotidien 0.3 TND = +643% ROI attendu.";
    }

    private function getStrategyComparisonResponse(): string
    {
        return "💰 **Comparaison Stratégies de Pricing :**

| **Stratégie** | **Prix** | **Fréquence** | **Taux Attendu** | **Revenus/Mois** | **ROI vs Actuel** |
|---------------|----------|---------------|------------------|------------------|-------------------|
| **🔶 Quotidien** | 0.3 TND | 30x/mois | **25%** | **173,632 TND** | **+643%** ⭐ |
| **🔸 Hebdomadaire** | 1.0 TND | 4x/mois | 18% | 37,041 TND | +58% |
| **🔷 Mensuel** | 3.0 TND | 1x/mois | 15% | 7,717 TND | -67% |

**🎯 Pourquoi Quotidien 0.3 TND ?**
1. **Volume ×30** : Plus d'occasions de succès
2. **Accessibilité** : Prix acceptable même en cas d'échec  
3. **Réactivation** : 67k clients à 0% succès deviennent utilisables
4. **Apprentissage ML** : Feedback 30x plus rapide

**💡 Recommandation :** Commencer par migrer les 29k clients high_risk vers quotidien 0.3 TND.";
    }

    private function getFeaturesResponse(): string
    {
        return "🧠 **Top 5 Features ML les Plus Importantes :**

| **Rang** | **Feature** | **Importance** | **Description** |
|----------|-------------|----------------|-----------------|
| **1** | `consecutive_failures` | 85.2 | Nombre d'échecs consécutifs (prédicteur #1) |
| **2** | `payment_success_rate` | 78.1 | Taux de succès historique du client |
| **3** | `total_payments` | 65.3 | Nombre total de paiements réussis |
| **4** | `recovery_after_failure_rate` | 54.7 | Capacité à récupérer après un échec |
| **5** | `timwe_success_rate` | 48.2 | Performance spécifique sur Timwe |

**🔍 Insights :**
- Les **échecs consécutifs** sont le facteur le plus prédictif
- La **capacité de récupération** est cruciale pour les struggling_payers
- Les **features multi-opérateur** (v2.1) améliorent la précision

**💡 Utilisation :** Ces features permettent au modèle LightGBM d'atteindre 0.876 AUC vs 0.7 baseline.";
    }

    private function getHighRiskResponse(): string
    {
        return "🚨 **Analyse Segment High Risk (29,439 clients) :**

**📊 Situation Actuelle :**
- **Taux de succès :** 0.2% (quasi-échec total)
- **Revenus perdus :** ~265k TND/mois de potentiel inexploité
- **Cause principale :** Prix 3.0 TND trop élevé

**🎯 Stratégie Recommandée : Migration Quotidienne 0.3 TND**

**Calcul ROI :**
- **Taux attendu** : 0.2% → 20% (×100 amélioration)
- **Succès quotidiens** : 29,439 × 0.20 = 5,888/jour  
- **Revenus** : 5,888 × 0.3 TND = 1,766 TND/jour
- **Revenus mensuels** : 52,980 TND (+2,265% vs actuel)

**📋 Plan d'Action :**
1. **A/B test** sur 1,000 clients high_risk
2. **Migration par batches** si test positif
3. **Monitoring quotidien** des taux de conversion
4. **Escalade progressive** : clients performants → 1.0 TND hebdo

**⏱️ Timeline :** 2 semaines test + 1 mois migration complète.";
    }

    private function getClientAnalysisResponse(string $question): string
    {
        // Extraire ID client si présent
        preg_match('/(\d+)/', $question, $matches);
        $clientId = $matches[1] ?? '12345';
        
        return "👤 **Analyse Client ID {$clientId} :**

**🎯 Profil Simulé :**
- **Segment :** struggling_payers  
- **Taux de succès :** 24.6%
- **Échecs consécutifs :** 3
- **Dernière tentative :** Il y a 15 jours
- **Opérateur préféré :** Timwe (mensuel)

**🔮 Prédictions ML :**
- **Probabilité succès Timwe :** 28% (mensuel 3.0 TND)
- **Probabilité succès Eklektik :** 42% (quotidien 0.3 TND)
- **Timing optimal :** Fin de mois, 14h-16h

**💡 Recommandation Personnalisée :**
1. **Migration vers Eklektik quotidien** (+50% chance succès)
2. **Timing :** Début de semaine, 15h
3. **Escalade :** Si 5 succès → passer à hebdo 1.0 TND

**📈 Impact attendu :** 24% → 42% succès (+75% amélioration)";
    }

    private function getOperatorComparisonResponse(): string
    {
        return "🌐 **Comparaison Multi-Opérateur :**

| **Opérateur** | **Modèle** | **Prix** | **Cible** | **Taux Attendu** |
|---------------|------------|----------|-----------|------------------|
| **🔶 Eklektik** | Club Privilèges Quotidien | 0.3 TND | Volume/Accessibilité | **25-30%** |
| **🔷 Timwe** | Premium Mensuel | 3.0 TND | Clients Premium | **12-18%** |
| **🔸 Ooredoo** | Loyauté Mensuelle | 3.0 TND | Base Ooredoo | **15-22%** |

**🎯 Recommandations par Profil :**

**Pour High Risk (29k) :** 
- **Eklektik quotidien** - Faible coût, haute fréquence

**Pour Struggling (2.7k) :**
- **Eklektik quotidien** - Prix accessible  

**Pour Regular (3.9k) :**
- **A/B test** Eklektik quotidien vs Timwe mensuel

**Pour Premium (591) :**
- **Timwe mensuel** - Conserver ce qui marche

**💡 Stratégie globale :** 90% Eklektik + 10% Timwe/Ooredoo = optimisation revenus/volume.";
    }

    private function getGeneralResponse(string $question, $context): string
    {
        return "🤖 **Analyse de votre Question :**

Vous me demandez : *\"{$question}\"*

**📊 Données Disponibles :**
- **85,744 clients** analysés avec 36 features ML
- **5 segments** de performance identifiés
- **3 opérateurs** : Timwe, Eklektik, Ooredoo/DGV
- **Modèles ML** : LightGBM + Rule-based (AUC 0.7+)

**💡 Suggestions de Questions :**
- \"Quel est le taux de succès actuel ?\"
- \"Compare quotidien vs mensuel ROI\"
- \"Stratégie pour high_risk clients\"
- \"Analyse segment premium_payers\"
- \"Top features ML importantes\"

**🎯 Mon Expertise :** Je peux analyser vos segments, recommander des stratégies de pricing, expliquer les modèles ML, et calculer le ROI de différentes approches.

*Reformulez votre question plus spécifiquement pour une réponse ciblée !*";
    }
}