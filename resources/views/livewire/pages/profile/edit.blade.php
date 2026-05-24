<div>
    <h1 class="text-2xl font-bold mb-6">Profile</h1>

    {{-- Profile Information --}}
    <x-mary-card title="Profile Information" class="mb-6">
        @if (session('profile_success'))
            <x-mary-alert title="{{ session('profile_success') }}" class="alert-success mb-4" />
        @endif
        <x-mary-form wire:submit="updateProfile">
            <x-mary-input label="Name" wire:model="name" required />
            <x-mary-input label="Email" wire:model="email" type="email" required />
            <x-slot:actions>
                <x-mary-button type="submit" label="Save" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>

    {{-- Update Password --}}
    <x-mary-card title="Update Password" class="mb-6">
        @if (session('password_success'))
            <x-mary-alert title="{{ session('password_success') }}" class="alert-success mb-4" />
        @endif
        <x-mary-form wire:submit="updatePassword">
            <x-mary-input label="Current Password" wire:model="current_password" type="password" required />
            <x-mary-input label="New Password" wire:model="password" type="password" required />
            <x-mary-input label="Confirm Password" wire:model="password_confirmation" type="password" required />
            <x-slot:actions>
                <x-mary-button type="submit" label="Update Password" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>

    {{-- Server Sync (mobile only) --}}
    @if ($isRunningInNative)
        <x-mary-card title="Server Sync" class="mb-6">
            @if (session('server_success'))
                <x-mary-alert title="{{ session('server_success') }}" class="alert-success mb-4" />
            @endif
            @if (session('server_error'))
                <x-mary-alert title="{{ session('server_error') }}" class="alert-error mb-4" />
            @endif

            @if ($isConnectedToServer)
                <p class="text-base-content/70 text-sm mb-4">
                    <span class="badge badge-success badge-sm mr-1">Connected</span>
                    Your data is syncing to the server.
                </p>
                <x-mary-button wire:click="disconnectFromServer" label="Disconnect" class="btn-outline btn-error btn-sm" />
            @else
                <p class="text-base-content/70 text-sm mb-4">Connect to your server account to back up your data.</p>
                <x-mary-form wire:submit="connectToServer">
                    <x-mary-input label="Server Email" wire:model="server_email" type="email" required />
                    <x-mary-input label="Server Password" wire:model="server_password" type="password" required />
                    <x-slot:actions>
                        <x-mary-button type="submit" label="Connect" class="btn-primary" />
                    </x-slot:actions>
                </x-mary-form>
            @endif
        </x-mary-card>
    @endif

    {{-- Delete Account --}}
    <x-mary-card title="Delete Account" class="border-error">
        <p class="text-base-content/70 text-sm mb-4">Once your account is deleted, all of its resources and data will be permanently deleted.</p>
        <x-mary-form wire:submit="deleteAccount">
            <x-mary-input label="Password" wire:model="delete_password" type="password" required />
            <x-slot:actions>
                <x-mary-button type="submit" label="Delete Account" class="btn-error" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
