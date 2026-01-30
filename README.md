# Ooredoo Privileges Dashboard - Laravel

Un tableau de bord Laravel PHP qui reproduit exactement le template HTML fourni pour analyser les performances d'Ooredoo Privilèges, avec intégration webservice prête pour l'accès à la base de données.

## 🚀 Fonctionnalités

### Interface Utilisateur
- **Design identique** au template HTML original
- **6 onglets d'analyse** : Overview, Subscriptions, Transactions, Merchants, Comparison, Insights
- **KPIs en temps réel** : Abonnements, transactions, marchands, taux de conversion
- **Graphiques interactifs** : Chart.js pour les visualisations
- **Interface responsive** : Compatible desktop et mobile
- **Logo Ooredoo intégré**

### Architecture Backend
- **Laravel 10** avec architecture MVC
- **API REST** pour les données
- **Service WebserviceClient** pour l'intégration externe
- **Cache intelligent** avec fallback automatique
- **Gestion d'erreurs robuste**
- **Configuration flexible** via variables d'environnement

### Données et Métriques
- **Périodes de comparaison** : 1-14 août vs 18-31 juillet 2025
- **Filtrage par méthode de paiement** : Subscribe via Timwe
- **KPIs détaillés** : Abonnements activés (12,321), transactions (32), marchands (16)
- **Analyses comparatives** automatiques avec calculs de pourcentages
- **Insights stratégiques** et recommandations

## 📁 Structure du Projet

```
ooredoo-dashboard/
├── app/
│   ├── Http/Controllers/
│   │   ├── DashboardController.php      # Contrôleur principal
│   │   └── Api/DataController.php       # API REST
│   ├── Services/
│   │   └── WebserviceClient.php         # Client webservice
│   └── Providers/
│       └── WebserviceServiceProvider.php # Service provider
├── resources/views/
│   └── dashboard.blade.php              # Vue principale (template HTML)
├── routes/
│   ├── web.php                          # Routes web
│   └── api.php                          # Routes API
├── config/
│   └── services.php                     # Configuration webservice
└── README.md
```

## 🛠️ Installation

### Prérequis
- PHP 8.1+
- Composer
- Node.js (optionnel pour les assets)

### Étapes d'installation

1. **Cloner/Copier le projet**
```bash
cd /path/to/your/projects
cp -r /home/ubuntu/ooredoo-dashboard ./
cd ooredoo-dashboard
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Configuration environnement**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configurer le webservice** (dans `.env`)
```env
WEBSERVICE_BASE_URL=http://your-webservice-url
WEBSERVICE_API_KEY=your_api_key_here
WEBSERVICE_TIMEOUT=30
```

5. **Démarrer l'application**
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

6. **Accéder au dashboard**
```
http://localhost:8000
```

## 🔗 Intégration Webservice

### Configuration
Le projet est prêt pour l'intégration avec votre webservice d'accès à la base de données :

```php
// config/services.php
'webservice' => [
    'base_url' => env('WEBSERVICE_BASE_URL', 'http://localhost:8080'),
    'api_key' => env('WEBSERVICE_API_KEY', ''),
    'timeout' => env('WEBSERVICE_TIMEOUT', 30),
]
```

### Endpoints attendus
Le WebserviceClient attend ces endpoints de votre webservice :

- `GET /api/kpis` - KPIs principaux
- `GET /api/merchants` - Données marchands
- `GET /api/transactions` - Données transactions
- `GET /api/subscriptions` - Données abonnements
- `GET /api/insights` - Insights et recommandations
- `GET /api/health` - Test de connexion
- `GET /api/status` - Statut du service

### Paramètres de requête
```
start_date=2025-08-01
end_date=2025-08-14
comparison_start_date=2025-07-18
comparison_end_date=2025-07-31
payment_method=timwe
```

### Fallback automatique
Si le webservice n'est pas disponible, l'application utilise automatiquement des données de démonstration basées sur vos chiffres réels.

## 📊 API REST

### Endpoints disponibles
- `GET /api/dashboard/data` - Données complètes du dashboard
- `GET /api/dashboard/kpis` - KPIs uniquement
- `GET /api/dashboard/merchants` - Données marchands
- `GET /api/dashboard/transactions` - Données transactions
- `GET /api/dashboard/subscriptions` - Données abonnements

### Exemple de réponse
```json
{
  "periods": {
    "primary": "August 1-14, 2025",
    "comparison": "July 18-31, 2025"
  },
  "kpis": {
    "activatedSubscriptions": {
      "current": 12321,
      "previous": 2129,
      "change": 478.8
    }
  },
  "last_updated": "2025-08-18T14:17:17.000000Z"
}
```

## 🎨 Personnalisation

### Couleurs et style
Les couleurs Ooredoo sont définies dans le CSS :
```css
:root {
  --brand-red: #E30613;
  --brand-dark: #1f2937;
  --bg: #f8fafc;
}
```

### Périodes de données
Modifiez les périodes dans `DashboardController.php` :
```php
'periods' => [
    'primary' => [
        'start_date' => '2025-08-01',
        'end_date' => '2025-08-14',
    ]
]
```

## 🔧 Maintenance

### Cache
Le système utilise un cache intelligent :
- **TTL** : 1 heure par défaut
- **Clé** : `dashboard_data_YYYY-MM-DD-HH`
- **Nettoyage** : Automatique

### Logs
Les erreurs sont loggées dans `storage/logs/laravel.log` :
```php
Log::error('WebserviceClient::getKpis failed: ' . $e->getMessage());
```

### Monitoring
Vérifiez la santé du webservice :
```php
$client = app(WebserviceClient::class);
$isConnected = $client->testConnection();
```

## 📈 Données Actuelles

### KPIs Période 1-14 Août 2025
- **Abonnements activés** : 12,321 (+478.8%)
- **Abonnements actifs** : 11,586 (+543.7%)
- **Transactions totales** : 32 (-3.0%)
- **Utilisateurs transactionnels** : 28 (+3.7%)
- **Marchands actifs** : 16 (+33.3%)
- **Taux de conversion** : 0.24% (vs benchmark 30%)

### Points Clés
- ✅ Croissance exceptionnelle des abonnements
- ✅ Excellent taux de rétention (94.0%)
- ⚠️ Conversion transactionnelle à améliorer
- 🎯 Focus sur l'engagement utilisateur

## 🚀 Prochaines Étapes

1. **Connecter votre webservice** avec les vraies données
2. **Tester l'intégration** avec vos endpoints
3. **Personnaliser les périodes** selon vos besoins
4. **Déployer en production** sur votre infrastructure

## 📞 Support

Le projet est entièrement fonctionnel et prêt pour l'intégration avec votre webservice d'accès à la base de données. Tous les composants sont en place pour une mise en production rapide.

---

**Développé pour Ooredoo Privilèges** - Dashboard d'analyse des performances avec Laravel PHP
