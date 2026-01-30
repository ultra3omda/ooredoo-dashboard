<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\UserOtpCode;
use App\Models\Invitation;
use App\Mail\OtpMail;

class AuthController extends Controller
{
    /**
     * Afficher le formulaire de connexion
     */
    public function showLogin()
    {
        if (Auth::check()) {
            // Si l'utilisateur est déjà connecté, redirection intelligente
            $preferredDashboard = Auth::user()->getPreferredDashboard();
            return redirect($preferredDashboard);
        }
        
        return view('auth.login');
    }

    /**
     * Traiter la connexion par email/password
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6'
        ], [
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail doit être valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.'
        ]);

        // Vérifier si l'utilisateur existe et est actif
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return back()->with('error', 'Aucun compte trouvé avec cette adresse e-mail.');
        }

        if ($user->status !== 'active') {
            return back()->with('error', 'Votre compte n\'est pas actif. Contactez l\'administrateur.');
        }

        // Tentative de connexion
        $credentials = $request->only('email', 'password');
        
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Mettre à jour les informations de dernière connexion
            $user->updateLastLogin($request->ip());
            
            Log::info("Connexion réussie pour l'utilisateur: " . $user->email);
            
            // Redirection intelligente selon le rôle et les permissions
            $preferredDashboard = $user->getPreferredDashboard();
            
            return redirect()->intended($preferredDashboard);
        }

        Log::warning("Tentative de connexion échouée pour: " . $request->email);
        
        return back()->with('error', 'Adresse e-mail ou mot de passe incorrect.');
    }

    /**
     * Afficher le formulaire de demande d'OTP
     */
    public function showOtpRequest()
    {
        return view('auth.otp-request');
    }

    /**
     * Envoyer un code OTP par email
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ], [
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail doit être valide.'
        ]);

        // Vérifier si l'utilisateur existe et est actif
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return back()->with('error', 'Aucun compte trouvé avec cette adresse e-mail.');
        }

        if ($user->status !== 'active') {
            return back()->with('error', 'Votre compte n\'est pas actif. Contactez l\'administrateur.');
        }

        // Supprimer les anciens codes OTP non utilisés pour cet email
        UserOtpCode::where('email', $request->email)
                   ->where('type', 'login')
                   ->where('is_used', false)
                   ->delete();

        // Créer un nouveau code OTP
        $otpCode = UserOtpCode::createForLogin(
            $request->email,
            $request->ip(),
            $request->userAgent()
        );

        // Envoyer l'email avec le code OTP
        try {
            Mail::to($request->email)->send(new OtpMail(
                $otpCode->code,
                $user->name,
                10, // 10 minutes d'expiration
                false // Ce n'est pas une invitation
            ));
            
            Log::info("Code OTP envoyé par email à {$request->email}");
            
            // Stocker l'email en session pour la page de vérification
            $request->session()->put('otp_email', $request->email);

            return redirect()->route('auth.otp.verify')->with('success', 
                "Un code de vérification a été envoyé à votre adresse e-mail."
            );
            
        } catch (\Exception $e) {
            Log::error("Erreur envoi email OTP: " . $e->getMessage());
            
            // En cas d'erreur d'envoi, afficher le code pour test
            return redirect()->route('auth.otp.verify')->with('success', 
                "Erreur d'envoi email. Code pour test: {$otpCode->code}"
            );
        }
    }

    /**
     * Afficher le formulaire de vérification d'OTP
     */
    public function showOtpVerify(Request $request)
    {
        $email = $request->session()->get('otp_email');
        
        if (!$email) {
            return redirect()->route('auth.otp.request')
                           ->with('error', 'Session expirée. Veuillez recommencer.');
        }

        return view('auth.otp-verify', compact('email'));
    }

    /**
     * Vérifier le code OTP et connecter l'utilisateur
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6'
        ], [
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'code.required' => 'Le code de vérification est obligatoire.',
            'code.digits' => 'Le code doit contenir exactement 6 chiffres.'
        ]);

        // Chercher le code OTP valide
        $otpCode = UserOtpCode::where('email', $request->email)
                             ->where('code', $request->code)
                             ->where('type', 'login')
                             ->valid()
                             ->first();

        if (!$otpCode) {
            return back()->with('error', 'Code de vérification invalide ou expiré.');
        }

        // Vérifier que l'utilisateur existe toujours et est actif
        $user = User::where('email', $request->email)->where('status', 'active')->first();
        
        if (!$user) {
            return back()->with('error', 'Compte utilisateur introuvable ou inactif.');
        }

        // Marquer le code comme utilisé
        $otpCode->markAsUsed();

        // Connecter l'utilisateur
        Auth::login($user);
        $request->session()->regenerate();
        
        // Mettre à jour les informations de dernière connexion
        $user->updateLastLogin($request->ip());
        
        // Nettoyer la session
        $request->session()->forget('otp_email');

        Log::info("Connexion OTP réussie pour l'utilisateur: " . $user->email);

        // Redirection intelligente selon le rôle et les permissions
        $preferredDashboard = $user->getPreferredDashboard();
        
        return redirect()->intended($preferredDashboard);
    }

    /**
     * Renvoyer un code OTP
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        // Vérifier si l'utilisateur peut recevoir un nouveau code
        $recentOtp = UserOtpCode::where('email', $request->email)
                               ->where('type', 'login')
                               ->where('created_at', '>', now()->subMinutes(2))
                               ->first();

        if ($recentOtp) {
            return back()->with('error', 'Veuillez attendre 2 minutes avant de demander un nouveau code.');
        }

        // Utiliser la même logique que sendOtp
        return $this->sendOtp($request);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        $userEmail = Auth::user()->email ?? 'Unknown';
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info("Déconnexion de l'utilisateur: " . $userEmail);

        return redirect()->route('auth.login')->with('success', 'Vous êtes déconnecté avec succès.');
    }

    /**
     * Traiter l'invitation par token (pour les nouveaux utilisateurs invités)
     */
    public function processInvitation(Request $request, $token)
    {
        $invitation = Invitation::where('token', $token)->pending()->first();
        
        if (!$invitation) {
            return redirect()->route('auth.login')
                           ->with('error', 'Lien d\'invitation invalide ou expiré.');
        }

        // Supprimer les anciens codes OTP pour cette invitation
        UserOtpCode::where('invitation_token', $token)
                   ->where('is_used', false)
                   ->delete();

        // Créer un code OTP pour l'invitation
        $otpCode = UserOtpCode::createForInvitation(
            $invitation->email,
            $token,
            $request->ip(),
            $request->userAgent()
        );

        // Envoyer l'email avec le code OTP d'invitation
        try {
            Mail::to($invitation->email)->send(new OtpMail(
                $otpCode->code,
                $invitation->first_name . ' ' . $invitation->last_name,
                15, // 15 minutes d'expiration pour les invitations
                true // C'est une invitation
            ));
            
            Log::info("Code OTP d'invitation envoyé par email à {$invitation->email}");
            
            $message = "Un code de vérification a été envoyé à votre adresse e-mail.";
        } catch (\Exception $e) {
            Log::error("Erreur envoi email OTP invitation: " . $e->getMessage());
            $message = "Erreur d'envoi email. Code pour test: {$otpCode->code}";
        }

        // Stocker les informations en session
        $request->session()->put('otp_email', $invitation->email);
        $request->session()->put('invitation_token', $token);

        return view('auth.otp-verify', [
            'email' => $invitation->email,
            'invitation_token' => $token
        ])->with('info', $message);
    }
}