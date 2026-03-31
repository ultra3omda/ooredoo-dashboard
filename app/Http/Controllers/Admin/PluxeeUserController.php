<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PluxeeUserController extends Controller
{
    /**
     * Page de gestion des utilisateurs Pluxee
     */
    public function index()
    {
        return view('admin.pluxee-users');
    }

    /**
     * Liste des campagnes Pluxee disponibles
     */
    public function getCampaigns()
    {
        $campaigns = DB::table('stores')
            ->where('store_name', 'LIKE', '%Pluxee%')
            ->where('store_active', 1)
            ->where('is_sub_store', 1)
            ->select('store_id', 'store_name', 'store_type', 'store_manager_name', 'created_at')
            ->orderBy('store_name')
            ->get();

        // Add client count per campaign
        $campaigns = $campaigns->map(function ($campaign) {
            $campaign->client_count = DB::table('client')
                ->where('sub_store', $campaign->store_id)
                ->count();
            $campaign->active_subscriptions = DB::table('client_abonnement')
                ->join('client', 'client_abonnement.client_id', '=', 'client.client_id')
                ->where('client.sub_store', $campaign->store_id)
                ->where('client_abonnement.client_abonnement_expiration', '>', now())
                ->count();
            return $campaign;
        });

        return response()->json(['campaigns' => $campaigns]);
    }

    /**
     * Liste des utilisateurs Pluxee groupés par campagne
     */
    public function listUsers()
    {
        $users = DB::table('users')
            ->whereNotNull('pluxee_campaign_access')
            ->where('pluxee_campaign_access', '!=', '')
            ->select('id', 'name', 'email', 'pluxee_campaign_access', 'status', 'created_at', 'last_login_at')
            ->orderBy('pluxee_campaign_access')
            ->orderBy('name')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * Créer un utilisateur Pluxee pour une campagne
     */
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_name' => 'required|string',
            'user_name' => 'required|string|max:255',
            'user_email' => 'required|email|unique:users,email',
            'user_password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify campaign exists
        $campaign = DB::table('stores')
            ->where('store_name', $request->campaign_name)
            ->where('store_active', 1)
            ->first();

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'error' => 'Campagne introuvable: ' . $request->campaign_name
            ], 404);
        }

        // Get collaborator role
        $collaboratorRoleId = DB::table('roles')->where('name', 'collaborator')->value('id');

        try {
            $userId = DB::table('users')->insertGetId([
                'name' => $request->user_name,
                'email' => $request->user_email,
                'password' => Hash::make($request->user_password),
                'role_id' => $collaboratorRoleId,
                'status' => 'active',
                'platform_type' => 'club_privileges',
                'pluxee_campaign_access' => $request->campaign_name,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create user_operators entry
            DB::table('user_operators')->insert([
                'user_id' => $userId,
                'operator_name' => $request->campaign_name,
                'is_primary' => 1,
                'is_active' => 1,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Pluxee user created: {$request->user_email} for campaign: {$request->campaign_name}");

            return response()->json([
                'success' => true,
                'user_id' => $userId,
                'email' => $request->user_email,
                'campaign' => $request->campaign_name
            ]);
        } catch (\Exception $e) {
            Log::error("Error creating Pluxee user: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la creation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Désactiver un utilisateur Pluxee
     */
    public function deactivateUser(Request $request, $userId)
    {
        $user = DB::table('users')->where('id', $userId)->whereNotNull('pluxee_campaign_access')->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur Pluxee introuvable'], 404);
        }

        DB::table('users')->where('id', $userId)->update([
            'status' => 'suspended',
            'updated_at' => now(),
        ]);

        Log::info("Pluxee user deactivated: {$user->email}");

        return response()->json(['success' => true, 'message' => 'Utilisateur desactive']);
    }

    /**
     * Réactiver un utilisateur Pluxee
     */
    public function activateUser(Request $request, $userId)
    {
        $user = DB::table('users')->where('id', $userId)->whereNotNull('pluxee_campaign_access')->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur Pluxee introuvable'], 404);
        }

        DB::table('users')->where('id', $userId)->update([
            'status' => 'active',
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Utilisateur reactive']);
    }
}
