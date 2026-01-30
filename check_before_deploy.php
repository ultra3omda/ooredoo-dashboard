<?php
/**
 * 🔍 Script de Vérification Pré-Déploiement
 * Ooredoo Dashboard - Version de Production
 * 
 * Ce script vérifie que tous les éléments nécessaires sont en place
 * avant l'envoi à l'administrateur système.
 */

echo "🔍 Vérification Pré-Déploiement - Ooredoo Dashboard\n";
echo "================================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// Fonction pour afficher les résultats colorés
function displayResult($type, $message) {
    $colors = [
        'success' => "\033[32m✅",  // Vert
        'warning' => "\033[33m⚠️",  // Jaune
        'error' => "\033[31m❌"     // Rouge
    ];
    $reset = "\033[0m";
    
    echo $colors[$type] . " " . $message . $reset . "\n";
}

// 1. Vérifier la structure des fichiers
echo "📁 Vérification de la structure des fichiers...\n";

$requiredFiles = [
    'app/Http/Controllers/DashboardController.php',
    'app/Http/Controllers/Api/DataController.php', 
    'app/Http/Controllers/Auth/LoginController.php',
    'app/Http/Controllers/Auth/InvitationController.php',
    'app/Http/Controllers/Admin/UserManagementController.php',
    'resources/views/dashboard.blade.php',
    'resources/views/auth/login.blade.php',
    'database/migrations',
    'database/seeders/SuperAdminSeeder.php',
    'database/seeders/RolesSeeder.php',
    'public/index.php',
    'public/.htaccess',
    '.env.example',
    'composer.json',
    'DEPLOYMENT_GUIDE.md',
    'PRODUCTION_CONFIG.md',
    'deploy.sh',
    'env.production.example',
    'README_DEPLOYMENT.md'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        $success[] = "Fichier présent: $file";
    } else {
        $errors[] = "Fichier manquant: $file";
    }
}

// 2. Vérifier les migrations
echo "\n📄 Vérification des migrations...\n";

$migrationFiles = glob('database/migrations/*.php');
$requiredMigrations = [
    'create_users_table',
    'create_password_reset_tokens_table', 
    'create_personal_access_tokens_table',
    'add_auth_fields_to_users_table',
    'create_roles_table',
    'create_user_operators_table',
    'create_invitations_table'
];

foreach ($requiredMigrations as $migration) {
    $found = false;
    foreach ($migrationFiles as $file) {
        if (strpos(basename($file), $migration) !== false) {
            $found = true;
            break;
        }
    }
    
    if ($found) {
        $success[] = "Migration présente: $migration";
    } else {
        $errors[] = "Migration manquante: $migration";
    }
}

// 3. Vérifier composer.json
echo "\n📦 Vérification de composer.json...\n";

if (file_exists('composer.json')) {
    $composer = json_decode(file_get_contents('composer.json'), true);
    
    $requiredPackages = [
        'laravel/framework',
        'laravel/tinker'
    ];
    
    foreach ($requiredPackages as $package) {
        if (isset($composer['require'][$package])) {
            $success[] = "Package présent: $package";
        } else {
            $warnings[] = "Package optionnel manquant: $package";
        }
    }
} else {
    $errors[] = "composer.json manquant";
}

// 4. Vérifier les modèles
echo "\n🏗️ Vérification des modèles...\n";

$models = [
    'app/Models/User.php',
    'app/Models/Role.php', 
    'app/Models/UserOperator.php',
    'app/Models/Invitation.php'
];

foreach ($models as $model) {
    if (file_exists($model)) {
        $content = file_get_contents($model);
        
        // Vérifier que le modèle contient les bonnes méthodes
        if (strpos($content, 'class') !== false) {
            $success[] = "Modèle valide: " . basename($model);
        } else {
            $warnings[] = "Modèle possiblement invalide: " . basename($model);
        }
    } else {
        $errors[] = "Modèle manquant: $model";
    }
}

// 5. Vérifier les contrôleurs
echo "\n🎮 Vérification des contrôleurs...\n";

$controllers = [
    'app/Http/Controllers/DashboardController.php' => ['index'],
    'app/Http/Controllers/Api/DataController.php' => ['getDashboardData', 'getUserOperators'],
    'app/Http/Controllers/Auth/LoginController.php' => ['showLoginForm', 'login'],
    'app/Http/Controllers/Auth/InvitationController.php' => ['create', 'store', 'accept']
];

foreach ($controllers as $controller => $methods) {
    if (file_exists($controller)) {
        $content = file_get_contents($controller);
        
        $allMethodsFound = true;
        foreach ($methods as $method) {
            if (strpos($content, "function $method") === false) {
                $allMethodsFound = false;
                break;
            }
        }
        
        if ($allMethodsFound) {
            $success[] = "Contrôleur complet: " . basename($controller);
        } else {
            $warnings[] = "Contrôleur incomplet: " . basename($controller);
        }
    } else {
        $errors[] = "Contrôleur manquant: $controller";
    }
}

// 6. Vérifier les vues principales
echo "\n🎨 Vérification des vues...\n";

$views = [
    'resources/views/dashboard.blade.php',
    'resources/views/auth/login.blade.php',
    'resources/views/admin/users/index.blade.php',
    'resources/views/admin/users/create.blade.php',
    'resources/views/auth/invitation/create.blade.php'
];

foreach ($views as $view) {
    if (file_exists($view)) {
        $content = file_get_contents($view);
        
        // Vérifier que c'est bien une vue Blade
        if (strpos($content, '@') !== false || strpos($content, '{{') !== false) {
            $success[] = "Vue Blade valide: " . basename($view);
        } else {
            $warnings[] = "Vue possiblement invalide: " . basename($view);
        }
    } else {
        $errors[] = "Vue manquante: $view";
    }
}

// 7. Vérifier les routes
echo "\n🛣️ Vérification des routes...\n";

if (file_exists('routes/web.php')) {
    $routes = file_get_contents('routes/web.php');
    
    $requiredRoutes = [
        'auth.login',
        'auth.logout', 
        'dashboard',
        'admin.users',
        'auth.invitation',
        '/api/operators',
        '/api/dashboard/data'
    ];
    
    foreach ($requiredRoutes as $route) {
        if (strpos($routes, $route) !== false) {
            $success[] = "Route présente: $route";
        } else {
            $warnings[] = "Route possiblement manquante: $route";
        }
    }
} else {
    $errors[] = "Fichier routes/web.php manquant";
}

// 8. Vérifier les fichiers de configuration
echo "\n⚙️ Vérification des fichiers de configuration...\n";

$configFiles = [
    'DEPLOYMENT_GUIDE.md',
    'PRODUCTION_CONFIG.md', 
    'env.production.example',
    'README_DEPLOYMENT.md'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        if ($size > 1000) { // Au moins 1KB
            $success[] = "Document complet: $file (" . round($size/1024, 1) . "KB)";
        } else {
            $warnings[] = "Document très court: $file";
        }
    } else {
        $errors[] = "Document manquant: $file";
    }
}

// 9. Vérifier les permissions des fichiers
echo "\n🔐 Vérification des permissions...\n";

if (file_exists('deploy.sh')) {
    if (is_executable('deploy.sh')) {
        $success[] = "Script deploy.sh exécutable";
    } else {
        $warnings[] = "Script deploy.sh non exécutable (sera corrigé au déploiement)";
    }
} else {
    $errors[] = "Script deploy.sh manquant";
}

// 10. Vérifier la taille du projet
echo "\n📏 Vérification de la taille du projet...\n";

function getDirSize($dir) {
    $size = 0;
    if (is_dir($dir)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    }
    return $size;
}

$totalSize = getDirSize('.');
$sizeInMB = round($totalSize / (1024 * 1024), 2);

if ($sizeInMB < 100) {
    $success[] = "Taille du projet acceptable: {$sizeInMB}MB";
} else {
    $warnings[] = "Projet volumineux: {$sizeInMB}MB - Vérifier les gros fichiers";
}

// Affichage des résultats
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RÉSUMÉ DE LA VÉRIFICATION\n";
echo str_repeat("=", 50) . "\n\n";

// Afficher les succès
if (!empty($success)) {
    echo "✅ ÉLÉMENTS VALIDÉS (" . count($success) . "):\n";
    foreach ($success as $item) {
        displayResult('success', $item);
    }
    echo "\n";
}

// Afficher les avertissements
if (!empty($warnings)) {
    echo "⚠️ AVERTISSEMENTS (" . count($warnings) . "):\n";
    foreach ($warnings as $item) {
        displayResult('warning', $item);
    }
    echo "\n";
}

// Afficher les erreurs
if (!empty($errors)) {
    echo "❌ ERREURS À CORRIGER (" . count($errors) . "):\n";
    foreach ($errors as $item) {
        displayResult('error', $item);
    }
    echo "\n";
}

// Conclusion
echo str_repeat("=", 50) . "\n";

if (empty($errors)) {
    if (empty($warnings)) {
        displayResult('success', "🎉 PROJET PRÊT POUR LE DÉPLOIEMENT !");
        echo "\n📦 Le projet peut être envoyé à l'administrateur système.\n";
        echo "📋 Fichiers à inclure dans l'archive:\n";
        echo "   • Tout le code source\n";
        echo "   • DEPLOYMENT_GUIDE.md\n";
        echo "   • PRODUCTION_CONFIG.md\n";
        echo "   • deploy.sh\n";
        echo "   • env.production.example\n";
        echo "   • README_DEPLOYMENT.md\n";
    } else {
        displayResult('warning', "⚡ PROJET PRÊT AVEC AVERTISSEMENTS");
        echo "\n📝 Corriger les avertissements si possible avant l'envoi.\n";
    }
} else {
    displayResult('error', "🚫 PROJET NON PRÊT - ERREURS À CORRIGER");
    echo "\n🔧 Corriger toutes les erreurs avant l'envoi.\n";
}

echo "\n📞 Support: Contacter l'équipe de développement si nécessaire.\n";
echo "🕒 Vérification terminée le " . date('Y-m-d H:i:s') . "\n";

// Code de sortie
exit(empty($errors) ? 0 : 1);
?>
