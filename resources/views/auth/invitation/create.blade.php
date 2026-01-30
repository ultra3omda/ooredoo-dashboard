@extends('layouts.app')

@section('title', 'Inviter un Utilisateur')

@section('content')
<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h2 class="mt-6 text-3xl font-bold text-gray-900">
                📧 Inviter un Utilisateur
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Envoyer une invitation par email pour rejoindre le dashboard Ooredoo
            </p>
        </div>

        <!-- Form -->
        <div class="bg-white py-8 px-6 shadow-lg rounded-lg sm:px-10">
            <form class="space-y-6" action="{{ route('auth.invitation.store') }}" method="POST">
                @csrf

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        📧 Adresse Email
                    </label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" autocomplete="email" required 
                               value="{{ old('email') }}"
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm @error('email') border-red-300 @enderror"
                               placeholder="utilisateur@example.com">
                    </div>
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Prénom -->
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">
                        👤 Prénom
                    </label>
                    <div class="mt-1">
                        <input id="first_name" name="first_name" type="text" required 
                               value="{{ old('first_name') }}"
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm @error('first_name') border-red-300 @enderror"
                               placeholder="Prénom">
                    </div>
                    @error('first_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Nom -->
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">
                        👤 Nom de Famille
                    </label>
                    <div class="mt-1">
                        <input id="last_name" name="last_name" type="text" required 
                               value="{{ old('last_name') }}"
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm @error('last_name') border-red-300 @enderror"
                               placeholder="Nom de famille">
                    </div>
                    @error('last_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Rôle -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">
                        🎭 Rôle
                    </label>
                    <div class="mt-1">
                        <select id="role" name="role" required 
                                class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm @error('role') border-red-300 @enderror">
                            <option value="">Sélectionner un rôle</option>
                            @if(auth()->user()->isSuperAdmin())
                                <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>
                                    👨‍💼 Administrateur
                                </option>
                            @endif
                            <option value="collaborator" {{ old('role') == 'collaborator' ? 'selected' : '' }}>
                                👥 Collaborateur
                            </option>
                        </select>
                    </div>
                    @error('role')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Opérateurs (si Admin/Collaborator) -->
                @if(auth()->user()->isSuperAdmin())
                <div id="operators-section">
                    <label for="operators" class="block text-sm font-medium text-gray-700">
                        📱 Opérateurs Assignés
                    </label>
                    <div class="mt-1">
                        <select id="operators" name="operators[]" multiple 
                                class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm @error('operators') border-red-300 @enderror">
                            <option value="Timwe">📱 Timwe</option>
                            <option value="Carte cadeaux">🎁 Carte Cadeaux</option>
                            <option value="Orange Money">💰 Orange Money</option>
                            <option value="Djezzy Money">💳 Djezzy Money</option>
                        </select>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Maintenir Ctrl/Cmd pour sélectionner plusieurs opérateurs
                    </p>
                    @error('operators')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @else
                <input type="hidden" name="operators[]" value="{{ auth()->user()->getPrimaryOperatorName() }}">
                <div class="text-sm text-gray-600 bg-blue-50 p-3 rounded-md">
                    📱 Opérateur assigné : <strong>{{ auth()->user()->getPrimaryOperatorName() }}</strong>
                </div>
                @endif

                <!-- Message personnalisé (optionnel) -->
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">
                        💬 Message Personnel (Optionnel)
                    </label>
                    <div class="mt-1">
                        <textarea id="message" name="message" rows="3" 
                                  class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                  placeholder="Message d'accompagnement pour l'invitation...">{{ old('message') }}</textarea>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="flex space-x-4">
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150 ease-in-out">
                        📧 Envoyer l'Invitation
                    </button>
                    
                    <a href="{{ route('admin.users.index') }}" 
                       class="group relative w-full flex justify-center py-2 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150 ease-in-out">
                        ↩️ Retour
                    </a>
                </div>
            </form>
        </div>

        <!-- Info Box -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">
                        💡 Information
                    </h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>L'invitation sera envoyée par email avec un lien sécurisé</li>
                            <li>Le lien d'invitation expire après 48 heures</li>
                            <li>L'utilisateur devra créer son mot de passe lors de l'acceptation</li>
                            <li>Un code OTP sera envoyé pour la première connexion</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Masquer/afficher la section opérateurs selon le rôle
document.getElementById('role').addEventListener('change', function() {
    const operatorsSection = document.getElementById('operators-section');
    const role = this.value;
    
    if (role === 'collaborator' || role === 'admin') {
        operatorsSection.style.display = 'block';
    } else {
        operatorsSection.style.display = 'none';
    }
});

// Initialiser l'affichage au chargement
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    if (roleSelect.value) {
        roleSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endsection
