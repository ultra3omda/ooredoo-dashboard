<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PluxeeTestDataSeeder extends Seeder
{
    /**
     * Campagnes Pluxee à créer
     */
    private array $campaigns = [
        [
            'store_name' => 'Pluxee - Campagne Ramadan 2025',
            'store_type' => 'partnership',
            'user_name' => 'Responsable Ramadan',
            'user_email' => 'pluxee.ramadan@test.oo',
            'short' => 'Ramadan',
        ],
        [
            'store_name' => 'Pluxee - Campagne Ete 2025',
            'store_type' => 'partnership',
            'user_name' => 'Responsable Ete',
            'user_email' => 'pluxee.ete@test.oo',
            'short' => 'Ete',
        ],
        [
            'store_name' => 'Pluxee - Campagne Back To School',
            'store_type' => 'partnership',
            'user_name' => 'Responsable BTS',
            'user_email' => 'pluxee.bts@test.oo',
            'short' => 'BTS',
        ],
    ];

    public function run(): void
    {
        // Guard: never run in production
        if (app()->environment('production')) {
            $this->command->error('ABORT: Ce seeder ne peut PAS etre execute en production.');
            return;
        }

        $this->command->info('=== PluxeeTestDataSeeder START ===');

        // Get valid promotion IDs for history records
        $promotionIds = DB::table('promotion')
            ->whereNotNull('partner_id')
            ->where('partner_id', '>', 0)
            ->pluck('promotion_id')
            ->toArray();

        if (empty($promotionIds)) {
            $this->command->warn('Aucune promotion trouvee, les transactions history ne seront pas creees.');
            $promotionIds = [];
        }

        // Get collaborator role_id
        $collaboratorRoleId = DB::table('roles')->where('name', 'collaborator')->value('id');
        if (!$collaboratorRoleId) {
            $this->command->error('Role "collaborator" introuvable.');
            return;
        }

        $now = Carbon::now();

        foreach ($this->campaigns as $campaign) {
            $this->command->info("--- Campagne: {$campaign['store_name']} ---");

            // A. Create store (idempotent)
            $existingStore = DB::table('stores')
                ->where('store_name', $campaign['store_name'])
                ->first();

            if ($existingStore) {
                $storeId = $existingStore->store_id;
                $this->command->info("  Store deja existant (ID: $storeId)");
            } else {
                $storeId = DB::table('stores')->insertGetId([
                    'store_name' => $campaign['store_name'],
                    'store_logo' => '',
                    'store_desc' => 'Campagne test Pluxee',
                    'store_mail' => 'test@pluxee.tn',
                    'store_manager_name' => $campaign['user_name'],
                    'store_mobile' => 0,
                    'store_active' => 1,
                    'store_featured' => 0,
                    'allow_subscribe' => 0,
                    'store_type' => $campaign['store_type'],
                    'is_sub_store' => 1,
                    'color' => '#E30045',
                    'store_start_date' => $now->format('Y-m-d'),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'forced_mobile_logo' => 0,
                ]);
                $this->command->info("  Store cree (ID: $storeId)");
            }

            // B. Create dashboard user (idempotent)
            $existingUser = DB::table('users')
                ->where('email', $campaign['user_email'])
                ->first();

            if ($existingUser) {
                $this->command->info("  User deja existant: {$campaign['user_email']}");
                // Update pluxee_campaign_access if needed
                DB::table('users')
                    ->where('id', $existingUser->id)
                    ->update(['pluxee_campaign_access' => $campaign['store_name']]);
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name' => $campaign['user_name'],
                    'email' => $campaign['user_email'],
                    'password' => Hash::make('Pluxee@2025!'),
                    'role_id' => $collaboratorRoleId,
                    'status' => 'active',
                    'platform_type' => 'club_privileges',
                    'pluxee_campaign_access' => $campaign['store_name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->command->info("  User cree (ID: $userId): {$campaign['user_email']}");

                // Create user_operators entry for sub-store access
                DB::table('user_operators')->insert([
                    'user_id' => $userId,
                    'operator_name' => $campaign['store_name'],
                    'is_primary' => 1,
                    'is_active' => 1,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // C. Create 15 test clients per campaign (idempotent)
            $existingClientCount = DB::table('client')
                ->where('sub_store', $storeId)
                ->where('client_email', 'LIKE', '%@pluxee.tn')
                ->count();

            if ($existingClientCount >= 15) {
                $this->command->info("  Clients deja existants ($existingClientCount)");
                $clientIds = DB::table('client')
                    ->where('sub_store', $storeId)
                    ->where('client_email', 'LIKE', '%@pluxee.tn')
                    ->pluck('client_id')
                    ->toArray();
            } else {
                $clientIds = [];
                for ($i = 1; $i <= 15; $i++) {
                    $createdAt = $now->copy()->subDays(rand(1, 90));
                    $clientId = DB::table('client')->insertGetId([
                        'client_prenom' => "TestClient$i",
                        'client_nom' => "Pluxee{$campaign['short']}",
                        'client_telephone' => '216' . rand(20000000, 99999999),
                        'client_gender' => $i % 2 === 0 ? 'F' : 'M',
                        'client_age' => rand(22, 55),
                        'client_active' => 1,
                        'client_phone_os' => (string)rand(1, 2),
                        'country_id' => 1,
                        'client_store' => $storeId,
                        'source' => 'pluxee_test',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'client_email' => "test.client{$i}.{$campaign['short']}@pluxee.tn",
                        'sub_store' => $storeId,
                        'is_connect' => 0,
                        'active_subscription' => ($i <= 12) ? 1 : 0,
                    ]);
                    $clientIds[] = $clientId;
                }
                $this->command->info("  15 clients crees");
            }

            // D. Create subscriptions (idempotent)
            foreach ($clientIds as $idx => $clientId) {
                $existingSub = DB::table('client_abonnement')
                    ->where('client_id', $clientId)
                    ->exists();

                if ($existingSub) continue;

                $creationDate = Carbon::parse(
                    DB::table('client')->where('client_id', $clientId)->value('created_at')
                );

                // 80% active (expiration future), 20% expired
                $isActive = ($idx < 12); // first 12 active, last 3 expired
                $expirationDate = $isActive
                    ? $creationDate->copy()->addYear()
                    : $creationDate->copy()->addDays(rand(10, 30));

                DB::table('client_abonnement')->insert([
                    'status' => $isActive ? 'active' : 'expired',
                    'client_id' => $clientId,
                    'tarif_id' => 1,
                    'country_payments_methods_id' => 8,
                    'client_abonnement_creation' => $creationDate,
                    'client_abonnement_expiration' => $expirationDate,
                    'duration' => $isActive ? 365 : rand(10, 30),
                    'created_at' => $creationDate,
                    'updated_at' => $creationDate,
                ]);
            }
            $this->command->info("  Abonnements crees/verifies");

            // E. Create transactions for active clients
            if (!empty($promotionIds)) {
                foreach (array_slice($clientIds, 0, 12) as $clientId) {
                    $existingHistory = DB::table('history')
                        ->where('client_id', $clientId)
                        ->exists();

                    if ($existingHistory) continue;

                    $abonnement = DB::table('client_abonnement')
                        ->where('client_id', $clientId)
                        ->first();

                    if (!$abonnement) continue;

                    $nbTransactions = rand(2, 8);
                    $transactions = [];
                    for ($t = 0; $t < $nbTransactions; $t++) {
                        $transactions[] = [
                            'client_abonnement_id' => $abonnement->client_abonnement_id,
                            'promotion_id' => $promotionIds[array_rand($promotionIds)],
                            'validated' => 1,
                            'time' => $now->copy()->subDays(rand(1, 60)),
                            'client_id' => $clientId,
                            'client_telephone' => DB::table('client')
                                ->where('client_id', $clientId)
                                ->value('client_telephone'),
                        ];
                    }
                    DB::table('history')->insert($transactions);
                }
                $this->command->info("  Transactions creees");
            }
        }

        $this->command->info('=== PluxeeTestDataSeeder COMPLETE ===');
    }
}
