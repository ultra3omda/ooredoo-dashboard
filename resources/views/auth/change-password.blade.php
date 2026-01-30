@extends('layouts.app')

@section('content')
<div style="max-width: 500px; margin: 40px auto; padding: 20px;">
    <div style="background: white; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="margin: 0 0 24px 0; color: #1f2937; font-size: 24px; font-weight: 600;">
            🔒 Changer mon mot de passe
        </h2>
        
        @if(session('error'))
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #fecaca;">
                {{ session('error') }}
            </div>
        @endif
        
        @if(session('success'))
            <div style="background: #dcfce7; color: #16a34a; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #bbf7d0;">
                {{ session('success') }}
            </div>
        @endif
        
        <form action="{{ route('password.change') }}" method="POST">
            @csrf
            
            <div style="margin-bottom: 24px;">
                <label for="current_password" style="display: block; color: #374151; font-weight: 500; margin-bottom: 8px; font-size: 14px;">
                    Mot de passe actuel
                </label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px; transition: all 0.2s ease;"
                    required
                    placeholder="••••••••"
                    onfocus="this.style.borderColor='#6B46C1'; this.style.boxShadow='0 0 0 3px rgba(107, 70, 193, 0.1)'"
                    onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'"
                >
                @error('current_password')
                    <div style="background: #fee2e2; color: #dc2626; padding: 8px 12px; border-radius: 6px; margin-top: 8px; font-size: 14px;">
                        {{ $message }}
                    </div>
                @enderror
            </div>
            
            <div style="margin-bottom: 24px;">
                <label for="password" style="display: block; color: #374151; font-weight: 500; margin-bottom: 8px; font-size: 14px;">
                    Nouveau mot de passe
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px; transition: all 0.2s ease;"
                    required
                    placeholder="••••••••"
                    onfocus="this.style.borderColor='#6B46C1'; this.style.boxShadow='0 0 0 3px rgba(107, 70, 193, 0.1)'"
                    onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'"
                >
                @error('password')
                    <div style="background: #fee2e2; color: #dc2626; padding: 8px 12px; border-radius: 6px; margin-top: 8px; font-size: 14px;">
                        {{ $message }}
                    </div>
                @enderror
                <div style="font-size: 12px; color: #6b7280; margin-top: 8px; line-height: 1.4;">
                    <strong>Exigences du mot de passe :</strong>
                    <ul style="margin: 4px 0 0 0; padding-left: 16px;">
                        <li>Au moins 8 caractères</li>
                        <li>Au moins une majuscule et une minuscule</li>
                        <li>Au moins un chiffre</li>
                    </ul>
                </div>
            </div>
            
            <div style="margin-bottom: 32px;">
                <label for="password_confirmation" style="display: block; color: #374151; font-weight: 500; margin-bottom: 8px; font-size: 14px;">
                    Confirmer le nouveau mot de passe
                </label>
                <input 
                    type="password" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px; transition: all 0.2s ease;"
                    required
                    placeholder="••••••••"
                    onfocus="this.style.borderColor='#6B46C1'; this.style.boxShadow='0 0 0 3px rgba(107, 70, 193, 0.1)'"
                    onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'"
                >
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button 
                    type="submit" 
                    style="flex: 1; padding: 12px 24px; background: linear-gradient(45deg, #6B46C1, #8B5CF6); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 25px rgba(107, 70, 193, 0.3)'"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'"
                >
                    💾 Enregistrer le nouveau mot de passe
                </button>
                
                <a 
                    href="{{ route('dashboard') }}" 
                    style="flex: 0 0 auto; padding: 12px 24px; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; font-size: 16px; font-weight: 500; text-decoration: none; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;"
                    onmouseover="this.style.background='#e5e7eb'"
                    onmouseout="this.style.background='#f3f4f6'"
                >
                    ← Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

