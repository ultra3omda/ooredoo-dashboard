<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\DataControllerOptimized;
use App\Http\Controllers\SubStoreController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\TimweDiagnosticController;
use App\Http\Controllers\Admin\MLDashboardController;
use App\Http\Controllers\Admin\EklektikSyncTrackingController;
use App\Http\Controllers\Admin\EklektikCronController;
use App\Http\Controllers\Admin\ClubPrivilegesSyncController;

// Routes publiques
Route::get('/', [LoginController::class, 'showLoginForm'])->name('login');

// Routes d'authentification
Route::prefix('auth')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('auth.login');
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    
    // Connexion par code OTP (demande + envoi + vérification)
    Route::get('otp/request', [AuthController::class, 'showOtpRequest'])->name('auth.otp.request');
    Route::post('otp/send', [AuthController::class, 'sendOtp'])->name('auth.otp.send');
    Route::get('verify-otp', [AuthController::class, 'showOtpVerify'])->name('auth.otp.verify');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('resend-otp', [AuthController::class, 'resendOtp'])->name('auth.otp.resend');
    
    // Invitations
    Route::get('invitation/{token}', [InvitationController::class, 'showAcceptForm'])->name('invitation.accept');
    Route::post('invitation/{token}', [InvitationController::class, 'acceptInvitation']);
});

// Mot de passe oublié / réinitialisation (public)
Route::get('/password/forgot', [PasswordController::class, 'showForgotPasswordForm'])->name('password.forgot');
Route::post('/password/send-reset', [PasswordController::class, 'sendResetLink'])->name('password.send-reset');
Route::get('/password/reset/{token}', [PasswordController::class, 'showResetForm'])->name('password.reset.form');
Route::post('/password/reset', [PasswordController::class, 'resetPassword'])->name('password.reset');

// Routes manquantes pour le dashboard (AVANT l'authentification)
Route::get('/password/change', function() { return redirect()->route('dashboard'); })->name('password.change');
Route::post('/password/change', function() { return redirect()->route('dashboard'); });

// Routes d'invitation 
Route::get('/admin/invitations', function() { return redirect()->route('dashboard'); })->name('admin.invitations.index');

// Routes authentifiées
Route::middleware(['auth'])->group(function () {
    
    // Dashboard principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // API dashboard (web en premier = ces routes matchent avant api.php → session + auth, pas de 301)
    Route::prefix('api')->group(function () {
        Route::get('/dashboard/data', [DataControllerOptimized::class, 'getDashboardData'])->name('api.dashboard.data');
        Route::get('/dashboard/subscriptions-details', [DataControllerOptimized::class, 'getSubscriptionsDetails'])->name('api.dashboard.subscriptions-details');
        Route::get('/dashboard/cohorts', [DataControllerOptimized::class, 'getCohorts'])->name('api.dashboard.cohorts');
        Route::get('/dashboard/transactions-separate', [DataControllerOptimized::class, 'getTransactions'])->name('api.dashboard.transactions-separate');
        Route::get('/dashboard/subscriptions/{clientId}', [DataControllerOptimized::class, 'getUserSubscriptions'])->name('api.dashboard.user.subscriptions');
        Route::get('/operators', [App\Http\Controllers\Api\OperatorsController::class, 'getOperators']);
        Route::get('/eklektik-dashboard/kpis', [App\Http\Controllers\Api\EklektikDashboardController::class, 'getKPIs']);
        Route::get('/eklektik-dashboard/revenue-distribution', [App\Http\Controllers\Api\EklektikDashboardController::class, 'getRevenueDistribution']);
        Route::get('/eklektik-dashboard/sync-status', [App\Http\Controllers\Api\EklektikDashboardController::class, 'getSyncStatus']);
        Route::get('/eklektik-dashboard/overview-chart', [App\Http\Controllers\Api\EklektikDashboardController::class, 'getOverviewChart']);
        Route::get('/eklektik-dashboard/revenue-evolution', [App\Http\Controllers\Api\EklektikDashboardController::class, 'getRevenueEvolution']);
        Route::get('/eklektik-dashboard/subs-evolution', [App\Http\Controllers\Api\EklektikDashboardController::class, 'getSubsEvolution']);
        // API Sub-Stores dashboard (liste, expirations, données utilisateurs)
        Route::get('/sub-stores', [SubStoreController::class, 'getSubStores'])->name('api.sub-stores');
        Route::get('/sub-store/dashboard/data', [SubStoreController::class, 'getDashboardData'])->name('api.sub-store.dashboard.data');
        Route::get('/expirations', [SubStoreController::class, 'getExpirationsAsync'])->name('api.expirations');
        Route::get('/users/data', [SubStoreController::class, 'getUsersData'])->name('api.users.data');
    });
    // Pas de doublon ici pour garder l’ordre des routes comme avant (api puis web)

    // Dashboards des Sub-Stores (accès selon les permissions)
    Route::get('/sub-store/{storeType}', [SubStoreController::class, 'index'])
        ->name('sub-stores.dashboard')
        ->where('storeType', 'eklektik');

    // Routes d'administration (réservées aux super-admins et admins)
    // Note: le rôle en base est "super_admin" (underscore), pas "super-admin"
    Route::middleware('role:admin,super_admin')->prefix('admin')->name('admin.')->group(function () {
        
        // Gestion des utilisateurs
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
        
        // Gestion des invitations
        Route::get('/invitations', function() { return redirect()->route('admin.users.index'); })->name('invitations.index');
        
        // Gestion du profil
        Route::get('/profile', function() { return redirect()->route('dashboard'); })->name('profile.edit');
        
        // Diagnostic Timwe (nom de route admin.timwe-diagnostic pour le bouton du dashboard)
        Route::get('/timwe-diagnostic', [TimweDiagnosticController::class, 'index'])->name('timwe-diagnostic');
        Route::get('/timwe-diagnostic/data', [TimweDiagnosticController::class, 'getDiagnosticData'])->name('timwe-diagnostic.data');
        Route::get('/timwe-diagnostic/phone/{phone}/transactions', [TimweDiagnosticController::class, 'getPhoneTransactions'])->name('timwe-diagnostic.phone.transactions');
        Route::get('/timwe-diagnostic/export', [TimweDiagnosticController::class, 'exportCsv'])->name('timwe-diagnostic.export');

        // === ML DASHBOARD ROUTES === 
        Route::prefix('ml-dashboard')->name('ml.')->group(function () {
            // Page principale
            Route::get('/', [MLDashboardController::class, 'index'])->name('dashboard');
            
            // API pour les données ML
            Route::get('/data', [MLDashboardController::class, 'getDashboardData'])->name('data');
            
            // Prédictions
            Route::post('/predict', [MLDashboardController::class, 'predictClient'])->name('predict');
            Route::get('/client/{clientId}', [MLDashboardController::class, 'getClientDetails'])->name('client.details');
            
            // Recommandations
            Route::post('/recommendations/generate', [MLDashboardController::class, 'generateRecommendations'])->name('recommendations.generate');
            Route::post('/recommendations/status', [MLDashboardController::class, 'updateRecommendationStatus'])->name('recommendations.status');
            Route::post('/recommendations/simulate', [MLDashboardController::class, 'simulateRecommendationImpact'])->name('recommendations.simulate');
            
            // Extraction de features
            Route::post('/features/extract', [MLDashboardController::class, 'extractFeatures'])->name('features.extract');
            
            // NOUVEAU v2.0: Entraînement modèle et A/B testing
            Route::post('/train', [MLDashboardController::class, 'trainModel'])->name('train');
            Route::post('/ab-test/start', [MLDashboardController::class, 'startABTest'])->name('ab-test.start');
            Route::get('/ab-test/results/{testId}', [MLDashboardController::class, 'getABTestResults'])->name('ab-test.results');
            Route::post('/ab-test/{testId}/end', [MLDashboardController::class, 'endABTest'])->name('ab-test.end');
        });

        // === AI AGENT ROUTES ===
        Route::prefix('ai-agent')->name('ai-agent.')->group(function () {
            // Interface principale
            Route::get('/', [\App\Http\Controllers\Admin\AIAgentController::class, 'index'])->name('index');
            
            // API pour interaction avec l'agent IA
            Route::post('/ask', [\App\Http\Controllers\Admin\AIAgentController::class, 'ask'])->name('ask');
            Route::get('/conversation/{sessionId}', [\App\Http\Controllers\Admin\AIAgentController::class, 'getConversation'])->name('conversation');
            Route::delete('/conversation/{sessionId}', [\App\Http\Controllers\Admin\AIAgentController::class, 'deleteConversation'])->name('conversation.delete');
            
            // Utilitaires
            Route::get('/sessions', [\App\Http\Controllers\Admin\AIAgentController::class, 'getRecentSessions'])->name('sessions');
            Route::get('/test', [\App\Http\Controllers\Admin\AIAgentController::class, 'test'])->name('test');
            Route::get('/stats', [\App\Http\Controllers\Admin\AIAgentController::class, 'getStats'])->name('stats');
        });
        
        // Tracking et monitoring Eklektik
        Route::prefix('eklektik')->name('eklektik.')->group(function () {
            Route::get('/dashboard', fn () => redirect()->route('sub-stores.dashboard', ['storeType' => 'eklektik']))->name('dashboard');
            Route::get('/sync-tracking', [EklektikSyncTrackingController::class, 'index'])->name('sync.tracking');
            Route::get('/sync-tracking/data', [EklektikSyncTrackingController::class, 'getData'])->name('sync.data');
            Route::post('/sync-tracking/retry/{id}', [EklektikSyncTrackingController::class, 'retry'])->name('sync.retry');
            
            Route::get('/cron-config', [EklektikCronController::class, 'index'])->name('cron.index');
            Route::post('/cron-config', [EklektikCronController::class, 'update'])->name('cron.update');
            Route::post('/cron-config/test', [EklektikCronController::class, 'testConnection'])->name('cron.test');
        });
        
        // Routes eklektik alternatives pour le menu
        Route::get('/eklektik-cron', [EklektikCronController::class, 'index'])->name('eklektik-cron');
        Route::get('/eklektik/sync', function() { return redirect()->route('admin.eklektik.sync.tracking'); })->name('eklektik.sync');
        
        // Club Privileges sync (alias admin.cp-sync.* pour le menu Eklektik)
        Route::get('/cp-sync', [ClubPrivilegesSyncController::class, 'index'])->name('cp-sync.index');
        Route::post('/cp-sync/visit', [ClubPrivilegesSyncController::class, 'visitSync'])->name('cp-sync.visit');
        Route::get('/cp-sync/test', [ClubPrivilegesSyncController::class, 'testConnection'])->name('cp-sync.test');
        Route::get('/cp-sync/status', [ClubPrivilegesSyncController::class, 'status'])->name('cp-sync.status');
        Route::get('/cp-sync/history', [ClubPrivilegesSyncController::class, 'history'])->name('cp-sync.history');
        // URLs longues (conservées pour compatibilité)
        Route::get('/club-privileges-sync', [ClubPrivilegesSyncController::class, 'index'])->name('club-privileges.sync.index');
    });
});

// Route de fallback pour les 404
Route::fallback(function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});