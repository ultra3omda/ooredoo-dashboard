<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrateur',
                'description' => 'Accès complet au système, gestion de tous les utilisateurs et opérateurs',
                'permissions' => [
                    'manage_users',
                    'manage_roles', 
                    'manage_operators',
                    'view_all_operators',
                    'create_admin_users',
                    'delete_users',
                    'manage_system_settings',
                    'view_audit_logs'
                ]
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrateur',
                'description' => 'Administration d\'un opérateur spécifique, peut inviter des collaborateurs',
                'permissions' => [
                    'view_operator_data',
                    'invite_collaborators',
                    'manage_operator_users',
                    'view_operator_reports',
                    'export_operator_data'
                ]
            ],
            [
                'name' => 'collaborator',
                'display_name' => 'Collaborateur',
                'description' => 'Accès en lecture seule aux données de l\'opérateur assigné',
                'permissions' => [
                    'view_operator_data',
                    'view_operator_reports'
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
        }

        $this->command->info('Rôles créés avec succès!');
    }
}