<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use App\Models\PasswordResetRequest;
use App\Mail\PasswordResetMail;

class PasswordController extends Controller
{
    /**
     * Afficher le formulaire "Mot de passe oublié"
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Envoyer l'email de réinitialisation
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ], [
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail doit être valide.'
        ]);

        // Vérifier que l'utilisateur existe
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return back()->with('error', 'Aucun compte trouvé avec cette adresse e-mail.');
        }

        if ($user->status !== 'active') {
            return back()->with('error', 'Votre compte n\'est pas actif. Contactez l\'administrateur.');
        }

        try {
            // Créer la demande de réinitialisation
            $resetRequest = PasswordResetRequest::createForPasswordReset($request->email);
            
            // Envoyer l'email
            $resetUrl = route('password.reset.form', $resetRequest->token);
            Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));
            
            Log::info("Email de réinitialisation envoyé à: " . $user->email);
            
            return back()->with('success', 'Un lien de réinitialisation a été envoyé à votre adresse e-mail.');
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi de l'email de réinitialisation: " . $e->getMessage());
            return back()->with('error', 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.');
        }
    }

    /**
     * Afficher le formulaire de réinitialisation
     */
    public function showResetForm(string $token)
    {
        $resetRequest = PasswordResetRequest::where('token', $token)->valid()->first();
        
        if (!$resetRequest) {
            return redirect()->route('auth.login')
                           ->with('error', 'Lien de réinitialisation invalide ou expiré.');
        }

        return view('auth.reset-password', compact('token', 'resetRequest'));
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'token.required' => 'Token de réinitialisation manquant.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.mixed' => 'Le mot de passe doit contenir des majuscules et minuscules.',
            'password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        ]);

        // Vérifier le token
        $resetRequest = PasswordResetRequest::where('token', $request->token)->valid()->first();
        
        if (!$resetRequest) {
            return back()->with('error', 'Lien de réinitialisation invalide ou expiré.');
        }

        // Vérifier que l'utilisateur existe toujours
        $user = User::where('email', $resetRequest->email)->first();
        
        if (!$user) {
            return back()->with('error', 'Utilisateur introuvable.');
        }

        try {
            // Mettre à jour le mot de passe
            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now()
            ]);

            // Marquer le token comme utilisé
            $resetRequest->markAsUsed($request->ip(), $request->userAgent());

            Log::info("Mot de passe réinitialisé avec succès pour: " . $user->email);

            return redirect()->route('auth.login')
                           ->with('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');

        } catch (\Exception $e) {
            Log::error("Erreur lors de la réinitialisation du mot de passe: " . $e->getMessage());
            return back()->with('error', 'Erreur lors de la réinitialisation. Veuillez réessayer.');
        }
    }

    /**
     * Afficher le formulaire de changement de mot de passe
     */
    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    /**
     * Changer le mot de passe (utilisateur connecté)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.mixed' => 'Le mot de passe doit contenir des majuscules et minuscules.',
            'password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        ]);

        $user = auth()->user();

        // Vérifier le mot de passe actuel
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'Le mot de passe actuel est incorrect.');
        }

        try {
            // Mettre à jour le mot de passe
            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now()
            ]);

            Log::info("Mot de passe changé avec succès pour: " . $user->email);

            return back()->with('success', 'Votre mot de passe a été modifié avec succès.');

        } catch (\Exception $e) {
            Log::error("Erreur lors du changement de mot de passe: " . $e->getMessage());
            return back()->with('error', 'Erreur lors du changement de mot de passe. Veuillez réessayer.');
        }
    }

    /**
     * Afficher le formulaire de première connexion (pour les invités)
     */
    public function showFirstLoginForm(string $token)
    {
        $resetRequest = PasswordResetRequest::where('token', $token)
                                          ->byType('first_login')
                                          ->valid()
                                          ->first();
        
        if (!$resetRequest) {
            return redirect()->route('auth.login')
                           ->with('error', 'Lien de première connexion invalide ou expiré.');
        }

        return view('auth.first-login', compact('token', 'resetRequest'));
    }

    /**
     * Traiter la première connexion
     */
    public function processFirstLogin(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'token.required' => 'Token de première connexion manquant.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.mixed' => 'Le mot de passe doit contenir des majuscules et minuscules.',
            'password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        ]);

        // Vérifier le token
        $resetRequest = PasswordResetRequest::where('token', $request->token)
                                          ->byType('first_login')
                                          ->valid()
                                          ->first();
        
        if (!$resetRequest) {
            return back()->with('error', 'Lien de première connexion invalide ou expiré.');
        }

        // Vérifier que l'utilisateur existe
        $user = User::where('email', $resetRequest->email)->first();
        
        if (!$user) {
            return back()->with('error', 'Utilisateur introuvable.');
        }

        try {
            // Mettre à jour le mot de passe et désactiver OTP obligatoire
            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now(),
                'is_otp_enabled' => false // L'utilisateur peut maintenant utiliser mot de passe
            ]);

            // Marquer le token comme utilisé
            $resetRequest->markAsUsed($request->ip(), $request->userAgent());

            // Connecter l'utilisateur
            auth()->login($user);
            $request->session()->regenerate();

            Log::info("Première connexion réussie pour: " . $user->email);

            // Redirection vers le dashboard approprié
            $preferredDashboard = $user->getPreferredDashboard();
            
            return redirect($preferredDashboard)
                         ->with('success', 'Bienvenue ! Votre mot de passe a été configuré avec succès.');

        } catch (\Exception $e) {
            Log::error("Erreur lors de la première connexion: " . $e->getMessage());
            return back()->with('error', 'Erreur lors de la configuration. Veuillez réessayer.');
        }
    }
}