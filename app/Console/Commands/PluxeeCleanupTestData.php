<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PluxeeCleanupTestData extends Command
{
    protected $signature = 'pluxee:cleanup-test-data';
    protected $description = 'Supprime toutes les donnees de test Pluxee (guard: non-production uniquement)';

    public function handle()
    {
        if (app()->environment('production')) {
            $this->error('INTERDIT en production.');
            return 1;
        }

        $this->info('=== Nettoyage des donnees test Pluxee ===');

        // 1. Transactions
        $d1 = DB::table('history')->whereIn('client_id', function ($q) {
            $q->select('client_id')->from('client')->where('client_email', 'LIKE', '%@pluxee.tn');
        })->delete();
        $this->line("Transactions supprimees: $d1");

        // 2. Abonnements
        $d2 = DB::table('client_abonnement')->whereIn('client_id', function ($q) {
            $q->select('client_id')->from('client')->where('client_email', 'LIKE', '%@pluxee.tn');
        })->delete();
        $this->line("Abonnements supprimes: $d2");

        // 3. Clients
        $d3 = DB::table('client')->where('client_email', 'LIKE', '%@pluxee.tn')->delete();
        $this->line("Clients supprimes: $d3");

        // 4. User operators
        $userIds = DB::table('users')
            ->where('email', 'LIKE', '%@test.oo')
            ->orWhere('email', 'LIKE', '%@pluxee.tn')
            ->pluck('id')->toArray();
        $d4 = DB::table('user_operators')->whereIn('user_id', $userIds)->delete();
        $this->line("User operators supprimes: $d4");

        // 5. Users
        $d5 = DB::table('users')->where(function ($q) {
            $q->where('email', 'LIKE', '%@test.oo')->orWhere('email', 'LIKE', '%@pluxee.tn');
        })->delete();
        $this->line("Users supprimes: $d5");

        // 6. Stores
        $d6 = DB::table('stores')->where(function ($q) {
            $q->where('store_name', 'LIKE', '%Campagne Ramadan 2025%')
              ->orWhere('store_name', 'LIKE', '%Campagne Ete 2025%')
              ->orWhere('store_name', 'LIKE', '%Campagne Back To School%');
        })->delete();
        $this->line("Stores supprimes: $d6");

        // Verification
        $this->newLine();
        $this->info('=== Verification ===');
        $this->line('Clients @pluxee.tn: ' . DB::table('client')->where('client_email', 'LIKE', '%@pluxee.tn')->count());
        $this->line('Stores Campagne: ' . DB::table('stores')->where('store_name', 'LIKE', '%Campagne%2025%')->orWhere('store_name', 'LIKE', '%Back To School%')->count());
        $this->line('Users test: ' . DB::table('users')->where('email', 'LIKE', '%@test.oo')->orWhere('email', 'LIKE', '%@pluxee.tn')->count());
        $this->info('=== Nettoyage termine ===');
        return 0;
    }
}
