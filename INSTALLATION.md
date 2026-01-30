# Guide d'Installation Rapide - Ooredoo Dashboard

## 🚀 Installation en 5 minutes

### 1. Copier le projet
```bash
cp -r /home/ubuntu/ooredoo-dashboard /path/to/your/projects/
cd /path/to/your/projects/ooredoo-dashboard
```

### 2. Installer les dépendances
```bash
composer install
```

### 3. Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configurer le webservice (optionnel)
Éditez le fichier `.env` :
```env
WEBSERVICE_BASE_URL=http://your-webservice-url
WEBSERVICE_API_KEY=your_api_key_here
```

### 5. Démarrer l'application
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 6. Accéder au dashboard
Ouvrez votre navigateur : `http://localhost:8000`

## ✅ Vérification

Le dashboard devrait afficher :
- ✅ Logo Ooredoo
- ✅ 6 onglets de navigation
- ✅ KPIs avec données de démonstration
- ✅ Graphiques interactifs
- ✅ Tableaux de données

## 🔗 Intégration Webservice

Une fois votre webservice prêt :

1. Configurez l'URL dans `.env`
2. Implémentez les endpoints attendus :
   - `GET /api/kpis`
   - `GET /api/merchants`
   - `GET /api/transactions`
   - `GET /api/subscriptions`
   - `GET /api/insights`

3. Testez la connexion :
```php
$client = app(\App\Services\WebserviceClient::class);
$isConnected = $client->testConnection();
```

## 📞 Support

Le projet est prêt pour la production. Consultez le README.md pour plus de détails.

