<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insérer les rôles de base
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrateur',
                'description' => 'Accès complet au système, gestion globale de tous les opérateurs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrateur',
                'description' => 'Gestion des utilisateurs et données pour les opérateurs assignés',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'collaborator',
                'display_name' => 'Collaborateur',
                'description' => 'Consultation des données pour les opérateurs assignés',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Utiliser DB::table pour éviter les problèmes de modèles
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                $role
            );
        }

        $this->command->info('✅ Rôles créés avec succès');
        $this->command->info('   • super_admin: Accès global');
        $this->command->info('   • admin: Gestion par opérateur');
        $this->command->info('   • collaborator: Consultation par opérateur');
    }
}
