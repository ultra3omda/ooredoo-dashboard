<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer le rôle Super Admin
        $superAdminRole = Role::where('name', 'super_admin')->first();
        
        if (!$superAdminRole) {
            $this->command->error('Le rôle Super Admin n\'existe pas. Veuillez d\'abord exécuter RoleSeeder.');
            return;
        }

        // Créer le super admin par défaut
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@ooredoo.tn'],
            [
                'name' => 'Super Administrateur',
                'first_name' => 'Super',
                'last_name' => 'Administrateur', 
                'password' => Hash::make('SuperAdmin@2025'),
                'role_id' => $superAdminRole->id,
                'status' => 'active',

                'phone' => '+216 20 000 000',
                'preferences' => [
                    'language' => 'fr',
                    'timezone' => 'Africa/Tunis',
                    'notifications' => [
                        'email' => true,
                        'browser' => true
                    ]
                ]
            ]
        );

        $this->command->info('Super Admin créé avec succès!');
        $this->command->info('Email: superadmin@ooredoo.tn');
        $this->command->info('Mot de passe: SuperAdmin@2025');
        $this->command->warn('IMPORTANT: Changez ce mot de passe par défaut après la première connexion!');
    }
}