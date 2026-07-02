<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('API Tokens') }}</h2>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'api-token-revoked')
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ __('API token revoked.') }}
            </div>
        @endif

        @if (session('status') === 'api-token-created' && session('plainTextToken'))
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-6">
                <h3 class="text-sm font-semibold text-amber-900">{{ __('Your new API token') }}</h3>
                <p class="mt-1 text-sm text-amber-800">{{ __("Copy this now — it won't be shown again. Anyone with this token can act on your account through the API.") }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <input type="text" readonly value="{{ session('plainTextToken') }}" class="block w-full truncate rounded-md border-amber-300 bg-white font-mono text-sm text-gray-900 shadow-sm" onclick="this.select()">
                </div>
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Create a New Token') }}</h3>

            <form method="post" action="{{ route('api-tokens.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf

                <div class="min-w-[240px] flex-1">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. Accounting System') }}" required />
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>

                <x-primary-button type="submit">{{ __('Generate Token') }}</x-primary-button>
            </form>
        </div>

        @if ($tokens->isEmpty())
            <x-empty-state
                :title="__('No API tokens yet')"
                :description="__('Generate a token above to start using the external API — see the Help page for the full endpoint reference.')"
            />
        @else
            <x-table :headers="[__('Name'), __('Created'), __('Last Used'), __('Status'), '']">
                @foreach ($tokens as $token)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $token->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $token->created_at->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $token->last_used_at?->format('M j, Y g:ia') ?? __('Never') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <x-status-badge :status="$token->active ? 'active' : 'revoked'" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            @if ($token->active)
                                <form method="post" action="{{ route('api-tokens.revoke', $token) }}" onsubmit="return confirm('{{ __('Revoke this token? Any integration using it will stop working immediately.') }}');">
                                    @csrf
                                    @method('patch')
                                    <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Revoke') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-app-layout>
