<div>
    <h1 class="text-2xl font-bold mb-6">Profile</h1>

    {{-- Profile Information --}}
    <x-card title="Profile Information" class="mb-6">
        @if (session('profile_success'))
            <x-alert title="{{ session('profile_success') }}" class="alert-success mb-4" />
        @endif
        <x-form wire:submit="updateProfile">
            <x-input label="Name" wire:model="name" required />
            <x-input label="Email" wire:model="email" type="email" required />
            <x-slot:actions>
                <x-button type="submit" label="Save" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-card>

    {{-- Update Password --}}
    <x-card title="Update Password" class="mb-6">
        @if (session('password_success'))
            <x-alert title="{{ session('password_success') }}" class="alert-success mb-4" />
        @endif
        <x-form wire:submit="updatePassword">
            <x-input label="Current Password" wire:model="current_password" type="password" required />
            <x-input label="New Password" wire:model="password" type="password" required />
            <x-input label="Confirm Password" wire:model="password_confirmation" type="password" required />
            <x-slot:actions>
                <x-button type="submit" label="Update Password" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-card>

    {{-- Delete Account --}}
    <x-card title="Delete Account" class="border-error">
        <p class="text-base-content/70 text-sm mb-4">Once your account is deleted, all of its resources and data will be permanently deleted.</p>
        <x-form wire:submit="deleteAccount">
            <x-input label="Password" wire:model="delete_password" type="password" required />
            <x-slot:actions>
                <x-button type="submit" label="Delete Account" class="btn-error" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
